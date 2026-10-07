window.uiAnimated_LightStrings = function (el, canvas, userSettings = {}) {
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

        uniform vec3  uColorStops[4];
        uniform vec3  uBackgroundColor;
        uniform float uTime;
        uniform vec3 uResolution;
        uniform float uIntensity; // 0..1 glow intensity
        uniform float uScale;     // 0..1
        uniform float uNoise;
        uniform vec2  uMouse;
        uniform float uInteractive;
        uniform float uSpeed;
        uniform float uProgress;
        uniform float uOffsetX;
        uniform float uOffsetY;
        uniform float uAngle;

        varying vec2  vUv;

        float tanh(float x) {
            return (2.0 / (1.0 + exp(-2.0 * x))) - 1.0;
        }
        float rand(vec2 n) {
            return fract(sin(dot(n, vec2(12.9898, 4.1414))) * 43758.5453);
        }
        void mainImage(out vec4 fragColor, in vec2 fragCoord)
        {
             vec2 pixelOffset = vec2(
                (uOffsetX / 100.0) * (uResolution.x * 0.5),
                (uOffsetY / 100.0) * (uResolution.y * 0.5)
            );

            // Shift pixel coordinates, then normalize
            vec2 p = ((gl_FragCoord.xy + pixelOffset) * 2.0 - uResolution.xy) / min(uResolution.x, uResolution.y);
            vec2 parallax = (uMouse - 0.5) * 0.1;
            p += parallax;

            // Apply rotation if needed value in deg
            float angle = radians(uAngle);
            p = vec2(
                p.x * cos(angle) - p.y * sin(angle),
                p.x * sin(angle) + p.y * cos(angle)
            );

            float time = uSpeed > 0.0 ? uTime * uSpeed : (uMouse.x + uMouse.y) * 1.3 + uProgress * 0.3;

            // Apply scale (zoom in/out)
            float scale = mix(0.4, 2.6, (uScale * 1.2));
            p *= scale;

            // Simple pseudo noise
            float n = mod(dot(fragCoord, sin(fragCoord.yx)), 0.1);

            // Frequencies and offsets for the waves
            float freq[4];
            float offset[4];
            freq[0] = 0.7; freq[1] = 1.0; freq[2] = 1.3; freq[3] = 1.6;
            offset[0] = 0.0; offset[1] = 1.0; offset[2] = 2.0; offset[3] = 3.0;

            // Combine four color waves
            vec3 color = uBackgroundColor; //vec3(0.0);
            for (int i = 0; i < 4; i++) {
                float wave = tanh(0.2 / abs(p.y + 0.3 * cos(time + n + p.x * freq[i] + offset[i])));
                color += uColorStops[i] * wave;
            }

            // Apply glow/intensity control
            color *= mix(0.5, 2.0, (uIntensity * 0.6));

            // Clamp to avoid overbright areas
            color = clamp(color, 0.0, 1.0);

            color += (rand(gl_FragCoord.xy + uTime) - 0.5) * uNoise * 0.06;
            fragColor = vec4(color, 1.0);
        }
        void main() {
            mainImage(gl_FragColor, gl_FragCoord.xy);
        }
    `;

  // Normalize and scale settings function
  const normalizeSettings = raw => {
    const normalized = {
      ...raw
    };
    if (raw.scale !== undefined) {
      normalized.scale = parseFloat(raw.scale) * 0.02;
    }
    if (raw.noise !== undefined) {
      normalized.noise = parseFloat(raw.noise) * 0.09;
    }
    if (raw.intensity !== undefined) {
      normalized.intensity = parseFloat(raw.intensity) * 0.01;
    }
    if (raw.speed !== undefined) {
      normalized.speed = parseFloat(raw.speed) * 0.017;
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
    offsetY,
    angle
  } = normalizeSettings(userSettings);
  const renderer = new Renderer({
    canvas
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
      uInteractive: {
        value: mouseInteractive
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
      uOffsetX: {
        value: offsetX
      },
      uOffsetY: {
        value: offsetY
      },
      uAngle: {
        value: angle
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