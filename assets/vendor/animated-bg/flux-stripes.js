window.uiAnimated_FluxStripes = (el, canvas, userSettings = {}) => {
  const {
    Renderer,
    Program,
    Mesh,
    Triangle
  } = window.uncoder_ogl;
  const vertex = `
        attribute vec2 position;
        attribute vec2 uv;
        varying vec2 vUv;

        void main() {
        vUv = uv;
        gl_Position = vec4(position, 0.0, 1.0);
        }
    `;
  const fragment = `
        #ifdef GL_ES
        precision mediump float;
        #endif

        uniform vec3  uResolution;
        uniform vec2  uMouse;
        uniform float uTime;

        uniform float uAngle;
        uniform float uNoise;
        uniform float uScale;
        uniform float uIntensity;
        uniform float uDistort;
        uniform float uProgress;
        uniform float uInteractive;
        uniform float uOffsetX;
        uniform float uOffsetY;
        uniform vec3  uColorStops[4];

        varying vec2 vUv;

        float rand(vec2 co){
        return fract(sin(dot(co, vec2(12.9898,78.233))) * 43758.5453);
        }

        vec2 rotate2D(vec2 p, float a){
        float c = cos(a);
        float s = sin(a);
        return mat2(c, -s, s, c) * p;
        }

        vec3 getGradientColor(float t){
            float tt = clamp(t, 0.0, 1.0);
            int count = 4;
            float scaled = tt * float(count - 1);
            float seg = floor(scaled);
            float f = fract(scaled);

            if (seg < 1.0) return mix(uColorStops[0], uColorStops[1], f);
            if (seg < 2.0) return mix(uColorStops[1], uColorStops[2], f);
            return mix(uColorStops[2], uColorStops[3], f);
        }

        void main() {
            // Normalized screen UV (0–1)
            vec2 uv = gl_FragCoord.xy / uResolution.xy;

            // Centered coordinates (-1..1), keeping aspect ratio
            vec2 p = (uv - 0.5) * 2.0;
            p.x *= uResolution.x / uResolution.y; // aspect correction for geometry

            // Apply rotation
            float angle = radians(uAngle);
            mat2 rot = mat2(cos(angle), -sin(angle), sin(angle), cos(angle));
            p = rot * p;

            // Back to normalized UVs (0–1) for effects that need uniform color distribution
            vec2 uvMod = (p / vec2(uResolution.x / uResolution.y, 1.0)) * 0.5 + 0.5;

            // Distortion
            if (uDistort > 0.0) {
                float a = uvMod.y * 6.0;
                float b = uvMod.x * 6.0;
                float w = 0.01 * uDistort;
                uvMod.x += sin(a) * w;
                uvMod.y += cos(b) * w;
            }

            // Use normalized X for gradient & stripe, NOT the aspect-corrected one
            float t = uvMod.x;
            vec3 base = getGradientColor(t);

            // Mouse spotlight
            vec2 offset = vec2(0.0);
            offset += uInteractive < 1.0 ? vec2(0.5 - uOffsetX * 0.005,0.5 - uOffsetY * 0.005) : vec2(uMouse.x, 1.0 - uMouse.y);
            float d = length(uv - offset);
            float r = max(uIntensity, 1e-4);
            float dn = d / r;
            float spot = (1.0 - 2.0 * pow(dn, 0.8)) * 0.35;
            vec3 cir = vec3(spot);

            // Evenly distributed stripes
            float stripe = fract(uvMod.x * max(uScale * 0.85, 1.0));
            stripe = 1.0 - stripe;
            vec3 ran = vec3(stripe);

            // Combine colors
            vec3 col = cir + base - ran;
            col += (rand(gl_FragCoord.xy + uTime) - 0.5) * uNoise;

            gl_FragColor = vec4(col, col.r + col.g + col.b);
        }
    `;

  // Normalize and scale settings function
  const normalizeSettings = raw => {
    const normalized = {
      ...raw
    };
    // Apply consistent scaling rules
    if (raw.intensity !== undefined) {
      normalized.intensity = parseFloat(raw.intensity) * 0.01;
    }
    if (raw.noise !== undefined) {
      normalized.noise = parseFloat(raw.noise) * 0.01;
    }
    return normalized;
  };
  let {
    colorArray,
    scale,
    intensity,
    angle,
    speed,
    noise,
    progress,
    mouseInteractive,
    offsetX,
    offsetY
  } = normalizeSettings(userSettings);
  const renderer = new Renderer({
    canvas,
    alpha: true,
    premultipliedAlpha: true,
    antialias: true
  });
  const gl = renderer.gl;
  const geometry = new Triangle(gl);
  const program = new Program(gl, {
    vertex,
    fragment,
    uniforms: {
      uTime: {
        value: 0
      },
      uScale: {
        value: scale
      },
      uSpeed: {
        value: speed || 0
      },
      uColorStops: {
        value: colorArray
      },
      uResolution: {
        value: [el.offsetWidth, el.offsetHeight, el.offsetHeight / el.offsetWidth]
      },
      uIntensity: {
        value: intensity
      },
      uAngle: {
        value: angle
      },
      uNoise: {
        value: noise
      },
      uDistort: {
        value: 0
      },
      uProgress: {
        value: progress
      },
      uMouse: {
        value: [0.5, 0.5]
      },
      uInteractive: {
        value: mouseInteractive
      },
      uOffsetX: {
        value: offsetX
      },
      uOffsetY: {
        value: offsetY
      }
    }
  });
  const mesh = new Mesh(gl, {
    geometry,
    program
  });
  el.animatedBackground = {
    normalizeSettings,
    renderer,
    gl,
    program,
    geometry,
    mesh
  };
};