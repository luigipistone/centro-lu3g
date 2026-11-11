export function startCompletionAnimation({
    canvas,
    veil,
    message,
    kind,
    onFinish,
}) {
    const stage = canvas.parentElement,
        ctx = canvas.getContext("2d");
    if (!ctx) {
        onFinish();
        return () => {};
    }
    let raf = 0;
    const settings = { duration: 5 };
    const clamp = (x) => Math.max(0, Math.min(1, x)),
        smooth = (x) => {
            x = clamp(x);
            return x * x * (3 - 2 * x);
        },
        lerp = (a, b, t) => a + (b - a) * t;
    function star(x, y, r, angle, color) {
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate(angle);
        ctx.fillStyle = color;
        ctx.beginPath();
        for (let i = 0; i < 10; i++) {
            const a = -Math.PI / 2 + (i * Math.PI) / 5,
                rr = i % 2 ? r * 0.4 : r;
            i
                ? ctx.lineTo(Math.cos(a) * rr, Math.sin(a) * rr)
                : ctx.moveTo(Math.cos(a) * rr, Math.sin(a) * rr);
        }
        ctx.closePath();
        ctx.fill();
        ctx.restore();
    }
    function mario(x, ground, jump, time, scale) {
        ctx.save();
        ctx.translate(x, ground - jump);
        ctx.scale(scale, scale);
        const running = jump < 2,
            cycle = Math.sin(time * 16),
            stride = running ? cycle : 0.65;
        const oval = (x, y, rx, ry, color) => {
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.ellipse(x, y, rx, ry, 0, 0, Math.PI * 2);
            ctx.fill();
        };
        const shade = (x, y, r, light, dark) => {
            const g = ctx.createRadialGradient(
                x - r * 0.35,
                y - r * 0.4,
                1,
                x,
                y,
                r,
            );
            g.addColorStop(0, light);
            g.addColorStop(1, dark);
            return g;
        };
        const limb = (x, y, angle, length, color, shoe) => {
            ctx.save();
            ctx.translate(x, y);
            ctx.rotate(angle);
            ctx.strokeStyle = color;
            ctx.lineWidth = 12;
            ctx.lineCap = "round";
            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.lineTo(0, length);
            ctx.stroke();
            oval(5, length + 4, 12, 7, shoe);
            ctx.restore();
        };
        limb(-8, -28, -stride * 0.65, 19, "#1959a7", "#603421");
        limb(8, -29, stride * 0.65, 20, "#2477cf", "#82492c");
        limb(-14, -48, stride * 0.8, 15, "#bb202b", "#e4e9ee");
        oval(0, -43, 20, 22, shade(0, -44, 25, "#ff5252", "#bb1725"));
        ctx.fillStyle = shade(0, -33, 23, "#4299ed", "#1951a1");
        ctx.beginPath();
        ctx.roundRect(-15, -47, 29, 27, 9);
        ctx.fill();
        ctx.strokeStyle = "#2777c6";
        ctx.lineWidth = 6;
        ctx.beginPath();
        ctx.moveTo(-9, -60);
        ctx.lineTo(-9, -35);
        ctx.moveTo(9, -60);
        ctx.lineTo(9, -35);
        ctx.stroke();
        oval(-9, -42, 3, 3, "#ffdc63");
        oval(9, -42, 3, 3, "#ffdc63");
        limb(16, -50, running ? -stride * 0.9 : -2.6, 15, "#ec3340", "#f8fcff");
        oval(1, -74, 19, 20, shade(1, -74, 23, "#ffe2b3", "#e9a474"));
        oval(-13, -76, 7, 9, "#77412b");
        oval(-14, -71, 5, 6, "#f5be8e");
        oval(9, -78, 5, 8, "#fff");
        oval(11, -77, 2.8, 5, "#398bce");
        oval(12, -77, 1.4, 3.5, "#172636");
        ctx.strokeStyle = "#61382a";
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(5, -88);
        ctx.quadraticCurveTo(10, -91, 15, -86);
        ctx.stroke();
        oval(19, -70, 10, 8, shade(18, -72, 13, "#ffdbb0", "#eda372"));
        for (let i = 0; i < 4; i++)
            oval(3 + i * 4.1, -63 + Math.sin(i) * 1.5, 4.7, 3.8, "#452c23");
        ctx.fillStyle = shade(0, -91, 25, "#ff6262", "#c31827");
        ctx.beginPath();
        ctx.moveTo(-18, -83);
        ctx.bezierCurveTo(-24, -103, 12, -109, 19, -88);
        ctx.closePath();
        ctx.fill();
        oval(12, -85, 19, 4, "#d72330");
        oval(1, -94, 7, 6, "#fff7ed");
        ctx.fillStyle = "#dc2533";
        ctx.font = "bold 9px system-ui";
        ctx.textAlign = "center";
        ctx.fillText("M", 1, -91);
        ctx.restore();
    }
    function play() {
        cancelAnimationFrame(raf);
        const w = stage.clientWidth,
            h = stage.clientHeight,
            cx = w / 2,
            dpr = Math.min(devicePixelRatio || 1, 2),
            reduced = matchMedia("(prefers-reduced-motion: reduce)").matches;
        canvas.width = w * dpr;
        canvas.height = h * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        const flakes = Array.from({ length: 170 }, () => ({
            x: Math.random() * w,
            y: -Math.random() * h * 0.9,
            r: 1 + Math.random() * 4.7,
            v: 60 + Math.random() * 115,
            drift: Math.random() * 30,
            phase: Math.random() * 6.28,
            rotation: Math.random() * 6.28,
        }));
        const sparks = Array.from({ length: 70 }, () => ({
            a: Math.random() * 6.28,
            v: 30 + Math.random() * 160,
            r: 1 + Math.random() * 3,
        }));
        const start = performance.now(),
            duration = reduced
                ? 850
                : (kind === "mario"
                      ? settings.duration * 0.72
                      : settings.duration) * 1000;

        function draw(now) {
            const t = (now - start) / duration,
                sec = t * 5,
                opacity = smooth(t / 0.12) * (1 - smooth((t - 0.84) / 0.16));
            ctx.clearRect(0, 0, w, h);
            ctx.globalAlpha = 1;
            veil.style.opacity = opacity * (kind === "mario" ? 0.89 : 0.94);
            const reveal =
                    kind === "snow" ? 0.25 : kind === "mario" ? 0.4 : 0.48,
                show =
                    smooth((t - reveal) / 0.12) *
                    (1 - smooth((t - 0.84) / 0.16));
            message.style.opacity = show;
            message.style.transform = `translate(-50%,calc(-50% + ${(1 - show) * 12}px))`;
            if (!reduced) {
                if (kind === "snow") {
                    flakes.forEach((p, i) => {
                        const x =
                                (p.x +
                                    Math.sin(sec * 0.8 + p.phase) * p.drift +
                                    sec * 12 +
                                    w) %
                                w,
                            y = p.y + sec * p.v;
                        if (y > h + 15) return;
                        ctx.save();
                        ctx.translate(x, y);
                        ctx.rotate(p.rotation + sec * 0.2);
                        ctx.globalAlpha = opacity * (0.3 + p.r / 8);
                        ctx.strokeStyle = "#e8f8ff";
                        ctx.fillStyle = "#f5fdff";
                        if (p.r > 4) {
                            ctx.lineWidth = 1;
                            ctx.beginPath();
                            for (let j = 0; j < 6; j++) {
                                const a = (j * Math.PI) / 3,
                                    rx = Math.cos(a),
                                    ry = Math.sin(a),
                                    len = p.r * 1.7;
                                ctx.moveTo(0, 0);
                                ctx.lineTo(rx * len, ry * len);
                                for (const s of [-1, 1]) {
                                    const aa = a + s * 0.65;
                                    ctx.moveTo(rx * len * 0.6, ry * len * 0.6);
                                    ctx.lineTo(
                                        rx * len * 0.6 -
                                            Math.cos(aa) * len * 0.3,
                                        ry * len * 0.6 -
                                            Math.sin(aa) * len * 0.3,
                                    );
                                }
                            }
                            ctx.stroke();
                        } else {
                            ctx.beginPath();
                            ctx.arc(0, 0, p.r * 0.6, 0, Math.PI * 2);
                            ctx.fill();
                        }
                        ctx.restore();
                    });
                    // Snow collects at the lower edge, leaving the task content untouched.
                    ctx.globalAlpha = opacity * 0.95;
                    ctx.fillStyle = "#e6f5fc";
                    ctx.beginPath();
                    ctx.moveTo(0, h);
                    for (let x = 0; x <= w + 10; x += 10) {
                        ctx.lineTo(
                            x,
                            h -
                                (7 +
                                    Math.sin(x * 0.017) * 4 +
                                    Math.sin(x * 0.041) * 2) *
                                    smooth(t / 0.65),
                        );
                    }
                    ctx.lineTo(w, h);
                    ctx.closePath();
                    ctx.fill();
                    ctx.globalAlpha = opacity * 0.45;
                    const frost = ctx.createLinearGradient(0, h - 65, 0, h);
                    frost.addColorStop(0, "#d8efff00");
                    frost.addColorStop(1, "#d8efff");
                    ctx.fillStyle = frost;
                    ctx.fillRect(0, h - 65, w, 65);
                } else if (kind === "mario") {
                    const ground = h * 0.79,
                        scale = w < 500 ? 0.8 : 1,
                        b = 40,
                        blockY = ground - 103 * scale - 123;
                    const x = lerp(-55, w + 55, clamp(t / 0.78));
                    const jumpPhase = (t - 0.29) / 0.2;
                    const jump =
                        jumpPhase > 0 && jumpPhase < 1
                            ? 4 * jumpPhase * (1 - jumpPhase) * 83
                            : 0;
                    ctx.globalAlpha = opacity;
                    // The block reacts at the apex; the coin lifts, spins and disappears.
                    const hit = t >= 0.39,
                        bump =
                            hit && t < 0.45
                                ? Math.sin(((t - 0.39) / 0.06) * Math.PI) * -8
                                : 0;
                    ctx.fillStyle = hit ? "#bb8439" : "#d98018";
                    ctx.fillRect(
                        cx - b / 2 - 2,
                        blockY + bump - 2,
                        b + 4,
                        b + 4,
                    );
                    ctx.fillStyle = hit ? "#dba55c" : "#ffcb45";
                    ctx.fillRect(cx - b / 2, blockY + bump, b, b);
                    ctx.fillStyle = hit ? "#be8c46" : "#ffef9a";
                    ctx.fillRect(cx - b / 2 + 3, blockY + bump + 3, b - 6, 3);
                    ctx.fillStyle = "#9f5b1f";
                    for (const dx of [-14, 12])
                        for (const dy of [4, 32])
                            ctx.fillRect(cx + dx, blockY + bump + dy, 3, 3);
                    if (!hit) {
                        ctx.font = "bold 30px monospace";
                        ctx.textAlign = "center";
                        ctx.fillStyle = "#fff5cb";
                        ctx.fillText("?", cx, blockY + 31 + bump);
                    }
                    if (hit && t < 0.68) {
                        const q = (t - 0.39) / 0.29,
                            coinY = blockY - 18 - Math.sin(q * Math.PI) * 66,
                            coinW = 3 + Math.abs(Math.cos(q * 13)) * 10;
                        ctx.save();
                        ctx.translate(cx, coinY);
                        ctx.fillStyle = "#d58a11";
                        ctx.beginPath();
                        ctx.ellipse(1, 0, coinW, 15, 0, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.fillStyle = "#ffe272";
                        ctx.beginPath();
                        ctx.ellipse(-1, -1, coinW * 0.7, 12, 0, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.fillStyle = "#fff4bf";
                        ctx.fillRect(-2, -9, 2, 17);
                        ctx.restore();
                        sparks.slice(0, 14).forEach((p) => {
                            ctx.globalAlpha = opacity * (1 - q);
                            star(
                                cx + Math.cos(p.a) * q * 100,
                                coinY + Math.sin(p.a) * q * 60,
                                3,
                                q * 2,
                                "#eeb327",
                            );
                        });
                        ctx.globalAlpha = opacity;
                    }
                    ctx.fillStyle = "#526c7930";
                    ctx.beginPath();
                    ctx.ellipse(x, ground + 3, 22, 4, 0, 0, Math.PI * 2);
                    ctx.fill();
                    mario(x, ground, jump, sec, scale);
                    ctx.globalAlpha = opacity * 0.65;
                    ctx.fillStyle = "#68a67c";
                    ctx.fillRect(0, ground + 16, w, 2);
                } else {
                    const launch = smooth((t - 0.1) / 0.65),
                        x = lerp(w * 0.17, w * 1.13, launch),
                        y = h * 0.92 - Math.pow(launch, 0.7) * h * 1.35;
                    ctx.globalAlpha = opacity;
                    sparks.forEach((p, i) => {
                        const q = clamp(launch - i * 0.0025);
                        const tx = lerp(w * 0.17, w * 1.13, q),
                            ty = h * 0.92 - Math.pow(q, 0.7) * h * 1.35;
                        ctx.globalAlpha = opacity * (1 - i / 70) * 0.55;
                        ctx.fillStyle = i % 3 ? "#ffc875" : "#83e3eb";
                        ctx.beginPath();
                        ctx.arc(
                            tx + Math.sin(p.a) * i * 0.35,
                            ty + Math.cos(p.a) * i * 0.2,
                            2 + i * 0.17,
                            0,
                            Math.PI * 2,
                        );
                        ctx.fill();
                    });
                    ctx.globalAlpha = opacity;
                    ctx.save();
                    ctx.translate(x, y);
                    ctx.rotate(0.55 + launch * 0.42);
                    const flame = 22 + Math.sin(sec * 40) * 6;
                    ctx.fillStyle = "#ff8e45";
                    ctx.beginPath();
                    ctx.moveTo(-11, 23);
                    ctx.quadraticCurveTo(-10, 45, 0, 30 + flame);
                    ctx.quadraticCurveTo(10, 45, 11, 23);
                    ctx.fill();
                    ctx.fillStyle = "#ffe9a3";
                    ctx.beginPath();
                    ctx.moveTo(-5, 23);
                    ctx.lineTo(0, 38 + flame * 0.4);
                    ctx.lineTo(5, 23);
                    ctx.fill();
                    ctx.fillStyle = "#ef604b";
                    ctx.beginPath();
                    ctx.moveTo(-12, 1);
                    ctx.lineTo(-25, 29);
                    ctx.lineTo(-9, 23);
                    ctx.closePath();
                    ctx.fill();
                    ctx.beginPath();
                    ctx.moveTo(12, 1);
                    ctx.lineTo(25, 29);
                    ctx.lineTo(9, 23);
                    ctx.closePath();
                    ctx.fill();
                    const metal = ctx.createLinearGradient(-15, 0, 15, 0);
                    metal.addColorStop(0, "#91b4c7");
                    metal.addColorStop(0.4, "#f4fcff");
                    metal.addColorStop(1, "#b5d3df");
                    ctx.fillStyle = metal;
                    ctx.beginPath();
                    ctx.moveTo(0, -36);
                    ctx.bezierCurveTo(21, -16, 17, 13, 11, 25);
                    ctx.lineTo(-11, 25);
                    ctx.bezierCurveTo(-17, 13, -21, -16, 0, -36);
                    ctx.fill();
                    ctx.fillStyle = "#f46b50";
                    ctx.beginPath();
                    ctx.moveTo(0, -36);
                    ctx.quadraticCurveTo(10, -26, 13, -17);
                    ctx.lineTo(-13, -17);
                    ctx.quadraticCurveTo(-10, -26, 0, -36);
                    ctx.fill();
                    ctx.fillStyle = "#2a7796";
                    ctx.beginPath();
                    ctx.arc(0, -3, 9, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.fillStyle = "#9ce9f2";
                    ctx.beginPath();
                    ctx.arc(-2, -5, 5, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.restore();
                    if (t > 0.5) {
                        const q = clamp((t - 0.5) / 0.36);
                        sparks.slice(0, 35).forEach((p) => {
                            ctx.globalAlpha = opacity * (1 - q);
                            star(
                                cx + Math.cos(p.a) * (50 + q * p.v),
                                h * 0.42 + Math.sin(p.a) * (35 + q * p.v),
                                p.r + 1,
                                q * 2 + p.a,
                                p.r > 2 ? "#ffda85" : "#83e3eb",
                            );
                        });
                    }
                }
            }
            ctx.globalAlpha = 1;
            if (t < 1) raf = requestAnimationFrame(draw);
            else {
                ctx.clearRect(0, 0, w, h);
                veil.style.opacity = 0;
                message.style.opacity = 0;
                onFinish();
            }
        }
        raf = requestAnimationFrame(draw);
    }

    play();
    return () => cancelAnimationFrame(raf);
}
