window.uiAnimated_Halftone = function (el, canvas, userSettings = {}) {
  const {
    Renderer,
    Program,
    Mesh,
    Triangle
  } = window.uncoder_ogl;
  const vertex = `
        attribute vec2 uv;
        attribute vec2 position;
        varying vec2 vUv;
        void main() {
        vUv = uv;
        gl_Position = vec4(position, 0, 1);
        }
    `;
  const fragment = `
        precision highp float;
        uniform vec3  uColorStops[1];
        uniform vec3  uBackgroundColor;
        uniform float uTime;
        uniform vec3  uResolution;
        uniform float uIntensity; // 0..1 glow intensity
        uniform float uScale;     // 0..1
        uniform float uNoise;
        uniform vec2  uMouse;
        uniform float uSpeed;
        uniform float uProgress;
        uniform float uInteractive;
        uniform float uOffsetX;
        uniform float uOffsetY;

        varying vec2  vUv;


        float hash(vec2 p) {
            return fract(sin(dot(p.xy, vec2(12.9898, 78.233))) * 43758.5453123);
        }
        float gyr(in vec3 p) {
            return dot(sin(p.xyz),cos(p.zxy));
        }
        float SS(float a, float b, float t) {
            return smoothstep(a-b,a+b,t);
        }
        float map(in vec3 p) {
            p *= mix(0.5, 5.0, uScale);

            return (1. + .2*sin(p.y*600.)) *
                gyr((p*10. + .8*gyr(p*8.))) *
                (1. + sin(length(p.xy)*10.)) +
                .3 * sin(p.z * 5. + p.y) *
                (2. + gyr(p*(350. + 250.)));
        }
        vec3 norm(in vec3 p) {
            float m = map(p);
            vec2 d = vec2(.06 + .06*sin(p.z), 0.);
            return m - vec3(
                map(p - d.xyy), map(p - d.yxy), map(p - d.yyx)
            );
        }

        void main() {
            // vec2 uv = (gl_FragCoord.xy - uResolution.xy / 2.0) / uResolution.y;
             // Apply screen-based offset before normalization
            vec2 pixelOffset = vec2(
                (uOffsetX / 100.0) * (uResolution.x * 0.5),
                (uOffsetY / 100.0) * (uResolution.y * 0.5)
            );

            // Shift pixel coordinates, then normalize
            vec2 pos = ((gl_FragCoord.xy + pixelOffset) * 2.0 - uResolution.xy) / min(uResolution.x, uResolution.y);


            float time = uSpeed > 0.0 ? uTime * uSpeed : (uMouse.x + uMouse.y) * 0.2 + uProgress * 0.09;
            vec2 parallax = (uMouse - 0.5) * 0.1;
            pos += parallax;

            float d = 0.0;
            float dd = 1.0;
            vec3 p = vec3(0.0, 0.0, time * 0.4);
            vec3 rd = normalize(vec3(pos.xy, 1.0));

            for (float i = 0.0; i < 90.0; i++) {
                if (dd <= 0.001 || d >= 2.0) break;
                d += dd;
                p += rd * d;
                dd = map(p) * 0.02;
            }

            vec3 n = norm(p);
            float bw = n.x + n.y;
            bw *= SS(0.9, 0.15, 1.0 / d);


            bw *= mix(0.2, 2.0, uIntensity);

            vec3 col = mix(uBackgroundColor, uColorStops[0] * bw, bw);
            gl_FragColor = vec4(col, 1.0);
        }
    `;

  // Normalize and scale settings function
  const normalizeSettings = raw => {
    const normalized = {
      ...raw
    };
    if (raw.scale !== undefined) {
      normalized.scale = parseFloat(raw.scale) * 0.01;
    }
    if (raw.noise !== undefined) {
      normalized.noise = parseFloat(raw.noise) * 0.04;
    }
    if (raw.intensity !== undefined) {
      normalized.intensity = parseFloat(raw.intensity) * 0.01;
    }
    if (raw.speed !== undefined) {
      normalized.speed = parseFloat(raw.speed) * 0.01;
    }
    if (raw.colorArray !== undefined && Array.isArray(raw.colorArray)) {
      normalized.colorArray = raw.colorArray.slice(0, 1);
    }
    return normalized;
  };
  let {
    colorArray,
    speed,
    intensity,
    noise,
    scale,
    backgroundColor,
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
      uColorStops: {
        value: colorArray
      },
      uBackgroundColor: {
        value: backgroundColor
      },
      uResolution: {
        value: [el.offsetWidth, el.offsetHeight, el.offsetWidth / el.offsetHeight]
      },
      uMouse: {
        value: [0.5, 0.5]
      },
      uIntensity: {
        value: intensity
      },
      uSpeed: {
        value: speed
      },
      uScale: {
        value: scale
      },
      uNoise: {
        value: noise
      },
      uProgress: {
        value: progress
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