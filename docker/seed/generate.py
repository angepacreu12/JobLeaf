#!/usr/bin/env python3
"""
generate.py - Build a JSON file of random TEST data for the job board DB.

Everything in the JSON is PLAINTEXT, exactly as a user or a company would type it
(email, password, name, CV, messages...). Nothing is hashed or encrypted here:
that is the job of inject.py. This makes the JSON easy to read and debug.

Values are picked from pools.json (edit it to change names, cities, jobs...).
Long texts (descriptions, CV, letters, messages) are lorem ipsum.

Usage:
    python generate.py                          # default sizes, random seed
    python generate.py --seed 42                # same seed = same data every time
    python generate.py --candidats 30 --entreprises 8 --annonces 25 --out seed_data.json
"""
import argparse
import json
import random
import string
import unicodedata
from datetime import datetime, timedelta, timezone
from pathlib import Path

HERE = Path(__file__).resolve().parent


# ----------------------------------------------------------------- helpers

def ascii_slug(text, sep=""):
    """'Lefèvre-Müller' -> 'lefevremuller' (used for emails, usernames, URLs)."""
    text = unicodedata.normalize("NFKD", text).encode("ascii", "ignore").decode()
    keep = [c.lower() if c.isalnum() else sep for c in text]
    slug = "".join(keep)
    while sep and sep * 2 in slug:
        slug = slug.replace(sep * 2, sep)
    return slug.strip(sep)


def iso(dt):
    return dt.isoformat(timespec="seconds") if dt else None


class Gen:
    def __init__(self, pools, rng, now):
        self.p = pools
        self.r = rng
        self.now = now
        self.used_emails = set()
        self.used_usernames = set()
        self.used_sirets = set()
        self.used_codes = set()

    # --- lorem ipsum -------------------------------------------------------
    def sentence(self, min_w=6, max_w=14):
        words = self.r.choices(self.p["lorem_mots"], k=self.r.randint(min_w, max_w))
        return " ".join(words).capitalize() + "."

    def paragraph(self, min_s=3, max_s=6):
        return " ".join(self.sentence() for _ in range(self.r.randint(min_s, max_s)))

    def paragraphs(self, n_min=2, n_max=4):
        return "\n\n".join(self.paragraph() for _ in range(self.r.randint(n_min, n_max)))

    # --- identity ----------------------------------------------------------
    def password(self):
        """Readable but strong test password, e.g. 'Kp7m-Rt2x-9qWz!'."""
        alphabet = string.ascii_letters + string.digits
        while True:
            groups = ["".join(self.r.choices(alphabet, k=4)) for _ in range(3)]
            pwd = "-".join(groups) + "!"
            if any(c.isdigit() for c in pwd) and any(c.isupper() for c in pwd) and any(c.islower() for c in pwd):
                return pwd

    def email(self, local_hint):
        base = ascii_slug(local_hint, ".") or "user"
        domain = self.r.choice(self.p["domaines_email"])
        n = 0
        while True:
            local = base if n == 0 else f"{base}{n}"
            mail = f"{local}@{domain}"
            if mail not in self.used_emails:
                self.used_emails.add(mail)
                return mail
            n += 1

    def username(self, hint):
        base = ascii_slug(hint, "_")[:20] or "user"
        while True:
            name = f"{base}_{self.r.choice(self.p['pseudos_suffixes']).strip('_')}"
            if self.r.random() < 0.5:
                name += str(self.r.randint(1, 99))
            if name not in self.used_usernames:
                self.used_usernames.add(name)
                return name

    def siret(self):
        """14-digit SIRET that passes the Luhn check (like a real one)."""
        while True:
            digits = [self.r.randint(0, 9) for _ in range(13)]
            for check in range(10):
                candidate = digits + [check]
                total = 0
                for i, d in enumerate(reversed(candidate)):
                    if i % 2 == 1:
                        d *= 2
                        if d > 9:
                            d -= 9
                    total += d
                if total % 10 == 0:
                    s = "".join(map(str, candidate))
                    if s not in self.used_sirets:
                        self.used_sirets.add(s)
                        return s
                    break

    def company_code(self, name):
        """3 letters used in annonce.reference. Must be unique per company,
        otherwise two companies can produce the same reference."""
        letters = ascii_slug(name).upper()
        words = [ascii_slug(w).upper() for w in name.split() if ascii_slug(w)]
        candidates = [letters[:3], "".join(w[0] for w in words)[:3]]
        candidates += [letters[0] + letters[i] + letters[j]
                       for i in range(1, len(letters)) for j in range(i + 1, len(letters))]
        for code in candidates:
            if len(code) == 3 and code not in self.used_codes:
                self.used_codes.add(code)
                return code
        while True:
            code = "".join(self.r.choices(string.ascii_uppercase, k=3))
            if code not in self.used_codes:
                self.used_codes.add(code)
                return code

    def days_ago(self, a, b):
        """Random datetime between a and b days ago (a > b)."""
        seconds = self.r.randint(int(b * 86400), int(a * 86400))
        return self.now - timedelta(seconds=seconds)

    def between(self, start, end):
        if end <= start:
            return start
        return start + timedelta(seconds=self.r.randint(0, int((end - start).total_seconds())))


