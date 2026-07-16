#!/bin/bash
# ============================================================================
# Git-Commands für die heutige Session
# ============================================================================
# Voraussetzung: Die Dateien aus den gelieferten ZIPs sind bereits in deinem
# lokalen Arbeitsverzeichnis übernommen (performance.php, media-lab-woocommerce
# ajax-handlers.php, alle media-lab-agency-core-Dateien, CHANGELOG.md).
#
# Reihenfolge: 5 Commits, je ein Tag – theme-fix zuerst (chronologisch
# ältester Fix), danach die 4 agency-core-Schritte in der Reihenfolge, in
# der du sie getestet hast.
# ============================================================================

set -e

# ── Schritt 0: Sync mit origin/main ────────────────────────────────────────
# Dein lokaler main hängt einen Commit hinter origin/main: der GZD/
# WooCommerce-Fix (mlwf_ajax_filter_products) ist dort schon gemerged.
# Da dein lokaler Stand für diese Datei inhaltlich bereits identisch ist,
# ist das ein sauberer Merge ohne Konflikt.
git fetch origin
git merge origin/main

# ── Commit 1: Theme-Fix (Fatal Error WebP Picture Element) ────────────────
git add cms/wp-content/themes/custom-theme/inc/performance.php

# CHANGELOG.md enthält mehrere neue Abschnitte auf einmal (alle heutigen
# Änderungen). Mit -p nur den Abschnitt "## [1.22.0]" (Theme-Fix, ganz unten
# im neuen Block, direkt über "## [1.21.1]") auswählen – bei den Hunk-Fragen
# mit 'y' bestätigen, alles andere mit 'n' überspringen.
git add -p CHANGELOG.md

git commit -m "fix(theme): TypeError in customtheme_webp_picture_element() bei WooCommerce Produkt-Edit

\$attr hatte den strikten Type-Hint 'array', WP-Core ruft
wp_get_attachment_image() aber mit Default \$attr = '' (leerer String) auf,
z.B. in WC_Meta_Box_Product_Images::output(). Führte zu TypeError, der die
Seite ab dieser Stelle abbrach. Type-Hint entfernt, Default array() ergänzt.

Version bump: starter-kit 1.21.1 -> 1.22.0"

git tag -a v1.22.0 -m "Fix: Fatal Error in customtheme_webp_picture_element()"

# ── Commit 2: Post Navigation Feature + Post Order Verbesserungen ─────────
git add cms/wp-content/plugins/media-lab-agency-core/inc/post-navigation.php
git add cms/wp-content/plugins/media-lab-agency-core/assets/css/post-navigation.css
git add cms/wp-content/plugins/media-lab-agency-core/assets/js/post-navigation.js
git add cms/wp-content/plugins/media-lab-agency-core/inc/post-order.php
git add cms/wp-content/plugins/media-lab-agency-core/assets/css/post-order.css
git add cms/wp-content/plugins/media-lab-agency-core/media-lab-agency-core.php

# CHANGELOG.md: Abschnitt "## [1.22.1]" (media-lab-agency-core 1.17.0) auswählen
git add -p CHANGELOG.md

git commit -m "feat(agency-core): Post Navigation (Prev/Next) + Post Order Verbesserungen

- Neue Post Navigation (inc/post-navigation.php): Voriger/Nächster direkt
  auf Post-/Page-/CPT- sowie Taxonomy-Term-Detailseiten. menu_order bei
  aktiver Post Order, sonst WP-Standard-Fallback. Berücksichtigt
  Filter-/Suchkontext der Ausgangs-Listenansicht (mlpn_ctx Round-Trip-Param).
  UI: klassischer Button-Bereich (edit_form_top) + Gutenberg-Sidebar-Panel.
- Post Order: 'Alle Post Types/Taxonomien aktivieren'-Toggle auf der
  Einstellungsseite.
- Post Order: Thumbnail-Spalte (column-thumb) per Flex-Layout gefixt
  (Versuch 1 - siehe Folgecommit für den finalen Fix).

