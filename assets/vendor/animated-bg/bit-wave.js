window.uiAnimated_BitWave = function (el, canvas, userSettings = {}) {
  var _userSettings$texture;
  //TODO: change to uicore_ogl when we have time to test
  const {
    Renderer,
    Program,
    Mesh,
    Plane,
    Texture,
    RenderTarget
  } = window.ogl;
  const vertex = `#version 300 es
        precision mediump float;

        in vec3 position;
        in vec2 uv;

        out vec2 vTextureCoord;
        out vec3 vVertexPosition;

        void main() {
            gl_Position = vec4(position, 1.0);
            vTextureCoord = uv;
            vVertexPosition = position;
        }
    `;
  const wave = `#version 300 es
        precision highp float;

        in vec2 vTextureCoord;
        out vec4 fragColor;

        uniform float uTime;
        uniform vec2  uMouse;
        uniform float uIntensity;
        uniform float uNoiseScale;
        uniform float uSpeed;
        uniform float uNoise;
        uniform vec3  uColorStops[4];
        uniform float uProgress;
        uniform float uInteractive;

        uniform sampler2D uPreviousFrame;
        uniform float uMouseVelocity;

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
    vec2 uv = vTextureCoord;
    vec2 center = vec2(0.5);
    float time = uSpeed > 0.0 ? uTime : uProgress * 0.5;
    float speed = uSpeed == 0.0 ? 0.1 : uSpeed;
    //generate a continuous time value even when speed is 0
    float noiseSpeed = uSpeed > 0.0 ? uSpeed : 0.16;

    // --- NOISE CALCULATION ---
    float noise1 = snoise(uv * uNoiseScale * 0.7 + uTime * noiseSpeed * 0.2);
    float noise2 = snoise(uv * uNoiseScale * 1.5 + uTime * noiseSpeed * 0.95);
    float smallNoise = snoise(uv * uNoiseScale * 4.0 + uTime * 0.07);
    float noiseCombined = (noise1 + noise2) * 0.2;


    // --- ORIGINAL WAVE CALCULATION (UNCHANGED) ---
    float dist = distance(uv, center);
    float fadeIn = smoothstep(0.0, 0.4, dist);
    float fadeOut = smoothstep(0.9, 0.5, dist);
    float distortedDist = dist + noiseCombined * 0.2;
    float waveProgress = fract(time * speed * 0.2);
    float waveThickness = 0.038;
    float distanceToWave = abs(distortedDist - waveProgress);

            // main wave core
            float radialWave = smoothstep(waveThickness, 0.0, distanceToWave);

    // soft glow region around the wave
    float glowOuter = smoothstep(0.12, 0.0, distanceToWave);  // softer outer glow
    float glow = glowOuter * 0.4; // reduce brightness of the glow

            // combine the wave core + glow
            float finalWave = radialWave + glow;
            finalWave *= fadeIn * fadeOut;

    // --- TRAIL AND MOUSE DOT CALCULATION (FEEDBACK LOOP) ---
    vec3 dotAndTrail = vec3(0.0);
    if(uInteractive > 0.0){
        vec3 previousFrameColor = texture(uPreviousFrame, vTextureCoord).rgb;
        previousFrameColor *= 0.98;

        // 2. Calculate the new dot based on current mouse position
        float mouseDistance = distance(uv, uMouse);
        float distortedMouseDistance = mouseDistance + noiseCombined * 0.15 + smallNoise * 0.02;
        float dotRadius = 0.15 * uMouseVelocity;
        float dotShape = smoothstep(dotRadius, dotRadius - 0.1, distortedMouseDistance);

        // 3. Make the dot's intensity proportional to mouse speed
        float dotIntensity = dotShape * uMouseVelocity * 0.3;
        dotIntensity = clamp(dotIntensity, 0.0, 1.0);
        dotAndTrail = mix(previousFrameColor, uColorStops[0], dotIntensity * uIntensity);
    }

            // --- FINAL COLOR COMBINATION ---
            // float waveIntensity = radialWave * uIntensity * 0.45;
            float waveIntensity = finalWave * uIntensity * 0.45;


    // 4. Additively blend the new dot and the background wave onto the faded trail
    // vec3 dotAndTrail = mix(previousFrameColor, uColorStops[0], dotIntensity * uIntensity + 0.1);
    vec3 finalColor = mix(dotAndTrail, uColorStops[0], waveIntensity);
    finalColor = mix(finalColor, uColorStops[1], 0.5);

    //mix color with noise 2
    finalColor = mix(finalColor, uColorStops[0], (noise2 - smallNoise * uNoise * 0.01) * uNoise * 0.08);

    fragColor = vec4(finalColor, 0.0);
}
  `;
  const fragment = `#version 300 es
        precision highp float;

        in vec3 vVertexPosition;
        in vec2 vTextureCoord;
        uniform sampler2D uWaveTexture;
        uniform sampler2D uSprite;
        uniform sampler2D uCustomTexture;

        uniform vec2 uMouse;
        uniform vec2 uResolution;
        uniform float uScale;
        uniform float uTextureSize;

        out vec4 fragColor;


        void main() {
            vec2 uv = vTextureCoord;
            vec2 pos = vec2(0.5, 0.5);
            float aspectRatio = uResolution.x / uResolution.y;

            float gridSize = mix(0.025, 0.0025, uScale * 0.5);

            float baseGrid = 1.0 / gridSize;
            vec2 cellSize = vec2(1.0 / (baseGrid * aspectRatio), 1.0 / baseGrid);
            vec2 offsetUv = uv - pos;

            // Quantize the background sample as you had
            vec2 cell = floor(offsetUv / cellSize);
            vec2 cellCenter = (cell + 0.5) * cellSize;
            vec2 pixelatedCoord = cellCenter + pos;

            vec4 color = texture(uWaveTexture, pixelatedCoord);
            vec4 bg    = texture(uWaveTexture, vTextureCoord);

            float luminance = dot(color.rgb, vec3(0.2126, 0.7152, 0.0722));
            float gamma = pow(mix(0.2, 2.2, 0.4), 2.2);

            // ----- Exact atlas sampling (no bleeding) -----
            // Map to local glyph space [0,1)^2 inside the current cell
            vec2 local = fract(offsetUv / cellSize);

    // Compute which sprite (column) to use
    ivec2 atlasSize = textureSize(uCustomTexture, 0);              // in texels
    float numSprites = max(1.0, float(atlasSize.x) / uTextureSize);
    float adjusted = pow(clamp(luminance + 0.1, 0.0, 1.0), 0.8 / gamma);
    int spriteIndex = int(clamp(floor(adjusted * numSprites), 0.0, numSprites - 1.0));

    // Convert local [0,1) to integer texel coords inside a uTextureSize x uTextureSize tile
    // Clamp to [0, uTextureSize-1] to stay on-texel.
    ivec2 glyphPx = clamp(ivec2(floor(local * uTextureSize)),
                          ivec2(0), ivec2(int(uTextureSize) - 1));

    // Atlas texel coordinate: sprites laid out horizontally
    int atlasX = spriteIndex * int(uTextureSize) + glyphPx.x;
    int atlasY = glyphPx.y;

            // Fetch exact texel from LOD 0 (no filtering, no mip)
            vec4 spriteColor = texelFetch(uCustomTexture, ivec2(atlasX, atlasY), 0);

            // Threshold/alpha as before
            float alpha = smoothstep(0.0, 0.1, spriteColor.r);
            vec3 final_color = bg.rgb * 0.3 + color.rgb * alpha;

            fragColor = vec4(final_color, 1.0);
        }
    `;
  const normalizeSettings = raw => {
    const normalized = {
      ...raw
    };
    if (raw.scale !== undefined) normalized.scale = parseFloat(raw.scale) * 0.01;
    if (raw.noise !== undefined) normalized.noise = parseFloat(raw.noise) * 0.1;
    if (raw.intensity !== undefined) normalized.intensity = parseFloat(raw.intensity) * 0.05;
    if (raw.speed !== undefined) normalized.speed = parseFloat(raw.speed) * 0.01;
    if (raw.colorArray !== undefined) normalized.colorArray = raw.colorArray;
    return normalized;
  };
  let {
    colorArray,
    speed,
    intensity,
    noise,
    scale,
    texture,
    textureSize = 40,
    progress,
    mouseInteractive
  } = normalizeSettings(userSettings);
  let lastMousePos = {
    x: 0.5,
    y: 0.5
  };
  let lastTime = 0;
  const renderer = new Renderer({
    canvas,
    alpha: true,
    premultipliedAlpha: true,
    antialias: true
  });
  const gl = renderer.gl;
  const geometry = new Plane(gl, {
    width: 2,
    height: 2
  });
  const rtOptions = {
    width: el.offsetWidth,
    height: el.offsetHeight
  };
  let waveRenderTarget1 = new RenderTarget(gl, rtOptions);
  let waveRenderTarget2 = new RenderTarget(gl, rtOptions);
  let sourceRT = waveRenderTarget1; // Read from
  let destRT = waveRenderTarget2; // Write to

  const createSolidTexture = (gl, rgb) => {
    var _rgb$, _rgb$2, _rgb$3;
    const t = new Texture(gl, {
      generateMipmaps: false,
      wrapS: gl.CLAMP_TO_EDGE,
      wrapT: gl.CLAMP_TO_EDGE
    });
    const data = new Uint8Array([Math.round(((_rgb$ = rgb[0]) !== null && _rgb$ !== void 0 ? _rgb$ : 0) * 255), Math.round(((_rgb$2 = rgb[1]) !== null && _rgb$2 !== void 0 ? _rgb$2 : 0) * 255), Math.round(((_rgb$3 = rgb[2]) !== null && _rgb$3 !== void 0 ? _rgb$3 : 0) * 255), 255]);
    gl.bindTexture(gl.TEXTURE_2D, t.texture);
    gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, 1, 1, 0, gl.RGBA, gl.UNSIGNED_BYTE, data);
    return t;
  };
  const spriteTexture = createSolidTexture(gl, [1, 1, 1]);
  const customTexture = new Texture(gl, {
    wrapS: gl.CLAMP_TO_EDGE,
    wrapT: gl.CLAMP_TO_EDGE
  });
  const loadImageToTexture = (tex, src) => {
    const img = new Image();
    img.crossOrigin = "anonymous";
    img.onload = () => {
      tex.image = img;
    };
    img.src = src;
  };
  if ((_userSettings$texture = userSettings.texture) !== null && _userSettings$texture !== void 0 && _userSettings$texture.src) {
    loadImageToTexture(customTexture, userSettings.texture.src);
  } else {
    const base64IMG = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAARgAAAAoCAYAAAAlg+WVAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAATCSURBVHgB7ZzvUeMwEMXFzX2HDoAKgA6gAqACoAOogFABUAGhAugAOghUAFQAVODT840y8uI/coy0ivN+MzvEmRgvcvTYXa28ZowpDCGEROCPIYSQSFBgCCHRoMAQQqJBgSGERIMCQwiJBgWGkEQ8PT2Vtkr8NYSMiK2trcrx+/u7yQXp2ypAgSFzJpPJ/DUm5nQ6DTpvd3fXHB0dzY9fXl7M4+Oj0WA2m5mNjY358d7eXumPNhAX+PX19VW+1hA+//72Yej9LGg0mMSKRtB5p6enlfPu7u5U/LcT+MffoOGHs/39/cKmRMXn5+cPv/AexsmKczJ/FmXI/WQNhjRiv1hLFdYjkvJ5fn42GiBSeXh4KOstVmQqEZX/GSvMZcR1fX1txgpTpEzwJzLCaJg2mAQQmYODA7MMYDL7aNVfIC7SlzbOz8/LnxcXFyYloePz/f1thlDQ9A2htAOvNXxowk6A1vNySZHsxO7ldwyTY4FUCH4gfXt7eyvN/jMp35OpE1KqmL5JUowHUyTSyeXl5VKkStJHjeLuyclJ5RhF5pubm0pEisgB7x0fH1c+6yKZMUGBIZ24mkLOwEdZg9EQGF/kICRtaQhqRL6POzs7ZmxQYEgj/tIkJm/Oxcg6cdGuY0H06gq8PqhvbW9vl7Ysta4+UGBII2dnZ5X/wAjh+xQvUyIF5uPjw2jgjxfEBellGxBBF+nk1BT4W3AViTSCLz9Exm9vx6oS6go5rHL5yPRCq7nu/v6+IsIQZTQhIhp0jXa5EFpXGyp8xRgNlXpXte+yVD5hlaDJB7mi0PS5mI1ZTasMtiBZed+mSpXzclhFms1mSVdk2sxfEawDvmKMQhsZY93fUAZeN90fmNIgMIkGMNjwpR9KzInTNC5uibXJjxwERgKfU/vg22QyKULAuGL8NMYolCHXZA2GdOJSJR+kSl0FzFTI+ksOjYrY94PC7e3tbWu6hjQFYwkbI6OtweALdnV1ZXICuWyTT+ifcDkxPodcvul3aIAlVfjuipZuYsheDg1y6H+pA/fK9bZAjN1GTPgni+XYNoDC9KIbEhcBApiCgqZvOXby1n1G1jrQkaqdIskaEY5TXj/U/JofUvjpdFrxG3W4mNf/zdQn1JgikV4gYvHTD0Q0m5ubRhO5gqS1ybEPiG4Qtfi+IrrRHsvfhgJDeiHTPEyKlGF9HbIGo5VGwg9XT4GF9Axp9eukgn0wpDfYR3N4eJhF0x0mtV9sRnSlWYNBVOJAbagrmlpfX68cD925nBuMYMhCYFUph6axnAq8uLYfPUGA2zp5UdiXTwLMrYFxKIxgMuH19XX+OpdVkDYwkSAy2psgZXrkj6MGeKaLPyZIHxHVuE5egD1d8FtGgKlXPf1oqw3c6yF1rYJGgy2yyiBXcFKvIslnwKRqWmuz0CY7H5wT269FGXg/4w84bTlMEnJOXZdvSoFp6zDWNLctpAu0JKTyWUNgmCKRQdRtiEwFUg5Zg8lliRp+oJHNpULw0z2MCukSUjn5PJgxsmb+Kw0hSwcmrV9Ehdilfq5tH2xEU/5M1UGbAxQYQhKxigLDFImQROSytJ8SRjCEkGiw0Y4QEg0KDCEkGhQYQkg0KDCEkGhQYAgh0fgH3h28+h8W7lUAAAAASUVORK5CYII=";
    loadImageToTexture(customTexture, base64IMG);
  }
  const waveProgram = new Program(gl, {
    vertex,
    fragment: wave,
    uniforms: {
      uTime: {
        value: 0
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
      uNoiseScale: {
        value: 3.5
      },
      uNoise: {
        value: noise
      },
      uColorStops: {
        value: colorArray
      },
      uPreviousFrame: {
        value: sourceRT.texture
      },
      uMouseVelocity: {
        value: 0
      },
      uProgress: {
        value: progress
      },
      uInteractive: {
        value: mouseInteractive
      }
    }
  });
  const ditherProgram = new Program(gl, {
    vertex,
    fragment,
    uniforms: {
      uTime: {
        value: 0
      },
      uResolution: {
        value: [el.offsetWidth, el.offsetHeight]
      },
      uWaveTexture: {
        value: destRT.texture
      },
      // Points to the latest-rendered RT
      uSprite: {
        value: spriteTexture
      },
      uCustomTexture: {
        value: customTexture
      },
      uScale: {
        value: scale
      },
      uTextureSize: {
        value: textureSize
      }
    }
  });
  const mesh = new Mesh(gl, {
    geometry,
    program: waveProgram
  });
  const getNormMouse = e => {
    const rect = el.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width;
    const y = (e.clientY - rect.top) / rect.height;
    return [Math.min(Math.max(x, 0), 1), Math.min(Math.max(1 - y, 0), 1)];
  };
  const onPointerMove = e => {
    const [mx, my] = getNormMouse(e);
    waveProgram.uniforms.uMouse.value = [mx, my];
  };
  if (mouseInteractive > 0) {
    el.addEventListener("pointermove", onPointerMove, {
      passive: true
    });
  }
  // Uncoder: the loop can be paused (off screen) and stopped (element removed).
  let stopped = false;
  let paused = false;
  let frame = 0;
  const customLoop = t => {
    frame = 0;
    if (stopped || paused) return;
    const time = t * 0.001;
    lastTime = time;
    const currentMousePos = {
      x: waveProgram.uniforms.uMouse.value[0],
      y: waveProgram.uniforms.uMouse.value[1]
    };
    const dx = currentMousePos.x - lastMousePos.x;
    const dy = currentMousePos.y - lastMousePos.y;
    // Calculate velocity. Multiply by a factor (e.g., 20.0) for visual impact, and clamp to 1.0.
    const mouseVelocity = Math.min(Math.sqrt(dx * dx + dy * dy) * 20.0, 1.0);
    lastMousePos = currentMousePos;

    // Update velocity uniform
    waveProgram.uniforms.uMouseVelocity.value = mouseVelocity;
    waveProgram.uniforms.uTime.value = time;
    ditherProgram.uniforms.uTime.value = time;

    // 1. Render Wave (with feedback)
    mesh.program = waveProgram;
    waveProgram.uniforms.uPreviousFrame.value = sourceRT.texture;
    renderer.render({
      scene: mesh,
      target: destRT
    });

    // 2. Render Dither (to screen)
    mesh.program = ditherProgram;
    ditherProgram.uniforms.uWaveTexture.value = destRT.texture;
    renderer.render({
      scene: mesh,
      target: null
    });
    let temp = sourceRT;
    sourceRT = destRT;
    destRT = temp;
    frame = requestAnimationFrame(customLoop);
  };
  frame = requestAnimationFrame(customLoop);
  const customResize = () => {
    renderer.setSize(el.offsetWidth, el.offsetHeight);
    const newOptions = {
      width: el.offsetWidth,
      height: el.offsetHeight
    };

    // Recreate both RTs on resize
    waveRenderTarget1 = new RenderTarget(gl, newOptions);
    waveRenderTarget2 = new RenderTarget(gl, newOptions);
    sourceRT = waveRenderTarget1;
    destRT = waveRenderTarget2;

    // Update uniforms to point to the new textures
    waveProgram.uniforms.uPreviousFrame.value = sourceRT.texture;
    ditherProgram.uniforms.uWaveTexture.value = destRT.texture;
    ditherProgram.uniforms.uResolution.value = [el.offsetWidth, el.offsetHeight];
  };
  window.addEventListener("resize", customResize);
  customResize();
  const customUpdateSettings = normalized => {
    for (const [k, v] of Object.entries(normalized)) {
      switch (k) {
        case "colorArray":
          waveProgram.uniforms.uColorStops.value = v;
          break;
        case "intensity":
          waveProgram.uniforms.uIntensity.value = v;
          break;
        case "speed":
          waveProgram.uniforms.uSpeed.value = v;
          break;
        case "scale":
          ditherProgram.uniforms.uScale.value = v;
          break;
        case "noise":
          waveProgram.uniforms.uNoise.value = v;
          break;
        case 'progress':
          if (waveProgram.uniforms.uSpeed.value === 0) {
            waveProgram.uniforms.uProgress.value = v;
          }
        case "texture":
          {
            if (v !== null && v !== void 0 && v.src) loadImageToTexture(customTexture, v.src);
            break;
          }
        case "textureSize":
          ditherProgram.uniforms.uTextureSize.value = v;
          break;
        case "mouseInteractive":
          waveProgram.uniforms.uInteractive.value = v;
          if (v) {
            el.addEventListener("pointermove", onPointerMove, {
              passive: true
            });
          } else {
            el.removeEventListener("pointermove", onPointerMove);
          }
      }
    }
  };
  el.animatedBackground = {
    normalizeSettings,
    custom: true,
    customUpdateSettings,
    pause: () => {
      paused = true;
    },
    resume: () => {
      if (stopped || !paused) return;
      paused = false;
      if (!frame) frame = requestAnimationFrame(customLoop);
    },
    destroy: () => {
      stopped = true;
      cancelAnimationFrame(frame);
      window.removeEventListener("resize", customResize);
      el.removeEventListener("pointermove", onPointerMove);
    }
  };
};