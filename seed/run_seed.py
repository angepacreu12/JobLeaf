"""
run_seed.py - entry point of the "seed" container.

1. Connects to the db service (credentials from .env, host/port from compose)
2. If the users table already has rows -> does nothing (your test data is kept)
3. Otherwise -> generates seed_data.json if missing (--seed 42), then runs inject.py
   The report is written to seed/inject_report.json on the laptop.

To re-seed from scratch:  docker compose down -v && docker compose up -d
"""
import os
import subprocess
import sys
from pathlib import Path
from urllib.parse import quote

import psycopg

APP = Path("/app") if Path("/app").is_dir() else Path.cwd()


def env(name):
    value = os.environ.get(name, "").strip()
    if not value:
        sys.exit(f"[seed] {name} is missing or empty in .env")
    return value


def main():
    user, password, dbname = env("DATABASE_USER"), env("DATABASE_MDP"), env("DATABASE_NAME")
    host = os.environ.get("DB_HOST", "db")
    port = os.environ.get("DB_PORT", "5432")
    dsn = f"postgresql://{quote(user, safe='')}:{quote(password, safe='')}@{host}:{port}/{quote(dbname, safe='')}"

    with psycopg.connect(dsn) as conn:
        exists = conn.execute("SELECT to_regclass('public.users') IS NOT NULL").fetchone()[0]
        if not exists:
            sys.exit("[seed] table users does not exist: is db/init/01_schema.sql loaded? "
                     "(it only runs on an EMPTY volume -> docker compose down -v)")
        count = conn.execute("SELECT count(*) FROM users").fetchone()[0]

    if count > 0:
        print(f"[seed] users already has {count} rows -> nothing to do")
        return

    data_file = APP / "seed_data.json"
    if not data_file.is_file():
        print("[seed] seed_data.json not found -> generating it with --seed 42")
        subprocess.run([sys.executable, "generate.py", "--seed", "42", "--out", str(data_file)],
                       cwd=APP, check=True)

    print("[seed] empty database -> injecting seed_data.json")
    subprocess.run([sys.executable, "inject.py", str(data_file), "--dsn", dsn,
                    "--report", str(APP / "inject_report.json")],
                   cwd=APP, check=True)
    print("[seed] done -> seed/inject_report.json")


if __name__ == "__main__":
    main()
