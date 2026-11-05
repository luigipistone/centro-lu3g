import { drawChristmasBulb, drawChristmasStars } from './christmasMotion';
import { drawEasterBranch, drawEasterEffect } from './easterMotion';

export const seasonalThemes = ['halloween', 'natale', 'pasqua', 'estate'];
export const isSeasonalTheme = (theme) => seasonalThemes.includes(theme);

const palettes = {
    halloween: ['#ff792d', '#2b211b', '#ffd398'],
    natale: ['#ce294b', '#a07849', '#ffe1a5'],
    pasqua: ['#dc7194', '#71a78a', '#f1d579'],
    estate: ['#008eaa', '#ffbd3b', '#ff7155'],
};

function ellipse(c, x, y, rx, ry, fill) {
    c.beginPath(); c.ellipse(x, y, rx, ry, 0, 0, Math.PI * 2); c.fillStyle = fill; c.fill();
}

export function drawSeasonObject(c, theme, time) {
    c.save(); c.translate(120, 120);
    c.rotate(Math.sin(time / 1500) * .045);
    if (theme === 'halloween') {
        c.fillStyle = '#40502e'; c.fillRect(-5, -74, 10, 23);
        for (const [x, rx, ry] of [[-39, 29, 44], [39, 29, 44], [-23, 30, 53], [23, 30, 53], [0, 26, 58]]) {
            const fill = c.createLinearGradient(x - rx, -35, x + rx, 40);
            fill.addColorStop(0, '#a4380e'); fill.addColorStop(.4, '#ff9f4b'); fill.addColorStop(1, '#c64c0e');
            ellipse(c, x, 0, rx, ry, fill);
        }
        c.fillStyle = '#ffe9aa'; c.shadowColor = '#ffcf68'; c.shadowBlur = 4 + Math.sin(time / 230) * 2;
        const blink = Math.pow(Math.max(0, Math.sin(time / 1800)), 24) * 11;
        for (const side of [-1, 1]) {
            c.beginPath(); c.moveTo(side * 19, -24 + blink); c.lineTo(side * 30, -8); c.lineTo(side * 9, -9); c.fill();
        }
        c.beginPath(); c.moveTo(-30, 14); c.quadraticCurveTo(0, 43, 30, 14); c.lineTo(18, 19); c.lineTo(13, 13); c.lineTo(5, 23); c.lineTo(-7, 22); c.lineTo(-14, 15); c.closePath(); c.fill();
    } else if (theme === 'natale') {
        c.strokeStyle = '#d1ae60'; c.lineWidth = 2; c.beginPath(); c.moveTo(0, -115); c.lineTo(0, -52); c.stroke();
        c.fillStyle = '#cba75a'; c.fillRect(-12, -55, 24, 13);
        const fill = c.createRadialGradient(-20, -20, 3, 0, 5, 59);
        fill.addColorStop(0, '#ff9caa'); fill.addColorStop(.25, '#ed4462'); fill.addColorStop(.65, '#b61636'); fill.addColorStop(1, '#610c21');
        ellipse(c, 0, 5, 48, 51, fill);
        c.strokeStyle = '#ffe3a1'; c.lineWidth = 3;
        for (const y of [-10, 6, 22]) { c.beginPath(); c.moveTo(-43, y); c.quadraticCurveTo(0, y + 26, 43, y); c.stroke(); }
        ellipse(c, -19, -18, 5, 10, '#ffffff66');
        c.strokeStyle = '#fff0cd'; c.lineWidth = 1;
        for (let i = 0; i < 5; i++) {
            c.save(); c.translate(-28 + i * 14, 28 - Math.sin(i) * 12); c.rotate(time / 7000 + i);
            c.globalAlpha = .6 + .3 * Math.sin(time / 1300 + i);
            c.beginPath(); c.moveTo(-3, 0); c.lineTo(3, 0); c.moveTo(0, -3); c.lineTo(0, 3); c.stroke(); c.restore();
        }
    } else if (theme === 'pasqua') {
        c.save(); c.rotate(-.17); c.beginPath(); c.moveTo(0, -67); c.bezierCurveTo(48, -65, 76, 65, 0, 65); c.bezierCurveTo(-76, 65, -48, -65, 0, -67); c.closePath();
        const fill = c.createRadialGradient(-18,-30,4,5,10,78); fill.addColorStop(0, '#ffe5ef'); fill.addColorStop(.3, '#f4afcd'); fill.addColorStop(.7, '#ca6c99'); fill.addColorStop(1, '#763751'); c.fillStyle = fill; c.fill(); c.clip();
        c.strokeStyle = '#f7dd8a'; c.lineWidth = 5;
        for (const y of [-25, 22]) { c.beginPath(); for (let x = -60; x <= 60; x += 12) c.lineTo(x, y + (x % 24 ? 10 : 0)); c.stroke(); }
        for (let x = -26; x <= 26; x += 26) {
            c.save(); c.translate(x,5); c.rotate(time/22000);
            for(let j=0;j<5;j++) { c.rotate(Math.PI*2/5); ellipse(c,0,-4,2.5,4,'#fff5df'); }
            ellipse(c,0,0,2,'#e9c566');c.restore();
        }
        ellipse(c,-21,-34,5,13,'#ffffff55');c.restore();
    } else if (theme === 'estate') {
        c.save(); c.rotate(time / 18000); c.strokeStyle = '#e59009'; c.lineWidth = 4; c.lineCap = 'round';
        for (let i = 0; i < 12; i++) { c.rotate(Math.PI / 6); c.beginPath(); c.moveTo(0, -52); c.lineTo(0, -66); c.stroke(); } c.restore();
        ellipse(c, 0, 0, 38, 38, '#ffc33f');
        for (let j = 0; j < 3; j++) { c.beginPath(); c.moveTo(-90, 47 + j * 13); for (let x = -90; x <= 90; x++) c.lineTo(x, 47 + j * 13 + Math.sin(x / 27 + time / 1400 + j) * 5); c.strokeStyle = ['#036e83', '#009db5', '#54ccce'][j]; c.lineWidth = 5; c.stroke(); }
    }
    c.restore();
}

