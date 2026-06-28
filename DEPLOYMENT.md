# Deployment Checklist

## 🔄 Staging Deployment

- [ ] Merge feature branch to `develop`
- [ ] Run `npm run build` locally (test build)
- [ ] Commit and push to `develop`
- [ ] GitHub Action deploys automatically
- [ ] Test on https://staging.your-domain.com
- [ ] Check console for errors
- [ ] Test all shortcodes
- [ ] Test responsive layouts
- [ ] Verify database works

## 🚀 Production Deployment

- [ ] All tests passed on staging
- [ ] Merge `develop` to `main`
- [ ] Create git tag: `git tag v1.0.0`
- [ ] Push tag: `git push origin v1.0.0`
- [ ] GitHub Action creates backup
- [ ] GitHub Action deploys to production
- [ ] Verify https://your-domain.com
- [ ] Monitor for 15 minutes
- [ ] Check error logs
- [ ] Better Stack Uptime: Monitor zeigt grün
- [ ] Better Stack Logs: Keine auffälligen Errors in den ersten 15 Minuten

## 🔙 Rollback Procedure

If deployment fails:
```bash
# SSH to server
ssh user@your-domain.com

# Restore backup
cd /var/www/production
tar -xzf backup-YYYYMMDD-HHMMSS.tar.gz

# Clear cache
wp cache flush
```

## 📊 Post-Deployment

- [ ] Update changelog
- [ ] Notify team
- [ ] Better Stack Logs auf Warns/Errors prüfen
- [ ] Better Stack Uptime: Verfügbarkeit bestätigt
- [ ] Check performance metrics
