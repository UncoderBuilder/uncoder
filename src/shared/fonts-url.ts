// Google Fonts css2 `family=` parameter. Twin of Fonts::family_param() (includes/Core/Fonts.php): both must build
// the same URL. Families with an optical-size axis also load it (browsers use the display cut for big text), and
// families with italics load them too, so <em> uses the real italic instead of a slanted copy (browsers fetch the
// italic files only when italic text is on the page).

export interface GoogleFontInfo {
  /** [min, max] of the optical-size axis. */
  o?: [number, number];
  /** 1 when the family has italics. */
  i?: number;
}

/** Weights sorted the way Google requires (numerically: "1000" after "900"). */
export const sortWeights = (weights: string[]): string[] => [...weights].sort((a, b) => parseFloat(a) - parseFloat(b));

export function googleFamilyParam(family: string, weights: string[] | string, info: GoogleFontInfo = {}): string {
  const name = family.replace(/ /g, '+');
  let tuples = typeof weights === 'string' ? [weights] : [...weights];
  const axes: string[] = [];
  if (info.o) {
    const range = `${Math.trunc(info.o[0])}..${Math.trunc(info.o[1])}`;
    tuples = tuples.map((w) => `${range},${w}`);
    axes.push('opsz');
  }
  if (info.i) {
    // Every upright tuple first (ital 0), then the italic ones (ital 1).
    tuples = [...tuples.map((t) => `0,${t}`), ...tuples.map((t) => `1,${t}`)];
    axes.unshift('ital');
  }
  axes.push('wght');
  return `family=${name}:${axes.join(',')}@${tuples.join(';')}`;
}
