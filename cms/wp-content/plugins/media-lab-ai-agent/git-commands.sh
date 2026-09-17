#!/bin/bash
set -e

# Ausführen aus dem media-lab-ai-agent Plugin-Verzeichnis.
# Robust gegen beide Ausgangslagen: falls hier schon ein Git-Repo liegt
# (z.B. vom allerersten git-commands.sh), wird einfach ein neuer Commit
# obendrauf gesetzt. Falls nicht, wird das Repo hier neu initialisiert.

if [ ! -d .git ]; then
  echo "Kein Git-Repo gefunden — initialisiere neu."
  git init
fi

git add -A

git commit -m "feat(ai-agent): v1.6.1 — RAG, Datei-Indexierung, natives PDF-Verstehen, PDF-Splitting

Kompletter Funktionsstand nach intensiver Entwicklungs- und Testrunde,
inklusive mehrerer während echter Tests mit Kundendokumenten gefundener
und behobener Bugs. Zusammengefasst (Details siehe CHANGELOG.md):

Basis (Chat-Kern):
- Provider-Abstraktion (Anthropic/OpenAI), Anbieterwechsel per ACF-Option
- AES-256-GCM-Verschluesselung der API-Keys, REST-Endpoint mit
  Rate-Limiting und Tages-Budget-Kill-Switch
- Mehrsprachige System-Prompts, Consent-Gating im Frontend-Widget
- Kosten-Dashboard (Admin-Seite + WP-Dashboard-Widget)

RAG-Modul:
- Embedding-basierte Website-Wissenssuche (OpenAI Embeddings),
  Quellenverweise in Antworten, konfigurierbarer Aehnlichkeits-Schwellwert
- Datei-Indexierung: PDF/PPTX/DOCX aus der Mediathek, dependency-freier
  Extraktor (WinAnsi/UTF-8-Bereinigung, Performance-Guard fuer grosse
  Bild-/Font-Streams, robuste CR/LF/CRLF-Erkennung)
- ACF-Feldgruppe fuer Produkt-Dokumente (Datenblaetter/Manuals/Spec
  Sheets), automatische Produkt-zu-Dokument-Verknuepfung im RAG-Kontext

Natives PDF-Verstehen (Formeln, Diagramme, Tabellen):
- Zweistufiges RAG: Text-Suche findet das Dokument, Original-PDF wird
  zusaetzlich nativ an Claude mitgeschickt (Anthropic Messages API
  document-Content-Block) statt nur linearisierten Text zu nutzen
- PDF-Seiten-Splitting fuer Dokumente ueber Anthropics 100-Seiten-Limit
  (setasign/fpdi + fpdf, lokal gebaut, vendor/ committed statt
  Server-Composer), bestes Fenster wird pro Anfrage gezielt ermittelt
- Ghostscript-Normalisierung als kostenloser Fallback fuer PDFs mit
  komprimierten Cross-Reference-Tabellen (kommerzielle FPDI-Erweiterung
  war ohne Budget keine Option) — verifiziert auf Hetzner-Shared-Hosting
  und lokal (Ghostscript 10.08.0, -dCompatibilityLevel=1.4 noetig, da
  Ghostscript ab 10.03 selbst wieder komprimiert)

Betriebsfestigkeit:
- ACF-PRO-Pruefung mit sichtbarem Admin-Hinweis statt stillem Fehlschlag
- dbDelta-kompatible Tabellen-Definitionen (doppelter PRIMARY KEY behoben)
- \$wpdb->insert()-Fehler werden jetzt geprueft und geloggt statt
  stillschweigend Chunks zu verlieren
- Automatischer Fallback auf Text-Kontext bei jedem Fehlschlag der
  nativen PDF-Anbindung (Seitenlimit, Verschluesselung, etc.)"

git tag -a "media-lab-ai-agent-v1.6.1" -m "media-lab-ai-agent v1.6.1 - RAG, Datei-Indexierung, natives PDF-Verstehen, PDF-Splitting mit Ghostscript-Fallback"

# git push origin main
# git push origin media-lab-ai-agent-v1.6.1
