import { useState } from 'react';
import { createPortal } from 'react-dom';
import { api } from '../lib/api';
import { Icon } from '../ui/Icon';
import { Button, Segmented } from '../ui/primitives';

export interface GeneratedImage {
  id: number;
  url: string;
  alt: string;
}

/**
 * Image field → Generate with AI (Site\Ai_Images): describe a picture, pick a shape, generate; the result is in
 * the media library and can be used right away or generated again.
 */
export function AiImageDialog({ onUse, onClose, initial = '' }: { onUse: (img: GeneratedImage) => void; onClose: () => void; initial?: string }) {
  const [prompt, setPrompt] = useState(initial);
  const [size, setSize] = useState('landscape');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState<GeneratedImage | null>(null);

  const generate = async () => {
    setBusy(true);
    setError('');
    try {
      setResult(await api<GeneratedImage>('ai/image', { body: { prompt, size } }));
    } catch (e) {
      setError(e instanceof Error ? e.message : 'The image could not be generated.');
    } finally {
      setBusy(false);
    }
  };

  return createPortal(
    <div className="uncoder-ui-scrim" onPointerDown={(e) => e.target === e.currentTarget && !busy && onClose()}>
      <div className="uncoder-ui-dialog uncoder-ui-aiimg" role="dialog" aria-modal="true" aria-labelledby="uncoder-ui-aiimg-title" onKeyDown={(e) => e.key === 'Escape' && !busy && onClose()}>
        <div className="uncoder-ui-dialog__head">
          <Icon name="sparkles" size={16} />
          <h2 id="uncoder-ui-aiimg-title">Generate an image</h2>
        </div>
        <label className="uncoder-ui-field">
          <span className="uncoder-ui-field__label">Describe the image</span>
          <textarea
            className="uncoder-ui-input uncoder-ui-aiimg__prompt"
            rows={4}
            autoFocus
            value={prompt}
            maxLength={2000}
            placeholder="A bright, modern kitchen with a cleaner wiping the counter, soft morning light, photo"
            onChange={(e) => setPrompt(e.currentTarget.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter' && (e.metaKey || e.ctrlKey) && prompt.trim().length > 2 && !busy) generate();
            }}
          />
        </label>
        <div className="uncoder-ui-aiimg__row">
          <Segmented
            ariaLabel="Shape"
            value={size}
            onChange={setSize}
            options={[
              { value: 'landscape', label: 'Landscape', icon: 'rectangle-horizontal' },
              { value: 'square', label: 'Square', icon: 'square' },
              { value: 'portrait', label: 'Portrait', icon: 'rectangle-vertical' },
            ]}
          />
          <Button variant={result ? 'secondary' : 'primary'} icon={result ? 'refresh-cw' : 'sparkles'} loading={busy} disabled={prompt.trim().length < 3} onClick={generate}>
            {result ? 'Generate again' : 'Generate'}
          </Button>
        </div>
        {busy && <p className="uncoder-ui-note">Creating the image — this can take up to a minute.</p>}
        {error && (
          <p className="uncoder-ui-note is-error" role="alert">
            {error}
          </p>
        )}
        {result && !busy && (
          <figure className="uncoder-ui-aiimg__result">
            <img src={result.url} alt={result.alt} />
            <figcaption>Saved to the media library.</figcaption>
          </figure>
        )}
        <div className="uncoder-ui-dialog__foot">
          <Button type="button" onClick={onClose} disabled={busy}>
            {result ? 'Close' : 'Cancel'}
          </Button>
          <Button type="button" variant="primary" disabled={!result || busy} onClick={() => result && onUse(result)}>
            Use this image
          </Button>
        </div>
      </div>
    </div>,
    document.querySelector('.uncoder-ui-portal') ?? document.body,
  );
}
