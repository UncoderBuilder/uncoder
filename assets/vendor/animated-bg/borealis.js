window.uiAnimated_Borealis = (el, canvas, userSettings = {}) => {
  const {
    Renderer,
    Program,
    Mesh,
    Triangle
  } = window.uncoder_ogl;
  const vertex = `
        attribute vec2 position;
        void main() {
            gl_Position = vec4(position, 0.0, 1.0);
        }
    `;
  const fragment = `
        precision highp float;

        uniform float uTime;
        uniform float uScale;
        uniform float uNoise;
        uniform vec3 uColorStops[4];
        uniform vec3 uResolution;
        uniform vec2 uMouse;
        uniform vec3 uBackgroundColor;
        uniform float uIntensity;
        uniform float uSpeed;
        uniform float uProgress;
        uniform float uInteractive;


        vec3 permute(vec3 x) {
            return mod(((x * 34.0) + 1.0) * x, 289.0);
        }

        float snoise(vec2 v){
            const vec4 C = vec4(0.211324865405187, 0.366025403784439,
                                -0.577350269189626, 0.024390243902439);
            vec2 i  = floor(v + dot(v, C.yy));
            vec2 x0 = v - i + dot(i, C.xx);
            vec2 i1 = (x0.x > x0.y) ? vec2(1.0,0.0) : vec2(0.0,1.0);
            vec4 x12 = x0.xyxy + C.xxzz;
            x12.xy -= i1;
            i = mod(i,289.0);
            vec3 p = permute(permute(i.y + vec3(0.0,i1.y,1.0)) + i.x + vec3(0.0,i1.x,1.0));
            vec3 m = max(0.5 - vec3(dot(x0,x0), dot(x12.xy,x12.xy), dot(x12.zw,x12.zw)), 0.0);
            m = m*m*m*m;
            vec3 x = 2.0 * fract(p * C.www) - 1.0;
            vec3 h = abs(x) - 0.5;
            vec3 ox = floor(x+0.5);
            vec3 a0 = x-ox;
            m *= 1.79284291400159 - 0.85373472095314 * (a0*a0 + h*h);
            vec3 g;
            g.x  = a0.x*x0.x + h.x*x0.y;
            g.yz = a0.yz*x12.xz + h.yz*x12.yw;
            return 130.0 * dot(m,g);
        }

        float random(vec2 st) {
            return fract(sin(dot(st.xy, vec2(12.9898,78.233))) * 43758.5453123);
        }

        struct ColorStop { vec3 color; float position; };

        vec3 getColorRamp(ColorStop colors[4], float factor) {
            ColorStop c0 = colors[0];
            ColorStop c1 = colors[1];
            ColorStop c2 = colors[2];
            ColorStop c3 = colors[3];
            vec3 color;

            if (factor <= c1.position) {
                float t = (factor - c0.position) / (c1.position - c0.position);
                color = mix(c0.color, c1.color, t);
            } else if (factor <= c2.position) {
                float t = (factor - c1.position) / (c2.position - c1.position);
                color = mix(c1.color, c2.color, t);
            } else {
                float t = (factor - c2.position) / (c3.position - c2.position);
                color = mix(c2.color, c3.color, t);
            }

            return color;
    }

        void main() {
            vec2 uv = gl_FragCoord.xy / uResolution.xy;
            float time = uSpeed > 0.0 ? uTime * uSpeed : (uMouse.x + uMouse.y) * 3.0 + uProgress * 0.09;
            float shift = sin(time * 0.2) * 0.13;

            // Aurora color ramp setup
            ColorStop colors[4];
            colors[0] = ColorStop(uColorStops[0], clamp(0.0 + shift, 0.0, 1.0));
            colors[1] = ColorStop(uColorStops[1], clamp(0.33 + shift, 0.0, 1.0));
            colors[2] = ColorStop(uColorStops[2], clamp(0.66 + shift, 0.0, 1.0));
            colors[3] = ColorStop(uColorStops[3], clamp(1.0 + shift, 0.0, 1.0));

            vec3 rampColor = getColorRamp(colors, uv.x);


            // Base aurora pattern
            float n = snoise(vec2(uv.x * 2.0 + time * 0.1, time * 0.25));
            float height = exp(n * 0.5 * uScale);
            height = uv.y * 2.0 - height + 0.2;
            float intensity = 0.6 * height;

            float midPoint = 0.2;
            float auroraAlpha = smoothstep(midPoint - uIntensity * 0.5, midPoint + uIntensity * 0.5, intensity);
            vec3 auroraColor = intensity * rampColor;


            float distToMouse = distance(uv, vec2(uMouse.x, 1.0 - uMouse.y));
            float mouseField = smoothstep(0.4, 0.0, distToMouse);
            float mouseNoise = snoise(vec2(uv.x * 3.0, uv.y * 6.0 + time * 0.3)) * 30.9 * mouseField;
            float localIntensity = intensity + mouseNoise;
            vec3 interactiveColor = auroraColor * (1.0 + mouseField * 0.6);

            vec3 finalColor = mix(uBackgroundColor, interactiveColor, auroraAlpha);

            // Optional subtle noise overlay
            float grain = (random(gl_FragCoord.xy + time * 0.1) - 0.5) * 0.1;
            finalColor += grain * uNoise;

            gl_FragColor = vec4(finalColor, 1.0);
        }
    `;

  // Normalize and scale settings function
  const normalizeSettings = raw => {
    const normalized = {
      ...raw
    };
    // Apply consistent scaling rules
    if (raw.scale !== undefined) {
      normalized.scale = parseFloat(raw.scale) * 0.1;
    }
    if (raw.intensity !== undefined) {
      normalized.intensity = parseFloat(raw.intensity) * 0.02;
    }
    if (raw.speed !== undefined) {
      normalized.speed = parseFloat(raw.speed) * 0.03;
    }
    if (raw.noise !== undefined) {
      normalized.noise = parseFloat(raw.noise) * 0.07;
    }
    return normalized;
  };
  let {
    colorArray,
    scale,
    intensity,
    speed,
    backgroundColor,
    noise,
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
  gl.clearColor(0, 0, 0, 0);
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
      uScale: {
        value: scale
      },
      uColorStops: {
        value: colorArray
      },
      uResolution: {
        value: [el.offsetWidth, el.offsetHeight]
      },
      uIntensity: {
        value: intensity
      },
      uSpeed: {
        value: speed
      },
      uBackgroundColor: {
        value: backgroundColor
      },
      uNoise: {
        value: noise
      },
      uMouse: {
        value: [0.5, 0.5]
      },
      uProgress: {
        value: progress
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