// Small smartness for link fields: what people type or paste becomes a working link.
//   example.com / www.example.com     → https://example.com …     (as soon as it looks like an address)
//   https://https://example.com        → https://example.com       (a full address pasted after the added https://)
//   name@example.com                  → mailto:name@example.com
//   +1 (555) 010-2030                  → tel:+15550102030           (when the field is left: digits are typed one by one)
// Page paths (/about/), anchors (#faq), queries (?q=), mailto:, tel:, sms: and dynamic values are left alone, and a
// single word ("contact") stays a page search.

const SCHEME = /^[a-z][a-z0-9+.-]*:/i;
const DOMAIN = /^(?:www\.)?[a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,}(?::\d+)?(?:[/?#].*)?$/i;
const EMAIL = /^[^\s@/:]+@[^\s@/:]+\.[a-z]{2,}$/i;
const PHONE = /^\+?[\d\s().-]{7,}$/;

/** A complete link on its own (an address with or without https://, an email, mailto:, tel:): pasting one replaces
 * the field instead of being added to what is there ("https://ex.cohttps://shop.example.org"). */
export function isWholeLink(text: string): boolean {
  const v = text.trim();
  return !!v && !/\s/.test(v) && (/^(?:https?:\/\/|mailto:|tel:|sms:)/i.test(v) || EMAIL.test(v) || DOMAIN.test(v));
}

/**
 * @param raw   The field's text.
 * @param final True when the field is committed (Enter, leaving it): then phone numbers become tel: links too.
 */
export function smartLink(raw: string, final = false): string {
  let v = final ? raw.trim() : raw.replace(/^\s+/, '');
  if (!v) return v;
  // A full address pasted after one that was already there (or after the https:// added while typing): keep the last.
  v = v.replace(/^(?:https?:\/\/)+(https?:\/\/)/i, '$1');
  if (/^[/#?]|^\{\{|^\[/.test(v)) return v;
  if (EMAIL.test(v)) return `mailto:${v}`;
  // Before the scheme test: "example.com:8080" looks like a scheme ("example.com:") but is an address.
  if (DOMAIN.test(v)) return `https://${v}`;
  if (SCHEME.test(v)) return v;
  if (final && PHONE.test(v) && (v.match(/\d/g) ?? []).length >= 7) return `tel:${v.replace(/[^\d+]/g, '')}`;
  return v;
}
