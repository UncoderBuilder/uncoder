window.uiAnimated_PhaseTunnel = function (el, canvas, userSettings = {}) {
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
        precision mediump float;
        precision mediump int;

        uniform float uTime;
        uniform vec3  uColorStops[4];
        uniform vec3  uBackgroundColor;
        uniform vec3  uResolution;
        uniform float uIntensity; // 0..1 glow intensity
        uniform float uScale;     // 0..1
        uniform float uNoise;     // 0..1
        uniform vec2  uMouse;
        uniform float uSpeed;
        uniform float uProgress;
        uniform float uInteractive;
        uniform float uOffsetX;
        uniform float uOffsetY;

        uniform vec2  uOffset;



        float hash21_fast(vec2 p){
            return fract(sin(dot(p, vec2(12.9898,78.233))) * 43758.5453);
        }

        float layeredNoise(vec2 fragPx){
            vec2 p = fragPx * 0.01 + vec2(uTime * 0.5, -uTime * 0.3);
            float n = 0.4 * hash21_fast(p);
            n += 0.25 * hash21_fast(p * 2.0 + 17.0);
            return n;
        }

        vec3 rayDir(vec2 frag, vec2 res, vec2 offset, float dist){
            float focal = res.y * max(dist, 1e-3);
            return normalize(vec3(2.0 * (frag - offset) - res, focal));
        }
        mat3 rotY(float a) {
            float c = cos(a), s = sin(a);
            return mat3(c,0.0,s, 0.0,1.0,0.0, -s,0.0,c);
        }
        mat3 rotX(float a) {
            float c = cos(a), s = sin(a);
            return mat3(1.0,0.0,0.0, 0.0,c,-s, 0.0,s,c);
        }

        float edgeFade(vec2 frag, vec2 res, vec2 offset){
            vec2 center = 0.5 * res + offset;
            vec2 toC = frag - center;
            float aspect = res.x / res.y;
            vec2 uv = toC / res.y; // Use consistent scaling
            float r = length(uv) / 0.5;
            float x = clamp(r, 0.0, 1.0);

            // Faster smoothstep approximation
            float s = x * x * (3.0 - 2.0 * x);
            s = pow(s, 1.2); // Reduced exponentiation complexity
            return clamp(s, 0.0, 1.0);
        }


        vec3 sampleGradient(float t) {
            t = clamp(t * 3.0, 0.0, 3.0);
            int i = int(t);
            float localT = fract(t);

            // Handle boundaries safely
            if (i >= 3) {
                return uColorStops[3];
            }

            // Interpolate between the two relevant color stops
            vec3 colorA = vec3(0.0);
            vec3 colorB = vec3(0.0);
            if (i == 0) {
                colorA = uColorStops[0];
                colorB = uColorStops[1];
            } else if (i == 1) {
                colorA = uColorStops[1];
                colorB = uColorStops[2];
            } else if (i == 2) {
                colorA = uColorStops[2];
                colorB = uColorStops[3];
            }
            return mix(colorA, colorB, localT);
        }


        vec2 rot2(vec2 v, float a){
            float s = sin(a), c = cos(a);
            return mat2(c, -s, s, c) * v;
        }

        float bendAngle(vec3 q, float t){
            vec3 f = vec3(0.55, 0.50, 0.60);
            float s = sin(dot(q, f) + t * 0.6);
            return s * 1.2; // visually similar but 3× cheaper
        }

        void main(){
            vec2 pixelOffset = vec2(
                (uOffsetX / 100.0) * (uResolution.x * 0.5),
                (uOffsetY / 100.0) * (uResolution.y * 0.5)
            );

            vec2 frag = gl_FragCoord.xy + pixelOffset;
            float t = uSpeed > 0.0 ? uTime * uSpeed : (uMouse.x + uMouse.y) * 0.6 + uProgress * 0.09;

            vec2 parallax = (uMouse - 0.5) * 0.1;
            float jitterAmp = 0.1 * clamp(uNoise, 0.0, 1.0);
            vec3 dir = rayDir(frag, uResolution.xy, uOffset, 1.0);
            float marchT = 0.0;
            vec3 col = vec3(0.0);
            float n = layeredNoise(frag);
            vec4 c = cos(t * 0.2 + vec4(0.0, 33.0, 11.0, 0.0));
            mat2 M2 = mat2(c.x, c.y, c.z, c.w);
            float amp = clamp(3.0, 0.0, 50.0) * 0.15;

            mat3 rot3dMat = mat3(1.0);

            mat3 hoverMat = mat3(1.0);
            vec2 m = uMouse * 0.5 - 1.0;
            vec3 ang = vec3(m.y * 0.6, m.x * 0.6, 0.0);
            hoverMat = rotY(ang.y) * rotX(ang.x);

            float stepLimit = float(14) * clamp(uIntensity + 0.1, 0.0, 1.0);
            for (int i = 0; i < 14; ++i) {
                if (float(i) > stepLimit) { break; }

                vec3 P = marchT * dir * mix(0.5, 1.5, uScale - 0.3);
                P.z -= 2.0;
                float rad = length(P);
                if (rad > 10.0) break;
                vec3 Pl = P * (10.0 / max(rad, 1e-6));
                Pl = hoverMat * Pl;


                float stepLen = min(rad - 0.3, n * jitterAmp) + 0.1;

                float grow = smoothstep(0.35, 3.0, marchT);
                float a1 = amp * grow * bendAngle(Pl * 4.1, t);
                float a2 = 0.5 * amp * grow * bendAngle(Pl.zyx * 0.5 + 3.3, t * 2.2);
                vec3 Pb = Pl;
                float s1 = sin(a1), c1 = cos(a1);
                Pb.xz = vec2(c1 * Pb.x - s1 * Pb.z, s1 * Pb.x + c1 * Pb.z);

                float s2 = sin(a2), c2 = cos(a2);
                Pb.xy = vec2(c2 * Pb.x - s2 * Pb.y, s2 * Pb.x + c2 * Pb.y);

                float rayPattern = smoothstep(0.5, 0.7,
                    sin(Pb.x + Pb.y) * sin(Pb.z + t)
                );


                float ang = atan(Pb.y, Pb.x);

                float comb = pow(0.5 + 0.5 * cos(10.0 * ang), 2.0);
                rayPattern *= comb;


                float saw = fract(marchT * 0.25);
                float tRay = saw * saw * (3.0 - 2.0 * saw);
                vec3 userGradient = 2.2 * sampleGradient(tRay);
                vec3 spectral = userGradient;
                vec3 base = (0.05 / (0.4 + stepLen))
                        * smoothstep(5.0, 0.0, rad)
                        * spectral;

                col += base * rayPattern * 1.6;
                marchT += stepLen;
            }

            col *= edgeFade(frag, uResolution.xy, uOffset);
            col = mix(uBackgroundColor, col, col);
            vec4 fragColor = vec4(clamp(col, 0.0, 1.0), 1.0);
            gl_FragColor = fragColor;
        }
    `;

  // Normalize and scale settings function
  const normalizeSettings = raw => {
    const normalized = {
      ...raw
    };
    if (raw.scale !== undefined) {
      normalized.scale = parseFloat(raw.scale) * 0.02;
    }
    if (raw.noise !== undefined) {
      normalized.noise = parseFloat(raw.noise) * 0.012;
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
    mouseInteractive,
    progress,
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
      uNoise: {
        value: noise
      },
      uOffset: {
        value: [0, 0]
      },
      uProgress: {
        value: progress
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