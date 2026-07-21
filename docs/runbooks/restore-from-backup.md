# Runbook: restore from backup (weekly drill — dev plan §9)

Backups: nightly encrypted `pg_dump` to NG object storage + weekly immutable
(object-lock) copy. Wire the cron at hosting decision:

```
pg_dump "$DATABASE_URL" | gpg --symmetric --batch --passphrase "$BACKUP_KEY" \
  | aws s3 cp - "s3://newco-backups/$(date +%F).sql.gpg" --endpoint-url "$NG_S3_ENDPOINT"
```

## Restore drill (staging, weekly)

1. Fetch latest: `aws s3 cp s3://newco-backups/<date>.sql.gpg - | gpg -d --batch --passphrase "$BACKUP_KEY" > restore.sql`
2. `createdb newco_restore && psql newco_restore < restore.sql`
3. Point a staging api at `newco_restore`; sign in with a seeded user; open a
   consult thread (proves encrypted casts decrypt ⇒ APP_KEY escrow is intact).
4. Record the drill date + restore duration in this file's log below.

**APP_KEY escrow is part of the backup** — a database without the matching
APP_KEY is ciphertext. Key lives in the password manager, access-controlled.

## Drill log

| Date | Duration | Outcome |
|---|---|---|
| _(first drill at staging bring-up)_ | | |
