// A small dialog with a few choices ("Save and open", "Open without saving", "Cancel"), as a promise:
//   const pick = await choose({ title, body, choices: [...] });   // the chosen id, or null when dismissed
import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { create } from 'zustand';
import { Icon } from '../ui/Icon';
import { Button } from '../ui/primitives';

interface Choice {
  id: string;
  label: string;
  primary?: boolean;
}
interface Request {
  title: string;
  body?: string;
  icon?: string;
  choices: Choice[];
  resolve: (id: string | null) => void;
}

const useChoice = create<{ req: Request | null }>(() => ({ req: null }));

export function choose(options: Omit<Request, 'resolve'>): Promise<string | null> {
  return new Promise((resolve) => {
    useChoice.getState().req?.resolve(null);
    useChoice.setState({ req: { ...options, resolve } });
  });
}

export function ChoiceHost() {
  const req = useChoice((s) => s.req);
  const primary = useRef<HTMLButtonElement>(null);
  useEffect(() => {
    if (req) requestAnimationFrame(() => primary.current?.focus());
  }, [req]);
  if (!req) return null;
  const done = (id: string | null) => {
    req.resolve(id);
    useChoice.setState({ req: null });
  };
  return createPortal(
    <div className="uncoder-ui-scrim" onPointerDown={(e) => e.target === e.currentTarget && done(null)}>
      <div className="uncoder-ui-dialog uncoder-ui-choice" role="alertdialog" aria-modal="true" aria-labelledby="uncoder-ui-choice-title" onKeyDown={(e) => e.key === 'Escape' && done(null)}>
        <div className="uncoder-ui-dialog__head">
          {req.icon && <Icon name={req.icon} size={16} />}
          <h2 id="uncoder-ui-choice-title">{req.title}</h2>
        </div>
        {req.body && <p className="uncoder-ui-note">{req.body}</p>}
        <div className="uncoder-ui-dialog__foot">
          {req.choices.map((c) => (
            <Button key={c.id} ref={c.primary ? primary : undefined} type="button" variant={c.primary ? 'primary' : undefined} onClick={() => done(c.id)}>
              {c.label}
            </Button>
          ))}
        </div>
      </div>
    </div>,
    document.querySelector('.uncoder-ui-portal') ?? document.body,
  );
}
