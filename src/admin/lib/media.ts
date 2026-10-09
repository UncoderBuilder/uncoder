// WordPress's media library from the admin screens (wp_enqueue_media() runs on every Uncoder screen).

export interface PickedImage {
  id: number;
  url: string;
}

/** The media library, images only: the chosen image, or null when closed. */
export function pickImage(title: string): Promise<PickedImage | null> {
  return new Promise((resolve) => {
    const wp = (window as { wp?: any }).wp;
    if (!wp?.media) return resolve(null);
    const frame = wp.media({ title, library: { type: 'image' }, multiple: false, button: { text: 'Use this image' } });
    frame.on('select', () => {
      const a = frame.state().get('selection').first()?.toJSON();
      resolve(a ? { id: a.id, url: a.sizes?.medium?.url ?? a.url } : null);
    });
    frame.on('close', () => setTimeout(() => resolve(null), 0));
    frame.open();
  });
}
