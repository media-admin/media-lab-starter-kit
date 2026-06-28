# Analytics Dokumentation

**Version:** 1.14.0 | **Letzte Aktualisierung:** 2026-06-28

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

Das Frontend-Tracking wird über `MLT_Analytics` in `media-lab-seo` gesteuert.
Es wird nur eingebunden wenn `mlt_analytics_enabled = 1` gesetzt ist.

**Unterstützte Provider:**
- **Google Analytics 4** — direktes `gtag.js` Tracking
- **Google Tag Manager** — GTM Container inkl. `<noscript>` Fallback

**Konfiguration:** WP-Admin → SEO Toolkit → Analytics

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
- Nach Consent: `analytics_storage: granted` + `page_view` Event
- Wiederkehrende Besucher: Consent wird aus `localStorage` (`mlt_consent_analytics`) geladen
- Admins werden nicht getrackt (`is_admin()` Check)

**Event-Bridge:**
```
Agency Core cookies:changed { statistics: true }
  → localStorage.setItem('mlt_consent_analytics', '1')
  → CustomEvent 'mlt:consent:analytics'
    → gtag consent update + page_view
```

### Manuelles Event-Tracking

**Per JS:**
```javascript
gtag('event', 'button_click', {
    button_name: 'Download PDF',
    button_location: 'Hero',
});
```

**Per PHP (Custom Event Hook):**
```php
do_action('medialab_track_event', 'button_click', [
    'button_name'     => 'Download PDF',
    'button_location' => 'Hero',
]);
```

---

## Analytics-Adapter im SEO-Dashboard

Pageview- und Traffic-Daten werden über einen pluggbaren Adapter in das SEO-Dashboard
integriert. Der Kunde entscheidet, welcher Anbieter eingesetzt wird.

**Unterstützte Anbieter:**
- **Google Analytics 4** — via Service Account (kein Cookie-Banner nötig für Backend-Abruf)
- **Matomo** — via Reporting API (DSGVO-konform, selbst gehostet)

**Vollständige Einrichtung:** → [SEO-Dokumentation → Analytics-Adapter](13_SEO.md#analytics-adapter)

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
