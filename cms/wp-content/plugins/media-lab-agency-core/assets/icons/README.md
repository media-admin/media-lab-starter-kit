# Icon-Bibliothek (Media Lab Agency Core)

Einfarbige SVG-Icons für Blöcke (zuerst Kachel). Farbe und Größe kommen aus dem CSS (`currentColor`, `1em`).

| Datei | Inhalt |
| --- | --- |
| `sprite-tabler.svg` | Tabler Icons (Linien), je Icon ein `<symbol id="tabler-NAME">` |
| `sprite-elegant.svg` | Elegant Icons (gefüllt), je Icon ein `<symbol id="elegant-NAME">` |
| `index.json` | Auswahl für den Editor: `[id, Bezeichnung, Suchbegriffe, Kategorie]`, Kategorien, Bibliotheken |
| `LICENSES.md` | Lizenztexte (MIT) |

**Gespeicherter Wert:** `tabler:shirt` oder `elegant:icon_pencil` im Attribut `icon`. Alles andere (zum Beispiel ein Emoji) wird als Text ausgegeben.

**Ausgabe in PHP:** `medialab_icon_svg( 'tabler:shirt' )` liefert ein Inline-SVG (`class="ml-icon ml-icon--tabler"`, `aria-hidden="true"`), bei unbekannter ID einen leeren String. `medialab_icon_is_id( $wert )` prüft das Format.

**Neu erzeugen / erweitern:** `tools/icon-library/` (Auswahl in `curated.py`, deutsche Bezeichnungen in `de.py`):

```bash
npm i @tabler/icons elegant-icons
python3 tools/icon-library/build.py --node-modules ./node_modules --out assets/icons
```
