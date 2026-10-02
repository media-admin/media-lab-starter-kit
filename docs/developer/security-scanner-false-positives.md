# Security Scanner: False Positives pflegen

Gilt für `inc/class-mla-security-scanner.php` (ab `media-lab-agency-core` 1.28.0).
Admin-Seite: **WP-Admin → Security Scan**.

## Warum es False Positives gibt

Die Code-Muster sind bewusst allgemein gehalten. Das Muster `chr_concat_chain`
(6 oder mehr `chr(..).`-Aufrufe hintereinander) wird von Malware zum Verschleiern
von Funktionsnamen genutzt, aber auch von PDF-Libraries (dompdf, TCPDF), die damit
Binär- und Steuerzeichen-Strings bauen. Solche Treffer liegen typischerweise in
`vendor/`-Ordnern von Plugins.

## Drei Ebenen, in dieser Reihenfolge

1. **Manuelle Whitelist** (Button „Als False Positive markieren")
   - Speichert den MD5-Hash des Dateiinhalts in der Option `mla_security_scan_whitelist`.
   - Gilt nur für diese eine Seite (Datenbank der Installation) und nur für exakt
     diesen Inhalt. Ändert sich die Datei (Plugin-Update oder Manipulation),
     wird sie erneut gemeldet.
2. **Pfad-Ausnahmen pro Muster** (`$pattern_path_excludes`)
   - Für Libraries ohne öffentliche Checksummen, z. B. dompdf in eigenen Plugins.
   - Gilt für alle Seiten, sobald `media-lab-agency-core` aktualisiert ist.
   - Betrifft nur das jeweilige Muster. Alle anderen Muster prüfen den Ordner weiter.
3. **Abgleich mit offiziellen Plugin-Checksummen**
   - Bei einem Treffer in `wp-content/plugins/<slug>/…` holt der Scanner die
     Checksummen von `downloads.wordpress.org` für die installierte Version.
     Stimmt der MD5 der Datei überein, ist es das Original und es gibt keinen Fund.
   - Greift automatisch für alle .org-Plugins (z. B. GiveWP), ohne Pflege.
   - Nicht abgedeckt: Themes, Premium-Plugins und eigene Plugins ohne .org-Eintrag.

## Neue Library ausnehmen

Per Filter, z. B. im Projekt-Plugin oder in `functions.php`:

```php
add_filter( 'mla_security_scan_pattern_excludes', function ( $excludes ) {
	$excludes['chr_concat_chain'][] = '#/plugins/[^/]+/vendor/mpdf/#';
	return $excludes;
} );
```

Die Regex wird gegen den Dateipfad mit `/` als Trenner geprüft. Den Pfad möglichst
eng fassen, nie `/plugins/` oder `/uploads/` pauschal ausnehmen.

## Wann den Button klicken, wann nicht

- **Vor dem Markieren prüfen**: .org-Plugin → `wp plugin verify-checksums <slug>`.
  Composer-Library im eigenen Plugin → frische Installation aus der `composer.lock`
  und Hash-Vergleich (`shasum -a 256`).
- **Nie pauschal markieren**: Treffer in `uploads/`, im Theme oder in Dateien, die
  nicht zu einer bekannten Library gehören, sind bis zum Beweis des Gegenteils echt.
- Treffer in einer Datei, die nach einem Plugin-Update erneut auftaucht, sind
  normalerweise unkritisch, sollten aber einmal gegen die Checksumme geprüft werden.

## Test nach Änderungen am Scanner

Mit abgeschalteten Pfad-Ausnahmen prüfen, dass der Checksum-Abgleich greift
(nur lokal, mit WP-CLI):

```
wp eval 'add_filter("mla_security_scan_pattern_excludes","__return_empty_array"); print_r(array_column(MLA_Security_Scanner::instance()->scan_suspicious_patterns(WP_CONTENT_DIR),"file"));' --path=cms
```

- Unveränderte GiveWP-Dateien dürfen **nicht** erscheinen.
- Eine absichtlich geänderte Kopie einer .org-Plugin-Datei **muss** erscheinen
  (Original danach wiederherstellen).
- Eigene Plugins mit `vendor/dompdf` erscheinen hier erwartungsgemäß, weil es
  dafür keine öffentlichen Checksummen gibt.
