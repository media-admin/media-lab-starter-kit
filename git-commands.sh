#!/bin/bash
set -e

# Ausführen aus dem media-lab-ai-agent Plugin-Verzeichnis (oder dem Repo-Root,
# falls es Teil des Starter-Kit-Monorepos wird — Pfad ggf. anpassen).

git add -A

git commit -m "feat(ai-agent): initial release of standalone AI Agent plugin

Datenschutzkonformer, mehrsprachiger AI-Chat-Assistent fuer WordPress mit
austauschbarem Anbieter (Anthropic, OpenAI) und optionalem RAG-Modul.

- Provider-Abstraktion (MLT_AI_Provider_Interface), Anbieterwechsel per
  ACF-Option ohne Code-Deploy, Erweiterungspunkt mlt_ai_register_providers
  fuer client-spezifische Provider (z.B. selbst gehostete Modelle)
- AES-256-GCM-Verschluesselung der API-Keys
- REST-Endpoint mit Rate-Limiting und Tages-Budget-Kill-Switch
- RAG-Modul: Embeddings-basierte Website-Wissenssuche (OpenAI Embeddings),
  Batch-Reindexierung per WP-Cron, Quellenverweise in Antworten
- Consent-Gating im Frontend-Widget, Cookie-Name/-Wert filterbar fuer
  bestehende Consent-Tools
- Kosten-Dashboard (Admin-Seite + WP-Dashboard-Widget), Kill-Switch deckt
  Chat- und Embedding-Kosten gemeinsam ab
- Vanilla-JS-Widget ohne Build-Step, EU-AI-Act Art. 50 Transparenzhinweis,
  klickbare Quellenlinks in Antworten
- ACF PRO als Voraussetzung (Options-Pages), sichtbarer Admin-Hinweis bei
  fehlender Lizenz statt stillem Fehlschlag"

git tag -a media-lab-ai-agent-v1.2.6 -m "media-lab-ai-agent v1.2.6 - initial release"

# git push origin main
# git push origin media-lab-ai-agent-v1.2.6
