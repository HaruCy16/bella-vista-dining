# Migrations

The schema was frozen at the end of Phase 0. `schema.sql` + `seed.sql` build a
**fresh** database (Docker runs them only when the `db-data` volume is empty).

Any schema change after the freeze needs two edits in the same commit:

1. Update `database/schema.sql` so a fresh install gets the latest structure.
2. Add a numbered file here for databases that already exist:

   ```
   001_short_description.sql
   002_another_change.sql
   ```

   Use the next unused number, never edit a migration that teammates may have
   already run, and keep each file to one logical change.

Apply a migration to the Docker database:

```bash
docker compose exec -T db sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
  < database/migrations/001_short_description.sql
```

On shared hosting, run the same file from phpMyAdmin's **Import** tab.

| # | Change |
|---|---|
| 001 | Standard tables take 2 to 4 guests (client spec) |
| 002 | `admin_login_attempts` table for login throttling |
