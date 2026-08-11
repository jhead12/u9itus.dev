/**
 * SpecularButton — WebGL rim-light sweep effect, ported from React Bits'
 * SpecularButton (https://reactbits.dev) to plain JS. This codebase has no
 * React runtime wired into the Vite build, and none of the component's
 * behavior actually depends on React — the interesting part (an ogl-rendered
 * canvas tracking the pointer around the button's edge) is imperative DOM/GL
 * code that a React useEffect just happened to host. Mounting onto an
 * existing element instead of rendering a whole new one keeps this button's
 * existing click handlers, aria attributes, and .top-btn base styling intact.
 */
import { Renderer, Program, Mesh, Triangle, Color } from 'ogl';

const PAD = 20;

const VERT = `#version 300 es
in vec2 position;
void main() {
  gl_Position = vec4(position, 0.0, 1.0);
}
`;

const FRAG = `#version 300 es
precision highp float;

uniform vec2 uCenter;
uniform vec2 uHalfSize;
uniform float uRadius;
uniform float uAngle;
uniform float uPx;
uniform vec3 uLineColor;
uniform vec3 uBaseColor;
uniform float uIntensity;
uniform float uShineSize;
uniform float uShineFade;
uniform float uThickness;
uniform float uBaseWidth;

out vec4 fragColor;

float sdRoundedRect(vec2 p, vec2 b, float r) {
  vec2 q = abs(p) - b + r;
  return length(max(q, 0.0)) + min(max(q.x, q.y), 0.0) - r;
}

float shapeSDF(vec2 p) { return sdRoundedRect(p, uHalfSize, uRadius); }

float gaussianLine(float d, float sigma) {
  float x = d / (sigma + 1e-6);
  float k = mix(1.0, 1.6, smoothstep(0.0, 1.5, x));
  return exp(-k * x * x);
}

void main() {
  vec2 p = gl_FragCoord.xy - uCenter;
  float d = shapeSDF(p);
  vec2 L = vec2(cos(uAngle), sin(uAngle));

  float base = (1.0 - smoothstep(0.0, uBaseWidth, abs(d))) * 0.45;

  vec2 nEll = normalize(p / (uHalfSize * uHalfSize) + 1e-6);
  float phi = acos(clamp(abs(dot(nEll, L)), 0.0, 1.0));
  float rim = 1.0 - smoothstep(uShineSize - uShineFade, uShineSize + uShineFade + 1e-4, phi);
  float line = gaussianLine(d, uThickness);
  float edgeClamp = 1.0 - smoothstep(0.5 * uPx, 3.0 * uPx, abs(d));
  float hi = line * rim * edgeClamp * uIntensity;

  vec3 col = uBaseColor * base + uLineColor * hi;
  float a = clamp(base + hi, 0.0, 1.0);
  fragColor = vec4(col, a);
}
`;

/**
 * Mounts the specular rim-light effect onto an existing button element.
 * Returns a destroy() function to tear down the renderer and listeners.
 * No-ops (returns a no-op destroy) if WebGL2 isn't available.
 */
