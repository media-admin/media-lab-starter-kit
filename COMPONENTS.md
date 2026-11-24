# Component Usage Guide

## Hero Slider
```php
get_template_part('template-parts/components/hero-slider', null, array(
    'slides' => array(
        array(
            'image' => 'URL',
            'title' => 'Title',
            'subtitle' => 'Subtitle',
            'button_text' => 'Button',
            'button_link' => '/link',
        ),
    )
));
```

## Accordion
```php
get_template_part('template-parts/components/accordion', null, array(
    'items' => array(
        array(
            'title' => 'Question',
            'content' => 'Answer',
        ),
    ),
    'allow_multiple' => false,
));
```

## Lightbox
```html
<a href="large-image.jpg" data-lightbox="gallery" data-caption="Caption">
<img src="thumbnail.jpg" alt="">
</a>

## Modal
```html
<button data-modal-trigger="modal-id">Open Modal</button>
```

## Animations
```html
<div data-animate="fade-in-up">Animated content</div>
<div data-animate="fade-in-left">Animated content</div>
<div data-animate-stagger">
    <div>Item 1</div>
    <div>Item 2</div>
</div>
```

## Dark Mode

Automatic! Button is added automatically.

## Back to Top

Automatic! Button appears after scrolling 300px.

## Cookie Notice

Automatic! Shows on first visit.

## Block: Kachel-Raster (Agency Core, seit 1.40.0)

Gutenberg-Block `medialab/tile-grid` mit Kind-Block `medialab/tile`. Kategorie „Design“.

- **Raster:** Spalten 1–4 (Smartphone 1, Tablet höchstens 2, Desktop wie eingestellt); Überschriftenebene H2–H4 (Standard H3) für alle Kacheln.
- **Kachel:** Titel, Text, optional Icon (aus der Icon-Bibliothek oder Emoji/Zeichen) oder Bild, Link-URL, optional Button-Text, optional „in neuem Tab öffnen“.
- **Link:** je Kachel genau ein Link. Mit Button-Text ist der Button der Link, ohne Button-Text der Titel. Die ganze Kachel ist klickbar.
- **Einsatz:** Katalog-Einstieg, Werbemittel-Sammelseite, Startseite, Leistungsseiten.
- **Überschriftenebene wählen:** unter einer H2-Abschnittsüberschrift H3, direkt unter der Seiten-H1 H2.

Markup (Frontend):
```html
<ul class="ml-block-tile-grid ml-tile-grid--cols-3" role="list">
    <li class="ml-tile ml-tile--linked">
        <div class="ml-tile__icon" aria-hidden="true">📦</div>
        <div class="ml-tile__body">
            <h3 class="ml-tile__title">Titel</h3>
            <p class="ml-tile__text">Text</p>
            <p class="ml-tile__action"><a class="btn btn--outline ml-tile__link" href="/ziel/">Button</a></p>
        </div>
    </li>
</ul>
```

Styling: `assets/src/scss/blocks.scss`, nur Variablen (`--ml-color-*`), keine festen Farbwerte. Anpassungen am Aussehen im Theme über diese Variablen.

## Icon-Bibliothek (Agency Core, seit 1.40.1)

Einfarbige SVG-Icons für Blöcke (aktuell Block „Kachel“): 818 Icons **Tabler** (Linien) und 317 **Elegant Icons** (gefüllt), 17 Kategorien, deutsche Namen und Suchbegriffe. Lizenzen: `assets/icons/LICENSES.md` (beide MIT).

- **Auswahl im Editor:** Kachel → Seitenleiste „Icon auswählen“ (Suche, Kategorie, Bibliothek, „Mehr anzeigen“). Reiter „Emoji“ und Feld „Eigenes Icon“ für Emoji/Zeichen.
- **Gespeichert:** im Attribut `icon` als `tabler:NAME` oder `elegant:NAME`; alles andere gilt als Text (Emoji).
- **Ausgabe:** Inline-SVG, Farbe = Textfarbe (`currentColor`), Größe 1em (Klasse `.ml-icon`, `.ml-icon--tabler`/`--elegant`), `aria-hidden`.
- **PHP:** `medialab_icon_is_id( $wert )`, `medialab_icon_svg( $id )` (`inc/icons.php`).
- **Dateien:** `assets/icons/sprite-tabler.svg`, `sprite-elegant.svg`, `index.json`.
- **Neuaufbau/Erweitern:** `tools/icon-library/` (`curated.py` Auswahl, `de.py` deutsche Begriffe, `build.py`); Befehl in `assets/icons/README.md`.

## Block: Schritte (Agency Core, seit 1.40.0)

Gutenberg-Block `medialab/steps` mit Kind-Block `medialab/step`. Kategorie „Design“.

- **Schritt:** Titel und Text (Text darf Links enthalten). Die Nummer ergibt sich aus der Reihenfolge (CSS-Zähler) und wird nicht gespeichert.
- **Block-Einstellungen:** Anordnung am Desktop nebeneinander oder untereinander (Smartphone immer untereinander); Überschriftenebene H2–H4 (Standard H3).
- **Einsatz:** Ablauf im Katalog-Einstieg (vier Schritte), „So läuft die Zusammenarbeit“ auf Leistungsseiten.

Markup (Frontend):
```html
<ol class="ml-block-steps ml-steps--horizontal" role="list">
    <li class="ml-step">
        <div class="ml-step__body">
            <h3 class="ml-step__title">Titel</h3>
            <p class="ml-step__text">Text</p>
        </div>
    </li>
</ol>
```

## Hinweis: Build der Blöcke

Nach Änderungen an Blöcken im Agency Core `npm run build:blocks` im Starter-Kit-Wurzelordner ausführen (erzeugt `assets/dist/js/blocks.js` und `assets/dist/css/blocks.css`).
