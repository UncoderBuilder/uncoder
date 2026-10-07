window.uiAnimated_EchoSphere = function (el, canvas, userSettings = {}) {
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
        uniform float uScale;   // 0.0 to 10.0
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
        vec3 hsv2rgb(float h, float s, float v)
        {
            vec4 t = vec4(1.0, 2.0/3.0, 1.0/3.0, 3.0);
            vec3 p = abs(fract(vec3(h) + t.xyz) * 6.0 - vec3(t.w));
            return v * mix(vec3(t.x), clamp(p - vec3(t.x), 0.0, 1.0), s);
        }
        vec3 drawCircle(vec2 pos, float radius, float width, float power, vec4 color, float time)
        {
            float dist1 = length(pos);
            dist1 = fract((dist1 * 2.0) - fract(time));
            float dist2 = dist1 - radius;

            // Core circle intensity
            float intensity = pow(radius / abs(dist2), width);
            float edge = max((1.1 - abs(dist2)), 0.0);

            vec3 baseColor = color.rgb * intensity * power * edge;
            float blendFactor = smoothstep(0.0, .9, abs(dist2) * 5.0);

            vec3 col = mix(uBackgroundColor, baseColor, 1.0 - blendFactor);

            return col;
        }

        void main() {
            // Apply screen-based offset before normalization
            vec2 pixelOffset = vec2(
                (uOffsetX / 100.0) * (uResolution.x * 0.5),
                (uOffsetY / 100.0) * (uResolution.y * 0.5)
            );

            vec2 pos = ((gl_FragCoord.xy + pixelOffset) * 2.0 - uResolution.xy) / min(uResolution.x, uResolution.y);

            float time = uSpeed > 0.0 ? uTime * uSpeed : (uMouse.x + uMouse.y) * 0.1 + uProgress * 0.09;
            float baseSpeed = uSpeed > 0.0 ? uSpeed : 0.1;
            vec2 parallax = (uMouse - 0.5) * 0.1;
            pos += parallax;

            float colorShift = sin(uTime * 0.2 * uIntensity) * 0.5 + 0.28;
            float t = clamp(length(pos), 0.0, 1.0);
            vec3 gradColor = mix(uColorStops[0], uColorStops[1], mix(t, 1.0 - t, colorShift));
            vec4 color = vec4(gradColor, t * uScale);

            float radius = 0.7 * uScale * 0.2;
            float width = uIntensity;
            float power = 0.1;

            vec2 uv = pos;
            float waveTime = uTime * 1.0 * baseSpeed;
            float waveStrength = 0.02 * uNoise; // 0 = no motion, 1 = full waves

            uv.x += sin(uv.y * 6.0 + waveTime) * waveStrength;
            uv.y += cos(uv.x * 6.0 + waveTime * 1.2) * waveStrength;

            vec3 finalColor = drawCircle(uv, radius, width, power, color, time);

            float shimmerStrength = 0.002 * uNoise; // Controls shimmer blending
            float wave = sin((uv.x + uv.y + waveTime) * 2.0) * 0.2;
            float a = cos((uv.x - uv.y + waveTime * 0.5) * 1.8) * 0.2;

            float r = 0.1 + 0.01 * sin(a + waveTime * 0.8);
            float g = 0.1 + 0.01 * sin(wave + waveTime * 1.1);
            float b = 0.1 + 0.01 * sin(a - wave + waveTime * 0.9);

            vec3 col = vec3(r, g, b);

            finalColor *= mix(vec3(1.0), col, shimmerStrength);

            finalColor += 0.003 * uNoise * sin((uv.xyx + vec3(0.0, 0.0, 0.0)) * 1.0 + waveTime);

            float fadeStrength = clamp(uScale / 1.0, 0.0, 1.0);
            float dist = length(pos);
            float fade = mix(smoothstep(0.0, 0.5, dist), 1.0, fadeStrength);
            finalColor *= fade;

            // Mix between background and circle color instead of fading to black
            // finalColor = mix(uBackgroundColor, finalColor, fade);

            gl_FragColor = vec4(finalColor, 1.0);
        }


    `;
  const normalizeSettings = raw => {
    const normalized = {
      ...raw
    };
    if (raw.scale !== undefined) {
      normalized.scale = parseFloat(raw.scale) * 0.1;
    }
    if (raw.noise !== undefined) {
      normalized.noise = parseFloat(raw.noise) * 0.07;
    }
    if (raw.intensity !== undefined) {
      normalized.intensity = parseFloat(raw.intensity) * 0.01;
    }
    if (raw.speed !== undefined) {
      normalized.speed = parseFloat(raw.speed) * 0.005;
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