window.uiAnimated_TheShining = (el, canvas, userSettings = {}) => {
  const {
    Renderer,
    Program,
    Mesh,
    Triangle
  } = window.uncoder_ogl;
  const vertex = `
        attribute vec2 position;
        varying vec2 vUv;
        void main() {
            vUv = position * 0.5 + 0.5;
            gl_Position = vec4(position, 0.0, 1.0);
        }
    `;
  const fragment = `
        precision highp float;

        uniform float uTime;
        uniform vec3  uResolution;
        uniform vec3  uColorStops[1];
        uniform float uNoise;
        uniform float uIntensity;
        uniform float uScale;
        uniform float uSpeed;
        uniform vec2  uMouse;
        uniform float uProgress;
        uniform float uInteractive;
        uniform float uOffsetX;
        uniform float uOffsetY;
        uniform float uAngle;

        varying vec2 vUv;

        float noise(vec2 st) {
            return fract(sin(dot(st.xy, vec2(12.9898,78.233))) * 43758.5453123);
        }
        float rayStrength(vec2 raySource, vec2 rayRefDirection, vec2 coord,
                        float seedA, float seedB, float speed,
                        float lightSpread, float rayLength, float fadeDistance, float time) {
            vec2 src = coord - raySource;
            vec2 dirNorm = normalize(src);
            float cosAngle = dot(dirNorm, rayRefDirection);
            cosAngle = cosAngle + 0.001 * sin(time * 2.0 + length(src) * 0.01) * 0.2;

            float spreadFactor = pow(max(cosAngle, 0.0), 1.0 / max(lightSpread, 0.001));

            float distance = length(src);
            float maxDistance = uResolution.x * rayLength;
            float lengthFalloff = clamp((maxDistance - distance) / maxDistance, 0.0, 1.0);

            float fadeFalloff = clamp((uResolution.x * fadeDistance - distance) / (uResolution.x * fadeDistance), 0.5, 1.0);

            float baseStrength = clamp(
                (0.45 + 0.15 * sin(cosAngle * seedA + time * speed)) +
                (0.3 + 0.2 * cos(-cosAngle * seedB + time * speed)),
                0.0, 1.0
            );

            return baseStrength * lengthFalloff * fadeFalloff * spreadFactor;
        }
        void main() {
            vec2 pixelOffset = vec2(
                (uOffsetX / 100.0) * (uResolution.x * 0.5),
                (uOffsetY / 100.0) * (uResolution.y * 0.5)
            );

            vec2 coord = vec2(gl_FragCoord.x, uResolution.y - gl_FragCoord.y) + pixelOffset;

            float time = uSpeed > 0.0 ? uTime * uSpeed :  uProgress * 0.2;

            vec2 rayPos = vec2(0.5 * uResolution.x, -0.2 * uResolution.y);
            vec2 rayDir = vec2(0.0, 1.0);

            vec2 finalRayDir = rayDir;
            if (uInteractive > 0.0) {
                vec2 mouseScreenPos = uMouse * uResolution.xy;
                vec2 mouseDirection = normalize(mouseScreenPos - rayPos);
                finalRayDir = normalize(mix(rayDir, mouseDirection, uInteractive * 0.1));
            }

            float angle = radians(uAngle);
            mat2 rot = mat2(cos(angle), -sin(angle),
                            sin(angle),  cos(angle));
            finalRayDir = normalize(rot * finalRayDir);

            float fadeDistance = 0.5;
            float lightSpread = uScale * 1.0 + 0.01;

            vec4 rays1 = vec4(1.0) *
                rayStrength(rayPos, finalRayDir, coord, 36.2214, 21.11349,
                            1.5, lightSpread, uIntensity, fadeDistance, time);
            vec4 rays2 = vec4(1.0) *
                rayStrength(rayPos, finalRayDir, coord, 22.3991, 18.0234,
                            1.1, lightSpread, uIntensity, fadeDistance, time);

            vec4 fragColor = rays1 * 0.5 + rays2 * 0.4;

            if (uNoise > 0.0) {
                float n = noise(coord * 0.01 + time * 0.1);
                fragColor.rgb *= (1.0 - uNoise + uNoise * n);
            }

            // Simple gradient brightness by vertical position
            float brightness = 1.0 - (coord.y / uResolution.y);
            fragColor.x *= 0.1 + brightness * 0.8;
            fragColor.y *= 0.3 + brightness * 0.6;
            fragColor.z *= 0.5 + brightness * 0.5;

            fragColor.rgb *= uColorStops[0];

            gl_FragColor = fragColor;
        }
    `;
  const normalizeSettings = raw => {
    const normalized = {
      ...raw
    };
    if (raw.scale !== undefined) {
      normalized.scale = parseFloat(raw.scale) * 0.025;
    }
    if (raw.intensity !== undefined) {
      normalized.intensity = parseFloat(raw.intensity) * 0.025;
    }
    if (raw.speed !== undefined) {
      normalized.speed = parseFloat(raw.speed) * 0.03;
    }
    if (raw.noise !== undefined) {
      normalized.noise = parseFloat(raw.noise) * 0.015;
    }
    if (raw.colorArray !== undefined && Array.isArray(raw.colorArray)) {
      normalized.colorArray = raw.colorArray.slice(0, 1);
    }
    return normalized;
  };
  let {
    colorArray,
    scale,
    intensity,
    speed,
    noise,
    progress,
    mouseInteractive,
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
        value: 10
      },
      uResolution: {
        value: [el.offsetWidth, el.offsetHeight]
      },
      uColorStops: {
        value: colorArray
      },
      uNoise: {
        value: noise
      },
      uSpeed: {
        value: speed
      },
      uIntensity: {
        value: intensity
      },
      uScale: {
        value: scale
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