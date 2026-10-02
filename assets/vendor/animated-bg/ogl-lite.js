// ===== custom-ogl.js =====
// Minimal OGL subset: Renderer, Program, Texture, Mesh, Plane, Triangle
// Exports to window.uncoder_ogl

(function () {
  class RenderTarget {
    constructor(gl, {
      width,
      height
    } = {}) {
      this.gl = gl;
      this.width = width;
      this.height = height;

      // Create texture to render into
      this.texture = gl.createTexture();
      gl.bindTexture(gl.TEXTURE_2D, this.texture);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
      gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, width, height, 0, gl.RGBA, gl.UNSIGNED_BYTE, null);

      // Create framebuffer and attach the texture
      this.framebuffer = gl.createFramebuffer();
      gl.bindFramebuffer(gl.FRAMEBUFFER, this.framebuffer);
      gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, this.texture, 0);

      // Cleanup
      gl.bindTexture(gl.TEXTURE_2D, null);
      gl.bindFramebuffer(gl.FRAMEBUFFER, null);
    }
    bind() {
      const gl = this.gl;
      gl.bindFramebuffer(gl.FRAMEBUFFER, this.framebuffer);
      gl.viewport(0, 0, this.width, this.height);
    }
    unbind() {
      this.gl.bindFramebuffer(this.gl.FRAMEBUFFER, null);
    }
  }
  class Renderer {
    constructor({
      canvas,
      alpha = true,
      premultipliedAlpha = true,
      antialias = true
    } = {}) {
      this.gl = canvas.getContext("webgl", {
        alpha,
        premultipliedAlpha,
        antialias
      });
      if (!this.gl) throw new Error("WebGL not supported");
      const gl = this.gl;
      gl.enable(gl.BLEND);
      gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA);
    }
    render({
      scene
    } = {}) {
      scene.draw();
    }
    setSize(width, height) {
      const gl = this.gl;
      gl.canvas.width = width;
      gl.canvas.height = height;
      gl.viewport(0, 0, width, height);
    }
  }
  class Program {
    constructor(gl, {
      vertex,
      fragment,
      uniforms = {}
    }) {
      this.gl = gl;
      this.uniforms = uniforms;
      this.program = gl.createProgram();
      const vert = this._compile(gl.VERTEX_SHADER, vertex);
      const frag = this._compile(gl.FRAGMENT_SHADER, fragment);
      gl.attachShader(this.program, vert);
      gl.attachShader(this.program, frag);
      gl.linkProgram(this.program);
      if (!gl.getProgramParameter(this.program, gl.LINK_STATUS)) {
        console.error("Program link error:", gl.getProgramInfoLog(this.program));
      }
    }
    addLineNumbers(string) {
      let lines = string.split('\n');
      for (let i = 0; i < lines.length; i++) {
        lines[i] = i + 1 + ': ' + lines[i];
      }
      return lines.join('\n');
    }
    _compile(type, source) {
      const gl = this.gl;
      const shader = gl.createShader(type);
      gl.shaderSource(shader, source);
      gl.compileShader(shader);
      if (gl.getShaderInfoLog(shader) !== '') {
        console.warn(`${gl.getShaderInfoLog(shader)}\nShader\n${this.addLineNumbers(source)}`);
      }
      return shader;
    }
    use() {
      this.gl.useProgram(this.program);
      this._applyUniforms();
    }
    _applyUniforms() {
      const gl = this.gl;
      for (const name in this.uniforms) {
        const uniform = this.uniforms[name];
        const loc = gl.getUniformLocation(this.program, name);
        if (loc === null) continue;
        const value = uniform.value;

        // Handle texture uniforms
        if (value && value instanceof Texture) {
          const unit = 0; // single texture unit for simplicity
          value.bind(unit);
          gl.uniform1i(loc, unit);
          continue;
        }

        //handle bolean uniforms
        if (typeof value === "boolean") {
          gl.uniform1f(loc, value ? 1 : 0);
          continue;
        }

        // Auto-assign by type
        if (typeof value === "number") gl.uniform1f(loc, value);else if (Array.isArray(value)) {
          if (Array.isArray(value[0])) {
            // Handle 2D array (e.g., array of vec3)
            const flattened = value.flat();
            if (value[0].length === 3) gl.uniform3fv(loc, flattened);else if (value[0].length === 4) gl.uniform4fv(loc, flattened);
          } else if (value.length === 2) gl.uniform2fv(loc, value);else if (value.length === 3) gl.uniform3fv(loc, value);else if (value.length === 4) gl.uniform4fv(loc, value);
        }
      }
    }
  }
  class Texture {
    constructor(gl, {
      image = null,
      wrapS = gl.CLAMP_TO_EDGE,
      wrapT = gl.CLAMP_TO_EDGE,
      minFilter = gl.LINEAR,
      magFilter = gl.LINEAR
    } = {}) {
      this.gl = gl;
      this.texture = gl.createTexture();
      this.image = image;
      this.wrapS = wrapS;
      this.wrapT = wrapT;
      this.minFilter = minFilter;
      this.magFilter = magFilter;
      this._setup();
    }
    _setup() {
      const gl = this.gl;
      gl.bindTexture(gl.TEXTURE_2D, this.texture);
      gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, this.wrapS);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, this.wrapT);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, this.minFilter);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, this.magFilter);
      if (this.image) {
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, this.image);
      } else {
        // 1x1 white pixel as placeholder
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, 1, 1, 0, gl.RGBA, gl.UNSIGNED_BYTE, new Uint8Array([255, 255, 255, 255]));
      }
      gl.bindTexture(gl.TEXTURE_2D, null);
    }
    bind(unit = 0) {
      const gl = this.gl;
      gl.activeTexture(gl.TEXTURE0 + unit);
      gl.bindTexture(gl.TEXTURE_2D, this.texture);
      if (this.image && this.image instanceof HTMLImageElement && this.image.complete) {
        gl.bindTexture(gl.TEXTURE_2D, this.texture);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, this.image);
      }
    }
  }
  class Mesh {
    constructor(gl, {
      geometry,
      program
    }) {
      this.gl = gl;
      this.geometry = geometry;
      this.program = program;
    }
    draw() {
      const gl = this.gl;
      this.program.use();
      this.geometry.bind(this.program);
      this.geometry.draw();
    }
  }
  class Plane {
    constructor(gl, {
      width = 2,
      height = 2
    } = {}) {
      this.gl = gl;
      const x1 = -width / 2;
      const y1 = -height / 2;
      const x2 = width / 2;
      const y2 = height / 2;
      const positions = new Float32Array([x1, y1, 0, 0, x2, y1, 1, 0, x1, y2, 0, 1, x1, y2, 0, 1, x2, y1, 1, 0, x2, y2, 1, 1]);
      this.vertexCount = 6;
      this.vbo = gl.createBuffer();
      gl.bindBuffer(gl.ARRAY_BUFFER, this.vbo);
      gl.bufferData(gl.ARRAY_BUFFER, positions, gl.STATIC_DRAW);
    }
    bind(program) {
      const gl = this.gl;
      gl.bindBuffer(gl.ARRAY_BUFFER, this.vbo);
      const posLoc = gl.getAttribLocation(program.program, "position");
      const uvLoc = gl.getAttribLocation(program.program, "uv");
      if (posLoc >= 0) {
        gl.enableVertexAttribArray(posLoc);
        gl.vertexAttribPointer(posLoc, 2, gl.FLOAT, false, 16, 0);
      }
      if (uvLoc >= 0) {
        gl.enableVertexAttribArray(uvLoc);
        gl.vertexAttribPointer(uvLoc, 2, gl.FLOAT, false, 16, 8);
      }
    }
    draw() {
      this.gl.drawArrays(this.gl.TRIANGLES, 0, this.vertexCount);
    }
  }
  class Triangle {
    constructor(gl) {
      this.gl = gl;
      const vertices = new Float32Array([-1, -1, 3, -1, -1, 3]);
      this.vertexCount = 3;
      this.vbo = gl.createBuffer();
      gl.bindBuffer(gl.ARRAY_BUFFER, this.vbo);
      gl.bufferData(gl.ARRAY_BUFFER, vertices, gl.STATIC_DRAW);
    }
    bind(program) {
      const gl = this.gl;
      gl.bindBuffer(gl.ARRAY_BUFFER, this.vbo);
      const loc = gl.getAttribLocation(program.program, "position");
      if (loc >= 0) {
        gl.enableVertexAttribArray(loc);
        gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
      }
    }
    draw() {
      this.gl.drawArrays(this.gl.TRIANGLES, 0, this.vertexCount);
    }
  }

  // Export globally
  window.uncoder_ogl = {
    RenderTarget,
    Renderer,
    Program,
    Texture,
    Mesh,
    Plane,
    Triangle
  };
})();