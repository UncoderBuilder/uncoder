// Audio widget: swaps the browser's player for the widget's own (play / pause button, seek bar, time). The page keeps
// one sound playing at a time: a player that allows it (data-pause-others) pauses the other players and any video playing
// with sound when it starts, and pauses itself when another one starts. Muted videos (backgrounds) are left alone.
const players = new Set<HTMLAudioElement>();
const quiet = new WeakSet<HTMLAudioElement>();
let listening = false;

const audible = (m: HTMLMediaElement) => !m.paused && !m.muted && m.volume > 0;

// A video (or any other media element) that starts with sound pauses the players that allow it.
const onAnyPlay = (e: Event) => {
  const media = e.target;
  if (!(media instanceof HTMLMediaElement) || players.has(media as HTMLAudioElement) || !audible(media)) return;
  for (const p of players) if (!p.paused && !quiet.has(p)) p.pause();
};

const clock = (s: number) => {
  if (!Number.isFinite(s) || s < 0) s = 0;
  const m = Math.floor(s / 60);
  const r = Math.floor(s % 60);
  return `${m}:${String(r).padStart(2, '0')}`;
};

window.UncoderWB.register('audio', (el) => {
  // The widget's attributes are merged into its block, so el is the .uncoder-audio element itself.
  const root = el.classList.contains('uncoder-audio') ? el : el.querySelector<HTMLElement>('.uncoder-audio');
  const audio = el.querySelector<HTMLAudioElement>('audio.uncoder-audio__el');
  const toggle = el.querySelector<HTMLButtonElement>('.uncoder-audio__toggle');
  if (!root || !audio || !toggle) return;
  const seek = el.querySelector<HTMLInputElement>('.uncoder-audio__seek');
  const current = el.querySelector<HTMLElement>('.uncoder-audio__current');
  const duration = el.querySelector<HTMLElement>('.uncoder-audio__duration');
  const pauseOthers = root.dataset.pauseOthers !== 'false';

  audio.controls = false;
  root.classList.add('is-ready');
  players.add(audio);
  if (!pauseOthers) quiet.add(audio);
  if (!listening) {
    // play does not bubble: listen in the capture phase.
    document.addEventListener('play', onAnyPlay, true);
    listening = true;
  }

  let seeking = false;
  const paint = () => {
    const d = audio.duration;
    const t = audio.currentTime;
    const p = Number.isFinite(d) && d > 0 ? Math.min(100, (t / d) * 100) : 0;
    root.style.setProperty('--uncoder-audio-p', `${p}%`);
    if (seek && !seeking) {
      seek.value = String(p);
      seek.setAttribute('aria-valuetext', `${clock(t)} of ${clock(d)}`);
    }
    if (current) current.textContent = clock(t);
  };
  const meta = () => {
    if (duration) duration.textContent = clock(audio.duration);
    paint();
  };
  const sync = () => {
    const playing = !audio.paused;
    root.classList.toggle('is-playing', playing);
    toggle.setAttribute('aria-label', (playing ? toggle.dataset.pauseLabel : toggle.dataset.playLabel) || '');
  };
  const onPlay = () => {
    if (pauseOthers) {
      for (const other of players) if (other !== audio && !other.paused) other.pause();
      document.querySelectorAll('video').forEach((v) => {
        if (audible(v)) v.pause();
      });
    }
    sync();
  };
  const onClick = () => {
    if (audio.paused) audio.play().catch(sync);
    else audio.pause();
  };
  const onInput = () => {
    if (!seek) return;
    seeking = true;
    const d = audio.duration;
    root.style.setProperty('--uncoder-audio-p', `${seek.value}%`);
    if (Number.isFinite(d) && d > 0) audio.currentTime = (Number(seek.value) / 100) * d;
  };
  const onChange = () => {
    seeking = false;
    paint();
  };
  const onEnded = () => {
    if (!audio.loop) audio.currentTime = 0;
    sync();
    paint();
  };

  toggle.addEventListener('click', onClick);
  audio.addEventListener('play', onPlay);
  audio.addEventListener('pause', sync);
  audio.addEventListener('ended', onEnded);
  audio.addEventListener('timeupdate', paint);
  audio.addEventListener('loadedmetadata', meta);
  audio.addEventListener('durationchange', meta);
  seek?.addEventListener('input', onInput);
  seek?.addEventListener('change', onChange);
  if (audio.readyState >= 1) meta();
  sync();

  return () => {
    audio.pause();
    players.delete(audio);
    toggle.removeEventListener('click', onClick);
    audio.removeEventListener('play', onPlay);
    audio.removeEventListener('pause', sync);
    audio.removeEventListener('ended', onEnded);
    audio.removeEventListener('timeupdate', paint);
    audio.removeEventListener('loadedmetadata', meta);
    audio.removeEventListener('durationchange', meta);
    seek?.removeEventListener('input', onInput);
    seek?.removeEventListener('change', onChange);
  };
});
