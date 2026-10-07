window.uiAnimated_MysticLake = function (el, canvas, userSettings = {}) {
  const {
    Renderer,
    Program,
    Mesh,
    Triangle
  } = window.uncoder_ogl;

  // Vertex shader
  const vertex = `
        attribute vec2 uv;
        attribute vec2 position;
        varying vec2 vUv;
        void main() {
        vUv = uv;
        gl_Position = vec4(position, 0, 1);
        }
    `;

  // Fragment shader
  const fragment = `
        precision highp float;

        uniform float uTime;
        uniform vec3 uColorStops[1];
        uniform vec3 uResolution;
        uniform vec2 uMouse;
        uniform float uProgress;
        uniform float uIntensity;
        uniform float uScale;
        uniform float uNoise;
        uniform float uSpeed;
        uniform float uInteractive;
        varying vec2 vUv;

        void main() {
            float mr = min(uResolution.x, uResolution.y);
            float time = uSpeed > 0.0 ? uTime * uSpeed : (uMouse.x + uMouse.y) * 3.0 + uProgress * 0.09;
            float speed = uSpeed > 0.0 ? uSpeed : 1.0;
            vec2 uv = (gl_FragCoord.xy * 2.0 - uResolution.xy) / min(uResolution.x, uResolution.y);
            // apply mouse offset
            uv += (uMouse - vec2(0.5)) * 0.1;

            float scale = mix(2.0, 0.2, clamp(uScale / 10.0, 0.0, 1.0));
            uv *= scale;

            // simple hash-based noise
            float noise = fract(sin(dot(uv * uNoise + time * 0.1, vec2(12.9898, 78.233))) * 43758.5453);
            uv += (noise - 0.1) * 0.01 * uNoise;

            float d = -time * 0.5 * speed;
            float a = 0.0;

            float intensity = clamp(uIntensity, 3.0, 10.0) * 0.8;
            float distortionIntensity = clamp(uIntensity, 0.0, 6.0) * 0.45;

            for (float i = 0.0; i < 8.0; i++) {
                a += cos(i - d - a * uv.x * distortionIntensity * 0.3);
                d += sin(uv.y * i + a * distortionIntensity * 0.3);
            }

            d += time * 0.5 * speed;

            float r = cos((uv.x + uv.y) * d * intensity * 0.3) * 0.6 + 0.4;
            float g = cos(a + d * intensity * 0.3) * 0.5 + 0.5;
            float b = cos(a - d * intensity * 0.3) * 0.5 + 0.5;

            vec3 col = vec3(r, g, b);

            // reduce color modulation intensity by half
            col = cos(col * cos(vec3(d, a, 2.5)) * 0.25 * intensity * 0.4 + 0.5) * uColorStops[0];

            gl_FragColor = vec4(col, 1.0);
        }
    `;

  // Normalize and scale settings function
  const normalizeSettings = raw => {
    const normalized = {
      ...raw
    };
    if (raw.scale !== undefined) {
      normalized.scale = parseFloat(raw.scale) * 0.1;
    }
    if (raw.noise !== undefined) {
      normalized.noise = parseFloat(raw.noise) * 0.1;
    }
    if (raw.intensity !== undefined) {
      normalized.intensity = parseFloat(raw.intensity) * 0.1;
    }
    if (raw.speed !== undefined) {
      normalized.speed = parseFloat(raw.speed) * 0.03;
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
    progress,
    mouseInteractive
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
      uMouse: {
        value: [0.5, 0.5]
      },
      uInteractive: {
        value: mouseInteractive
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