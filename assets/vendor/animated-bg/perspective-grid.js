window.uiAnimated_PerspectiveGrid = function (el, canvas, userSettings = {}) {
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

  // Fragment shader
  const fragment = `
        precision mediump float;
        precision mediump int;

        uniform float uTime;
        uniform vec3 uColorStops[4];
        uniform vec3 uBackgroundColor;
        uniform vec3 uResolution;
        uniform float uIntensity;
        uniform float uScale;
        uniform float uNoise;
        uniform vec2 uMouse;
        uniform float uSpeed;
        uniform float uProgress;
        uniform float uInteractive;
        uniform float uOffsetX;
        uniform float uOffsetY;


        // Utility functions
        float remap01(float a, float b, float t) {
            return clamp((t - a) / (b - a), 0.0, 1.0);
        }

        float remap(float a, float b, float c, float d, float t) {
            return remap01(a, b, t) * (d - c) + c;
        }

        float remapF01(float a, float b, float t) {
            return t * (b - a) + a;
        }

        float N21(vec2 p) {
            p = fract(p * vec2(233.34, 851.73));
            p += dot(p, p + 237.45);
            return fract(p.x * p.y);
        }

        vec2 N22(vec2 p) {
            float n = N21(p);
            return vec2(n, N21(p + n));
        }

        vec2 PerspectiveUV(vec2 uv) {
            float z = 1.0 / abs(uv.y);
            return vec2(uv.x * z, z);
        }

        vec2 GetPos(vec2 id) {
            vec2 n = N22(id) * uTime * 0.7;
            return sin(n) * 0.4;
        }

        float Circle(vec2 uv, vec2 p, float r, float blur) {
            float d = length(uv - p);
            return smoothstep(r, r - blur, d);
        }

        float Band(float t, float start, float end, float blur) {
            float step1 = smoothstep(start - blur, start + blur, t);
            float step2 = smoothstep(end + blur, end - blur, t);
            return step1 * step2;
        }

        float Rectangle(vec2 uv, float left, float right, float bottom, float top, float blur) {
            float band1 = Band(uv.x, left, right, blur);
            float band2 = Band(uv.y, bottom, top, blur);
            return band1 * band2;
        }

        float GridLine(vec2 uv, vec2 ouv, float density, float width) {
            float gridLine = 0.0;
            float masky = abs(ouv.y - 0.5);
            ouv = 1.0 - abs(ouv - 0.5);
            uv *= density;
            uv = fract(uv);
            uv -= 0.5;
            uv = abs(uv);
            float maskx = uv.x;

            uv.x = smoothstep(width, 0.0, uv.x);
            uv.y = smoothstep(ouv.y * ouv.y * width * 5.0, 0.0, uv.y);
            gridLine = max(uv.y, uv.x);

            maskx = smoothstep(0.1, 0.0, maskx);
            masky = smoothstep(0.1, 0.3, masky);
            masky = clamp((masky - maskx), 0.0, 1.0);
            return gridLine * (maskx + masky);
        }

        vec2 GridRing(vec2 uv, float density, float radius, float Blur, float holeRadiusRatio) {
            uv *= density;
            uv = fract(uv);
            uv -= 0.5;
            uv = abs(uv);

            float circle = Circle(uv, vec2(0.0), radius, Blur);
            float ring = circle - Circle(uv, vec2(0.0), radius * holeRadiusRatio, Blur);
            return vec2(circle, ring);
        }

        float GridRingLine(vec2 uv, vec2 ouv, float density, float width, float radius, float Blur, float holeRadiusRatio) {
            float grid = GridLine(uv, ouv, density, width);
            vec2 ring = GridRing(uv, density, radius, Blur, holeRadiusRatio);
            grid *= (1.0 - ring.x);
            grid += ring.y;
            return grid;
        }

        float RandomSquares(vec2 uv, float density) {
            uv *= density;
            vec2 id = floor(uv);
            uv = fract(uv) - 0.5;
            vec2 p = N22(id);
            p = vec2(remapF01(-0.5, 0.5, p.x), remapF01(-0.5, 0.5, p.y));
            float s = remapF01(0.02, 0.3, N21(id));
            float f = N21(id + 365.22);
            float rect = Rectangle(uv, p.x - s, p.x + s, p.y - s, p.y + s, 0.02) * f * (sin(uTime * 5.0 + N21(id + 765.1) * 6.283185) + 1.0) * 0.5;
            return rect;
        }

        float RandomSparkers(vec2 uv, float density, float i) {
            uv *= density;
            vec2 id = floor(uv);
            uv = fract(uv) - 0.5;
            float maxR = 0.1;
            vec2 p = N22(id + i);
            p = vec2(remapF01(-0.5 + maxR, 0.5 - maxR, p.x), remapF01(-0.5 + maxR, 0.4 - maxR, p.y));
            vec2 j = (p - uv) * 70.0;
            float dots = 1.0 / dot(j, j);
            dots *= sin(uTime * 10.0 + p.x * 10.0) * 0.5 + 0.5;
            return dots;
        }

        float RandomSparkersForward(vec2 uv, float speed, float scale) {
            float sparkerForward = 0.0;
            for (float k = 0.0; k < 1.0; k += 0.25) {
                float z = fract(k + uTime * speed);
                float size = mix(scale, 0.5, z);
                float fade = smoothstep(0.0, 0.5, z) * smoothstep(1.0, 0.6, z);
                sparkerForward += RandomSparkers(uv, size, k) * fade;
            }
            return sparkerForward;
        }

        float RandomSparkersRise(vec2 uv, float density) {
            uv *= density;
            vec2 id = floor(uv);
            uv = fract(uv) - 0.5;
            vec2 p = GetPos(id);
            vec2 j = (p - uv) * 70.0;
            float dots = 1.0 / dot(j, j);
            dots *= sin(uTime * 10.0 + p.x * 10.0) * 0.5 + 0.9;
            return dots;
        }

        float FlowLines(vec2 uv, float resolutionX, float resolutionY) {
            uv.x -= 0.5;
            uv.x = abs(uv.x) * 2.0;
            uv.x = 1.0 - uv.x;
            uv.x *= 2.0;

            uv.y = 1.0 - uv.y;
            uv.y += uv.x;

            float a = fract(tan(resolutionX) * 5.0);
            float b = a * 10.0 * uv.y / (mod(a * uResolution.y * (uTime + 60.0) * 0.1 * uSpeed, uResolution.x) - resolutionY) * uv.y;
            return b;
        }

        void main() {
            // Apply screen-based offset before normalization
            vec2 pixelOffset = vec2(
                (uOffsetX / 100.0) * (uResolution.x * 0.5),
                (uOffsetY / 100.0) * (uResolution.y * 0.5)
            );

            // Shift pixel coordinates, then normalize
            vec2 fragCoord = gl_FragCoord.xy + pixelOffset;



            float time = uSpeed > 0.0 ? uTime * uSpeed : (uMouse.x + uMouse.y) * 1.0 + uProgress * 0.09;
            float baseSpeed = uSpeed > 0.0 ? uSpeed : 0.2;
            // Horizontal parallax only (affects X)
            float parallaxX = (uMouse.x - 0.5) * 0.1;

            // Vertical parallax only (affects Y)
            float parallaxY = (uMouse.y - 0.5) * 0.04;

            vec3 _horizonColor = uColorStops[0];
            vec3 _gridColor = uColorStops[1];
            vec3 _sparkColor = uColorStops[2];
            vec3 _squareColor = uColorStops[3];


            float _gridSpeed = 0.3 * baseSpeed;
            float _gridScale = 2.0 * uScale;
            float _gridLineWidth = 0.015;
            float _gridRingRadius = 0.08;
            float _gridRingHoleRate = 0.8;
            float _gridIntensity = 1.0;

            float _squareSpeed = 0.04 * baseSpeed;
            float _squareScale = 1.4 * uScale;
            float _squareIntensity = 1.0;

            float _sparkerForwardSpeed = 0.04 * baseSpeed;
            float _sparkerForwardScale = 10.0 * uScale;
            float _sparkerForwardIntensity = 0.1;

            float _sparkRiseSpeed = 0.05 * baseSpeed;
            float _sparkRiseScale = 10.0 * uScale;
            float _sparkRiseIntensity = 0.8;

            float _BGcircleScale = 1.0 - (1.0 * uIntensity);
            float _haloBGIntensity = 2.0;
            float _horizontalHight = 0.1;
            float _horizonBGIntensity = 0.3 * uIntensity;

            // vec2 fragCoord = gl_FragCoord.xy;
            vec2 ouv = fragCoord / uResolution.xy;
            vec2 uv = (fragCoord - 0.5 * uResolution.xy) / uResolution.y;
            uv.y -= _horizontalHight;




            vec2 centeredUV = uv;
            float rotateStrength = radians(5.0); // Max rotation angle (in radians)
            float rotation = (uMouse.x - 0.5) * -rotateStrength;
            float tiltStrength = 0.3; // adjust for subtle or strong effect
            float tilt = (uMouse.y - 0.5) * -tiltStrength;
            float cosA = cos(rotation);
            float sinA = sin(rotation);
            mat2 rot = mat2(cosA, -sinA, sinA, cosA);
            centeredUV = rot * centeredUV;
            centeredUV.x += centeredUV.y * tilt;

            // Apply back
            uv = centeredUV;




            vec2 ouv2 = uv;
            uv.y -= parallaxY;
            ouv2.x *= uResolution.y / uResolution.x;
            ouv2 += 0.5;

            float fade = clamp(remap(0.0, 0.5, 0.0, 0.8, -uv.y - 0.12), 0.0, 1.0);
            float inversFade = clamp(remap(0.0, 0.3, 0.0, 0.5, uv.y - (-0.10)), 0.0, 1.0);

            vec2 gridUv = uv + vec2(parallaxX, 0.0);
            vec2 luv = PerspectiveUV(gridUv);
            vec2 puv = PerspectiveUV(uv);
            float circleFade=smoothstep(-0.6+_BGcircleScale,0.2+_BGcircleScale,1.-length((ouv-0.5)*2.0));
            float gridRingLine = GridRingLine(vec2(luv.x, luv.y + time * _gridSpeed), ouv2, _gridScale, _gridLineWidth, _gridRingRadius, 0.02, _gridRingHoleRate);
            float squares = RandomSquares(vec2(puv.x, puv.y + mod(uTime, 7200.0) * _squareSpeed), _squareScale);
            float sparkerRise = RandomSparkersRise(vec2(uv.x, uv.y - uTime * _sparkRiseSpeed), _sparkRiseScale);
            float sparkerForward = RandomSparkersForward(uv, _sparkerForwardSpeed, _sparkerForwardScale);
            float flowLine = FlowLines(gridUv, fragCoord.x, fragCoord.y);

            // Mask for lower part: grid & rings
            float maskdown = clamp(
                gridRingLine * _gridIntensity +
                sparkerForward * _sparkerForwardIntensity,
                0.0, 1.0
            ) * fade * 1.5;

            // Mask for upper part: rising sparks + flow lines
            float maskup = (
                clamp(sparkerRise * _sparkRiseIntensity, 0.0, 1.0) +
                clamp(flowLine * 0.2, 0.0, 1.0)
            ) * inversFade * 1.5;

            float mask = maskup + maskdown;

            // === COLOR APPLICATION ===
            // Grid & squares use _gridColor
            vec3 lowerEffectSquares = _squareColor * squares * _squareIntensity * fade;
            vec3 lowerEffect = _gridColor * maskdown;

            // Spark effects use _sparkColor
            vec3 upperEffect = _sparkColor * maskup;

            // Horizon glow
            float horizonBG = length(uv * vec2(1.0, 50.0) - vec2(0.0, -5.0));
            horizonBG = (1.0 / pow(horizonBG * horizonBG, 0.5)) * _horizonBGIntensity;
            // horizonBG = (1.0 / pow(horizonBG * horizonBG + 0.3, 2.8)) * _horizonBGIntensity;
            vec3 horizonGlow = _horizonColor * horizonBG;

            // Combine all layers
            vec3 finalColor =
                uBackgroundColor +
                lowerEffect +
                lowerEffectSquares +
                upperEffect +
                horizonGlow;

            // Optional: dim very dark areas slightly, but avoid hard cutoffs
            finalColor = clamp(finalColor*circleFade+vec3(0.0), 0.0, 1.0) + (1.0 - circleFade) * uBackgroundColor;

            gl_FragColor = vec4(finalColor, 1.0);
        }
    `;

  // Normalize and scale settings function
  const normalizeSettings = raw => {
    const normalized = {
      ...raw
    };
    if (raw.scale !== undefined) {
      normalized.scale = parseFloat(raw.scale) * 0.03;
    }
    if (raw.noise !== undefined) {
      normalized.noise = parseFloat(raw.noise) * 0.06;
    }
    if (raw.intensity !== undefined) {
      normalized.intensity = parseFloat(raw.intensity) * 0.01;
    }
    if (raw.speed !== undefined) {
      normalized.speed = parseFloat(raw.speed) * 0.035;
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
        value: [el.offsetWidth, el.offsetHeight]
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