export function mountSpecularButton(btn, options = {}) {
    const {
        radius = 18,
        lineColor = '#ffffff',
        baseColor = '#525252',
        intensity = 1,
        shineSize = 10,
        shineFade = 40,
        thickness = 1,
        speed = 0.35,
        followMouse = true,
        proximity = 250,
        autoAnimate = false,
    } = options;

    btn.classList.add('specular-button');

    const label = document.createElement('span');
    label.className = 'specular-button__label';
    label.append(...btn.childNodes);

    const fx = document.createElement('span');
    fx.className = 'specular-button__fx';
    fx.setAttribute('aria-hidden', 'true');

    btn.append(fx, label);

    // WebGL setup as a single unit — a shader compile failure (e.g. a
    // fallback to a WebGL1 context, which can't run these #version 300 es
    // shaders) must not throw uncaught, since app.js runs its init calls
    // sequentially and an uncaught throw here would abort every init after
    // this one. Worst case: the button just keeps its plain .top-btn look.
    const dpr = window.devicePixelRatio || 1;
    let renderer, gl, geometry, program, mesh;
    try {
        renderer = new Renderer({ alpha: true, premultipliedAlpha: true, antialias: true, dpr });
        gl = renderer.gl;
        if (!gl) throw new Error('no WebGL context');

        gl.clearColor(0, 0, 0, 0);
        gl.enable(gl.BLEND);
        gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA);

        geometry = new Triangle(gl);
        if (geometry.attributes.uv) delete geometry.attributes.uv;

        program = new Program(gl, {
            vertex: VERT,
            fragment: FRAG,
            uniforms: {
                uCenter: { value: [0, 0] },
                uHalfSize: { value: [1, 1] },
                uRadius: { value: 0 },
                uAngle: { value: 2.4 },
                uPx: { value: dpr },
                uLineColor: { value: [1, 1, 1] },
                uBaseColor: { value: [0.32, 0.32, 0.32] },
                uIntensity: { value: 1 },
                uShineSize: { value: 0.17 },
                uShineFade: { value: 0.7 },
                uThickness: { value: 1 },
                uBaseWidth: { value: dpr },
            },
        });

        mesh = new Mesh(gl, { geometry, program });
    } catch (err) {
        console.warn('[specular-button] WebGL setup failed, falling back to plain button:', err);
        fx.remove();
        return () => {};
    }

    fx.appendChild(gl.canvas);

    const sizeRef = { w: 1, h: 1 };
    const resize = () => {
        const rect = btn.getBoundingClientRect();
        sizeRef.w = rect.width;
        sizeRef.h = rect.height;
        renderer.setSize(rect.width + PAD * 2, rect.height + PAD * 2);
        program.uniforms.uCenter.value = [(PAD + rect.width / 2) * dpr, (PAD + rect.height / 2) * dpr];
        program.uniforms.uHalfSize.value = [(rect.width / 2) * dpr, (rect.height / 2) * dpr];
    };
    const ro = new ResizeObserver(resize);
    ro.observe(btn);
    resize();

    let pointerAngle = null;
    let proximityT = 0;
    const onPointerMove = (e) => {
        const rect = btn.getBoundingClientRect();
        const cx = rect.left + rect.width / 2;
        const cy = rect.top + rect.height / 2;
        const dx = Math.max(rect.left - e.clientX, 0, e.clientX - rect.right);
        const dy = Math.max(rect.top - e.clientY, 0, e.clientY - rect.bottom);
        const dist = Math.hypot(dx, dy);
        if (dist === 0) {
            const nx = (e.clientX - cx) / (rect.width / 2);
            const ny = (cy - e.clientY) / (rect.height / 2);
            pointerAngle = Math.atan2(2 / rect.height, -2 / rect.width) + nx * 0.3 + ny * 0.15;
        } else {
            pointerAngle = Math.atan2(cy - e.clientY, e.clientX - cx);
        }
        const t = Math.max(0, 1 - dist / Math.max(proximity, 1));
        proximityT = t * t * (3 - 2 * t);
    };
    window.addEventListener('pointermove', onPointerMove);

    let angle = 2.4;
    let idleAngle = 2.4;
    let bright = 0;
    let last = performance.now();
    let raf = 0;

    const lineC = new Color();
    const baseC = new Color();

    const update = (now) => {
        raf = requestAnimationFrame(update);
        const dt = Math.min((now - last) / 1000, 0.05);
        last = now;

        idleAngle += speed * dt;
        const steer = followMouse && pointerAngle != null && (!autoAnimate || proximityT > 0);
        const target = steer ? pointerAngle : idleAngle;
        const diff = ((target - angle + Math.PI * 3) % (Math.PI * 2)) - Math.PI;
        angle += diff * (1 - Math.exp(-dt * 7));

        const brightTarget = autoAnimate ? 1 : proximityT;
        bright += (brightTarget - bright) * (1 - Math.exp(-dt * 8));

        lineC.set(lineColor);
        baseC.set(baseColor);
        program.uniforms.uAngle.value = angle;
        program.uniforms.uRadius.value = Math.min(radius, Math.min(sizeRef.w, sizeRef.h) / 2) * dpr;
        program.uniforms.uLineColor.value = [lineC.r, lineC.g, lineC.b];
        program.uniforms.uBaseColor.value = [baseC.r, baseC.g, baseC.b];
        program.uniforms.uIntensity.value = intensity * bright;
        program.uniforms.uShineSize.value = (shineSize * Math.PI) / 180;
        program.uniforms.uShineFade.value = (shineFade * Math.PI) / 180;
        program.uniforms.uThickness.value = thickness * dpr;
        renderer.render({ scene: mesh });
    };
    raf = requestAnimationFrame(update);

    return function destroy() {
        cancelAnimationFrame(raf);
        ro.disconnect();
        window.removeEventListener('pointermove', onPointerMove);
        if (gl.canvas.parentNode === fx) fx.removeChild(gl.canvas);
        gl.getExtension('WEBGL_lose_context')?.loseContext();
    };
}
