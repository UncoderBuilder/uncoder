window.uiAnimated_NeonEclipse = function (el, canvas, userSettings = {}) {
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
        uniform vec3  uColorStops[4];
        uniform vec3  uResolution;
        uniform float uIntensity; // 0..1 glow intensity
        uniform float uScale;     // 0..1
        uniform float uNoise;     // 0..1
        uniform float uSpeed;
        uniform float uProgress;
        uniform vec2 uMouse;
        uniform float uInteractive;
        uniform float uStatic;
        uniform float uOffsetX;
        uniform float uOffsetY;

        varying vec2  vUv;

        const float PI = 3.141592653589793;


        float fastTanh(float x) { return (2.0 / (1.0 + exp(-2.0 * x))) - 1.0; }
        vec3  fastTanh(vec3  x) { return (2.0 / (1.0 + exp(-2.0 * x))) - 1.0; }
        float rand(vec2 co) {
            return fract(sin(dot(co, vec2(12.9898,78.233))) * 43758.5453);
        }

        vec3 getCycledColor(float t) {
            t = fract(t);
            if (t < 0.25) {
                float f = smoothstep(0.0, 0.25, t);
                return mix(uColorStops[0], uColorStops[1], f);
            } else if (t < 0.5) {
                float f = smoothstep(0.25, 0.5, t);
                return mix(uColorStops[1], uColorStops[2], f);
            } else if (t < 0.75) {
                float f = smoothstep(0.5, 0.75, t);
                return mix(uColorStops[2], uColorStops[3], f);
            } else {
                float f = smoothstep(0.75, 1.0, t);
                return mix(uColorStops[3], uColorStops[0], f);
            }
        }

        void main() {
        {
            vec2 pixelOffset = vec2(
                (uOffsetX / 100.0) * (uResolution.x * 0.5),
                (uOffsetY / 100.0) * (uResolution.y * 0.5)
            );

            // Shift pixel coordinates, then normalize
            vec2 pos = ((gl_FragCoord.xy + pixelOffset) * 2.0 - uResolution.xy) / min(uResolution.x, uResolution.y);

            float time = uSpeed > 0.0 ? uTime * uSpeed : (uMouse.x + uMouse.y) * 0.2 + uProgress * 0.06;
            vec2 parallax = (uMouse - 0.5) * 0.06;
            pos += parallax;

            float r = length(pos);
            float theta = atan(pos.y, pos.x);

            float R = 0.2 * uScale;  // radius
            float w = mix(0.015, 0.15, clamp(uScale, 0.0, 1.0));
            float sharp = mix(60.0, 100.0, clamp(uScale, 0.0, 1.0));

            float tShift = time * 0.5;
            float t = fract((theta / (2.0 * PI)) - tShift);

            // Make gradient cover only part of ring (like a sweep)
            // 0.0–0.5 is the transition, rest holds steady last color
            float gradWidth = 1.0;  // how much of ring is blended between two colors
            float localT = smoothstep(0.0, gradWidth, t) * (1.0 - smoothstep(1.0 - gradWidth, 1.0, t));

            vec3 baseColorA = getCycledColor(t);
            vec3 baseColorB = getCycledColor(t + 0.25); // next stop ahead
            vec3 ringColor = mix(baseColorA, baseColorB, localT);

            float d = abs(r - R);
            float glow = exp(-d * sharp); // Increased glow size
            glow += 0.5 * exp(-d * (sharp * 0.04)); // Increased secondary glow size

            float innerDisk = smoothstep(R - w, R - w * 0.1, r * 0.8);
            vec3 color = ringColor * glow * uIntensity * uScale * 2.5 * innerDisk;


            // Optional contrast shaping + noise
            color = 0.5 + 0.5 * fastTanh(color - 1.5);
            color += (rand(gl_FragCoord.xy + time) - 0.5) * uNoise * 0.06;

            gl_FragColor = vec4(color, clamp(glow, 0.0, 1.0));
        }

        }
    `;

  // Normalize and scale settings function
  const normalizeSettings = raw => {
    const normalized = {
      ...raw
    };
    if (raw.scale !== undefined) {
      normalized.scale = parseFloat(raw.scale) * 0.09;
    }
    if (raw.noise !== undefined) {
      normalized.noise = parseFloat(raw.noise) * 0.02;
    }
    if (raw.intensity !== undefined) {
      normalized.intensity = parseFloat(raw.intensity) * 0.2;
    }
    if (raw.speed !== undefined) {
      normalized.speed = parseFloat(raw.speed) * 0.01;
    }
    if (raw.mouseInteractive !== undefined) {
      normalized.mouseInteractive = raw.mouseInteractive === true;
    }
    if (raw.staticSpeed !== undefined) {
      normalized.staticSpeed = raw.staticSpeed === true;
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
  gl.enable(gl.BLEND);
  gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA);
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