window.uiAnimated_PulseBubble = function (el, canvas, userSettings = {}) {
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
        uniform vec3 uColorStops[2];
        uniform vec3 uBackgroundColor;
        uniform vec3 uResolution;
        uniform vec2 uMouse;
        uniform float uIntensity;
        uniform float uScale;
        uniform float uNoise;
        uniform float uSpeed;
        uniform float uProgress;
        uniform float uInteractive;
        uniform float uOffsetX;
        uniform float uOffsetY;

        varying vec2 vUv;

        float rand(vec2 n) {
            return fract(sin(dot(n, vec2(12.9898, 4.1414))) * 43758.5453);
        }


        float map(vec3 p, float time) {
            float amplitude = uIntensity * 0.3;

            float frequency = 27.0 * sin(time * 0.25);
            float speed = 2.0;

            float angle = tan(p.y + p.x * 0.01);
            float dynamic_radius = (1.0 + amplitude * sin(angle * frequency + time * speed)) * uScale * 0.5;

            return length(p) - dynamic_radius;
        }


        vec3 calcNormal(vec3 p, float time) {
            vec2 e = vec2(0.01, 0.1);
            return normalize(
                vec3(
                    map(p + e.xyy, time) - map(p - e.xyy, time),
                    map(p + e.yxy, time) - map(p - e.yxy, time),
                    map(p + e.yyx, time) - map(p - e.yyx, time)
                )
            );
        }

        void main() {
             vec2 pixelOffset = vec2(
                (uOffsetX / 100.0) * (uResolution.x * 0.5),
                (uOffsetY / 100.0) * (uResolution.y * 0.5)
            );

            // Shift pixel coordinates, then normalize
            vec2 pos = ((gl_FragCoord.xy + pixelOffset) * 2.0 - uResolution.xy) / min(uResolution.x, uResolution.y);

            float time = uSpeed > 0.0 ? uTime * uSpeed :  (uMouse.x + uMouse.y) * 0.7 + uProgress * 0.09;
            vec2 parallax = (uMouse - 0.5) * 0.2;
            pos += parallax;

            // Camera setup
            vec3 ro = vec3(0.0, 0.0, 2.05); // Ray origin (camera position)
            vec3 ta = vec3(0.0, 0.0, 0.0);  // Target to look at

            // Camera orientation
            vec3 fwd = normalize(ta - ro);
            vec3 right = normalize(cross(fwd, vec3(0.0, 1.0, 0.0)));
            vec3 up = cross(right, fwd);
            vec3 rd = normalize(fwd + pos.x * right + pos.y * up); // Ray direction

            // Raymarching loop
            float t = 0.0;
            for (int i = 0; i < 100; i++) {
                vec3 p = ro + t * rd;
                float d = map(p, time);
                if (abs(d) < 0.001 || t > 20.0) break;
                t += d;
            }

            vec3 col = vec3( 0.0 );
            if (t < 20.0) {
                vec3 pos = ro + t * rd; // Position of the hit
                vec3 nor = calcNormal(pos, time); // Normal at the hit position
                vec3 lightDir = normalize(vec3(1.0, 1.0, -1.0));
                float dif = max(0.0, dot(nor, lightDir)); // Diffuse lighting
                col = uColorStops[0] * dif + vec3(0.1); // Apply color and ambient light
                // Add lighting effects
                col = mix(uColorStops[1], col, col.r + col.g + col.b);
            } else {
                // Use background color when no object is hit
                col = uBackgroundColor;
            }
            

            // Apply noise
            col += (rand(gl_FragCoord.xy + time) - 0.5) * uNoise * 0.1;

            gl_FragColor = vec4(col, 1.0); // Set the final fragment color
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
      normalized.noise = parseFloat(raw.noise) * 0.09;
    }
    if (raw.intensity !== undefined) {
      normalized.intensity = parseFloat(raw.intensity) * 0.01;
    }
    if (raw.speed !== undefined) {
      normalized.speed = parseFloat(raw.speed) * 0.03;
    }
    if (raw.colorArray !== undefined && Array.isArray(raw.colorArray)) {
      normalized.colorArray = raw.colorArray.slice(0, 2);
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