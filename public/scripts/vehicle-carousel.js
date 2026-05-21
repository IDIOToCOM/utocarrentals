(function () {
  var viewport = document.querySelector('[data-vehicle-carousel-viewport]');
  var track = document.querySelector('[data-vehicle-carousel-track]');
  if (!viewport || !track) return;

  var slides = track.querySelectorAll('[data-vehicle-carousel-slide]');
  if (slides.length === 0) return;

  var prevBtn = document.querySelector('[data-vehicle-carousel-prev]');
  var nextBtn = document.querySelector('[data-vehicle-carousel-next]');
  var dotsHost = document.querySelector('[data-vehicle-carousel-dots]');
  var index = 0;
  var timer = null;
  var autoplayMs = 6000;
  var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function goTo(i) {
    index = (i + slides.length) % slides.length;
    track.style.transform = 'translateX(-' + index * 100 + '%)';
    if (dotsHost) {
      dotsHost.querySelectorAll('.lp-catalog-spotlight__dot').forEach(function (dot, d) {
        dot.classList.toggle('is-active', d === index);
        dot.setAttribute('aria-selected', d === index ? 'true' : 'false');
      });
    }
    if (prevBtn) prevBtn.disabled = slides.length <= 1;
    if (nextBtn) nextBtn.disabled = slides.length <= 1;
  }

  if (dotsHost) {
    slides.forEach(function (_, i) {
      var dot = document.createElement('button');
      dot.type = 'button';
      dot.className = 'lp-catalog-spotlight__dot' + (i === 0 ? ' is-active' : '');
      dot.setAttribute('role', 'tab');
      dot.setAttribute('aria-label', 'Slide ' + (i + 1));
      dot.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
      dot.addEventListener('click', function (e) {
        e.preventDefault();
        stopAutoplay();
        goTo(i);
        startAutoplay();
      });
      dotsHost.appendChild(dot);
    });
  }

  function stopAutoplay() {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  }

  function startAutoplay() {
    if (reducedMotion || slides.length <= 1) return;
    stopAutoplay();
    timer = setInterval(function () {
      goTo(index + 1);
    }, autoplayMs);
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', function () {
      stopAutoplay();
      goTo(index - 1);
      startAutoplay();
    });
  }
  if (nextBtn) {
    nextBtn.addEventListener('click', function () {
      stopAutoplay();
      goTo(index + 1);
      startAutoplay();
    });
  }

  viewport.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowLeft') {
      e.preventDefault();
      stopAutoplay();
      goTo(index - 1);
      startAutoplay();
    } else if (e.key === 'ArrowRight') {
      e.preventDefault();
      stopAutoplay();
      goTo(index + 1);
      startAutoplay();
    }
  });

  viewport.addEventListener('mouseenter', stopAutoplay);
  viewport.addEventListener('mouseleave', startAutoplay);

  var touchStartX = 0;
  viewport.addEventListener('touchstart', function (e) {
    touchStartX = e.changedTouches[0].screenX;
  }, { passive: true });
  viewport.addEventListener('touchend', function (e) {
    var dx = e.changedTouches[0].screenX - touchStartX;
    if (Math.abs(dx) < 40) return;
    stopAutoplay();
    goTo(dx < 0 ? index + 1 : index - 1);
    startAutoplay();
  }, { passive: true });

  goTo(0);
  startAutoplay();
})();
