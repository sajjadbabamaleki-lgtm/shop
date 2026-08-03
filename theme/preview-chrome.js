/* Theme switching for the preview.
   All three palettes ship as <style data-theme> blocks; switching toggles
   their `disabled` flag, so no stylesheet is re-parsed and the swap is
   instant even on a page this size. */
(function () {
  // The Artifact wrapper owns <html>, so direction is set at runtime.
  document.documentElement.setAttribute('dir', 'rtl');
  document.documentElement.setAttribute('lang', 'fa');

  var NOTES = {
    minimal: 'گوشه تیز · بدون سایه · فاصله باز',
    boutique: 'زمینه کرم · گوشه نرم · سایه ملایم',
    contrast: 'کادر مشکی · تایپ درشت · فشرده',
  };

  var sheets = {};
  document.querySelectorAll('style[data-theme]').forEach(function (el) {
    sheets[el.dataset.theme] = el;
  });

  var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-pick]'));
  var note = document.getElementById('vp-note');

  function apply(name) {
    Object.keys(sheets).forEach(function (key) {
      sheets[key].disabled = key !== name;
    });
    buttons.forEach(function (b) {
      b.setAttribute('aria-checked', String(b.dataset.pick === name));
    });
    note.textContent = NOTES[name] || '';

    // Sliders measure themselves on init; a palette that changes card padding
    // or border width leaves them a few pixels out until they remeasure.
    if (window.Swiper && window.Swiper.instances) return;
    document.querySelectorAll('.swiper').forEach(function (el) {
      if (el.swiper) el.swiper.update();
    });
  }

  buttons.forEach(function (b) {
    b.addEventListener('click', function () {
      apply(b.dataset.pick);
    });
  });

  // Left/right arrows move between options, as a radiogroup should.
  document.querySelector('.vp-bar__switch').addEventListener('keydown', function (e) {
    var i = buttons.indexOf(document.activeElement);
    if (i < 0) return;
    var next = e.key === 'ArrowLeft' ? i + 1 : e.key === 'ArrowRight' ? i - 1 : -1;
    if (next < 0 || next >= buttons.length) return;
    e.preventDefault();
    buttons[next].focus();
    apply(buttons[next].dataset.pick);
  });

  apply('minimal');
})();
