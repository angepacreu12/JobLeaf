0. Shared building blocks (needed before or alongside the routes)

Piece	Where	Why
db(): PDO with a static connection	db_connection.php	one connection per request
require_role(string ...$roles): array	auth.php	require_auth() + fetch the role → 403 if not allowed
Routes with an id in the path (/api/annonces/{id})	index.php	⚠️ your router only matches exact paths today. The simplest fix: regex patterns in the table + preg_match, passing the id to the handler
generate_dek(), wrap_dek(), unwrap_dek()	crypto.php	DEK ↔ master key (SecretBox)
encrypt_field($plain, $dek), decrypt_field($blob, $dek)	crypto.php	encrypt/decrypt personal fields with a user’s DEK
create_dek(), find_dek(), delete_dek()	dek_store.php	DEK table (delete = crypto-shredding)
get_user_dek($pdo, $user_id)	logic	find + unwrap in one call
validation helpers: email format, length, uuid, int, enum	http_help.php or validate.php	reused in every handler
pagination: limit / offset from the query string	http_help.php	for every list

1. Account

Route	Who	Notes
POST /api/register	public	index → 409 if taken, Argon2id (seed params), DEK, encrypted fields, one transaction, role = candidate or company only
POST /api/verify-email	public	token table: create / find / consume, then email_verifie = true. For dev emails: Mailpit (open source, catches all mail locally)
GET /api/me	logged in	✅ done, extend later to return the decrypted profile
PATCH /api/me	logged in	re-encrypt changed fields; changing the email = new index + re-verify
POST /api/me/password	logged in	check the old one, rehash, delete the other sessions
DELETE /api/me	logged in	RGPD: delete DEK + sessions + user (crypto-shredding), in a transaction
GET /api/me/export	logged in	RGPD portability: all of the user’s data, decrypted (nice for the defense)
forgot / reset password	public	optional, also uses the token table

2. Company profile

Route	Who	Notes
GET /api/entreprises/{id}	public	public profile (not encrypted)
PUT /api/me/entreprise	company	create / update own profil_entreprise

3. Job ads (annonce)

Route	Who	Notes
GET /api/annonces	public	list of open ads, filters (place, contract, text), pagination
GET /api/annonces/{id}	public	detail (the “learn more” view)
POST /api/annonces	company	create
PATCH /api/annonces/{id}	owner company	ownership check: entreprise_id = current user
DELETE /api/annonces/{id} (or close)	owner company	close rather than delete if it has applications
GET /api/me/annonces	company	own ads, including closed / expired
expiry	—	switch statut to expiree (on read, or via a scheduled SQL query)

4. Applications (candidature)

Route	Who	Notes
POST /api/annonces/{id}/candidatures	candidate	CV / letter encrypted with the candidate’s DEK; ad must be open; duplicate → 409
GET /api/me/candidatures	candidate	own applications, decrypted with own DEK
GET /api/annonces/{id}/candidatures	owner company	decrypt each one with that candidate’s DEK, server-side
PATCH …/candidatures/{user_id} (status)	owner company	accept / reject, if your schema has a status
DELETE /api/me/candidatures/{annonce_id}	candidate	withdraw
CV as a file?	—	if yes: multipart upload, size / type checks, encrypt the bytes

5. Messages

Route	Who	Notes
GET /api/candidatures/{annonce_id}/{user_id}/messages	the candidate or the owner company	participants only, decrypted with the candidate’s DEK
POST same path	same	encrypt with the candidate’s DEK

6. Admin

Route	Who	Notes
list / edit / delete users, ads, applications	admin	⚠️ check your subject: job-board subjects often require an admin view with full CRUD on every table

7. Maintenance

purge expired sessions and used / expired tokens
expire ads past their end date

Suggested order

Building blocks: db(), router with ids, require_role, crypto helpers + DEK store
Register (uses all the crypto helpers, so it tests them at the same time)
Ads: the public list + detail first, so the front can start in parallel
Company profile → applications → messages
Account management / RGPD (edit, password, delete, export)
Admin