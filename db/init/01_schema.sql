CREATE TYPE "role_user" AS ENUM (
  'candidat',
  'entreprise',
  'admin'
);

CREATE TYPE "categorie_annonce" AS ENUM (
  'Hopital',
  'Informatique',
  'Parfumerie'
);

CREATE TYPE "statut_annonce" AS ENUM (
  'brouillon',
  'publiee',
  'expiree',
  'pourvue'
);

CREATE TYPE "type_token" AS ENUM (
'verification_email',
'reset_password'
);

CREATE TABLE "users" (
  "user_id" UUID PRIMARY KEY DEFAULT (uuidv7()),
  "email_index" bytea UNIQUE NOT NULL,
  "password_hash" varchar NOT NULL,
  "email_chiffre" bytea NOT NULL,
  "nom_chiffre" bytea,
  "prenom_chiffre" bytea,
  "telephone_chiffre" bytea,
  "username" varchar UNIQUE,
  "role" role_user NOT NULL,
  "created_at" timestamptz,
  "echecs_connexion" integer DEFAULT 0,
  "bloque_jusqua" timestamptz,
  "email_verifie" boolean DEFAULT false
);

CREATE TABLE "profil_entreprise" (
  "user_id" UUID PRIMARY KEY,
  "nom_entreprise" varchar NOT NULL,
  "siret" varchar(14) UNIQUE,
  "secteur" varchar,
  "ville" varchar,
  "site_web" varchar,
  "description" text
);

CREATE TABLE "annonce" (
  "annonce_id" uuid PRIMARY KEY DEFAULT (uuidv7()),
  "reference" varchar UNIQUE NOT NULL,
  "titre" VARCHAR NOT NULL,
  "description" text NOT NULL,
  "type_contrat" varchar NOT NULL,
  "category" categorie_annonce,
  "teletravail" varchar,
  "salaire_min" numeric,
  "salaire_max" numeric,
  "niveau_experience" varchar,
  "ville" varchar,
  "statut" statut_annonce NOT NULL DEFAULT 'brouillon',
  "date_publication" timestamptz,
  "date_expiration" timestamptz,
  "entreprise_id" UUID NOT NULL,
  "created_at" timestamptz,
  "updated_at" timestamptz,
  "count_annonce" integer NOT NULL DEFAULT 0
);

CREATE TABLE "candidature" (
  "candidature_id" UUID PRIMARY KEY DEFAULT (uuidv7()),
  "annonce_id" UUID NOT NULL,
  "candidat_id" uuid NOT NULL,
  "cv_chiffre" bytea,
  "lettre_chiffree" bytea,
  "statut" varchar NOT NULL DEFAULT 'envoyee',
  "date_candidature" timestamptz
);

CREATE TABLE "message" (
  "message_id" UUID PRIMARY KEY DEFAULT (uuidv7()),
  "candidature_id" UUID NOT NULL,
  "auteur_id" UUID NOT NULL,
  "contenu_chiffre" bytea NOT NULL,
  "date_envoi" timestamptz NOT NULL,
  "lu" boolean DEFAULT false
);

CREATE TABLE "token" (
  "token_id" UUID PRIMARY KEY DEFAULT (uuidv7()),
  "user_id" UUID NOT NULL,
  "type" varchar NOT NULL,
  "token_hash" bytea UNIQUE NOT NULL,
  "expires_at" timestamptz NOT NULL,
  "used_at" timestamptz
);

CREATE TABLE "session" (
  "session_id" UUID PRIMARY KEY DEFAULT (uuidv7()),
  "user_id" UUID NOT NULL,
  "token_hash" bytea UNIQUE NOT NULL,
  "created_at" timestamptz NOT NULL,
  "expires_at" timestamptz NOT NULL,
  "user_agent" varchar
);

CREATE TABLE "DEK" (
  "user_id" UUID PRIMARY KEY,
  "dek_chiffree" bytea NOT NULL,
  "version_master_key" int NOT NULL DEFAULT 1
);

CREATE UNIQUE INDEX ON "candidature" ("annonce_id", "candidat_id");

COMMENT ON COLUMN "users"."email_index" IS 'HMAC of email, for login';

COMMENT ON COLUMN "users"."password_hash" IS 'Argon2id';

COMMENT ON COLUMN "users"."email_chiffre" IS 'encrypted with DEK';

COMMENT ON COLUMN "users"."nom_chiffre" IS 'encrypted with DEK';

COMMENT ON COLUMN "users"."prenom_chiffre" IS 'encrypted with DEK';

COMMENT ON TABLE "profil_entreprise" IS 'Public company info - stored in clear, visible to everyone';

COMMENT ON TABLE "candidature" IS 'Access rights come from the links: candidat_id + annonce.entreprise_id (checked by the app)';

COMMENT ON COLUMN "candidature"."cv_chiffre" IS 'encrypted with candidate DEK';

COMMENT ON COLUMN "candidature"."lettre_chiffree" IS 'encrypted with candidate DEK';

COMMENT ON TABLE "message" IS 'Conversation between candidate and company, attached to one candidature';

COMMENT ON COLUMN "message"."auteur_id" IS 'who wrote it: candidate or company';

COMMENT ON COLUMN "message"."contenu_chiffre" IS 'encrypted with CANDIDATE DEK';

COMMENT ON COLUMN "message"."lu" IS 'read by the other person';

COMMENT ON COLUMN "token"."type" IS 'verification_email / reset_password';

COMMENT ON COLUMN "token"."expires_at" IS 'short: 15 min to 24 h';

COMMENT ON COLUMN "token"."used_at" IS 'filled when used - a token works only once';

COMMENT ON COLUMN "session"."token_hash" IS 'hash of the cookie value, never the raw token';

COMMENT ON COLUMN "session"."user_agent" IS 'optional: which browser or device';

ALTER TABLE "profil_entreprise" ADD FOREIGN KEY ("user_id") REFERENCES "users" ("user_id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "annonce" ADD FOREIGN KEY ("entreprise_id") REFERENCES "profil_entreprise" ("user_id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "candidature" ADD FOREIGN KEY ("annonce_id") REFERENCES "annonce" ("annonce_id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "candidature" ADD FOREIGN KEY ("candidat_id") REFERENCES "users" ("user_id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "message" ADD FOREIGN KEY ("candidature_id") REFERENCES "candidature" ("candidature_id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "message" ADD FOREIGN KEY ("auteur_id") REFERENCES "users" ("user_id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "token" ADD FOREIGN KEY ("user_id") REFERENCES "users" ("user_id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "session" ADD FOREIGN KEY ("user_id") REFERENCES "users" ("user_id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;

ALTER TABLE "DEK" ADD FOREIGN KEY ("user_id") REFERENCES "users" ("user_id") ON DELETE CASCADE DEFERRABLE INITIALLY IMMEDIATE;
