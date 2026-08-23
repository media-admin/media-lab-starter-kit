# Changelog — Custom Theme

Alle wesentlichen Änderungen werden in dieser Datei dokumentiert.
Format: [Keep a Changelog](https://keepachangelog.com/de/1.0.0/)
Versionierung: [Semantic Versioning](https://semver.org/)

**Hinweis zur Vollständigkeit:** Diese Datei existiert seit 22.08.2026.
Ältere Versionshistorie (vor `1.15.3`) wurde **bewusst nicht
rekonstruiert** (siehe `docs/BACKLOG.md`, „Struktur/Prozess" — anders als
bei `media-lab-agency-core` wurde hier keine aufwendige Git-Log-/Tag-
Auswertung gemacht). Bei Bedarf nachträglich über
`git log --follow -- cms/wp-content/themes/custom-theme/style.css`
möglich.

---

## [1.15.4] - 2026-08-22

### Added
- **Interne README.md komplett überarbeitet** — stand seit dem
  allerersten Release unverändert auf Version `1.0.0`
  (`style.css` war längst bei `1.15.x`). Neu: echte Requirements
  (WP 6.0+/PHP 8.0+ statt veralteter 5.9+/7.4+), echte Design-Tokens
  (`$color-primary: #e00000` statt der nie zutreffenden Platzhalter
  `#667eea`/`#764ba2`), repräsentative JS-Component-Liste, Verweise auf
  `docs/06_DEVELOPMENT.md` statt Duplikation. Richtigstellung: Es gibt
  **keinen** klassischen WordPress-Customizer für dieses Theme (kein
  `customize_register()`-Hook) — die alte README hatte fälschlich
  „Configure in Customizer" behauptet. Tatsächliche Anpassung läuft über
  SCSS-Tokens (Build-Zeit) bzw. ACF-Options-Seiten aus
  `media-lab-agency-core` (Laufzeit).

### Fixed
- **`CUSTOM_THEME_VERSION` war von `style.css` entkoppelt** (`functions.php`)
  – Konstante stand hartcodiert auf eingefrorenem `'1.4.0'`, während
  `style.css` (die für WordPress maßgebliche Versionsnummer) längst bei
  `1.15.3` stand. Praktisch folgenlos (nur Cache-Busting-Dekoration für
  das Haupt-JS, Vite nutzt ohnehin Content-Hashes im Dateinamen), aber
  irreführend beim Debuggen. Jetzt dynamisch über
  `wp_get_theme()->get('Version')` gezogen — kann nicht mehr aus dem
  Takt geraten.

---

## [1.15.3] - 2026-08-22

### Fixed
- **Modal-Komponente ließ sich nicht öffnen** (`assets/src/scss/components/_modal.scss`)
  – CSS zeigte das Modal nur bei Klasse `.is-active`, `modal.js` setzte
  aber konsequent `.is-open` (Öffnen, Schließen, ESC-Taste-Handler).
  Klick auf einen Trigger löste zwar korrekt `openModal()` aus, aber die
  Sichtbarkeits-Regel griff nie. Betraf jede Nutzung von
  `[modal_trigger]`/`[modal]` unabhängig vom Inhalt. SCSS-Selektor von
  `.is-active` auf `.is-open` umbenannt.

### Documentation
- **CF7-Layout-Helfer-Klassennamen präzisiert** (`docs/06_DEVELOPMENT.md`)
  – ein Formular nutzte `cf7-two-columns`/`cf7-full-width` statt der
  tatsächlich in `_contact-form-7.scss` definierten
  `cf7-grid-2`/`cf7-full`. Da die falschen Klassennamen im kompilierten
  CSS nicht existieren, fiel das Formular lautlos auf 1-spaltiges
  Block-Layout zurück (kein Fehler, keine Warnung). Warnhinweis mit den
  vier tatsächlich gültigen Klassennamen ergänzt.
