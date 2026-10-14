# Jobleaf API

Contract between the back end (PHP) and the front end.
Legend: ✅ done · 🧪 written, not tested yet · 🚧 to do · ❓ to decide · ✂️ cut (not delivered)

---

## Conventions

| Topic | Rule |
|---|---|
| Base URL | `/api` — same origin as the front (`http://localhost:8586`), no CORS |
| Format | JSON in and out (`Content-Type: application/json`), except **apply**: `multipart/form-data` |
| Auth | `session` cookie (HttpOnly, Secure, SameSite=Lax, 1 h), set by `POST /api/login`. The front never reads it: the browser sends it by itself (`fetch` default `credentials: 'same-origin'`) |
| Errors | Always `{"error": "message"}`. For field errors: `"<field>: <reason>"` |
| IDs | UUID v7, lowercase, e.g. `01a129ba-aeea-73ed-8929-69169825b117`. Bad id in the URL → 404 |
| Dates | ISO 8601 with timezone, e.g. `2026-10-11T09:30:00+02:00`. ⚠️ Currently returned in Postgres format (`2026-09-23 02:04:43+00`), fix in progress |
| Empty value | To clear an optional field, send `null`. `""` is refused (400) |
| PATCH | Send only the fields you change. Missing field = unchanged, `null` = cleared |
| Body size | JSON body max 64 KB. Above that the API answers 400 `Invalid JSON` |

### Pagination (every list)

Query: `?page=1&per_page=20` — `page` ≥ 1 (default 1), `per_page` 1–50 (default 20, above 50 → 400).

```json
{ "items": [ ... ], "page": 1, "per_page": 20, "total": 137 }
```

Number of pages = `ceil(total / per_page)`. A page past the end returns `items: []`.

### Allowed values (case-insensitive in, canonical value out)

| Field | Values |
|---|---|
| `role` | `candidat`, `entreprise`, `admin` (register: `candidat` or `entreprise` only) |
| `category` | `Hopital`, `Informatique`, `Parfumerie` |
| `statut` (ad) | `brouillon`, `publiee`, `expiree`, `pourvue` |
| `type_contrat` | `CDI`, `CDD`, `alternance`, `stage`, `freelance` |
| `teletravail` | `sur site`, `hybride`, `full remote` |
| `statut` (application) | `envoyee`, `vue`, `entretien`, `refusee`, `acceptee` |

### Field limits

| Field | Rule |
|---|---|
| `email` | valid email, max 254, stored lowercase |
| `username` | 3–30 characters, unique, public (shown on the site) |
| `nom`, `prenom` | 1–100 characters, required at register |
| `password` | 12–256 characters |
| `telephone` | digits, optional leading `+`, 6–15 digits (spaces . - ( ) / , are removed) |
| `salaire_min`, `salaire_max` | whole euros, 0–1 000 000, `salaire_max` ≥ `salaire_min` |
| CV | PDF only, max 2 MB |
| `nom_entreprise` | 1–200 characters |
| `titre` (ad) | 3–150 characters |
| `description` (ad) | 10–5000 characters |
| `ville`, `niveau_experience` | 1–100 characters |
| `date_expiration` | ISO 8601 date |
| `message`, `contenu` | 1–5000 characters |

### Status codes

| Code | Meaning |
|---|---|
| 200 | OK |
| 201 | Created (returns the new resource) |
| 204 | Deleted, no body |
| 400 | Missing / invalid field, invalid JSON |
| 401 | Not logged in, or session expired |
| 403 | Logged in but not allowed (wrong role, not your resource) |
| 404 | Resource or route not found |
| 405 | Route exists, wrong HTTP method |
| 409 | Conflict (email / username taken, already applied) |
| 413 | File or body too large. ⚠️ above 3 MB nginx answers **HTML**, not JSON |
| 415 | Wrong content type (e.g. CV not a PDF) |
| 429 | Account blocked 15 min after 5 failed logins |
| 500 | Internal error (details only in the server log) |

---

## Auth