# ----------------------------------------------------------------- builders

def make_person(g, ref, role, cas_test=None, **overrides):
    prenom = overrides.get("prenom") or g.r.choice(g.p["prenoms"])
    nom = overrides.get("nom") or g.r.choice(g.p["noms"])
    created = g.days_ago(200, 40)
    user = {
        "ref": ref,
        "role": role,
        "email": g.email(f"{prenom}.{nom}"),
        "password": g.password(),
        "nom": nom,
        "prenom": prenom,
        "username": g.username(prenom + nom[:3]),
        "created_at": iso(created),
        "email_verifie": True,
        "echecs_connexion": 0,
        "bloque_jusqua": None,
    }
    user.update(overrides)
    if cas_test:
        user["_cas_test"] = cas_test
    return user


def build(pools, rng, n_cand, n_ent, n_ann):
    now = datetime.now(timezone.utc).replace(microsecond=0)
    g = Gen(pools, rng, now)
    data = {"users": [], "profils_entreprise": [], "annonces": [], "candidatures": [],
            "messages": [], "sessions": [], "tokens": []}

    # ---------------- admin
    admin = make_person(g, "admin_01", "admin", "Compte admin (accès complet en phase de test)")
    admin["email"] = "admin@jobboard.test"
    data["users"].append(admin)

    # ---------------- candidates (the first ones are edge cases)
    special = [
        ("Email non vérifié : un token verification_email existe", {"email_verifie": False}),
        ("Compte bloqué : 5 échecs de connexion, bloqué 15 min",
         {"echecs_connexion": 5, "bloque_jusqua": iso(now + timedelta(minutes=15))}),
        ("Nom et prénom non renseignés (colonnes facultatives à NULL)", {"nom": None, "prenom": None}),
        ("Caractères spéciaux : apostrophe, accents, tiret", {"nom": "O'Connor-Lefèvre", "prenom": "Zoé-Anaïs"}),
        ("Email saisi en majuscules avec espaces : la connexion doit marcher en minuscules", "UPPER"),
        ("Candidat sans aucune candidature", "NO_APPLY"),
    ]
    no_apply = set()
    for i in range(1, n_cand + 1):
        ref = f"cand_{i:02d}"
        cas, over = (special[i - 1] if i <= len(special) else (None, {}))
        if over == "UPPER":
            u = make_person(g, ref, "candidat", cas)
            u["email"] = "  " + u["email"].upper() + " "
        elif over == "NO_APPLY":
            u = make_person(g, ref, "candidat", cas)
            no_apply.add(ref)
        else:
            u = make_person(g, ref, "candidat", cas, **over)
        data["users"].append(u)
    candidates = [u for u in data["users"] if u["role"] == "candidat"]

    # ---------------- companies
    categories = list(pools["entreprises"].keys())
    companies = []
    used_names = set()
    for i in range(1, n_ent + 1):
        ref = f"ent_{i:02d}"
        cat = categories[(i - 1) % len(categories)]
        names = [n for n in pools["entreprises"][cat]["noms"] if n not in used_names] or pools["entreprises"][cat]["noms"]
        nom_ent = rng.choice(names)
        used_names.add(nom_ent)
        cas = "Entreprise sans aucune annonce" if i == n_ent and n_ent > 1 else None
        u = make_person(g, ref, "entreprise", cas)
        u["email"] = g.email(f"contact.{ascii_slug(nom_ent)}")
        u["username"] = g.username(ascii_slug(nom_ent))
        u["created_at"] = iso(g.days_ago(220, 150))
        data["users"].append(u)
        data["profils_entreprise"].append({
            "user_ref": ref,
            "nom_entreprise": nom_ent,
            "siret": g.siret(),
            "secteur": pools["entreprises"][cat]["secteur"],
            "ville": rng.choice(pools["villes"]),
            "site_web": f"https://www.{ascii_slug(nom_ent, '-')}{rng.choice(pools['sites_web_tld'])}",
            "description": g.paragraph(),
        })
        companies.append({"ref": ref, "cat": cat, "nom": nom_ent, "created": u["created_at"],
                          "code": g.company_code(nom_ent), "no_annonce": cas is not None})

    # ---------------- annonces
    posting = [c for c in companies if not c["no_annonce"]] or companies
    weights = {k: v for k, v in pools["statut_annonce_poids"].items() if not k.startswith("_")}
    for i in range(1, n_ann + 1):
        c = posting[(i - 1) % len(posting)]
        statut = rng.choices(list(weights), weights=list(weights.values()))[0]
        if i == 1:
            statut = "brouillon"
        contrat = rng.choice(pools["type_contrat"])
        created = g.between(datetime.fromisoformat(c["created"]) + timedelta(days=1), now - timedelta(days=5))
        pub = exp = None
        if statut != "brouillon":
            pub = created + timedelta(days=rng.randint(0, 3))
            if statut == "expiree":
                pub = g.days_ago(140, 70)
                created = pub - timedelta(days=rng.randint(0, 3))
                exp = g.between(pub + timedelta(days=30), now - timedelta(days=1))
            elif statut == "publiee":
                exp = max(pub + timedelta(days=rng.randint(30, 60)), now + timedelta(days=rng.randint(5, 40)))
            else:  # pourvue
                exp = pub + timedelta(days=rng.randint(30, 60))
        updated = g.between(created, pub or now)

        smin = smax = None
        if not (i == 2):  # annonce 2: no salary shown (edge case)
            lo, hi = pools["salaire_par_contrat"][contrat]
            smin = rng.randrange(lo, hi, 500)
            smax = min(hi, smin + rng.randrange(0, 12001, 500))

        a = {
            "ref": f"ann_{i:02d}",
            "entreprise_ref": c["ref"],
            "reference": None,  # filled below, in date order
            "titre": rng.choice(pools["entreprises"][c["cat"]]["titres"]),
            "description": g.paragraphs(2, 4),
            "type_contrat": contrat,
            "category": c["cat"],
            "teletravail": rng.choice(pools["teletravail"]) if c["cat"] == "Informatique" else "sur_site",
            "salaire_min": smin,
            "salaire_max": smax,
            "niveau_experience": rng.choice(pools["niveau_experience"]),
            "ville": rng.choice(pools["villes"]),
            "statut": statut,
            "date_publication": iso(pub),
            "date_expiration": iso(exp),
            "created_at": iso(created),
            "updated_at": iso(updated),
        }
        if i == 1:
            a["_cas_test"] = "Annonce en brouillon : pas de date de publication, invisible pour les candidats"
        if i == 2:
            a["_cas_test"] = "Annonce sans salaire (salaire_min et salaire_max à NULL)"
        data["annonces"].append(a)

    # references: YY-MM-[3 letters]-NN, NN counted per company in date order
    by_company = {}
    for a in data["annonces"]:
        by_company.setdefault(a["entreprise_ref"], []).append(a)
    codes = {c["ref"]: c["code"] for c in companies}
    for ref, ads in by_company.items():
        ads.sort(key=lambda x: x["date_publication"] or x["created_at"])
        for n, a in enumerate(ads, start=1):
            d = datetime.fromisoformat(a["date_publication"] or a["created_at"])
            a["reference"] = f"{d:%y}-{d:%m}-{codes[ref]}-{n:02d}"

    # ---------------- candidatures + messages
    open_ads = [a for a in data["annonces"] if a["statut"] != "brouillon"]
    appliers = [u for u in candidates if u["ref"] not in no_apply]
    n_cv_missing = 0
    k = 0
    for a in open_ads:
        pub = datetime.fromisoformat(a["date_publication"])
        end = min(datetime.fromisoformat(a["date_expiration"]), now)
        pool = [u for u in appliers if datetime.fromisoformat(u["created_at"]) < end]
        for u in rng.sample(pool, k=min(len(pool), rng.randint(1, 4))):
            start = max(pub, datetime.fromisoformat(u["created_at"]))
            date_c = g.between(start, end)
            k += 1
            who = " ".join(x for x in (u["prenom"], u["nom"]) if x) or u["username"]
            cand = {
                "ref": f"app_{k:03d}",
                "annonce_ref": a["ref"],
                "candidat_ref": u["ref"],
                "cv": f"CV – {who}\n\n" + g.paragraphs(2, 3),
                "lettre": f"Madame, Monsieur,\n\n{g.paragraph()}\n\nCordialement,\n{who}",
                "statut": rng.choice(pools["statut_candidature"]),
                "date_candidature": iso(date_c),
            }
            if n_cv_missing == 0:
                cand["cv"] = None
                cand["_cas_test"] = "Candidature sans CV (cv_chiffre à NULL)"
                n_cv_missing += 1
            data["candidatures"].append(cand)

            # conversation: 0 to 4 messages, alternating company / candidate
            t = date_c
            author_is_company = True
            for m in range(rng.randint(0, 4)):
                t = g.between(t + timedelta(minutes=30), min(t + timedelta(days=4), now))
                if t >= now:
                    break
                data["messages"].append({
                    "ref": f"msg_{len(data['messages']) + 1:03d}",
                    "candidature_ref": cand["ref"],
                    "auteur_ref": a["entreprise_ref"] if author_is_company else u["ref"],
                    "contenu": " ".join(g.sentence() for _ in range(rng.randint(1, 3))),
                    "date_envoi": iso(t),
                    "lu": True,
                })
                author_is_company = not author_is_company
            msgs = [x for x in data["messages"] if x["candidature_ref"] == cand["ref"]]
            if msgs and rng.random() < 0.5:
                msgs[-1]["lu"] = False

    # ---------------- sessions (cookie tokens)
    def raw_token():
        return "".join(rng.choices(string.ascii_letters + string.digits, k=43))

    s = 0
    for u in data["users"]:
        if rng.random() < 0.6 or u["role"] == "admin":
            s += 1
            created = now - timedelta(minutes=rng.randint(1, 50))
            data["sessions"].append({
                "ref": f"sess_{s:02d}", "user_ref": u["ref"], "token": raw_token(),
                "created_at": iso(created), "expires_at": iso(created + timedelta(hours=1)),
                "user_agent": rng.choice(pools["user_agents"]), "_etat": "active",
            })
        if rng.random() < 0.3:
            s += 1
            created = g.days_ago(30, 2)
            data["sessions"].append({
                "ref": f"sess_{s:02d}", "user_ref": u["ref"], "token": raw_token(),
                "created_at": iso(created), "expires_at": iso(created + timedelta(hours=1)),
                "user_agent": rng.choice(pools["user_agents"]), "_etat": "expirée",
            })

    # ---------------- tokens (email verification / password reset)
    t = 0
    for u in data["users"]:
        if not u["email_verifie"]:
            t += 1
            created = now - timedelta(hours=2)
            data["tokens"].append({
                "ref": f"tok_{t:02d}", "user_ref": u["ref"], "type": "verification_email",
                "token": raw_token(), "expires_at": iso(created + timedelta(days=1)),
                "used_at": None, "_etat": "valide, pas encore utilisé",
            })
    reset_targets = rng.sample(candidates, k=min(3, len(candidates)))
    states = ["valide, pas encore utilisé", "déjà utilisé", "expiré"]
    for u, state in zip(reset_targets, states):
        t += 1
        if state == "expiré":
            exp, used = now - timedelta(days=2), None
        elif state == "déjà utilisé":
            exp, used = now + timedelta(hours=20), now - timedelta(hours=3)
        else:
            exp, used = now + timedelta(hours=23), None
        data["tokens"].append({
            "ref": f"tok_{t:02d}", "user_ref": u["ref"], "type": "reset_password",
            "token": raw_token(), "expires_at": iso(exp), "used_at": iso(used), "_etat": state,
        })

    return data, now


