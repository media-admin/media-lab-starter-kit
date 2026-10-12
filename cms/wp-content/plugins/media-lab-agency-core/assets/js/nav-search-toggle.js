/**
 * Nav Search Overlay – Toggle
 *
 * Öffnet/schließt #mlc-search-overlay (siehe inc/nav-search-icon.php).
 * Rührt die eigentliche Such-Logik (.ajax-search__*) NICHT an – dafür ist
 * ausschließlich ajax-search.js (Theme) zuständig, das sich selbstständig
 * für jedes .ajax-search-Element im DOM initialisiert.
 *
 * Trigger:
 *   - .mlc-nav-search-toggle  Menü-Eintrag aus dem Plugin (Desktop UND Mobile-Menü)
 *   - [data-search-toggle]    eigener Button im Theme (z. B. Header-Icons), wenn der
 *                             Menü-Eintrag abgeschaltet ist
 *                             (Agency Core -> Suche -> Allgemein -> "Kein Menü-Eintrag")
 */
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var overlay = document.getElementById('mlc-search-overlay');
    var toggles = document.querySelectorAll('.mlc-nav-search-toggle, [data-search-toggle]');
    if (!overlay || !toggles.length) return;

    var closeBtn = overlay.querySelector('.mlc-search-overlay__close');
    var backdrop = overlay.querySelector('.mlc-search-overlay__backdrop');
    var input    = overlay.querySelector('.ajax-search__input');
    var results  = overlay.querySelector('.ajax-search__results');
    var lastTrigger = null;

    function setExpanded(state) {
      toggles.forEach(function (t) {
        t.setAttribute('aria-expanded', state ? 'true' : 'false');
      });
    }

    function open(trigger) {
      lastTrigger = trigger || toggles[0];
      overlay.classList.add('is-open');
      overlay.setAttribute('aria-hidden', 'false');
      setExpanded(true);
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
      setExpanded(false);
      document.body.classList.remove('mlc-search-overlay-active');

      // Beim erneuten Öffnen nicht die alte Suche zeigen
      if (input) input.value = '';
      if (results) {
        results.style.display = 'none';
        results.innerHTML = '';
      }

      if (lastTrigger) lastTrigger.focus();
    }

    toggles.forEach(function (t) {
      t.addEventListener('click', function () {
        if (overlay.classList.contains('is-open')) {
          close();
        } else {
          open(t);
        }
      });
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
