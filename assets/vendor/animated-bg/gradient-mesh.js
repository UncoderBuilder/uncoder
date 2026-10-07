window.uiAnimated_GradientMesh = function (el, canvas, userSettings = {}) {
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
        uniform float uIntensity;
        uniform float uScale;
        uniform float uSpeed;
        uniform float uNoise;
        uniform vec2  uMouse;
        uniform float uProgress;
        uniform float uInteractive;
        varying vec2  vUv;
        float rand(vec2 n) {
            return fract(sin(dot(n, vec2(12.9898, 4.1414))) * 43758.5453);
        }
        mat2 R(float a){float s=sin(a),c=cos(a);return mat2(c,-s,s,c);}
        vec2 h(vec2 p){p=vec2(dot(p,vec2(2127.1,81.17)),dot(p,vec2(1269.5,283.37)));return fract(sin(p)*43758.5453);}
        float n(vec2 p){
        vec2 i=floor(p),f=fract(p),u=f*f*(3.-2.*f);
        float a=dot(-1.+2.*h(i),f),
        b=dot(-1.+2.*h(i+vec2(1,0)),f-vec2(1,0)),
        c=dot(-1.+2.*h(i+vec2(0,1)),f-vec2(0,1)),
        d=dot(-1.+2.*h(i+vec2(1,1)),f-vec2(1,1));
        return .5+.5*mix(mix(a,b,u.x),mix(c,d,u.x),u.y);
        }

        void main() {
            vec2 r = uResolution.xy;
            vec2 p = (gl_FragCoord.xy / r - 0.5) * mix(0.5, 2.0, uScale);
            float ratio = r.x / r.y;
            float t = uSpeed > 0.0 ? uTime * uSpeed : (uMouse.x + uMouse.y) * 0.5 + uProgress * 0.3;
            vec2 parallax = (uMouse - 0.5) * 0.1;
            p += parallax;

            float d = n(vec2(t * 0.1, p.x * p.y * (1.0 + 0.5)));
            p.y /= ratio;
            p *= R(radians((d - 0.5) * 720.0 + 180.0));
            p.y *= ratio;

            float f = mix(3.0, 7.0, uScale);
            float a = mix(40.0, 20.0, uScale);
            float time = t * 2.0;

            p.x += sin(p.y * f + time) / a;
            p.y += sin(p.x * f * 1.5 + time) / (a * 0.5);

            float g = smoothstep(-0.3, 0.2, (R(radians(-5.0)) * p).x);
            vec3 c = mix(
                mix(uColorStops[0], uColorStops[1], g),
                mix(uColorStops[2], uColorStops[3], g),
                smoothstep(0.5, -0.3, p.y)
            );

            c = mix(c, c * 1.5 + vec3(0.1), uIntensity);
            c += (rand(gl_FragCoord.xy + t) - 0.5) * uNoise * 0.1;

            gl_FragColor = vec4(c, 1.0);
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
      normalized.speed = parseFloat(raw.speed) * 0.017;
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
    mouseInteractive
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
      uProgress: {
        value: progress
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