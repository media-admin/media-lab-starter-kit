# Analytics Dokumentation

**Version:** 1.14.0 | **Letzte Aktualisierung:** 2026-08-13

---

## Übersicht

Analytics wird vollständig im **`media-lab-seo`-Plugin** verwaltet — sowohl das
Frontend-Tracking als auch die Backend-Dashboard-Daten.

| Was | Wo |
|---|---|
| SEO-KPIs (GSC) | `media-lab-seo` → Dashboard → immer verfügbar |
| Pageviews / Quellen (Adapter) | `media-lab-seo` → Dashboard → optional (GA4 oder Matomo) |
| Frontend-Tracking (GA4 oder GTM) | `media-lab-seo` → Einstellungen → Analytics |

---

## Frontend-Tracking (GA4 / GTM)

Das Frontend-Tracking wird über `MLT_Analytics` in `media-lab-seo` gesteuert
(`inc/class-analytics.php`). Es wird nur geladen, wenn `mlt_analytics_enabled = 1`
gesetzt ist (Bedingung in der Haupt-Plugin-Datei), und bindet innerhalb der
Klasse selbst nur ein Script ein, wenn zusätzlich eine Tracking-ID
(`mlt_analytics_id`) gesetzt ist.

**Unterstützte Provider:**
- **Google Analytics 4** — direktes `gtag.js` Tracking
- **Google Tag Manager** — GTM Container inkl. `<noscript>` Fallback

**Konfiguration:** WP-Admin → SEO Toolkit → Einstellungen

```
✅ Analytics-Tracking aktivieren
Provider:  GA4 | GTM  (Auswahl)
Tracking-ID: G-XXXXXXXXXX  (GA4)
             GTM-XXXXXXX   (GTM)
```

**Via WP-CLI:**
```bash
wp option update mlt_analytics_enabled 1
wp option update mlt_analytics_provider 'ga4'   # oder 'gtm'
wp option update mlt_analytics_id 'G-XXXXXXXXXX'
```

### Consent Mode v2

Das Tracking ist vollständig Consent-aware und integriert mit dem Agency Core Cookie Banner:

- Beim ersten Besuch: alles `denied` (GA4 Consent Mode v2 Default)
- Nach Consent: `analytics_storage: granted` (bei GA4 zusätzlich sofortiges `page_view`-Event; bei GTM wird stattdessen der GTM-Container nachgeladen)
- Wiederkehrende Besucher: Consent wird aus `localStorage` (`mlt_consent_analytics`) geladen, bevorzugt jedoch über `window.CookieConsent.hasConsent('statistics')` (Agency-Core-API) geprüft

> **Korrektur:** „Admins werden nicht getrackt" (frühere Doku-Aussage) ist
> ungenau. Der Code prüft `is_admin()` — das schließt **WP-Admin-
> Backend-Seiten** aus (`wp-admin/...`), **nicht** Admin-Nutzer, die die
> Website im Frontend als normale Besucher ansehen. Ein eingeloggter
> Admin, der die Live-Website browst, wird getrackt wie jeder andere
> Besucher.

**Event-Bridge (verifiziert gegen `class-analytics.php`):**
```
Agency Core cookies:changed { statistics: true }
  → localStorage.setItem('mlt_consent_analytics', '1')
  → CustomEvent 'mlt:consent:analytics'
    → gtag consent update (+ page_view bei GA4 / GTM-Nachladen bei GTM)
```

### Manuelles Event-Tracking

**Per JS** (funktioniert, sobald `gtag` durch das Modul initialisiert und Consent erteilt wurde):
```javascript
gtag('event', 'button_click', {
    button_name: 'Download PDF',
    button_location: 'Hero',
});
```

> **Korrektur:** Der vorher hier dokumentierte PHP-Hook
> `do_action('medialab_track_event', ...)` existiert **nicht** —
> `class-analytics.php` enthält keinen einzigen `do_action()`-Aufruf.
> Server-seitiges Event-Tracking ist mit dem aktuellen Code nicht
> vorgesehen; Events müssen client-seitig per `gtag()` (oben) ausgelöst
> werden.

---

## Analytics-Adapter im SEO-Dashboard

Pageview- und Traffic-Daten werden über einen pluggbaren Adapter (`mlt_analytics_adapter`-Filter)
in das SEO-Dashboard integriert. Der Kunde entscheidet, welcher Anbieter eingesetzt wird
(Einstellung „Provider": `ga4` oder `matomo`).

**Unterstützte Anbieter:**
- **Google Analytics 4** — primär via OAuth2 (teilt sich die Zugangsdaten mit der GSC-Anbindung, siehe [SEO-Dokumentation](13_SEO.md#google-search-console--google-analytics-4)); Service-Account-JSON ist nur noch ein Legacy-Fallback für ältere Projekte
- **Matomo** — via Reporting API (DSGVO-konform, selbst gehostet)

**Vollständige Einrichtung:** → [SEO-Dokumentation → Google Search Console & Google Analytics 4](13_SEO.md#google-search-console--google-analytics-4)

---

## Datenschutz-Einordnung

| Anbieter | DSGVO | Anmerkung |
|---|---|---|
| Google Search Console | ✅ Unproblematisch | Aggregierte Suchdaten, keine personenbezogenen Daten |
| Matomo (self-hosted) | ✅ Konform | Kein Drittland-Transfer, keine Cookies nötig |
| Google Analytics 4 | ⚠️ Mit Consent | US-Datentransfer – Consent-Banner erforderlich |
| Google Tag Manager | ⚠️ Je nach Tags | Abhängig von den eingebundenen Tags |

---

## Weiterführende Docs

- [SEO Dokumentation](13_SEO.md) — GSC Dashboard, GA4 & Matomo Adapter
- [Plugin-Übersicht](03_PLUGINS.md)
- [Deployment](10_DEPLOYMENT.md)