### ✅ POST /api/login
Who: public
Body:
```json
{ "email": "lea@mail.test", "password": "..." }
```
200 + `Set-Cookie: session=...`:
```json
{ "role": "candidat" }
```
Front: redirect `admin` → admin page, others → job board.
Errors: 400 `Invalid JSON` / `Email and password required` · 401 `Invalid email or password` · 403 `Email not verified` (cannot happen today: register creates accounts already verified) · 429 `Rate limit, try again in 15 minutes`

### ✅ POST /api/logout
Who: anyone (works without a session)
200:
```json
{ "status": "Log_out" }
```
Clears the cookie.

### 🚧 POST /api/password-resets
Who: public — asks for a reset email (Mailpit in dev).
Body: `{ "email": "..." }`
202 always, even if the email does not exist (no account discovery):
```json
{ "status": "If this account exists, an email has been sent" }
```

### 🚧 POST /api/password-resets/confirmation
Who: public — the link in the email carries the token.
Body: `{ "token": "...", "password": "new password" }`
200 `{ "status": "Password changed" }` — every session of the user is closed, they must log in again.
Errors: 400 invalid / expired / already used token, weak password.

---

## Me (the logged-in user)

### ✅ GET /api/me
Who: logged in
200:
```json
{
  "user_id": "uuid",
  "role": "candidat",
  "username": "lea_m",
  "email": "lea@mail.test",
  "nom": "Martin",
  "prenom": "Léa",
  "telephone": null
}
```
Use it to pre-fill the apply form and the profile page. 401 if not logged in.

### 🚧 PATCH /api/me
Who: logged in
Body (any subset): `username`, `email`, `nom`, `prenom`, `telephone`
200: same shape as `GET /api/me`
Errors: 400 · 409 email or username taken

### 🚧 PUT /api/me/password
Who: logged in
Body: `{ "old_password": "...", "new_password": "..." }`
200 `{ "status": "Password changed" }` — the other sessions are closed, the current one stays.
Errors: 400 · 401 wrong old password

### 🧪 DELETE /api/me
Who: logged in — **crypto-shredding**: the user's key (DEK) is destroyed first, then the account and everything linked to it, in one transaction.
Body: `{ "password": "..." }` — required, confirms the deletion (it cannot be undone).
204, cookie cleared. Errors: 400 `password: is required` · 401 `Invalid password`
⚠️ Company account: its profile, its ads, the applications on them and their messages are deleted too.

### 🚧 GET /api/me/export
Who: logged in — GDPR data export, everything decrypted.
200: JSON file (`Content-Disposition: attachment`) with profile, company profile, ads, applications (with message, CV as a download link), messages, active sessions.

### ✅ GET /api/me/candidatures
Who: `candidat`
200: paginated list of my applications
```json
{
  "candidature_id": "uuid",
  "statut": "envoyee",
  "date_candidature": "...",
  "annonce": { "annonce_id": "uuid", "titre": "...", "entreprise": "Nom entreprise" },
  "has_cv": true
}
```

### 🧪 GET /api/me/annonces
Who: `entreprise` — paginated list of my ads, **all statuses** (draft, published, expired, filled), newest first.
Item:
```json
{
  "annonce_id": "uuid",
  "reference": "26-10-SAN-04",
  "titre": "...",
  "statut": "brouillon",
  "type_contrat": "CDI",
  "ville": "...",
  "date_publication": null,
  "date_expiration": null,
  "nb_candidatures": 3
}
```
Errors: 401 · 403 not a company

---

## Users

### ✅ POST /api/users — register
Who: public
Body:
```json
{
  "email": "lea@mail.test",
  "password": "...",
  "role": "candidat",
  "username": "lea_m",
  "nom": "Martin",
  "prenom": "Léa",
  "telephone": "06 12 34 56 78",
  "nom_entreprise": "Only when role = entreprise"
}
```
Required: `email`, `password`, `role`, `username` (3–30 characters, unique), `nom`, `prenom`, + `nom_entreprise` for a company. `telephone` is optional. `nom_entreprise` is ignored for a candidate.
201: same shape as `GET /api/me`. The account is created already verified (no email verification). Not logged in yet → call `POST /api/login` next.
Errors: 400 · 409 email or username taken

