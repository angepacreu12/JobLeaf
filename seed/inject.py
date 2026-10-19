#!/usr/bin/env python3
"""
inject.py - Insert the JSON produced by generate.py into PostgreSQL.

For each row it applies the same rules as the real app (see crypto_utils.py):
    password        -> Argon2id hash
    email           -> HMAC blind index + encrypted copy
    nom, prenom     -> encrypted with the user's DEK
    cv, lettre,     -> encrypted with the CANDIDATE's DEK
    message
    session/token   -> SHA-256 of the raw token
    DEK             -> random key, encrypted with the master key

Then it writes a REPORT (JSON) listing, table by table, what was sent
(plaintext) and what was stored (hash / ciphertext preview), with the UUID
the database gave to each row. Use it to log in and test.

Everything runs in ONE transaction: if anything fails, nothing is inserted.
After inserting, it reads the data back and checks it can be decrypted.

Usage:
    python inject.py --gen-keys                       # print 2 new keys for your .env
    python inject.py seed_data.json --dsn postgresql://user:pass@localhost:5432/jobboard
    python inject.py seed_data.json --reset           # empty the tables first (TEST DB ONLY)
    python inject.py seed_data.json --dry-run         # do everything, then roll back

The DSN can also come from the DATABASE_URL variable (environment or .env).
"""
import argparse
import json
import os
import sys
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urlsplit, urlunsplit

import crypto_utils as cu

try:
    import psycopg
except ImportError:
    sys.exit("psycopg is missing:  pip install -r requirements.txt")

# Order matters for --reset (children first)
TABLES = ["message", "candidature", "annonce", "session", "token", '"DEK"', "profil_entreprise", "users"]


# ------------------------------------------------------------------ small helpers

def ts(value):
    return datetime.fromisoformat(value) if value else None


def preview(blob, n=12):
    """bytes -> 'a3f9c1d2e4b5…(72 bytes)' so the report stays readable."""
    if blob is None:
        return None
    b = bytes(blob)
    return f"{b[:n].hex()}…({len(b)} bytes)"


def public(row):
    """Drop the _notes and keep the plaintext values that were sent."""
    return {k: v for k, v in row.items() if not k.startswith("_") and k != "ref"}


def notes(row):
    return {k[1:]: v for k, v in row.items() if k.startswith("_")}


def safe_dsn(dsn):
    try:
        p = urlsplit(dsn)
        if p.password:
            netloc = p.netloc.replace(f":{p.password}@", ":***@")
            return urlunsplit((p.scheme, netloc, p.path, p.query, p.fragment))
    except ValueError:
        pass
    return dsn


# ------------------------------------------------------------------ injection

