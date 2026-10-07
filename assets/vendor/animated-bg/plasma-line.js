window.uiAnimated_PlasmaLine = function (el, canvas, userSettings = {}) {
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
        uniform float uTime;
        uniform vec3  uResolution;
        uniform float uIntensity; // 0..1 glow intensity
        uniform float uScale;     // 0..1
        uniform float uNoise;
        uniform float uInteractive;
        varying vec2  vUv;
        uniform float uSpeed;
        uniform float uProgress;
        uniform vec2 uMouse;
        uniform float uOffsetX;
        uniform float uOffsetY;
        uniform float uAngle;

        float rand(vec2 co) {
            return fract(sin(dot(co, vec2(12.9898, 78.233))) * 43758.5453);
        }
        vec3 getColorCycle(float t) {
            float p = mod(t, 4.0);             // cycle between 0.0 and 4.0
            int idx = int(floor(p));           // which color stop we're at
            float f = fract(p);                // fractional blend amount
            vec3 c1 = (idx == 0) ? uColorStops[0] :
                    (idx == 1) ? uColorStops[1] :
                    (idx == 2) ? uColorStops[2] :
                                uColorStops[3];
            vec3 c2 = (idx == 0) ? uColorStops[1] :
                    (idx == 1) ? uColorStops[2] :
                    (idx == 2) ? uColorStops[3] :
                                uColorStops[0]; // next color
            return mix(c1, c2, smoothstep(0.0, 1.0, f));
        }

        void main() {

            vec2 I = gl_FragCoord.xy;
            vec4 O;
            vec2 pixelOffset = vec2(
                (uOffsetX / 100.0) * (uResolution.x * 0.5),
                (uOffsetY / 100.0) * (uResolution.y * 0.5)
            );

            // Shift pixel coordinates before normalization
            I += pixelOffset;

            vec2 u = (I + I - uResolution.xy) / uResolution.y;

            float time = uSpeed > 0.0 ? uTime * uSpeed :  (uMouse.x + uMouse.y) * 0.5 * (1.4 - uScale) + uProgress * 0.01;
            vec2 parallax = (uMouse - 0.5) * 0.2;
            u += parallax;
            float angle = radians(uAngle - 45.0);
            u = vec2(
                u.x * cos(angle) - u.y * sin(angle),
                u.x * sin(angle) + u.y * cos(angle)
            );

            float scale = mix(0.05, 0.95, uScale);
            u /= 0.5 + scale * dot(u, u);
            u += scale * cos(time) - 7.56;

            vec3 col = vec3(0.0);

            // Control glow spread and softness
            float glowSpread = mix(10.0, 2.0, uIntensity);
            float glowPower  = mix(8.0, 3.0, uIntensity);

            for (int i = 0; i < 3; i++) {
                float v = 1.0 - exp(-glowSpread / exp(glowPower * length(I + sin(5.0 * I.y - 3.0 * time + float(i)) / 4.0)));

                // Dynamic multi-color cycling
                vec3 cycleColor = getColorCycle(time * 0.2 + float(i) * 0.8);

                col += v * (0.2 + 0.4 * cycleColor);
                I = sin(1.5 * u.yx + 2.0 * cos(u -= 0.01));
            }

            // Add procedural noise
            col += (rand(I.xy + uTime * 2.0) - 0.5) * uNoise * 0.06;

            // Smooth transparency
            float alpha = clamp(length(col), 0.0, 1.0);
            O = vec4(col, alpha);

            gl_FragColor = O;
        }
    `;
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
      normalized.intensity = parseFloat(raw.intensity) * 0.007;
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
    mouseInteractive,
    progress,
    offsetX,
    offsetY,
    angle
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
      uInteractive: {
        value: mouseInteractive
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