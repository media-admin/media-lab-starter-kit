/**
 * Nav Search Overlay – Toggle
 *
 * Öffnet/schließt #mlc-search-overlay (siehe inc/nav-search-icon.php).
 * Rührt die eigentliche Such-Logik (.ajax-search__*) NICHT an – dafür ist
 * ausschließlich ajax-search.js (Theme) zuständig, das sich selbstständig
 * für jedes .ajax-search-Element im DOM initialisiert.
 */
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var toggle  = document.querySelector('.mlc-nav-search-toggle');
    var overlay = document.getElementById('mlc-search-overlay');
    if (!toggle || !overlay) return;

    var closeBtn = overlay.querySelector('.mlc-search-overlay__close');
    var backdrop = overlay.querySelector('.mlc-search-overlay__backdrop');
    var input    = overlay.querySelector('.ajax-search__input');

    function open() {
      overlay.classList.add('is-open');
      overlay.setAttribute('aria-hidden', 'false');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.classList.add('mlc-search-overlay-active');

      if (input) {
        // Kurzer Timeout: Fokus erst nach der Öffnen-Transition setzen,
        // sonst „springt" der Screenreader-Fokus während der Animation.
        setTimeout(function () { input.focus(); }, 50);
      }
    }

    function close() {
      overlay.classList.remove('is-open');
      overlay.setAttribute('aria-hidden', 'true');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.classList.remove('mlc-search-overlay-active');
      toggle.focus();
    }

    toggle.addEventListener('click', function () {
      if (overlay.classList.contains('is-open')) {
        close();
      } else {
        open();
      }
    });

    if (closeBtn) closeBtn.addEventListener('click', close);
    if (backdrop) backdrop.addEventListener('click', close);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
        close();
      }
    });
  });
})();
