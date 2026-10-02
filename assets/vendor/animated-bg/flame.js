window.uiAnimated_Flame = function (el, canvas, userSettings = {}) {
  const {
    Renderer,
    Program,
    Mesh,
    Triangle,
    Color
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
        uniform vec3  uColorStops[1];
        uniform vec3  uBackgroundColor;
        uniform float uTime;
        uniform vec3  uResolution;
        uniform float uIntensity; // 0..1 glow intensity
        uniform float uScale;     // 0..1
        uniform float uNoise;
        uniform vec2  uMouse;
        uniform float uSpeed;
        uniform float uProgress;
        uniform float uInteractive;
        uniform float uOffsetX;
        uniform float uOffsetY;

        varying vec2  vUv;


        vec3 mod289(vec3 x) {
            return x - floor(x * (1.0 / 289.0)) * 289.0;
        }
        vec4 mod289(vec4 x) {
            return x - floor(x * (1.0 / 289.0)) * 289.0;
        }
        vec4 permute(vec4 x) {
                return mod289(((x*34.0)+1.0)*x);
        }
        vec4 taylorInvSqrt(vec4 r)
        {
            return 1.79284291400159 - 0.85373472095314 * r;
        }
        float rand(vec2 n) {
            return fract(sin(dot(n, vec2(12.9898, 4.1414))) * 43758.5453);
        }
        float snoise(vec3 v)
            {
            const vec2	C = vec2(1.0/6.0, 1.0/3.0) ;
            const vec4	D = vec4(0.0, 0.5, 1.0, 2.0);

            vec3 i	= floor(v + dot(v, C.yyy) );
            vec3 x0 =	 v - i + dot(i, C.xxx) ;

            vec3 g = step(x0.yzx, x0.xyz);
            vec3 l = 1.0 - g;
            vec3 i1 = min( g.xyz, l.zxy );
            vec3 i2 = max( g.xyz, l.zxy );
            vec3 x1 = x0 - i1 + C.xxx;
            vec3 x2 = x0 - i2 + C.yyy;
            vec3 x3 = x0 - D.yyy;

            i = mod289(i);
            vec4 p = permute( permute( permute(
                                i.z + vec4(0.0, i1.z, i2.z, 1.0 ))
                            + i.y + vec4(0.0, i1.y, i2.y, 1.0 ))
                            + i.x + vec4(0.0, i1.x, i2.x, 1.0 ));

            float n_ = 0.142857142857;
            vec3	ns = n_ * D.wyz - D.xzx;

            vec4 j = p - 49.0 * floor(p * ns.z * ns.z);	//	mod(p,7*7)

            vec4 x_ = floor(j * ns.z);
            vec4 y_ = floor(j - 7.0 * x_ );

            vec4 x = x_ *ns.x + ns.yyyy;
            vec4 y = y_ *ns.x + ns.yyyy;
            vec4 h = 1.0 - abs(x) - abs(y);

            vec4 b0 = vec4( x.xy, y.xy );
            vec4 b1 = vec4( x.zw, y.zw );

            vec4 s0 = floor(b0)*2.0 + 1.0;
            vec4 s1 = floor(b1)*2.0 + 1.0;
            vec4 sh = -step(h, vec4(0.0));

            vec4 a0 = b0.xzyw + s0.xzyw*sh.xxyy ;
            vec4 a1 = b1.xzyw + s1.xzyw*sh.zzww ;

            vec3 p0 = vec3(a0.xy,h.x);
            vec3 p1 = vec3(a0.zw,h.y);
            vec3 p2 = vec3(a1.xy,h.z);
            vec3 p3 = vec3(a1.zw,h.w);


            vec4 norm = inversesqrt(vec4(dot(p0,p0), dot(p1,p1), dot(p2, p2), dot(p3,p3)));
            p0 *= norm.x;
            p1 *= norm.y;
            p2 *= norm.z;
            p3 *= norm.w;


            vec4 m = max(0.6 - vec4(dot(x0,x0), dot(x1,x1), dot(x2,x2), dot(x3,x3)), 0.0);
                m = m * m;
                return 42.0 * dot( m*m, vec4( dot(p0,x0), dot(p1,x1),
                dot(p2,x2), dot(p3,x3) ) );
            }




        float prng(in vec2 seed) {
            seed = fract (seed * vec2 (5.3983, 5.4427));
            seed += dot (seed.yx, seed.xy + vec2 (21.5351, 14.3137));
            return fract (seed.x * seed.y * 95.4337);
        }



        float PI = 3.1415926535897932384626433832795;

        float noiseStack(vec3 pos,int octaves,float falloff){
            float noise = snoise(vec3(pos));
            float off = 1.0;
            if (octaves>1) {
                pos *= 2.0;
                off *= falloff;
                noise = (1.0-off)*noise + off*snoise(vec3(pos));
            }
            if (octaves>2) {
                pos *= 2.0;
                off *= falloff;
                noise = (1.0-off)*noise + off*snoise(vec3(pos));
            }
            if (octaves>3) {
                pos *= 2.0;
                off *= falloff;
                noise = (1.0-off)*noise + off*snoise(vec3(pos));
            }
            return (1.0+noise)/2.0;
        }

        vec2 noiseStackUV(vec3 pos,int octaves,float falloff,float diff){
            float displaceA = noiseStack(pos,octaves,falloff);
            float displaceB = noiseStack(pos+vec3(3984.293,423.21,5235.19),octaves,falloff);
            return vec2(displaceA,displaceB);
        }


        vec3 rgb2hsl(vec3 c) {
            vec4 K = vec4(0.0, -1.0 / 3.0, 2.0 / 3.0, -1.0);
            vec4 p = mix(vec4(c.bg, K.wz), vec4(c.gb, K.xy), step(c.b, c.g));
            vec4 q = mix(vec4(p.xyw, c.r), vec4(c.r, p.yzx), step(p.x, c.r));
            float d = q.x - min(q.w, q.y);
            float e = 1.0e-10;
            return vec3(abs(q.z + (q.w - q.y) / (6.0 * d + e)), d / (q.x + e), q.x);
        }

        vec3 hsl2rgb(vec3 c) {
            vec3 rgb = clamp(abs(mod(c.x * 6.0 + vec3(0.0, 4.0, 2.0), 6.0) - 3.0) - 1.0, 0.0, 1.0);
            float C = (1.0 - abs(2.0 * c.z - 1.0)) * c.y;
            return (rgb - 0.5) * C + c.z;
        }


        void main() {
            vec2 resolution = uResolution.xy;
            vec2 offset = uMouse.xy;
            float time = uSpeed > 0.0 ? uTime * uSpeed : (uMouse.x + uMouse.y) * 0.06 + uProgress * 0.09;
            vec2 parallax = (uMouse - 0.5) * 0.01;

            vec2 pixelOffset = vec2(
                (uOffsetX / 100.0) * (uResolution.x * 0.5),
                (uOffsetY / 100.0) * (uResolution.y * 0.5)
            );
            vec2 fragCoord = gl_FragCoord.xy + pixelOffset;

            float xpart = fragCoord.x / resolution.x;
            float ypart = fragCoord.y / resolution.y;

            float clip = 1000.0 * uScale + 0.01;
            float ypartClip = fragCoord.y / clip;
            float ypartClippedFalloff = clamp(2.0 - ypartClip, 0.0, 1.0);
            float ypartClipped = min(ypartClip, 1.0);
            float ypartClippedn = 1.0 - ypartClipped;
            float xfuel = 1.0 - abs(2.0 * xpart - 1.0);

            float timeSpeed = 0.2;
            float realTime = timeSpeed * time;

            vec2 coordScaled = 0.01 * fragCoord - parallax * 50.0;
            vec3 position = vec3(coordScaled, 0.0) + vec3(1223.0, 6434.0, 8425.0);
            vec3 flow = vec3(
                4.1 * (0.5 - xpart) * pow(ypartClippedn, 4.0),
                -2.0 * xfuel * pow(ypartClippedn, 64.0),
                0.0
            );
            vec3 timing = realTime * vec3(0.0, -1.7, 1.1) + flow;

            vec3 displacePos = vec3(1.0, 0.5, 1.0) * 2.4 * position + realTime * vec3(0.01, -0.7, 1.3);
            vec3 displace3 = vec3(noiseStackUV(displacePos, 2, 0.4, 0.1), 0.0);
            vec3 noiseCoord = (vec3(2.0, 1.0, 1.0) * position + timing + 0.4 * displace3);
            float noise = noiseStack(noiseCoord, 3, 0.4);
            float flames = pow(ypartClipped, 0.3 * xfuel) * pow(noise, 0.3 * xfuel);
            float f = ypartClippedFalloff * pow(1.0 - flames * flames * flames, 8.0);

            vec3 uColor_hsl = rgb2hsl(uColorStops[0]);
            float hue = uColor_hsl.x;

            float saturation = 1.0 - pow(f, 4.0);
            float lightness = f * 1.5;

            vec3 fire_hsl = vec3(hue, saturation, clamp(lightness, 0.0, 1.0));
            vec3 fire_rgb = hsl2rgb(fire_hsl);

            vec3 fire = uColorStops[0] * fire_rgb * uIntensity * 5.0;
            fire += (rand(fragCoord + uTime) - 0.5) * uNoise * 0.1;
            if (fragCoord.x < 0.0 || fragCoord.x > uResolution.x ||
            fragCoord.y < 0.0 || fragCoord.y > uResolution.y) {
            discard;
        }

        vec2 center = uResolution.xy * 0.5;
        float distFromCenter = distance(fragCoord, center);
        float maxDist = length(center);
        float fadeMask = smoothstep(maxDist, maxDist * 0.7, distFromCenter);

        fire *= fadeMask;
        fire = mix(uBackgroundColor, fire, fire);

            gl_FragColor = vec4(fire, 1.0);
        }
    `;

  // Normalize and scale settings function
  const normalizeSettings = raw => {
    const normalized = {
      ...raw
    };
    if (raw.scale !== undefined) {
      normalized.scale = parseFloat(raw.scale) * 0.01;
    }
    if (raw.noise !== undefined) {
      normalized.noise = parseFloat(raw.noise) * 0.09;
    }
    if (raw.intensity !== undefined) {
      normalized.intensity = parseFloat(raw.intensity) * 0.01;
    }
    if (raw.speed !== undefined) {
      normalized.speed = parseFloat(raw.speed) * 0.01;
    }
    if (raw.colorArray !== undefined && Array.isArray(raw.colorArray)) {
      normalized.colorArray = raw.colorArray.slice(0, 1);
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
    canvas
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