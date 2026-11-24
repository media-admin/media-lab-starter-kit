#!/usr/bin/env python3
"""
Erzeugt die Icon-Bibliothek des Media Lab Agency Core.

Eingabe : node_modules mit @tabler/icons und elegant-icons (npm i @tabler/icons elegant-icons)
Ausgabe : assets/icons/sprite-tabler.svg, sprite-elegant.svg, index.json

Aufruf  : python3 tools/icon-library/build.py --node-modules ./node_modules --out assets/icons
Die kuratierte Auswahl steht in curated.py (CATS), die deutschen Bezeichnungen in de.py (RAW).
"""
import argparse, json, os, re, sys
from xml.sax.saxutils import escape

ap = argparse.ArgumentParser()
ap.add_argument('--node-modules', default='./node_modules')
ap.add_argument('--out', default='./assets/icons')
ap.add_argument('--here', default=os.path.dirname(os.path.abspath(__file__)))
a = ap.parse_args()
sys.path.insert(0, a.here)
from curated import CATS          # kuratierte Auswahl
from de import DE                 # deutsche Bezeichnungen

NM = a.node_modules
TAB = os.path.join(NM, '@tabler/icons')
ELE = os.path.join(NM, 'elegant-icons')
os.makedirs(a.out, exist_ok=True)

# ---------------------------------------------------------------- Tabler
meta = json.load(open(os.path.join(TAB, 'icons.json')))
have = {f[:-4] for f in os.listdir(os.path.join(TAB, 'icons/outline'))}
sel, order = {}, []
for cat, txt in CATS:
    for n in txt.split():
        if n.endswith('?') or n not in have or n in sel:
            continue
        sel[n] = cat
        order.append(n)

def tokens_label(tokens, skip=()):
    words = []
    for t in tokens:
        if t in skip:
            continue
        lab = DE.get(t, (t.capitalize(), ''))[0]
        if lab:
            words.append(lab)
    return ' '.join(words)

def tokens_syn(tokens):
    out = []
    for t in tokens:
        out.append(t)
        if t in DE and DE[t][1]:
            out.extend(DE[t][1].split())
    return out

def uniq(seq):
    seen, res = set(), []
    for s in seq:
        s = str(s).lower()
        if s and s not in seen:
            seen.add(s); res.append(s)
    return res

def clean_tabler(svg):
    m = re.search(r'<svg[^>]*>(.*)</svg>', svg, re.S)
    inner = m.group(1)
    inner = re.sub(r'<path stroke="none" d="M0 0h24v24H0z" fill="none"\s*/>', '', inner)
    inner = re.sub(r'\s+', ' ', inner).strip()
    inner = re.sub(r'>\s+<', '><', inner)
    return inner

icons, sym_tabler = [], []
for n in order:
    svg = open(os.path.join(TAB, 'icons/outline', n + '.svg')).read()
    inner = clean_tabler(svg)
    sym_tabler.append(f'<symbol id="tabler-{n}" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{inner}</g></symbol>')
    toks = n.split('-')
    label = tokens_label(toks)
    tags = uniq(tokens_syn(toks) + label.split() + meta.get(n, {}).get('tags', [])[:8])
    icons.append([f'tabler:{n}', label, ' '.join(tags), sel[n]])

# ---------------------------------------------------------------- Elegant Icons
css = open(os.path.join(ELE, 'style.css')).read()
cls2cp = {}
for name, cp in re.findall(r'\.([a-z0-9_\-]+):before\s*\{\s*content:\s*"\\([0-9a-fA-F]+)"', css):
    cls2cp.setdefault(name, int(cp, 16))
font = open(os.path.join(ELE, 'fonts/ElegantIcons.svg')).read()
cp2d = {}
for m in re.finditer(r'<glyph\s+unicode="&#x([0-9a-fA-F]+);"\s+d="([^"]*)"', font):
    cp2d[int(m.group(1), 16)] = m.group(2)

SOCIAL_KEEP = ('facebook', 'instagram', 'linkedin', 'youtube', 'pinterest', 'vimeo', 'skype', 'rss', 'wordpress', 'share')
SKIP_ELE = {'icon_rook', 'icon_loading', 'icon_calulator'}   # icon_calculator_alt bleibt bestehen

