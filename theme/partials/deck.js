/* Hero deck.
   Assigns each card a slot relative to the active one and lets CSS place it.
   Advancing means moving the front card off to one side while its neighbour
   rises into its place; the slot arithmetic wraps so the deck is endless. */
(function () {
  var deck = document.querySelector('[data-deck]');
  if (!deck) return;

  var cards = Array.prototype.slice.call(deck.querySelectorAll('.vp-deck__card'));
  if (cards.length < 2) return;

  var dotsHost = deck.querySelector('.vp-deck__dots');
  var active = Math.floor(cards.length / 2);
  var timer = null;
  var DELAY = 4600;

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

  function layout() {
    var n = cards.length;
    cards.forEach(function (card, i) {
      // Shortest signed distance from the active card, wrapped both ways.
      var d = i - active;
      if (d > n / 2) d -= n;
      if (d < -n / 2) d += n;
      card.dataset.slot = Math.abs(d) <= 2 ? String(d) : 'out';
      card.setAttribute('aria-hidden', d === 0 ? 'false' : 'true');
    });

    Array.prototype.forEach.call(dotsHost.children, function (dot, i) {
      dot.setAttribute('aria-selected', String(i === active));
      dot.setAttribute('tabindex', i === active ? '0' : '-1');
    });
  }

  function go(next) {
    active = (next + cards.length) % cards.length;
    layout();
  }

  // Dots are built here rather than in the markup: without script there is no
  // deck to step through, so controls for it would be dead chrome.
  cards.forEach(function (_, i) {
    var dot = document.createElement('button');
    dot.type = 'button';
    dot.className = 'vp-deck__dot';
    dot.setAttribute('role', 'tab');
    dot.setAttribute('aria-label', 'تصویر ' + (i + 1).toLocaleString('fa-IR'));
    dot.addEventListener('click', function () { go(i); restart(); });
    dotsHost.appendChild(dot);
  });

  deck.querySelector('[data-deck-next]').addEventListener('click', function () { go(active + 1); restart(); });
  deck.querySelector('[data-deck-prev]').addEventListener('click', function () { go(active - 1); restart(); });

  // Clicking a card that is behind brings it forward instead of following its
  // link — the link only applies once the card is the front one.
  cards.forEach(function (card, i) {
    card.addEventListener('click', function (e) {
      if (card.dataset.slot === '0') return;
      e.preventDefault();
      go(i);
      restart();
    });
  });

  // The deck is a horizontal arrangement, so arrow keys follow the screen:
  // ArrowRight always moves the fan rightward, in either text direction.
  deck.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowRight') { e.preventDefault(); go(active + 1); restart(); }
    else if (e.key === 'ArrowLeft') { e.preventDefault(); go(active - 1); restart(); }
  });

  function start() {
    if (reduced.matches) return;
    timer = window.setInterval(function () { go(active + 1); }, DELAY);
  }
  function stop() { window.clearInterval(timer); timer = null; }
  function restart() { stop(); start(); }

  // Auto-advance pauses while the reader is engaged, or the tab is hidden.
  deck.addEventListener('mouseenter', stop);
  deck.addEventListener('mouseleave', start);
  deck.addEventListener('focusin', stop);
  deck.addEventListener('focusout', start);
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) stop(); else start();
  });
  reduced.addEventListener('change', function () { reduced.matches ? stop() : restart(); });

  layout();
  start();
})();
