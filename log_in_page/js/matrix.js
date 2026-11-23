
(() => {
  const canvas = document.querySelector('.matrix-bg');

  if (!canvas) return;

  const ctx = canvas.getContext('2d');

  const characters = '0123456789';
  const fontSize = 25;

  let columns = 0;
  let drops = [];
  let animationId;

  function resizeCanvas() {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);

    canvas.width = window.innerWidth * dpr;
    canvas.height = window.innerHeight * dpr;

    canvas.style.width = window.innerWidth + 'px';
    canvas.style.height = window.innerHeight + 'px';

    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    columns = Math.ceil(window.innerWidth / fontSize);

    drops = Array.from(
      { length: columns },
      () => Math.random() * -100
    );
  }

  function drawMatrix() {
    ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);

    ctx.font = `${fontSize}px monospace`;

    for (let i = 0; i < drops.length; i++) {
      const number = characters[
        Math.floor(Math.random() * characters.length)
      ];

      const x = i * fontSize;
      const y = drops[i] * fontSize;

      ctx.fillStyle = 'rgba(103, 195, 238, 0.45)';
      ctx.fillText(number, x, y);

      if (y > window.innerHeight && Math.random() > 0.975) {
        drops[i] = 0;
      }

      drops[i] += 0.12;
    }

    animationId = requestAnimationFrame(drawMatrix);
  }

  resizeCanvas();

  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    drawMatrix();
  }

  window.addEventListener('resize', resizeCanvas);

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      cancelAnimationFrame(animationId);
    } else if (
      !window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
      cancelAnimationFrame(animationId);
      drawMatrix();
    }
  });
})();
