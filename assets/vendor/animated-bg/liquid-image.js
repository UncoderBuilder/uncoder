window.uiAnimated_LiquidImage = function (el, canvas, userSettings = {}) {
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
    varying vec2 vDistortionDir;

    uniform vec2 uMouse;
    uniform vec3 uResolution;

    void main() {
        vUv = uv;

        // Convert clip-space mouse to normalized -1..1 coords to match plane space
        vec2 mouseNorm = (uMouse * 2.0 - 1.0);
        mouseNorm.y *= -1.0;

        // Compute direction vector from vertex to mouse
        vec2 vertexPos = position;
        vDistortionDir = mouseNorm - vertexPos;

        gl_Position = vec4(position, 0.0, 1.0);
    }
      `;
  const fragment = `
         precision highp float;

    varying vec2 vUv;
    varying vec2 vDistortionDir;

    uniform float uTime;
    uniform vec2 uMouse;
    uniform vec3 uResolution;
    uniform sampler2D uCustomTexture;
    uniform vec2 uTextureResolution;
    uniform float uScale;
    uniform float uNoise;
    uniform float uIntensity;
    uniform float uInteractive;

    // Simple noise (optional for subtle motion)
    float random(vec2 st) {
        return fract(sin(dot(st.xy, vec2(12.9898,78.233))) * 43758.5453123);
    }

    void main() {
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

        // Base texture color
        vec4 baseColor = texture2D(uCustomTexture, coverUv);

        // Mouse displacement
        float dist = length(vDistortionDir);
        vec2 disp = vDistortionDir * (0.3 / (dist + 0.2));
        vec2 displacedUv = coverUv + disp * (uScale * 0.05);

        // Add subtle temporal noise
        displacedUv += (random(vUv + uTime * 0.1) * 0.0003 * uScale) * uNoise * 0.01;

        vec4 displacedColor = texture2D(uCustomTexture, displacedUv);

        // Optional: grayscale mix based on distance
        float grayscaleMix = smoothstep(0.0, 0.7, dist * uIntensity * 0.1);
        vec3 bw = vec3(dot(displacedColor.rgb, vec3(0.299, 0.587, 0.114)));
        vec3 finalColor = mix(displacedColor.rgb, bw, grayscaleMix * 0.7);

        gl_FragColor = vec4(finalColor, 1.0);
    }
      `;
  const normalizeSettings = raw => {
    const normalized = {
      ...raw
    };
    if (raw.scale !== undefined) normalized.scale = parseFloat(raw.scale) * 0.1;
    if (raw.intensity !== undefined) normalized.intensity = parseFloat(raw.intensity) * 0.1;
    return normalized;
  };
  let {
    noise,
    scale,
    intensity,
    mouseInteractive,
    offsetX,
    offsetY
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
      uMouse: {
        value: [0.5, 0.5]
      },
      uCustomTexture: {
        value: customTexture
      },
      uTextureResolution: {
        value: [2048, 1365]
      },
      uNoise: {
        value: noise
      },
      uScale: {
        value: scale
      },
      uIntensity: {
        value: intensity
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