def rnd(d):
    d = re.sub(r'-?\d+\.\d+', lambda m: ('%.1f' % float(m.group(0))).rstrip('0').rstrip('.'), d)
    return re.sub(r'\s+', ' ', d).strip()

ELE_CAT = [
    ('Pfeile', ('arrow',)),
    ('Social Media', ('social',)),
    ('Preise & Zahlen', ('cart', 'creditcard', 'wallet', 'currency', 'percent')),
    ('Werbemittel & Textilien', ('gift', 'ribbon', 'bag', 'mug', 'tag', 'tags', 'cone', 'pens')),
    ('Kontakt & Kommunikation', ('mail', 'phone', 'chat', 'comment', 'contacts', 'mic', 'headphones', 'link', 'pin', 'pushpin', 'map', 'compass', 'globe', 'quotations', 'info', 'question')),
    ('Büro & Business', ('document', 'documents', 'folder', 'book', 'archive', 'calendar', 'clipboard', 'briefcase', 'calculator', 'building', 'flowchart', 'datareport', 'easel', 'floppy', 'drive', 'balance', 'hourglass', 'id', 'piechart', 'table', 'drawer', 'toolbox', 'target', 'paperclip', 'puzzle')),
    ('Medien & Marketing', ('image', 'images', 'film', 'camera', 'music', 'vol', 'volume', 'genius')),
    ('Technik & Geräte', ('mobile', 'tablet', 'laptop', 'desktop', 'cloud', 'upload', 'download', 'cog', 'cogs', 'tool', 'tools', 'cursor', 'printer')),
    ('Menschen & Gesten', ('profile', 'group', 'like', 'dislike')),
    ('Service & Prozess', ('lock', 'key', 'clock', 'refresh', 'shield', 'lifesaver', 'trash', 'heart', 'star', 'lightbulb', 'house', 'blocked', 'error')),
]
def ele_category(name):
    toks = re.split(r'[_\-]', name)
    for cat, keys in ELE_CAT:
        if any(k in toks for k in keys):
            return cat
    return 'Symbole & Zeichen'

EXTRA = {  # Elegant-spezifische Begriffe
    'carrot': ('Winkel', 'pfeil'), 'condense': ('Verkleinern', ''), 'expand': ('Vergrößern', ''), 'move': ('Bewegen', ''),
    'lightbulb': ('Glühbirne', 'idee licht'), 'genius': ('Genie', 'idee'), 'cog': ('Zahnrad', 'einstellungen'),
    'cogs': ('Zahnräder', 'einstellungen'), 'mic': ('Mikrofon', ''), 'ul': ('Aufzählung', 'liste'), 'ol': ('Nummerierung', 'liste'),
    'pens': ('Stifte', ''), 'easel': ('Staffelei', 'präsentation'), 'flowchart': ('Flussdiagramm', 'ablauf'),
    'datareport': ('Datenbericht', 'statistik'), 'piechart': ('Kreisdiagramm', 'statistik'), 'toolbox': ('Werkzeugkasten', ''),
    'drawer': ('Schublade', ''), 'ribbon': ('Schleife', 'band'), 'cone': ('Pylon', 'kegel baustelle'), 'floppy': ('Diskette', 'speichern'),
    'drive': ('Laufwerk', 'festplatte'), 'puzzle': ('Puzzle', ''), 'like': ('Gefällt mir', 'daumen'), 'dislike': ('Gefällt nicht', 'daumen'),
    'hourglass': ('Sanduhr', ''), 'balance': ('Waage', ''), 'contacts': ('Kontakte', ''), 'profile': ('Profil', 'person'),
    'group': ('Gruppe', 'team'), 'lifesaver': ('Rettungsring', 'hilfe support'), 'pushpin': ('Pinnnadel', ''), 'pin': ('Stecknadel', 'ort'),
    'quotations': ('Anführungszeichen', 'zitat'), 'blocked': ('Gesperrt', 'verboten'), 'cursor': ('Mauszeiger', ''),
    'creditcard': ('Kreditkarte', 'zahlung'), 'calulator': ('Taschenrechner', ''), 'adjust': ('Regler', ''), 'horiz': ('horizontal', ''),
    'vert': ('vertikal', ''), 'alt': ('', 'variante'), 'alt2': ('', 'variante'), 'icon': ('', ''), 'social': ('', 'social media'),
    'circle': ('rund', ''), 'square': ('eckig', ''), 'box': ('Kasten', ''), 'empty': ('leer', ''), 'selected': ('ausgewählt', ''),
    'checked': ('angehakt', ''), 'document': ('Dokument', ''), 'documents': ('Dokumente', ''), 'triangle': ('Dreieck', ''),
    'octagon': ('Achteck', ''), 'oct': ('Achteck', ''), 'error': ('Fehler', 'warnung'), 'stop': ('Stopp', ''), 'pause': ('Pause', ''),
    'building': ('Gebäude', 'firma'), 'briefcase': ('Aktentasche', 'business'), 'target': ('Ziel', ''), 'percent': ('Prozent', 'rabatt'),
    'globe': ('Globus', 'welt'), 'id': ('Ausweis', 'id'), 'clipboard': ('Klemmbrett', ''), 'table': ('Tabelle', ''),
    'currency': ('Währung', 'geld'), 'wallet': ('Geldbörse', ''), 'shield': ('Schild', 'schutz'), 'house': ('Haus', 'startseite'),
    'wordpress': ('WordPress', ''), 'rss': ('RSS', 'feed'), 'share': ('Teilen', ''), 'skype': ('Skype', ''), 'vimeo': ('Vimeo', ''),
}
def ele_label(name):
    toks = [t for t in re.split(r'[_\-]', name)]
    words, syn = [], []
    for t in toks:
        if t in EXTRA:
            lab, s = EXTRA[t]
        elif t in DE:
            lab, s = DE[t]
        else:
            lab, s = t.capitalize(), ''
        if lab: words.append(lab)
        syn.append(t)
        if s: syn.extend(s.split())
    return ' '.join(words).strip() or name, syn

