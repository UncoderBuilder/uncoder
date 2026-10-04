// Light Rays (Uncoder's own): soft, broad beams of light fanning out from above a corner of the container over a wash of
// the same light, slowly drifting, in Colour 1 on a transparent layer (the container's own background shows through).
// The source sits above the top right corner; Offset X / Y move it (percent of half the width / height), Angle turns
// the beams, Scale sets how far the light reaches, Intensity how bright it is. The light fades towards the bottom.
window.uiAnimated_LightRays = function (el, canvas, userSettings = {}) {
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
        uniform vec3 uResolution;
        uniform vec2 uMouse;
        uniform float uIntensity;
        uniform float uScale;
        uniform float uSpeed;
        uniform float uProgress;
        uniform float uOffsetX;
        uniform float uOffsetY;
        uniform float uAngle;

        varying vec2 vUv;

        float hash(float n) {
            return fract(sin(n) * 43758.5453);
        }

        // Smooth 1D value noise: beams of varying width that drift without a visible period.
        float wave(float x) {
            float i = floor(x);
            float f = fract(x);
            f = f * f * (3.0 - 2.0 * f);
            return mix(hash(i), hash(i + 1.0), f);
        }

        void main() {
            vec2 res = uResolution.xy;
            // The source: above the top right corner (GL's y runs upwards), moved by the offsets.
            vec2 source = vec2(res.x * (1.0 + uOffsetX / 200.0), res.y * (1.35 + uOffsetY / 200.0));
            vec2 d = gl_FragCoord.xy - source;
            float dist = length(d) / length(res);
            float angle = atan(d.y, d.x) + radians(uAngle);
            float t = uSpeed > 0.0 ? uTime * uSpeed : uProgress * 0.1;

            // Two layers of broad beams across the angle, drifting in opposite directions: a wash of light with darker
            // bands (never below 45%).
            float beams = wave(angle * 7.0 + t * 0.35) * 0.55 + wave(angle * 3.5 - t * 0.5 + 7.0) * 0.45;
            beams = clamp(0.4 + beams * 0.75, 0.45, 1.0);

            // The light weakens with the distance from the source (Scale sets how far it reaches) and towards the
            // bottom of the container.
            float reach = 0.4 + uScale * 2.0;
            float fall = clamp(1.0 - dist / reach, 0.45, 1.0);
            float down = 1.0 - gl_FragCoord.y / res.y;
            float fade = clamp(1.3 - down, 0.3, 1.0);

            // Two such layers of light over each other.
            float light = beams * fall * fade;
            float alpha = clamp((2.0 * light - light * light) * uIntensity, 0.0, 1.0);
            gl_FragColor = vec4(uColorStops[0] * alpha, alpha);
        }
    `;

  const normalizeSettings = raw => {
    const normalized = {
      ...raw
    };
    if (raw.scale !== undefined) {
      normalized.scale = parseFloat(raw.scale) * 0.01;
    }
    if (raw.intensity !== undefined) {
      normalized.intensity = parseFloat(raw.intensity) * 0.02;
    }
    if (raw.speed !== undefined) {
      normalized.speed = parseFloat(raw.speed) * 0.02;
    }
    return normalized;
  };
  const {
    colorArray,
    speed,
    intensity,
    scale,
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
  // The beams are see-through: clear before every frame (ogl-lite draws over the last one, which suits opaque shaders).
  gl.clearColor(0, 0, 0, 0);
  const draw = renderer.render.bind(renderer);
  renderer.render = (options) => {
    gl.clear(gl.COLOR_BUFFER_BIT);
    draw(options);
  };
  const geometry = new Triangle(gl);
  const program = new Program(gl, {
    vertex,
    fragment,
    uniforms: {
      uTime: {
        value: 0
      },
      uColorStops: {
        value: colorArray ? [colorArray[0]] : [[1, 1, 1]]
      },
      uResolution: {
        value: [el.offsetWidth, el.offsetHeight, el.offsetWidth / el.offsetHeight]
      },
      uMouse: {
        value: [0.5, 0.5]
      },
      uIntensity: {
        value: intensity ?? 1
      },
      uSpeed: {
        value: speed ?? 0.4
      },
      uScale: {
        value: scale ?? 0.1
      },
      uProgress: {
        value: progress ?? 10
      },
      uOffsetX: {
        value: offsetX ?? 0
      },
      uOffsetY: {
        value: offsetY ?? 0
      },
      uAngle: {
        value: angle ?? 0
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
