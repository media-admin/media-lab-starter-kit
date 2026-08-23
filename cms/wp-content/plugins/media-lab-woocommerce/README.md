# Media Lab WooCommerce

WooCommerce-Integrationsplugin für Media-Lab-Agentur-Websites: Catalog Mode
(Anfrage statt Kauf), Produkt-Konfigurator, Filter/Suche – und seit 2.0.0 eine
vollständige **Wunschlisten-Funktion**, gebaut auf einer zentralen
**Inquiry-Engine**, die Cart-Anfrage, Konfigurator-Anfrage und Wunschliste
einheitlich verarbeitet.

Entwickelt von [Media Lab Tritremmel GmbH](https://media-lab.at).

---

## Features

- **Catalog Mode**: Shop ohne Kauf-Funktion, Anfrage-Formular statt Checkout
- **Produkt-Konfigurator**: mehrstufiger Wizard (Alpine.js) mit Preisberechnung, Mengenrabatt-Staffeln, Datei-Uploads
- **Wunschliste**: eigenständig vom WooCommerce-Warenkorb, funktioniert unabhängig davon ob Catalog Mode aktiv ist; unterstützt konfigurierte Produkte inkl. Preis/Konfiguration/Datei-Uploads
- **Inquiry-Engine**: zentrale Anfrage-Verarbeitung (CPT, Validierung, Mail-Versand über mehrere Kanäle, Mehrsprachigkeit) – gemeinsam genutzt von Cart-Anfrage, Konfigurator-Anfrage und Wunschliste
- **Filter & Suche**: AJAX-Produktfilter, Suche, Load-More

---

## Voraussetzungen

- WordPress 6.0+
- PHP 8.0+
- WooCommerce (aktiv)
- Advanced Custom Fields Pro (für alle Einstellungsseiten)
- `media-lab-agency-core` (für Honeypot-Spam-Schutz der Formulare)

---

## Dependencies

Keine Composer-Abhängigkeiten — kein `composer.json`/`composer.lock`
vorhanden. Reines Hook-/ACF-basiertes PHP ohne externe Bibliotheken.
(Ausnahme im Starter Kit: `media-lab-backup`, das phpseclib3 für
SSH-Key-Auth benötigt.)

---

## Installation


1. Upload nach `/wp-content/plugins/media-lab-woocommerce/`
2. Im WordPress-Backend aktivieren
3. Einstellungen unter **Anfragen → Einstellungen** konfigurieren (siehe unten)

---

## Wunschliste einrichten

1. Neue WordPress-Seite anlegen (z.B. "Wunschliste"), Inhalt: `[mlw_wishlist_page]`
2. Optional: **Anfragen → Einstellungen → Tab „Navigation"** – Icon im Hauptmenü aktivieren, die eben angelegte Seite auswählen
3. Formularfelder, Pflichtfelder, Datenschutz-Text und Mail-Templates unter **Anfragen → Einstellungen** anpassen – gelten automatisch für Cart-Anfrage, Konfigurator-Anfrage **und** Wunschliste gemeinsam

### Konfigurierbare Produkte

Damit ein Produkt im Konfigurator-Wizard erscheint, braucht es (per ACF am
Produkt): `is_configurable = true`, `config_type = standard`, sowie
mindestens einen `config_steps`-Eintrag vom Typ `contact_form` (rendert
Name/E-Mail/Telefon + die konfigurierten Zusatzfelder) – ohne diesen Step
gibt es im Wizard kein Kontaktformular.

> **Hinweis:** Der `config_type` (Textilien/Drucksorten/Give-Aways/
> Benutzerdefiniert) dient rein der Organisation im Backend - alle Typen
> laden die Konfigurationsschritte identisch aus dem `config_steps`-Repeater
> (seit dem Fix in 2.5.1, siehe CHANGELOG.md).

---

## Einstellungen (Anfragen → Einstellungen)

| Tab | Inhalt |
|---|---|
| Wording | Bezeichnungen, Button-Texte, Erfolgsmeldung (einsprachiger Fallback) |
| Sprachen | Mehrsprachigkeit an/aus, pro Sprache: Wording, Wunschlisten-Seite, Mail-Templates |
| Formularfelder | Zusatzfelder (Text/E-Mail/Auswahl/Checkbox/…), Pflicht/Optional, pro Sprache übersetzbar |
| Kanäle | E-Mail, WhatsApp-Link, Webhook (JSON-POST) – beliebig kombinierbar |
| Mail-Templates | Kunden-/Admin-Mail, Platzhalter-basiert (einsprachiger Fallback) |
| Navigation | Wunschlisten-Icon im Hauptmenü an/aus, Mengen-Badge an/aus |

Mehrsprachigkeit funktioniert unabhängig davon, ob WPML, Polylang oder gar
kein Mehrsprachigkeits-Plugin installiert ist (eigene Sprach-Erkennung,
Fallback auf `get_locale()`).

---

## Architektur-Hinweis

Die Inquiry-Engine (`inc/inquiry/`) ist absichtlich von der Wunschliste
(`inc/wishlist/`) getrennt: Erstere kennt nur "Items + Kontaktdaten + Quelle",
Letztere ist reine Datenhaltung (Session/User-Meta) ohne Wissen über
Mail-Versand. `catalog-mode.php` und `class-configurator.php` reichen ihre
Daten ebenfalls nur an die Engine durch. Neue Anfrage-Quellen lassen sich so
ergänzen, ohne Mail-/Validierungs-/Mehrsprachigkeits-Logik zu duplizieren.

---

## Changelog

Siehe [CHANGELOG.md](./CHANGELOG.md) für die vollständige Versionshistorie.
Aktuelle Version: siehe `Version:`-Header in `media-lab-woocommerce.php`.

---

## Lizenz

GPL v2 or later — https://www.gnu.org/licenses/gpl-2.0.html
