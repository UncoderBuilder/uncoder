# Animated backgrounds

Background animations for containers (Style → Background → Animated background), loaded on demand by
`src/frontend/modules/bg-animated.ts`. They come from Uncoder Elements (`assets/js/backgrounds`), with these changes:

- `bit-wave.js`, `fluid-gradient.js`: `pause` / `resume` / `destroy` hooks so the loop stops off screen and when the
  element is removed (the editor re-renders elements).
- `liquid-image.js`, `liquid-mask.js`: no remote default image (the module passes the chosen image, or the
  container's background image).

`ogl-lite.js` is Uncoder Elements' minimal OGL subset (`window.uncoder_ogl`); `ogl.js` is the full OGL build
(`window.ogl`, https://github.com/oframe/ogl), used by Bit Wave only. `fluid-gradient.js` includes the Ashima
Arts simplex noise (MIT, see the notice in the file).

Each script defines `window.uiAnimated_<Name>(element, canvas, settings)` and stores its WebGL objects on
`element.animatedBackground`.
