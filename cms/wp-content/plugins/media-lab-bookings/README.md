# Media Lab Bookings

Buchungssystem für WordPress mit Standortverwaltung, Öffnungszeiten,
Zeitslots, Kapazitätslimits und standortspezifischen E-Mail-Bestätigungen.
Läuft komplett ohne WooCommerce oder externe Buchungs-Dienste.

Entwickelt von [Media Lab Tritremmel GmbH](https://media-lab.at).

---

## Features

- **Standortverwaltung** (CPT `mlb_location`) – beliebig viele Standorte, je eigene Öffnungszeiten, Zeitslots, Kapazitätslimits und Formular-Labels
- **Buchungsformular** (Shortcode `[mlb_booking_form]`) – Standort/Datum/Uhrzeit/Dienstleistung/Personenanzahl/Kontaktdaten, DSGVO-Checkbox, Honeypot-Spam-Schutz (via `media-lab-agency-core`)
- **Zeitslot-Logik** – konfigurierbare Slot-Länge, Kapazität pro Slot, maximale Buchungen pro Tag
- **Blockierte Zeiträume pro Standort** – einzelne Tage oder Zeiträume sperren (Betriebsurlaub, Wartung), inkl. automatischem österreichischem Feiertags-Import
- **Kalenderansicht im Backend** – Monatsübersicht farbkodiert nach Status, Tages-Detailansicht
- **Wording-Konfiguration** – der Begriff „Buchung" ist projektweit austauschbar (z.B. „Reservierung", „Termin", „Anfrage") ohne Code-Änderung, siehe unten
- **iCal**: `.ics`-Download nach Bestätigung, abonnierbarer Kalender-Feed (Google/Apple/Outlook), automatischer Anhang an Bestätigungs- und Erinnerungsmails
- **Automatische Erinnerungsmail** per WP-Cron, Vorlaufzeit pro Standort konfigurierbar
- **Stornierung per Link** – einmaliger Token, kein Login nötig
- **CSV-Export** der Buchungsliste, respektiert aktive Filter
- **WP-Dashboard-Widget** mit den nächsten anstehenden Buchungen
- **Template-Override** – eigenes Formular-Template unter `{theme}/media-lab-bookings/booking-form.php`
- **Filter & Actions** für projektspezifische Erweiterungen (`mlb_before_save_booking`, `mlb_after_save_booking`, `mlb_confirmation_subject`, `mlb_confirmation_body`)

---

## Voraussetzungen

- WordPress 6.0+
- PHP 7.4+
- Advanced Custom Fields Pro (für Standort-Einstellungen, Wording-Konfiguration, Mail-Templates)
- `media-lab-agency-core` (für Honeypot-Spam-Schutz im Formular)


## Dependencies

Keine Composer-Abhängigkeiten — kein `composer.json`/`composer.lock`
vorhanden. Reines Hook-/ACF-basiertes PHP ohne externe Bibliotheken.
(Ausnahme im Starter Kit: `media-lab-backup`, das phpseclib3 für
SSH-Key-Auth benötigt.)


---

## Installation

1. Upload nach `/wp-content/plugins/media-lab-bookings/`
2. Im WordPress-Backend aktivieren
3. Unter **Bookings → Standorte** mindestens einen Standort anlegen (Öffnungszeiten, Zeitslots, Kapazität)
4. Formular per Shortcode einbinden: `[mlb_booking_form]`

### Shortcode-Attribute

```
[mlb_booking_form]
[mlb_booking_form location="123"]
[mlb_booking_form location="wien-mitte" title="Jetzt buchen"]
```

| Attribut | Beschreibung |
|---|---|
| `location` | ID oder Slug eines Standorts – wenn gesetzt, wird das Standort-Dropdown ausgeblendet |
| `title` | Überschrift über dem Formular (optional) |
| `class` | Zusätzliche CSS-Klasse am Formular-Wrapper (optional) |

Bei genau einem angelegten Standort wird dieser automatisch vorausgewählt,
auch ohne `location`-Attribut.

---

## Wording konfigurieren

Unter **Bookings → Einstellungen → Wording** lässt sich der Begriff
„Buchung" projektweit ersetzen – z.B. für ein Restaurant „Reservierung",
für eine Praxis „Termin":

| Feld | Beispiel |
|---|---|
| Singular | Reservierung |
| Plural | Reservierungen |
| Verb / Button-Text | Jetzt reservieren |
| Vergangenheit | Reservierung eingegangen |

Wirkt automatisch auf CPT-Labels im Backend, Admin-Menü, Dashboard-Zähler,
Formular-Button (sofern kein standortspezifisches ACF-Label gesetzt ist)
und die Erfolgsmeldung nach dem Absenden – ohne Code-Änderung pro Projekt.

---

## Buchungsstatus

Buchungen (CPT `mlb_booking`) durchlaufen drei Custom Post Stati:

| Status | Bedeutung |
|---|---|
| `mlb-pending` | Ausstehend – neu eingegangen, noch nicht bestätigt |
| `mlb-confirmed` | Bestätigt – löst Bestätigungsmail (inkl. iCal) und Erinnerungsmail-Planung aus |
| `mlb-cancelled` | Storniert – manuell oder per Stornierungslink im Kunden-Mail |

Statuswechsel im Backend lösen automatisch die passende Benachrichtigung aus.

---

## iCal-Feed einrichten

Abonnierbare Kalender-URL für Google Calendar, Apple Calendar, Outlook u.a.
– Feed-URLs werden im Backend-Dashboard unter **Bookings → Übersicht**
angezeigt (gesamt + je Standort). Optional per Token geschützt, filterbar
nach Standort (`?location=ID`) und Status (`?status=confirmed`); stornierte
Buchungen sind standardmäßig ausgeblendet.

---

## Changelog

Siehe [CHANGELOG.md](./CHANGELOG.md) für die vollständige Versionshistorie.
Aktuelle Version: siehe `Version:`-Header in `media-lab-bookings.php`.

---

## Lizenz

GPL v2 or later — https://www.gnu.org/licenses/gpl-2.0.html