### ✂️ GET /api/users
Who: `admin` — paginated, decrypted. Filters: `?role=`

### ✂️ GET /api/users/{id}
Who: `admin` — same shape as `GET /api/me`.

### ✂️ PATCH /api/users/{id}
Who: `admin` — same body as `PATCH /api/me`, plus `role`.

### 🧪 DELETE /api/users/{id}
Who: `admin` — same crypto-shredding as `DELETE /api/me`, no password asked. 204. Errors: 403 · 404

---

## Companies (profile stored in clear) — ✂️ not delivered

### ✂️ GET /api/entreprises
Who: public — paginated.
Item:
```json
{
  "user_id": "uuid",
  "nom_entreprise": "...",
  "siret": "12345678901234",
  "secteur": "...",
  "ville": "...",
  "site_web": "https://...",
  "description": "..."
}
```

### ✂️ GET /api/entreprises/{id}
Who: public — one company, same shape.

### ✂️ GET /api/entreprises/{id}/annonces
Who: public — paginated, published ads of that company only.

### ✂️ PATCH /api/entreprises/{id}
Who: the company itself, or `admin`
Body (any subset): `nom_entreprise`, `siret`, `secteur`, `ville`, `site_web`, `description`
200: the updated company. Errors: 400 · 403 · 409 SIRET taken
(Created at register, deleted with the account: no POST / DELETE here.)

---

## Ads (annonces)

### ✅ GET /api/annonces
Who: logged in — only `publiee` and not past `date_expiration`, for every role (admin included). Newest first.
Filters (all optional): `q` (text in title / description), `ville`, `type_contrat`, `category`, `teletravail`, `page`, `per_page`
Item (short, for the board):
```json
{
  "annonce_id": "uuid",
  "reference": "26-10-ABC-01",
  "titre": "Développeur PHP",
  "extrait": "first 200 characters of the description...",
  "type_contrat": "CDI",
  "category": "Informatique",
  "ville": "Montpellier",
  "date_publication": "...",
  "entreprise": { "user_id": "uuid", "nom_entreprise": "..." }
}
```
`extrait` = the first 200 characters of `description`, built by the API. The full text comes with `GET /api/annonces/{id}`.
Errors: 400 invalid filter · 401 not logged in

### ✅ GET /api/annonces/{id}
Who: logged in. Published ads: everyone. Draft / expired / filled: only the owner company and admin, others get 404 (same answer as an unknown id, so drafts stay hidden).
200 — full ad, for the "learn more" view (no page reload):
```json
{
  "annonce_id": "uuid",
  "reference": "26-10-ABC-01",
  "titre": "...",
  "description": "...",
  "type_contrat": "CDI",
  "category": "Informatique",
  "teletravail": "hybride",
  "salaire_min": 35000,
  "salaire_max": 42000,
  "niveau_experience": "...",
  "ville": "...",
  "statut": "publiee",
  "date_publication": "...",
  "date_expiration": "...",
  "entreprise": { "user_id": "uuid", "nom_entreprise": "...", "ville": "...", "site_web": "..." }
}
```
Errors: 401 · 404

### 🧪 POST /api/annonces
Who: `entreprise` (own ad) or `admin` (must send `entreprise_id`)
Body: `titre`, `description`, `type_contrat` required · `category`, `teletravail`, `salaire_min`, `salaire_max`, `niveau_experience`, `ville`, `date_expiration`, `statut` (`brouillon` or `publiee`, default `brouillon`) optional
201: the full ad. `reference` is generated by the server: `YY-MM-ABC-NN` (year, month, 3 letters of the company name, counter per company). `date_publication` is set when created as `publiee`.
Errors: 400 invalid field · 400 `salaire_max: must be >= salaire_min` · 400 `entreprise_id: unknown company` (admin) · 401 · 403

### 🧪 PATCH /api/annonces/{id}
Who: owner company or `admin` — any subset of the POST fields, `statut` can be any of the 4 values.
`titre`, `description`, `type_contrat`, `statut` cannot be `null`. The other fields accept `null` to clear them.
Publishing = `{"statut": "publiee"}`: sets `date_publication` the first time only. Closing = `{"statut": "pourvue"}`.
200: the full ad. Errors: 400 invalid field · 400 `No field to update` · 400 salary check (compared with the stored value for the side not sent) · 401 · 403 · 404 not found or not yours

