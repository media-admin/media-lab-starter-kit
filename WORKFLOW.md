# Development Workflow

## Neues Feature entwickeln

1. **Feature Branch erstellen**
```bash
git checkout development
git pull
git checkout -b feature/neue-funktion
```

2. **Lokal entwickeln**
```bash
npm run dev
```

3. **Testen**
- Responsive Check
- Cross-Browser
- Accessibility

4. **Commit & Push**
```bash
git add .
git commit -m "Add neue Funktion"
git push origin feature/neue-funktion
```

5. **Pull Request erstellen**

6. **Nach Merge: Deployment**
```bash
git checkout main
git pull
./scripts/deploy.sh production
```

## Figma Updates übernehmen

1. Figma Design analysieren
2. Design Tokens aktualisieren (_variables.scss)
3. Komponenten anpassen
4. Testen
5. Deployen

## Troubleshooting

### Assets laden nicht
```bash
rm -rf node_modules
npm install
npm run build
```

### Theme nicht sichtbar
```bash
wp theme list
wp theme activate custom-theme
wp cache flush
```