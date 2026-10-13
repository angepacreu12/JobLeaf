# Jobleaf backend — 5-day plan

Deadline: **Thursday 15 October 2026**.
Workflow: code on `dev-php` → test on the local Docker stack → merge everything into `master` at the end.
Legend: ✅ done · 🚧 to do · ⚠️ blocking for the front

---

## D1 — Sunday 11 October ✅

- ✅ Bug fixes: `db()` required, `config.php` (`safeLoad`, `??`), `display_errors = Off`, dead files removed, logout always 200
- ✅ Schema `01_schema.sql`: 9 × `ON DELETE CASCADE`, `telephone_chiffre`
- ✅ Router with `{id}` (strict lowercase UUID), `current_user()`, `require_role()`
- ✅ `validate.php`: string, email, phone, number, uuid, enum + the 7 enum constants
- ✅ Pagination: `get_pagination()` (max 50), `paginated_response()`
- ✅ Upload limits: nginx 3 MB, PHP 2 MB / 3 MB
- ✅ Crypto matching the Python seed: `encrypt_field` / `decrypt_field`, DEK generate / wrap / unwrap, `dek_store.php`, `bytea_to_string()`
- ✅ `GET /api/me` returns the decrypted profile (checked against the seed)
- ✅ `docs/API.md`

## D2 — Monday 12 October ✅ (apply moved to D3)

- ✅ `POST /api/users` (register): validation, Argon2id, DEK, encrypted fields, one transaction, 409 email / username
- ✅ `GET /api/annonces`: filters `q` / `ville` / `type_contrat` / `category` / `teletravail`, `extrait`, pagination — tested
- ✅ `GET /api/annonces/{id}`: published only, 404 otherwise — tested
- ✅ **Front can now wire the board + "learn more"**
- ✅ `POST /api/annonces/{id}/candidatures` | multipart, `message` 1–5000 required, CV PDF ≤ 2 MB required, encrypted with the candidate's DEK, one transaction: `candidature` + first `message`, 409 if already applied ⚠️ |
- ✅ `GET /api/candidatures/{id}/cv` | decrypt, send the PDF, candidate / owner company / admin only (others 404) |
- ✅ `GET /api/me/candidatures` | candidate's own applications |
- ✅ `GET /api/annonces/{id}/candidatures` | owner company / admin, decrypted with each candidate's DEK |

## D3 — Tuesday 13 October 🚧 — applications + company ads

| Route | Notes |
|---|---|
- ✅ `PATCH /api/candidatures/{id}` | status change, owner company / admin |
| `POST /api/annonces` | company (or admin + `entreprise_id`), generates `reference` |
| `PATCH /api/annonces/{id}` | owner / admin, PATCH rules (`array_key_exists`, column whitelist) |
| `DELETE /api/annonces/{id}` | owner / admin, cascades applications + messages |
| `GET /api/me/annonces` | company's own ads, all statuses, `nb_candidatures` |

Small leftovers from D2: salaries as `int` (when not null), dates in ISO 8601 (`format_date()`) or update `API.md`.

**Done when:** a company publishes an ad, a candidate applies with a PDF, the company sees the application decrypted and downloads the CV.

## D4 — Wednesday 14 October 🚧 — messages, account, admin, crypto-shredding

| Route | Notes |
|---|---|
| `GET /api/conversations` | the route `page_employe.js` already calls ⚠️ |
| `GET /api/candidatures/{id}/messages` | participants only, oldest first, marks as read |
| `POST /api/candidatures/{id}/messages` | 1–5000, always encrypted with the **candidate's** DEK |
| `DELETE /api/me` | **crypto-shredding**, password required, one shared `delete_account()` |
| `DELETE /api/users/{id}` | admin, same `delete_account()` |
| `PATCH /api/me` | re-encrypt changed fields, new `email_index` if the email changes, `null` = clear |
| `PUT /api/me/password` | check old one, rehash, close the other sessions |
| `GET /api/me/export` | GDPR export, everything decrypted |
| `GET /api/users`, `GET /api/users/{id}`, `PATCH /api/users/{id}` | admin, paginated, decrypted |
| `GET /api/candidatures`, `GET /api/candidatures/{id}`, `DELETE /api/candidatures/{id}` | admin / participants / withdraw |
| `GET /api/entreprises`, `GET /api/entreprises/{id}`, `GET /api/entreprises/{id}/annonces`, `PATCH /api/entreprises/{id}` | public profiles + owner / admin edit |

**Done when:** a deleted account cannot be decrypted anymore (shredding demo), and the admin page can list / edit / delete every resource.

## D5 — Thursday 15 October 🚧 — finish + deliver

**Morning — last features**
- `POST /api/password-resets` + `POST /api/password-resets/confirmation` (Mailpit container, one-time token, hash stored, closes all sessions)
- Cleanup script: purge expired sessions / used tokens, move past-due ads to `expiree`

**Afternoon — feature freeze, no new routes**
- Front integration with Asleya, fix what breaks
- Smoke test script: every route, every status code (200 / 201 / 204 / 400 / 401 / 403 / 404 / 409 / 413 / 415)
- `README.md`: install, config (`.env.example`), run, DB schema, API routes (link `docs/API.md`), pages, crypto-shredding + backup strategy, deliberate choices (account required to apply, lockout rule, `session` / `token` / `DEK` not exposed)
- Git: `git rm --cached` on `docker/.env`, `inject_report.json`, `.~lock` file, `.pyc` · rotate keys · remove or document `lettre_chiffree` · merge into `master`
- Final check: `docker compose down -v && docker compose up -d` on a fresh clone, then the demo

**If late, slips first:** password reset → cleanup script (can be a SQL script run by hand). D4 crypto-shredding is protected.
