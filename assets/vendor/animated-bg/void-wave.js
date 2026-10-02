window.uiAnimated_VoidWave = function (el, canvas, userSettings = {}) {
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

        uniform float uTime;
        uniform vec3 uColorStops[1];
        uniform vec3 uBackgroundColor;
        uniform vec3 uResolution;
        uniform vec2 uMouse;
        uniform float uIntensity;
        uniform float uScale;
        uniform float uNoise;
        uniform float uInteractive;
        uniform float uProgress;
        uniform float uSpeed;

        varying vec2 vUv;

        float rand(vec2 n) {
            return fract(sin(dot(n, vec2(12.9898, 4.1414))) * 43758.5453);
        }

        void mainImage(out vec4 fragColor, in vec2 fragCoord) {
            // Normalize and scale UV
            vec2 uv = (2.0 * fragCoord - uResolution.xy) / min(uResolution.x, uResolution.y);
            uv *= uScale; // apply scale
            float time = uSpeed > 0.0 ? uTime * uSpeed : (uMouse.x + uMouse.y) * 1.5 + uProgress * 0.3 ;
            vec2 parallax = (uMouse - 0.5) * 0.06;
            uv += parallax;

            // Apply animated wave distortion based on intensity
            for (float i = 1.0; i < 8.0; i++) {
                float wave = sin(uv.x * i * i + time * 0.5) * sin(uv.y * i * i + time * 0.5);
                uv.y += (i * 0.1 / i) * wave * uIntensity * 0.12;
            }

            // Compute base color gradient
            vec3 col;
            col.r = uv.y + 0.6;
            col.g = uv.y + 0.6;
            col.b = uv.y + 0.6;

            // Apply base color tint
            col *= uColorStops[0];


            float brightness = dot(col, vec3(0.299, 0.587, 0.114));
            float mixStrength = 2.0;
            col = mix(uBackgroundColor, col, brightness * mixStrength);

            // Add subtle noise
            col += (rand(gl_FragCoord.xy + uTime * 0.5) - 0.5) * uNoise * 0.1;

            fragColor = vec4(col, 1.0);
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
      normalized.scale = parseFloat(raw.scale) * 0.01;
    }
    if (raw.noise !== undefined) {
      normalized.noise = parseFloat(raw.noise) * 0.02;
    }
    if (raw.intensity !== undefined) {
      normalized.intensity = parseFloat(raw.intensity) * 0.09;
    }
    if (raw.speed !== undefined) {
      normalized.speed = parseFloat(raw.speed) * 0.01;
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
      uBackgroundColor: {
        value: backgroundColor
      },
      uResolution: {
        value: [el.offsetWidth, el.offsetHeight, el.offsetWidth / el.offsetHeight]
      },
      uMouse: {
        value: [0.5, 0.5]
      },
      uInteractive: {
        value: mouseInteractive
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
      uProgress: {
        value: progress
      },
      uNoise: {
        value: noise
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