def inject(conn, data, master, hkey, key_version):
    report = {k: [] for k in ("users", "profils_entreprise", "annonces", "candidatures",
                              "messages", "sessions", "tokens")}
    user_id, user_dek, annonce_id, cand_id, cand_owner = {}, {}, {}, {}, {}

    with conn.cursor() as cur:
        # ---------- users + DEK
        for u in data["users"]:
            dek = cu.new_dek()
            email_norm = cu.normalize_email(u["email"])
            stored = {
                "email_index": cu.email_index(hkey, u["email"]),
                "password_hash": cu.hash_password(u["password"]),
                "email_chiffre": cu.encrypt(dek, email_norm),
                "nom_chiffre": cu.encrypt(dek, u.get("nom")),
                "prenom_chiffre": cu.encrypt(dek, u.get("prenom")),
            }
            cur.execute(
                """INSERT INTO users (email_index, password_hash, email_chiffre, nom_chiffre, prenom_chiffre,
                                      username, role, created_at, echecs_connexion, bloque_jusqua, email_verifie)
                   VALUES (%s, %s, %s, %s, %s, %s, %s::role_user, %s, %s, %s, %s)
                   RETURNING user_id""",
                (stored["email_index"], stored["password_hash"], stored["email_chiffre"],
                 stored["nom_chiffre"], stored["prenom_chiffre"], u["username"], u["role"],
                 ts(u["created_at"]), u["echecs_connexion"], ts(u["bloque_jusqua"]), u["email_verifie"]))
            uid = cur.fetchone()[0]
            wrapped = cu.wrap_dek(master, dek)
            cur.execute('INSERT INTO "DEK" (user_id, dek_chiffree, version_master_key) VALUES (%s, %s, %s)',
                        (uid, wrapped, key_version))
            user_id[u["ref"]], user_dek[u["ref"]] = uid, dek
            report["users"].append({
                "ref": u["ref"], "user_id": str(uid), **({"cas_test": u["_cas_test"]} if "_cas_test" in u else {}),
                "envoye": {**public(u), "email_normalise": email_norm},
                "stocke": {
                    "email_index": preview(stored["email_index"]),
                    "password_hash": stored["password_hash"],
                    "email_chiffre": preview(stored["email_chiffre"]),
                    "nom_chiffre": preview(stored["nom_chiffre"]),
                    "prenom_chiffre": preview(stored["prenom_chiffre"]),
                    "DEK.dek_chiffree": preview(wrapped),
                    "DEK.version_master_key": key_version,
                },
            })

        # ---------- profil_entreprise (clear text, public)
        for p in data["profils_entreprise"]:
            uid = user_id[p["user_ref"]]
            cur.execute(
                """INSERT INTO profil_entreprise (user_id, nom_entreprise, siret, secteur, ville, site_web, description)
                   VALUES (%s, %s, %s, %s, %s, %s, %s)""",
                (uid, p["nom_entreprise"], p["siret"], p["secteur"], p["ville"], p["site_web"], p["description"]))
            report["profils_entreprise"].append({"user_ref": p["user_ref"], "user_id": str(uid),
                                                 "envoye": public(p), "stocke": "identique (en clair)"})

        # ---------- annonce (clear text, public)
        for a in data["annonces"]:
            cur.execute(
                """INSERT INTO annonce (reference, titre, description, type_contrat, category, teletravail,
                                        salaire_min, salaire_max, niveau_experience, ville, statut,
                                        date_publication, date_expiration, entreprise_id, created_at, updated_at)
                   VALUES (%s, %s, %s, %s, %s::categorie_annonce, %s, %s, %s, %s, %s, %s::statut_annonce,
                           %s, %s, %s, %s, %s)
                   RETURNING annonce_id""",
                (a["reference"], a["titre"], a["description"], a["type_contrat"], a["category"], a["teletravail"],
                 a["salaire_min"], a["salaire_max"], a["niveau_experience"], a["ville"], a["statut"],
                 ts(a["date_publication"]), ts(a["date_expiration"]), user_id[a["entreprise_ref"]],
                 ts(a["created_at"]), ts(a["updated_at"])))
            aid = cur.fetchone()[0]
            annonce_id[a["ref"]] = aid
            report["annonces"].append({"ref": a["ref"], "annonce_id": str(aid),
                                       "entreprise_id": str(user_id[a["entreprise_ref"]]),
                                       **({"cas_test": a["_cas_test"]} if "_cas_test" in a else {}),
                                       "envoye": public(a), "stocke": "identique (en clair)"})

        # ---------- candidature (CV + letter encrypted with the CANDIDATE's DEK)
        for c in data["candidatures"]:
            dek = user_dek[c["candidat_ref"]]
            cv, lettre = cu.encrypt(dek, c["cv"]), cu.encrypt(dek, c["lettre"])
            cur.execute(
                """INSERT INTO candidature (annonce_id, candidat_id, cv_chiffre, lettre_chiffree, statut, date_candidature)
                   VALUES (%s, %s, %s, %s, %s, %s) RETURNING candidature_id""",
                (annonce_id[c["annonce_ref"]], user_id[c["candidat_ref"]], cv, lettre, c["statut"],
                 ts(c["date_candidature"])))
            cid = cur.fetchone()[0]
            cand_id[c["ref"]], cand_owner[c["ref"]] = cid, c["candidat_ref"]
            report["candidatures"].append({
                "ref": c["ref"], "candidature_id": str(cid),
                "annonce_id": str(annonce_id[c["annonce_ref"]]), "candidat_id": str(user_id[c["candidat_ref"]]),
                **({"cas_test": c["_cas_test"]} if "_cas_test" in c else {}),
                "envoye": public(c),
                "stocke": {"cv_chiffre": preview(cv), "lettre_chiffree": preview(lettre),
                           "cle_utilisee": f"DEK du candidat {c['candidat_ref']}"},
            })

        # ---------- message (always the CANDIDATE's DEK, whoever wrote it)
        for m in data["messages"]:
            owner = cand_owner[m["candidature_ref"]]
            blob = cu.encrypt(user_dek[owner], m["contenu"])
            cur.execute(
                """INSERT INTO message (candidature_id, auteur_id, contenu_chiffre, date_envoi, lu)
                   VALUES (%s, %s, %s, %s, %s) RETURNING message_id""",
                (cand_id[m["candidature_ref"]], user_id[m["auteur_ref"]], blob, ts(m["date_envoi"]), m["lu"]))
            mid = cur.fetchone()[0]
            report["messages"].append({
                "ref": m["ref"], "message_id": str(mid), "candidature_id": str(cand_id[m["candidature_ref"]]),
                "auteur_id": str(user_id[m["auteur_ref"]]),
                "envoye": public(m),
                "stocke": {"contenu_chiffre": preview(blob), "cle_utilisee": f"DEK du candidat {owner}"},
            })

        # ---------- session (only the hash of the cookie value)
        for s in data["sessions"]:
            h = cu.hash_token(s["token"])
            cur.execute(
                """INSERT INTO session (user_id, token_hash, created_at, expires_at, user_agent)
                   VALUES (%s, %s, %s, %s, %s) RETURNING session_id""",
                (user_id[s["user_ref"]], h, ts(s["created_at"]), ts(s["expires_at"]), s["user_agent"]))
            sid = cur.fetchone()[0]
            report["sessions"].append({"ref": s["ref"], "session_id": str(sid), "user_id": str(user_id[s["user_ref"]]),
                                       **notes(s), "envoye": public(s),
                                       "stocke": {"token_hash": preview(h)},
                                       "cookie_a_utiliser": s["token"]})

        # ---------- token (only the hash of the raw token)
        for t in data["tokens"]:
            h = cu.hash_token(t["token"])
            cur.execute(
                """INSERT INTO token (user_id, type, token_hash, expires_at, used_at)
                   VALUES (%s, %s::type_token, %s, %s, %s) RETURNING token_id""",
                (user_id[t["user_ref"]], t["type"], h, ts(t["expires_at"]), ts(t["used_at"])))
            tid = cur.fetchone()[0]
            report["tokens"].append({"ref": t["ref"], "token_id": str(tid), "user_id": str(user_id[t["user_ref"]]),
                                     **notes(t), "envoye": public(t),
                                     "stocke": {"token_hash": preview(h)},
                                     "token_brut_pour_lien": t["token"]})

    return report, list(cand_id.values())


