import { APP_ICON, BOLT, BRAND, U_LEFT, U_RIGHT } from '@shared/brand';

/** The app icon: the symbol, optically centred in a petrol circle (cream with a petrol symbol in dark UI). */
export function AppIcon({ size = 32, className }: { size?: number; className?: string }) {
  if (BRAND.icon) {
    // White-label: the agency's icon.
    return <img className={'uncoder-ui-appicon uncoder-ui-appicon--custom' + (className ? ' ' + className : '')} src={BRAND.icon} width={size} height={size} alt="" />;
  }
  return (
    <svg className={'uncoder-ui-appicon' + (className ? ' ' + className : '')} width={size} height={size} viewBox="0 0 256 256" aria-hidden focusable="false">
      <circle className="uncoder-ui-appicon__disc" cx="128" cy="128" r="128" />
      <g className="uncoder-ui-appicon__mark" transform={`translate(${APP_ICON.x} ${APP_ICON.y}) scale(${APP_ICON.scale})`}>
        <path d={U_LEFT} />
        <path d={U_RIGHT} />
      </g>
    </svg>
  );
}

/** The Uncoder symbol in the current text color. */
export function BrandMark({ size = 18, className }: { size?: number; className?: string }) {
  if (BRAND.icon) {
    return <img className={(className ?? 'uncoder-ui-brandmark') + ' uncoder-ui-brandmark--custom'} src={BRAND.icon} width={size} height={size} alt="" />;
  }
  return (
    <svg className={className ?? 'uncoder-ui-brandmark'} width={(size * 92) / 96} height={size} viewBox="0 0 92 96" fill="currentColor" aria-hidden focusable="false">
      <path d={U_LEFT} />
      <path d={U_RIGHT} />
    </svg>
  );
}

/**
 * The loading mark: the U holds still while the bolt cut strikes and flickers like a lightning flash.
 * Same markup as Brand::loader() in PHP (the editor's boot screen, before React runs).
 */
export function Loader({ label = 'Loading…', size = 'md', className }: { label?: string; size?: 'sm' | 'md' | 'lg'; className?: string }) {
  if (BRAND.white) {
    // White-label: a plain disc with the agency's icon (if any), no Uncoder mark.
    return (
      <div className={`uncoder-ui-loader uncoder-ui-loader--${size} uncoder-ui-loader--plain${className ? ' ' + className : ''}`} role="status">
        <div className="uncoder-ui-loader__disc">{BRAND.icon && <img className="uncoder-ui-loader__img" src={BRAND.icon} alt="" />}</div>
        <span className="uncoder-ui-loader__label">{label}</span>
      </div>
    );
  }
  return (
    <div className={`uncoder-ui-loader uncoder-ui-loader--${size}${className ? ' ' + className : ''}`} role="status">
      <div className="uncoder-ui-loader__disc">
        <svg className="uncoder-ui-loader__mark" viewBox="-2 -2 96 100" aria-hidden focusable="false">
          <path className="uncoder-ui-loader__u" d={U_LEFT} />
          <path className="uncoder-ui-loader__u" d={U_RIGHT} />
          <path className="uncoder-ui-loader__bolt" d={BOLT} />
        </svg>
      </div>
      <span className="uncoder-ui-loader__label">{label}</span>
    </div>
  );
}