def main():
    ap = argparse.ArgumentParser(description="Generate plaintext test data (JSON) for the job board DB")
    ap.add_argument("--candidats", type=int, default=20)
    ap.add_argument("--entreprises", type=int, default=6)
    ap.add_argument("--annonces", type=int, default=20)
    ap.add_argument("--seed", type=int, default=None, help="same seed = same data")
    ap.add_argument("--pools", default=str(HERE / "pools.json"))
    ap.add_argument("--out", default="seed_data.json")
    args = ap.parse_args()

    if args.candidats < 1 or args.entreprises < 1 or args.annonces < 1:
        ap.error("--candidats, --entreprises and --annonces must be >= 1")

    seed = args.seed if args.seed is not None else random.SystemRandom().randint(1, 10**9)
    rng = random.Random(seed)
    pools = json.loads(Path(args.pools).read_text(encoding="utf-8"))

    data, now = build(pools, rng, args.candidats, args.entreprises, args.annonces)
    out = {
        "meta": {
            "info": "PLAINTEXT test data. Fields starting with _ are notes for testing and are not inserted.",
            "seed": seed,
            "generated_at": iso(now),
            "counts": {k: len(v) for k, v in data.items()},
        },
        **data,
    }
    Path(args.out).write_text(json.dumps(out, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"Wrote {args.out}  (seed {seed})")
    for k, v in out["meta"]["counts"].items():
        print(f"  {k:<20} {v}")


if __name__ == "__main__":
    main()