Version bump: media-lab-agency-core 1.16.0 -> 1.17.0
Version bump: starter-kit 1.22.0 -> 1.22.1"

git tag -a v1.22.1 -m "Feature: Post Navigation (Prev/Next) im Backend"

# ── Commit 3: Fix Drag-Handle-Spalte (dedizierte <td> statt Flex-Hack) ─────
git add cms/wp-content/plugins/media-lab-agency-core/assets/js/post-order.js
git add cms/wp-content/plugins/media-lab-agency-core/assets/css/post-order.css
git add cms/wp-content/plugins/media-lab-agency-core/media-lab-agency-core.php

# CHANGELOG.md: Abschnitt "## [1.22.2]" (media-lab-agency-core 1.17.1) auswählen
git add -p CHANGELOG.md

git commit -m "fix(agency-core): Drag-Handle überlappt Thumbnail in schmalen Spalten

Flex-Layout innerhalb von td.column-thumb (Commit zuvor) hat nicht
zuverlässig funktioniert - Spalte ist fix/schmal, Handle + Thumbnail
haben sich weiterhin überlappt/umgebrochen (z.B. WooCommerce-
Produktübersicht). Handle bekommt jetzt eine eigene, dedizierte
<td class=\"medialab-drag-handle-cell\"> als erste Zelle jeder Zeile.
Kopf-/Fußzeile werden per JS um eine passende leere <th> ergänzt.

Version bump: media-lab-agency-core 1.17.0 -> 1.17.1
Version bump: starter-kit 1.22.1 -> 1.22.2"

git tag -a v1.22.2 -m "Fix: Drag-Handle eigene Tabellenspalte statt Flex-Hack"

# ── Commit 4: Post Navigation - Gutenberg Toolbar-Icon ─────────────────────
git add cms/wp-content/plugins/media-lab-agency-core/assets/js/post-navigation.js
git add cms/wp-content/plugins/media-lab-agency-core/media-lab-agency-core.php

# CHANGELOG.md: Abschnitt "## [1.22.3]" (media-lab-agency-core 1.17.2) auswählen
git add -p CHANGELOG.md

git commit -m "fix(agency-core): Post Navigation nicht sichtbar im Block Editor

edit_form_top gehört zum Classic-Editor-Template (edit-form-advanced.php)
und rendert im Block Editor nicht mit. Von PluginDocumentSettingPanel auf
PluginSidebar umgestellt: eigenes, dauerhaft sichtbares Icon in der
Editor-Toolbar statt versteckt im Dokument-Tab.

Version bump: media-lab-agency-core 1.17.1 -> 1.17.2
Version bump: starter-kit 1.22.2 -> 1.22.3"

git tag -a v1.22.3 -m "Fix: Post Navigation Toolbar-Icon im Block Editor"

# ── Commit 5: Post Navigation - paralleles Dokument-Panel ──────────────────
git add cms/wp-content/plugins/media-lab-agency-core/assets/js/post-navigation.js
git add cms/wp-content/plugins/media-lab-agency-core/media-lab-agency-core.php

# CHANGELOG.md: Abschnitt "## [1.22.4]" (media-lab-agency-core 1.17.3) auswählen
git add -p CHANGELOG.md

git commit -m "feat(agency-core): Post Navigation zusätzlich als Dokument-Panel

PluginDocumentSettingPanel wieder ergänzt, parallel zum PluginSidebar-
Toolbar-Icon aus dem letzten Commit. Beide SlotFills sind unabhängige
Registrierungen ohne Konflikt.

Version bump: media-lab-agency-core 1.17.2 -> 1.17.3
Version bump: starter-kit 1.22.3 -> 1.22.4"

git tag -a v1.22.4 -m "Feature: Post Navigation paralleles Dokument-Panel"

# ── Alles prüfen, dann pushen ───────────────────────────────────────────────
git log --oneline -6
git status

# git push origin main --follow-tags