export function drawSeasonCorner(c, theme, time) {
    c.save();
    const colors = palettes[theme];
    if (theme === 'halloween') {
        c.strokeStyle = '#e0c9a7'; c.lineWidth = .7;
        const angles = [0, .18, .41, .66, .94, 1.23, Math.PI / 2];
        const point = (r, a) => [240 - Math.cos(a) * r, 200 - Math.sin(a) * r];
        for (const a of angles) { c.beginPath(); c.moveTo(240, 200); c.lineTo(...point(230, a)); c.stroke(); }
        for (let ring = 1; ring < 8; ring++) {
            const r = 22 + ring * 27;
            for (let j = 0; j < angles.length - 1; j++) {
                const a = angles[j], b = angles[j + 1];
                const tension = Math.sin(time / 1700 + ring * .4 + j * .5) * 3;
                c.beginPath(); c.moveTo(...point(r, a));
                c.quadraticCurveTo(...point(r - 12 + tension, (a + b) / 2), ...point(r + Math.sin(j * 2) * 3, b)); c.stroke();
            }
        }
    } else if (theme === 'natale') {
        c.strokeStyle = colors[1]; c.lineWidth = 2; c.beginPath(); c.moveTo(10, 180); c.quadraticCurveTo(180, 220, 225, 20); c.stroke();
        for (let i = 0; i < 9; i++) { const x = 45 + i * 20, y = 185 - i * i * 1.8; drawChristmasBulb(c, x, y, i, time); }
    } else if (theme === 'pasqua') {
        drawEasterBranch(c,time);
    } else {
        for (let j = 0; j < 3; j++) { c.beginPath(); for (let x = 0; x <= 240; x++) c.lineTo(x, 168 + j * 12 + Math.sin(x / 25 + time / 1400) * 6); c.strokeStyle = colors[j]; c.lineWidth = 3; c.stroke(); }
    }
    c.restore();
}

export function drawSeasonEffect(c, theme, progress, width, height, stars = []) {
    const colors = palettes[theme];
    if (!colors || progress <= 0 || progress >= 1) return;
    c.save();
    if (theme === 'natale') {
        drawChristmasStars(c, progress, width, height, stars);
    } else if (theme === 'pasqua') {
        drawEasterEffect(c,progress,width,height,stars);
    } else if (theme === 'halloween') {
        const radius = Math.sin(progress * Math.PI) * Math.min(width * .5, 420);
        c.globalAlpha = Math.sin(progress * Math.PI) * .6; c.strokeStyle = '#d66e29'; c.lineWidth = 1;
        for (const flip of [false, true]) { c.save(); if (flip) { c.translate(width, height); c.rotate(Math.PI); }
            const point = (r, a) => [Math.cos(a) * r, Math.sin(a) * r];
            for (let j = 0; j <= 6; j++) { c.beginPath(); c.moveTo(0, 0); c.lineTo(...point(radius, j * Math.PI / 12)); c.stroke(); }
            for (let r = 24; r < radius; r += 32) for (let j = 0; j < 6; j++) { c.beginPath(); c.moveTo(...point(r, j * Math.PI / 12)); c.quadraticCurveTo(...point(r - 12, (j + .5) * Math.PI / 12), ...point(r, (j + 1) * Math.PI / 12)); c.stroke(); }
            c.restore();
        }
    } else {
        for (let i = 0; i < 40; i++) { const p = Math.max(0, Math.min(1, (progress - i % 4 * .025) / .9)); c.save(); c.translate(width * i / 39 + Math.sin(p * 6 + i) * 30, -30 + p * (height + 60)); c.rotate(i + p * 4); c.globalAlpha = Math.sin(p * Math.PI) * .8;
            c.fillStyle = colors[i % 3]; c.fillRect(-3, -7, 6, 14);
            c.restore();
        }
    }
    c.restore();
}