# ------------------------------------------------------------------ read-back check

def verify(conn, data, master, hkey, cand_ids):
    """Read everything back from the DB and check the crypto round trip."""
    problems = []
    checked = {"login_email_index": 0, "password": 0, "email": 0, "nom_prenom": 0, "cv_lettre": 0, "messages": 0}
    with conn.cursor() as cur:
        deks = {}
        for u in data["users"]:
            cur.execute('''SELECT u.user_id, u.password_hash, u.email_chiffre, u.nom_chiffre, u.prenom_chiffre,
                                  d.dek_chiffree
                           FROM users u JOIN "DEK" d ON d.user_id = u.user_id
                           WHERE u.email_index = %s''', (cu.email_index(hkey, u["email"]),))
            row = cur.fetchone()
            if not row:
                problems.append(f"{u['ref']}: login lookup by email_index failed")
                continue
            checked["login_email_index"] += 1
            uid, pwh, email_c, nom_c, prenom_c, dek_c = row
            if cu.verify_password(pwh, u["password"]):
                checked["password"] += 1
            else:
                problems.append(f"{u['ref']}: password does not match its hash")
            dek = cu.unwrap_dek(master, dek_c)
            deks[uid] = dek
            if cu.decrypt(dek, email_c) == cu.normalize_email(u["email"]):
                checked["email"] += 1
            else:
                problems.append(f"{u['ref']}: email decrypts to a different value")
            if cu.decrypt(dek, nom_c) == u["nom"] and cu.decrypt(dek, prenom_c) == u["prenom"]:
                checked["nom_prenom"] += 1
            else:
                problems.append(f"{u['ref']}: nom/prenom decrypt mismatch")

        # only the rows inserted by this run
        cur.execute("""SELECT candidature_id, candidat_id, cv_chiffre, lettre_chiffree
                       FROM candidature WHERE candidature_id = ANY(%s)""", (cand_ids,))
        for cid, owner, cv, lettre in cur.fetchall():
            try:
                cu.decrypt(deks[owner], cv)
                cu.decrypt(deks[owner], lettre)
                checked["cv_lettre"] += 1
            except Exception as e:  # noqa: BLE001
                problems.append(f"candidature {cid}: {e}")

        cur.execute("""SELECT m.message_id, c.candidat_id, m.contenu_chiffre
                       FROM message m JOIN candidature c ON c.candidature_id = m.candidature_id
                       WHERE c.candidature_id = ANY(%s)""", (cand_ids,))
        for mid, owner, blob in cur.fetchall():
            try:
                cu.decrypt(deks[owner], blob)
                checked["messages"] += 1
            except Exception as e:  # noqa: BLE001
                problems.append(f"message {mid}: {e}")
    return checked, problems


