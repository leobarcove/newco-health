# Runbook: production deploy

Deploys are tag-driven (`git tag vX.Y.Z && git push --tags`) → `.github/workflows/deploy.yml`
builds the API image + SPA and ships both. **Enable by setting the repo variable
`DEPLOY_ENABLED=true` and the `DEPLOY_*` secrets once hosting exists.**

## Migrations (human-triggered — CLAUDE.md hard rule 2)

Never run automatically. Per release, BEFORE tagging:
1. Review new migrations for expand→migrate→contract compatibility (dev plan §10).
2. After the api container is up on the new tag:
   `docker compose exec api php artisan migrate --force` — run by a human, in a
   deploy window, with the rollback below rehearsed.
3. Destructive migrations require a second reviewer on the PR.

## Rollback

- **App:** `export TAG=<previous tag> && docker compose up -d --no-deps api queue scheduler reverb`
- **SPA:** `mv web-dist web-dist-broken && mv web-dist-previous web-dist`
- **Migration:** only via a new forward migration — never `migrate:rollback` in production.

## Post-deploy checks

`curl https://<domain>/up` → 200 · sign in with a test patient · watch Sentry for 10 minutes.
