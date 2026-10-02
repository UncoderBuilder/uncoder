window.uiAnimated_NoirHaze = function (el, canvas, userSettings = {}) {
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

        float noise(vec2 p){
            vec2 ip = floor(p);
            vec2 u = fract(p);
            u = u*u*(3.0-2.0*u);

            float res = mix(
                mix(rand(ip),rand(ip+vec2(1.0,0.0)),u.x),
                mix(rand(ip+vec2(0.0,1.0)),rand(ip+vec2(1.0,1.0)),u.x),u.y);
            return res;
        }

        const mat2 mtx = mat2(0.80, 0.60, -0.60, 0.80);

        float fbm(vec2 p, float time)
        {
            float f = 0.0;
            f += 0.500000 * noise(p + time); p = mtx * p * 2.02;
            f += 0.250000 * noise(p);         p = mtx * p * 2.03;
            f += 0.125000 * noise(p);         p = mtx * p * 2.01;
            f += 0.062500 * noise(p);         p = mtx * p * 2.04;
            f += 0.031250 * noise(p + sin(time));
            return f / 0.96875;
        }

        float pattern(in vec2 p, float time)
        {
            return fbm(p + fbm(p + fbm(p, time), time), time);
        }

        void main() {
            vec2 uv = gl_FragCoord.xy / uResolution.x;
            uv *= uScale;
            float time = uSpeed > 0.0 ? uTime * uSpeed : (uMouse.x + uMouse.y) * 0.3 + uProgress * 0.3;
            vec2 parallax = (uMouse - 0.5) * 0.1;
            uv += parallax;

            float shade = pattern(uv, time);
            shade = clamp(shade, 0.0, 1.0);

            // Blend between uColorStops[0] and uBackgroundColor
            float mixAmount = shade * uIntensity * 0.3;
            vec3 finalColor = mix(uColorStops[0], uBackgroundColor, mixAmount);

            // Add optional procedural noise
            finalColor += (rand(gl_FragCoord.xy + uTime) - 0.5) * uNoise * 0.1;
            finalColor = clamp(finalColor, 0.0, 1.0);

            gl_FragColor = vec4(finalColor, 1.0);
        }

    `;

  // Normalize and scale settings function
  const normalizeSettings = raw => {
    const normalized = {
      ...raw
    };
    if (raw.scale !== undefined) {
      normalized.scale = parseFloat(raw.scale) * 0.2;
    }
    if (raw.noise !== undefined) {
      normalized.noise = parseFloat(raw.noise) * 0.04;
    }
    if (raw.intensity !== undefined) {
      normalized.intensity = parseFloat(raw.intensity) * 0.1;
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