/*
 * binary-rain.js — the moving 0s and 1s in the background.
 * Draws on <canvas id="binary-bg">. The lock itself is a normal image, so it never moves.
 *
 * Tweak the look here:
 */
var BINARY_RAIN = {
  color:      '165, 208, 242',   // normal digits (r, g, b)
  pulseColor: '45, 212, 191',    // bright streaks (teal)
  fps:        30,                // lower = lighter on the computer
  speedMin:   6,                 // slowest column (pixels per second)
  speedMax:   30                 // fastest column
};

(function () {
  var canvas = document.getElementById('binary-bg');
  if (!canvas || !canvas.getContext) return;

  var cfg = BINARY_RAIN;
  var ctx = canvas.getContext('2d');
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var W = 0, H = 0, cellW = 22, cellH = 24, cols = 0, rows = 0;
  var speed = [], scroll = [], baseAlpha = [], seed = [], pulses = [];
  var last = 0, running = true, frameGap = 1000 / cfg.fps;

  function rand(a, b) { return a + Math.random() * (b - a); }

  function resize() {
    var dpr = Math.min(window.devicePixelRatio || 1, 1.5);
    W = window.innerWidth;
    H = window.innerHeight;
    canvas.width  = Math.round(W * dpr);
    canvas.height = Math.round(H * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    var small = W < 700;
    cellW = small ? 26 : 22;
    cellH = small ? 28 : 24;
    cols  = Math.ceil(W / cellW) + 1;
    rows  = Math.ceil(H / cellH) + 2;

    speed = []; scroll = []; baseAlpha = []; seed = [];
    for (var i = 0; i < cols; i++) {
      speed.push(rand(cfg.speedMin, cfg.speedMax));
      scroll.push(rand(0, cellH * 40));
      baseAlpha.push(rand(0.14, 0.34));
      seed.push((Math.random() * 1e9) | 0);
    }

    pulses = [];
    var n = Math.max(4, Math.round(cols / 9));
    for (var p = 0; p < n; p++) pulses.push(newPulse(true));

    ctx.font = (small ? 13 : 14) + 'px "Courier New", ui-monospace, monospace';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
  }

  function newPulse(anywhere) {
    return {
      col:   (Math.random() * cols) | 0,
      y:     anywhere ? rand(-H, H) : rand(-300, 0),
      speed: rand(110, 260),
      len:   (rand(7, 14)) | 0
    };
  }

  /* the same row always gets the same digit; a few digits flip over time */
  function bit(col, rowAbs, bucket) {
    var h = (col * 73856093) ^ (rowAbs * 19349663) ^ seed[col];
    h = Math.imul(h ^ (h >>> 13), 1274126177);
    h ^= h >>> 16;
    if (((h >>> 4) % 12) === 0) h ^= bucket;     // ~8% of digits flip every few seconds
    return (h & 1) ? '1' : '0';
  }

  function draw(now, dt) {
    ctx.clearRect(0, 0, W, H);

    var bucket = Math.floor(now / 2500);
    var t = now / 2200;

    /* 1) the sliding columns of dim digits */
    ctx.fillStyle = 'rgb(' + cfg.color + ')';
    for (var c = 0; c < cols; c++) {
      scroll[c] += speed[c] * dt;
      var shift  = scroll[c] % cellH;
      var rowOff = Math.floor(scroll[c] / cellH);
      var x = c * cellW + cellW / 2;

      for (var r = -1; r < rows; r++) {
        var y = r * cellH + shift;
        var shimmer = 0.62 + 0.38 * Math.sin(t + c * 0.35 + r * 0.12);
        ctx.globalAlpha = baseAlpha[c] * shimmer;
        ctx.fillText(bit(c, r - rowOff, bucket), x, y);
      }
    }

    /* 2) a few bright streaks that run down the screen */
    ctx.fillStyle = 'rgb(' + cfg.pulseColor + ')';
    for (var p = 0; p < pulses.length; p++) {
      var pu = pulses[p];
      pu.y += pu.speed * dt;

      for (var k = 0; k < pu.len; k++) {
        var py = pu.y - k * cellH;
        if (py < -cellH || py > H + cellH) continue;
        ctx.globalAlpha = (1 - k / pu.len) * (k === 0 ? 0.95 : 0.6);
        ctx.fillText(bit(pu.col, Math.floor(py / cellH), bucket), pu.col * cellW + cellW / 2, py);
      }

      if (pu.y - pu.len * cellH > H) pulses[p] = newPulse(false);
    }
    ctx.globalAlpha = 1;
  }

  function loop(now) {
    if (!running) return;
    requestAnimationFrame(loop);
    if (now - last < frameGap) return;
    var dt = Math.min((now - last) / 1000, 0.1);
    last = now;
    draw(now, dt);
  }

  resize();
  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () { resize(); if (reduceMotion) draw(0, 0); }, 150);
  });

  if (reduceMotion) {            // people who asked for less motion get one still picture
    draw(0, 0);
    return;
  }

  document.addEventListener('visibilitychange', function () {   // pause when the tab is hidden
    running = !document.hidden;
    if (running) { last = performance.now(); requestAnimationFrame(loop); }
  });

  requestAnimationFrame(function (t) { last = t; loop(t); });
})();