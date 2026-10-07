window.uiAnimated_LiquidMask = function (el, canvas, userSettings = {}) {
  var _userSettings$texture;
  const {
    Renderer,
    Program,
    Mesh,
    Plane,
    Texture
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
        varying vec2 vUv;

        uniform float uTime;
        uniform float uIdleTime;
        uniform vec2 uMouse;
        uniform vec3 uResolution;
        uniform sampler2D uCustomTexture;
        uniform vec2 uTextureResolution;

        uniform float uScale;
        uniform float uIntensity;
        uniform float uInteractive;

        vec3 mod289(vec3 x) { return x - floor(x * (1.0 / 289.0)) * 289.0; }
        vec2 mod289(vec2 x) { return x - floor(x * (1.0 / 289.0)) * 289.0; }
        vec3 permute(vec3 x) { return mod289(((x*34.0)+1.0)*x); }
        float snoise(vec2 v) {
            const vec4 C = vec4(0.211324865405187, 0.366025403784439, -0.577350269189626, 0.024390243902439);
            vec2 i  = floor(v + dot(v, C.yy) );
            vec2 x0 = v -   i + dot(i, C.xx);
            vec2 i1 = (x0.x > x0.y) ? vec2(1.0, 0.0) : vec2(0.0, 1.0);
            vec4 x12 = x0.xyxy + C.xxzz;
            x12.xy -= i1;
            i = mod289(i);
            vec3 p = permute( permute( i.y + vec3(0.0, i1.y, 1.0 )) + i.x + vec3(0.0, i1.x, 1.0 ));
            vec3 m = max(0.5 - vec3(dot(x0,x0), dot(x12.xy,x12.xy), dot(x12.zw,x12.zw)), 0.0);
            m = m*m; m = m*m;
            vec3 x = 2.0 * fract(p * C.www) - 1.0;
            vec3 h = abs(x) - 0.5;
            vec3 ox = floor(x + 0.5);
            vec3 a0 = x - ox;
            m *= 1.79284291400159 - 0.85373472095314 * ( a0*a0 + h*h );
            vec3 g;
            g.x  = a0.x  * x0.x  + h.x  * x0.y;
            g.yz = a0.yz * x12.xz + h.yz * x12.yw;
            return 130.0 * dot(m, g);
        }


        void main() {
    // Flip the texture coordinates to match the CSS background-size: cover
    vec2 coverUv = vUv;
    vec2 texResolution = uTextureResolution;
    float screenAspect = uResolution.x / uResolution.y;
    float textureAspect = texResolution.x / texResolution.y;

    if (screenAspect > textureAspect) {
        coverUv.y *= textureAspect / screenAspect;
        coverUv.y += (1.0 - textureAspect / screenAspect) / 2.0;
    } else {
        coverUv.x *= screenAspect / textureAspect;
        coverUv.x += (1.0 - screenAspect / textureAspect) / 2.0;
    }

    vec4 textureColor = texture2D(uCustomTexture, coverUv);

    float aspect = uResolution.x / uResolution.y;
    vec2 correctedUv = vUv;
    correctedUv.x *= aspect;
    vec2 correctedMouse = vec2(uMouse.x, 1.0 - uMouse.y);
    correctedMouse.x *= aspect;

    float dist = distance(correctedUv, correctedMouse);

    float intensity = clamp(uIntensity / 100.0, 0.0, 1.0);

    float noise     = snoise(vUv * 4.1   + uTime * 0.4)  * intensity * 6.0;
    float noiseBig  = snoise(vUv * 9.05  + uTime * 0.2)  * intensity * 1.0;
    float microNoise= snoise(vUv * 19.5  + uTime * 1.0)  * intensity;

    float finalNoise = (noise * 0.9 + noiseBig * 0.3 + microNoise * 0.8);
    float edgeNoise = snoise(vUv * 3.0 + uTime * 0.025) * intensity * 0.5;
    float edgeSoftness = 0.001 + edgeNoise * 0.05;
    edgeSoftness = max(0.001, edgeSoftness);

    float distortedMouseDistance = dist + finalNoise * mix(0.0, 0.08, intensity);
    float dotRadius = uScale * 0.01;
    // float dotShape = smoothstep(dotRadius, dotRadius - edgeSoftness - 0.01, distortedMouseDistance);
    float dotShape = smoothstep(dotRadius, dotRadius - edgeSoftness * 5.5 - 0.03, distortedMouseDistance);


    // 3. Make the dot's intensity proportional to mouse speed
    float dotIntensity = dotShape * 0.2;
    dotIntensity = clamp(dotIntensity, 0.0, 1.0);

    // Create a soft-edged circular mask using smoothstep
    float mask = smoothstep(0.0, 0.15, dotIntensity);

    float disintegrateNoise = snoise(vUv * 10.0 + uTime * 0.04);
    disintegrateNoise = (disintegrateNoise + 1.0) * 0.5;
    float noiseFade = 1.0 - smoothstep(0.0, 0.1,  -disintegrateNoise);

    mask *= noiseFade;
    gl_FragColor = mix(vec4(0.0, 0.0, 0.0, 0.0), textureColor, mask);
}

      `;
  const normalizeSettings = raw => {
    const normalized = {
      ...raw
    };
    if (raw.noise !== undefined) normalized.noise = parseFloat(raw.noise) * 0.1;
    return normalized;
  };
  let {
    scale,
    intensity,
    backgroundColor,
    mouseInteractive
  } = normalizeSettings(userSettings);
  const renderer = new Renderer({
    canvas,
    alpha: true,
    premultipliedAlpha: true
  });
  const gl = renderer.gl;
  gl.clearColor(0, 0, 0, 0);
  const geometry = new Plane(gl, {
    width: 2,
    height: 2
  });
  const customTexture = new Texture(gl, {
    wrapS: gl.CLAMP_TO_EDGE,
    wrapT: gl.CLAMP_TO_EDGE
  });
  const loadImageToTexture = (tex, src) => {
    const img = new Image();
    img.crossOrigin = "anonymous";
    img.onload = () => {
      tex.image = img;
      program.uniforms.uTextureResolution.value = [img.width, img.height];
    };
    img.src = src;
  };
  if ((_userSettings$texture = userSettings.texture) !== null && _userSettings$texture !== void 0 && _userSettings$texture.url) {
    loadImageToTexture(customTexture, userSettings.texture.url);
  } else {
    // Uncoder: no remote default image. Without an image (or a container background image) nothing is drawn.
  }
  const program = new Program(gl, {
    vertex,
    fragment,
    uniforms: {
      uTime: {
        value: 0
      },
      uResolution: {
        value: [el.offsetWidth, el.offsetHeight]
      },
      uBackgroundColor: {
        value: backgroundColor
      },
      uMouse: {
        value: [0.5, 0.5]
      },
      uCustomTexture: {
        value: customTexture
      },
      uTextureResolution: {
        value: [2048, 1365]
      },
      uScale: {
        value: scale
      },
      uIntensity: {
        value: intensity
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