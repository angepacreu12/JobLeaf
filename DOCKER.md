# Docker setup

| Service | Image | Host port | Role |
|---|---|---|---|
| db | postgres:18 | 5433 | database, data kept in the `db_data` volume |
| seed | built from docker/seed | - | runs once: injects seed/seed_data.json **only if `users` is empty** |
| php | built from docker/php (php 8.4-fpm + pdo_pgsql + composer) | - | the API |
| nginx | nginx:stable-alpine | 8586 | serves public/, sends every /api/... to public/api/index.php |

## First time

1. Copy these files into the project root (merge with what you have).
2. Move your Python scripts into `seed/` (generate.py, inject.py, crypto_utils.py, pools.json, requirements.txt)
   and put your **seed_data.json** there too (otherwise one is generated with `--seed 42`).
3. `.env`: keep yours. Needed names: HMAC_KEY, MASTER_KEY, MASTER_KEY_VERSION,
   DATABASE_URL, DATABASE_PORT, DATABASE_NAME, DATABASE_USER, DATABASE_MDP (see .env.example).
4. **Stop your old Postgres container**: it also uses port 5433.
5. `docker compose up -d --build`

## Check

- http://localhost:8586/ → empty page
- http://localhost:8586/api/health → `{"status":"ok", ..., "users":27}`
- `docker compose logs seed` → "injecting" the first time, "nothing to do" afterwards
- Test accounts: `seed/inject_report.json`

## Everyday

| Need | Command |
|---|---|
| start / update after git pull | `docker compose up -d --build` |
| stop, keep data | `docker compose down` |
| wipe DB + re-seed | `docker compose down -v` then `docker compose up -d` |
| PHP logs | `docker compose logs -f php` |
| psql from the laptop | `psql -h localhost -p 5433 -U <DATABASE_USER> -d <DATABASE_NAME>` |

## Good to know

- `db/init/01_schema.sql` runs **only on an empty volume**. After changing the schema: `down -v`.
- PHP code is mounted live: edit, reload, no rebuild. Rebuild only after changing docker/php/.
- Inside Docker, PHP reaches the DB at `db:5432` (compose overrides DATABASE_URL / DATABASE_PORT).
  Your .env keeps `localhost` / `5433` for tests run directly on the laptop.
- `public/api/index.php` is a **placeholder** (only /api/health). Replace it with your front controller.