# ------------------------------------------------------------------ main

def main():
    ap = argparse.ArgumentParser(description="Inject generate.py JSON into the job board DB")
    ap.add_argument("json_file", nargs="?", default="seed_data.json")
    ap.add_argument("--dsn", help="postgresql://user:pass@host:5432/dbname (or DATABASE_URL)")
    ap.add_argument("--reset", action="store_true", help="TRUNCATE all tables first - TEST DATABASE ONLY")
    ap.add_argument("--dry-run", action="store_true", help="run everything then ROLLBACK (nothing kept)")
    ap.add_argument("--report", default="inject_report.json")
    ap.add_argument("--gen-keys", action="store_true", help="print new MASTER_KEY and HMAC_KEY and exit")
    args = ap.parse_args()

    if args.gen_keys:
        print("# Put these lines in a .env file (keep it OUT of git)")
        print(f"MASTER_KEY={cu.generate_key_b64()}")
        print(f"HMAC_KEY={cu.generate_key_b64()}")
        print("MASTER_KEY_VERSION=1")
        return

    master, hkey, key_version = cu.load_keys()
    dsn = args.dsn or os.environ.get("DATABASE_URL")
    if not dsn:
        sys.exit("No database: use --dsn postgresql://... or set DATABASE_URL")

    data = json.loads(Path(args.json_file).read_text(encoding="utf-8"))
    print(f"Source : {args.json_file} (seed {data.get('meta', {}).get('seed')})")
    print(f"Target : {safe_dsn(dsn)}{'  [DRY RUN]' if args.dry_run else ''}")

    with psycopg.connect(dsn) as conn:  # one transaction until commit/rollback
        try:
            if args.reset:
                conn.execute("TRUNCATE " + ", ".join(TABLES) + " CASCADE")
                print("Tables emptied (--reset)")
            report, cand_ids = inject(conn, data, master, hkey, key_version)
            checked, problems = verify(conn, data, master, hkey, cand_ids)
            if problems:
                raise RuntimeError("Read-back check failed:\n  " + "\n  ".join(problems))
            if args.dry_run:
                conn.rollback()
            else:
                conn.commit()
        except psycopg.errors.UniqueViolation as e:
            conn.rollback()
            sys.exit(f"\nERROR - transaction rolled back, nothing was inserted.\n{e.diag.message_primary}\n"
                     "This data is probably already in the DB: add --reset (test DB only) or generate with another --seed.")
        except Exception:
            conn.rollback()
            print("\nERROR - transaction rolled back, nothing was inserted.\n")
            raise

    counts = {k: len(v) for k, v in report.items()}
    accounts = [{
        "role": u["envoye"]["role"],
        "email": u["envoye"]["email_normalise"],
        "password": u["envoye"]["password"],
        "username": u["envoye"]["username"],
        "user_id": u["user_id"],
        **({"cas_test": u["cas_test"]} if "cas_test" in u else {}),
    } for u in report["users"]]

    full = {
        "meta": {
            "info": "TEST DATA ONLY - contains plaintext passwords and raw tokens. Never use with real users.",
            "source": args.json_file,
            "seed": data.get("meta", {}).get("seed"),
            "base": safe_dsn(dsn),
            "injecte_le": datetime.now(timezone.utc).isoformat(timespec="seconds"),
            "dry_run": args.dry_run,
            "master_key_version": key_version,
            "nombre_de_lignes": counts,
            "verification_relecture": checked,
        },
        "comptes_de_test": accounts,
        **report,
    }
    Path(args.report).write_text(json.dumps(full, ensure_ascii=False, indent=2), encoding="utf-8")

    print("\nRows inserted" + (" (then rolled back)" if args.dry_run else "") + ":")
    for k, v in counts.items():
        print(f"  {k:<20} {v}")
    print("\nRead-back check: " + ", ".join(f"{k} {v}" for k, v in checked.items()) + "  -> OK")
    print("\nTest accounts:")
    print(f"  {'role':<11} {'email':<42} {'password':<17} note")
    for a in accounts:
        print(f"  {a['role']:<11} {a['email']:<42} {a['password']:<17} {a.get('cas_test', '')}")
    print(f"\nFull report: {args.report}")


if __name__ == "__main__":
    main()