sym_ele, seen_d = [], set()
for name, cp in cls2cp.items():
    if name in SKIP_ELE or cp not in cp2d:
        continue
    if name.startswith('social_') and not any(k in name for k in SOCIAL_KEEP):
        continue
    d = rnd(cp2d[cp])
    if d in seen_d:
        continue
    seen_d.add(d)
    sym_ele.append(f'<symbol id="elegant-{name}" viewBox="0 0 512 512"><path fill="currentColor" transform="matrix(1 0 0 -1 0 480)" d="{d}"/></symbol>')
    label, syn = ele_label(name)
    tags = uniq(syn + label.split() + ['elegant'])
    icons.append([f'elegant:{name}', label, ' '.join(tags), ele_category(name)])

# ---------------------------------------------------------------- Ausgabe
cats = []
for c, _ in CATS:
    if c not in cats: cats.append(c)
used = {i[3] for i in icons}
for c in sorted(used):
    if c not in cats: cats.append(c)
cats = [c for c in cats if c in used]
# Reihenfolge der Icons: je Kategorie in Kategorie-Reihenfolge, Tabler zuerst, dann Elegant
icons.sort(key=lambda i: (cats.index(i[3]), 0 if i[0].startswith('tabler:') else 1))

def sprite(symbols):
    return '<svg xmlns="http://www.w3.org/2000/svg">\n' + '\n'.join(symbols) + '\n</svg>\n'

open(os.path.join(a.out, 'sprite-tabler.svg'), 'w').write(sprite(sym_tabler))
open(os.path.join(a.out, 'sprite-elegant.svg'), 'w').write(sprite(sym_ele))
index = {
    'version': 1,
    'sets': {
        'tabler':  {'label': 'Tabler (Linien)', 'sprite': 'sprite-tabler.svg'},
        'elegant': {'label': 'Elegant Icons',   'sprite': 'sprite-elegant.svg'},
    },
    'categories': cats,
    'icons': icons,   # [id, Bezeichnung, Suchbegriffe, Kategorie]
}
json.dump(index, open(os.path.join(a.out, 'index.json'), 'w'), ensure_ascii=False, separators=(',', ':'))
print('Tabler:', len(sym_tabler), 'Elegant:', len(sym_ele), 'Kategorien:', len(cats))