### 🧪 DELETE /api/annonces/{id}
Who: owner company or `admin` — deletes the ad **and its applications and messages**. 204. Errors: 401 · 403 · 404 not found or not yours

---

## Applications (candidatures)

An account is required to apply. Not logged in → the front shows one form (account fields + message + CV), calls `POST /api/users`, `POST /api/login`, then this route.

### ✅ POST /api/annonces/{id}/candidatures
Who: `candidat`
`multipart/form-data`:
| Field | Rule |
|---|---|
| `message` | required, 1–5000 characters — becomes the **first message of the conversation** (stored encrypted in `message`) |
| `cv` | required, PDF, max 2 MB |
201:
```json
{ "candidature_id": "uuid", "statut": "envoyee", "date_candidature": "..." }
```
Errors: 400 · 404 ad not found or not published · 409 already applied · 413 file too large · 415 not a PDF

### ✅ GET /api/annonces/{id}/candidatures
Who: owner company or `admin` — paginated, decrypted.
Item:
```json
{
  "candidature_id": "uuid",
  "statut": "envoyee",
  "date_candidature": "...",
  "message": "the application message (= first message of the conversation)",
  "has_cv": true,
  "candidat": { "user_id": "uuid", "nom": "...", "prenom": "...", "email": "...", "telephone": "..." }
}
```

### ✂️ GET /api/candidatures
Who: `admin` — paginated, all applications. Filters: `?annonce_id=`, `?statut=`

### ✂️ GET /api/candidatures/{id}
Who: the candidate, the owner company, or `admin` (others: 404) — same item shape.

### ✅ PATCH /api/candidatures/{id}
Who: owner company or `admin`
Body: `{ "statut": "entretien" }`
200: the application, same shape as an item of `GET /api/annonces/{id}/candidatures`. Errors: 400 · 401 · 403 candidate · 404 not found or not yours

### ✂️ DELETE /api/candidatures/{id}
Who: the candidate (withdraw) or `admin` — deletes its messages too. 204.

### ✅ GET /api/candidatures/{id}/cv
Who: the candidate, the owner company, or `admin` (others: 404)
200: the PDF (`Content-Type: application/pdf`, `Content-Disposition: attachment`). 404 if no CV.

---

## Messages (one conversation per application)

### 🧪 GET /api/conversations
Who: `candidat` or `entreprise` (admin: 403) — my conversations (candidate: my applications · company: applications on my ads). Paginated, most recent activity first.
Item:
```json
{
  "candidature_id": "uuid",
  "annonce": { "annonce_id": "uuid", "titre": "..." },
  "interlocuteur": "Nom entreprise (seen by the candidate) or Prénom Nom (seen by the company)",
  "dernier_message": { "contenu": "...", "date_envoi": "...", "auteur_id": "uuid" },
  "non_lus": 2
}
```
`dernier_message` is `null` if the conversation has no message.
⚠️ Front: the list is in `.items`, not the response itself.

### 🧪 GET /api/candidatures/{id}/messages
Who: the candidate or the owner company only (admin and others: 404) — paginated, oldest first. The first message is always the candidate's application message. Marks the other person's messages as read; `lu` in the response is the state **before** this call.
Item:
```json
{ "message_id": "uuid", "auteur_id": "uuid", "contenu": "...", "date_envoi": "...", "lu": true }
```
Front: poll every few seconds (no websocket).

### 🧪 POST /api/candidatures/{id}/messages
Who: the candidate or the owner company only (others: 404)
Body: `{ "contenu": "..." }` — 1–5000 characters
201: the message, same shape as an item of the GET above, with `lu: false`.
Every message is encrypted with the **candidate's** key, whoever writes it: deleting the candidate's account shreds the whole conversation.

---

## Not exposed on purpose

`session`, `token` and `DEK` (encryption keys) are security internals: no route reads or edits them, admin included.
