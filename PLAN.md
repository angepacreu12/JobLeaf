# Jobleaf backend — 5-day plan

Target: **Thursday 15 October 2026** (personal goal) · Real deadline: **Sunday 18 October 2026** (buffer: debug, README, demo).
Workflow: code on `dev-php` → test on the local Docker stack → merge everything into `master` at the end.
Legend: ✅ done · 🧪 written, not tested yet · 🚧 to do · ⚠️ blocking for the front

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
- ✅ (D3) Ads require login: `GET /api/annonces` and `GET /api/annonces/{id}` → 401 without a session; owner company / admin can read their draft / expired ads — tested
- ✅ **Front can now wire the board + "learn more"**
- ✅ `POST /api/annonces/{id}/candidatures` | multipart, `message` 1–5000 required, CV PDF ≤ 2 MB required, encrypted with the candidate's DEK, one transaction: `candidature` + first `message`, 409 if already applied ⚠️ |
- ✅ `GET /api/candidatures/{id}/cv` | decrypt, send the PDF, candidate / owner company / admin only (others 404) |
- ✅ `GET /api/me/candidatures` | candidate's own applications |
- ✅ `GET /api/annonces/{id}/candidatures` | owner company / admin, decrypted with each candidate's DEK |

## D3 — Tuesday 13 October — applications + company ads (late, finished on D4)

| Route | State | Notes |
|---|---|---|
| `PATCH /api/candidatures/{id}` | ✅ tested | status change, owner company / admin |
| `POST /api/annonces` | ✅ tested | company (or admin + `entreprise_id`), `reference` = `YY-MM-ABC-NN` with `MAX` per company |
| `PATCH /api/annonces/{id}` | ✅ tested | owner / admin, `array_key_exists`, column whitelist, `date_publication` set on first publish |
| `DELETE /api/annonces/{id}` | ✅ tested | owner / admin, cascades applications + messages, `empty_response(204)` |
| `GET /api/me/annonces` | ✅ tested | company's own ads, all statuses, `nb_candidatures` |

Small leftovers from D2: salaries as `int` (when not null), dates in ISO 8601 (`format_date()`). `API.md` documents the current format meanwhile.

**Done when:** a company publishes an ad, a candidate applies with a PDF, the company sees the application decrypted and downloads the CV.

## D4 — Wednesday 14 October 🚧 — crypto-shredding, messages, account, admin

**Must do**

| Route | State | Notes |
|---|---|---|
| `DELETE /api/me` | ✅ tested | **crypto-shredding**, password required, `delete_account()`: DEK first, then the user, one transaction |
| `DELETE /api/users/{id}` | ✅ tested | admin, same `delete_account()` |
| `GET /api/conversations` | ✅ tested | ⚠️ `page_employe.js` must read `.items`, `interlocuteur`, `dernier_message.contenu` / `.date_envoi` |
| `GET /api/candidatures/{id}/messages` | ✅ tested | participants only (admin 404), oldest first, marks as read |
| `POST /api/candidatures/{id}/messages` | ✅ tested | 1–5000, always encrypted with the **candidate's** DEK |

**Then**

| Route | State | Notes |
|---|---|---|
| `GET /api/me/export` | ✅ tested | GDPR export, everything decrypted (good for the defence) |
| `PUT /api/me/password` | ✅ tested | check old one, rehash, close the other sessions |
| `PATCH /api/me` | 🚧 | re-encrypt changed fields, new `email_index` if the email changes, `null` = clear |
| `GET /api/users`, `GET /api/users/{id}`, `PATCH /api/users/{id}` | 🚧 | admin, paginated, decrypted |
| `GET /api/candidatures`, `GET /api/candidatures/{id}`, `DELETE /api/candidatures/{id}` | 🚧 | admin / participants / withdraw |
| `GET /api/entreprises`, `GET /api/entreprises/{id}`, `GET /api/entreprises/{id}/annonces`, `PATCH /api/entreprises/{id}` | 🚧 | company profiles + owner / admin edit (ads list: logged in, like the other ad routes) |

**Done when:** a deleted account cannot be decrypted anymore (shredding demo: the `DEK` row and the user's rows are gone), and the admin page can list / edit / delete every resource.

## D5 — Thursday 15 October 🚧 — test + deliver

**Debug session 1 — first thing in the morning** ✅ done on D4 (14 Oct): 22 routes, 101/101 checks, shredding demo OK
- `php -l` on every file, then the D3 ad routes (create draft / published as company and as admin, read, list, edit, publish, delete, other company refused everywhere)
- `DELETE /api/me` + `DELETE /api/users/{id}`: shredding demo
- Switch every passing 🧪 to ✅ in `API.md` and here

**Late morning — last features**
- `POST /api/password-resets` + `POST /api/password-resets/confirmation` (Mailpit container, one-time token, hash stored, closes all sessions)
- Cleanup script: purge expired sessions / used tokens, move past-due ads to `expiree`

**Debug session 2 — start of the afternoon**
- Messages: conversations, read, send, unread counter, participants only
- Front integration with Asleya on the merged branch, fix what breaks

**Afternoon — feature freeze, no new routes**
- Smoke test script: every route, every status code (200 / 201 / 204 / 400 / 401 / 403 / 404 / 409 / 413 / 415)
- `README.md`: install, config (`.env.example`), run, DB schema, API routes (link `docs/API.md`), pages, crypto-shredding + backup strategy (a backup still holds the wrapped DEK: limit `DEK` backup retention or rotate the master key), deliberate choices (account required to see ads and apply, lockout rule, `session` / `token` / `DEK` not exposed)
- Git: `git rm --cached` on `docker/.env`, `inject_report.json`, `.~lock` file, `.pyc` · rotate keys · remove or document `lettre_chiffree` · merge into `master`
- Final check: `docker compose down -v && docker compose up -d` on a fresh clone, then the demo

**If late, slips to the buffer (Friday 16 – Sunday 18):** password reset → cleanup script (can be a SQL script run by hand). D4 crypto-shredding is protected.
