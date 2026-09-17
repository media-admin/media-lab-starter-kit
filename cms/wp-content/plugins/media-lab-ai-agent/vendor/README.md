# vendor/ — manuell gebaut, nicht via Composer

Dieser Ordner enthält [FPDI](https://github.com/Setasign/FPDI) und
[FPDF](https://github.com/Setasign/FPDF) (beide MIT-lizenziert), genutzt für
PDF-Seiten-Splitting (siehe `inc/rag/class-ai-agent-pdf-splitter.php`).

**Warum kein `composer.json` / `composer install`?** Das Plugin wird per
SFTP-Directory-Replace auf Shared-Hosting ohne SSH/Composer deployt. Der
Ordner wurde deshalb einmalig lokal aus den offiziellen GitHub-Repos gebaut
und ins Repository committed — Bytes-identisch zu den Originalquellen,
keine eigenen Änderungen.

## Struktur

```
vendor/
├── autoload.php              # Handgebauter Ersatz für Composers Autoloader
├── setasign/fpdf/fpdf.php    # Basis-PDF-Bibliothek (Olivier Plathey)
└── setasign/fpdi/src/        # Seiten-Import (Setasign GmbH & Co. KG)
```

`vendor/autoload.php` bindet zuerst `fpdf.php` ein (globale `\FPDF`-Klasse,
von der `setasign\Fpdi\FpdfTpl` erbt), danach FPDIs eigenes mitgeliefertes
`src/autoload.php` (PSR-4-artiger `spl_autoload_register()`-Callback für
den Namespace `setasign\Fpdi\`).

## Neu aufbauen (falls nötig)

```bash
git clone --depth 1 https://github.com/Setasign/FPDI.git fpdi-src
git clone --depth 1 https://github.com/Setasign/FPDF.git fpdf-src

mkdir -p vendor/setasign/fpdi vendor/setasign/fpdf
cp -r fpdi-src/src vendor/setasign/fpdi/src
cp fpdi-src/LICENSE.txt vendor/setasign/fpdi/LICENSE.txt
cp fpdf-src/fpdf.php vendor/setasign/fpdf/fpdf.php
cp fpdf-src/license.txt vendor/setasign/fpdf/LICENSE.txt
```

`vendor/autoload.php` selbst bleibt unverändert (ist nicht Teil der
geklonten Repos, sondern von uns geschrieben).

## Lizenzen

Beide Bibliotheken sind MIT-lizenziert — uneingeschränkte kommerzielle
Nutzung, siehe jeweilige `LICENSE.txt`. FPDI in der hier genutzten
kostenlosen Community-Version unterstützt PDFs bis Version 1.7 ohne
Verschlüsselung; die kommerzielle FPDI-Variante (nicht genutzt) würde
zusätzlich PDF 2.0 und verschlüsselte PDFs abdecken.
