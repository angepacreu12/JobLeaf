# JobLeaf — Plateforme de recrutement chiffrée & RGPD

Projet Pré-MSc Epitech Montpellier (2026) réalisé par **Ange PACREU** et **Léa BOSSUAT**.

## Présentation

**JobLeaf** est un job board mettant en relation candidats (« les feuilles ») et entreprises autour d'offres d'emploi (« les branches ») avec une messagerie intégrée par candidature et une architecture de chiffrement de bout en bout au repos (Envelope Encryption + Crypto-shredding RGPD).

## Architecture technique

- **Back-end :** PHP 8.x (API REST JSON native sans framework lourd, typage strict `declare(strict_types=1)`), PDO PostgreSQL.
- **Base de données :** PostgreSQL (schéma relationnel avec UUID, index aveugles HMAC-SHA256 pour la recherche d'email, colonnes `BYTEA` chiffrées).
- **Sécurité & Cryptographie :**
  - Mots de passe hachés avec **Argon2id** (`memory_cost: 65536`, `time_cost: 3`, `threads: 4`) et protection contre les attaques temporelles (timing attacks) et le brute-force (verrouillage 15 minutes après 5 échecs).
  - **Chiffrement par enveloppe (Libsodium XSalsa20-Poly1305) :** Chaque utilisateur possède une clé de chiffrement de données (`DEK`) chiffrée par la `MASTER_KEY` serveur. Les données personnelles (nom, prénom, email, téléphone, CV PDF, messages) sont chiffrées avec la `DEK`.
  - **Crypto-shredding RGPD :** La suppression d'un compte détruit sa `DEK`, rendant toutes ses données chiffrées mathématiquement irrécupérables.
  - **Portabilité RGPD :** Export complet déchiffré au format JSON via `GET /api/me/export`.
- **Front-end :** HTML5, JavaScript ES6+ (`fetch`, `<template>`), Tailwind CSS v4.1.4.

## Prérequis & Installation (Docker)

1. Copier le fichier d'environnement et configurer les clés (32 octets encodés en Base64 pour `HMAC_KEY` et `MASTER_KEY`) :
   ```bash
   cp .env.example .env