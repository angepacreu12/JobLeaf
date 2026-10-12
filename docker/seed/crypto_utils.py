"""
crypto_utils.py - Hashing and encryption rules of the job board DB.

Reuse these exact functions in your backend: data injected by inject.py
will then be readable by the app, and the other way round.

Keys (never stored in the DB):
    MASTER_KEY  32 bytes, base64 - encrypts every user's DEK
    HMAC_KEY    32 bytes, base64 - builds email_index (blind index)
They are read from environment variables, or from a .env file next to this script.
Create them with:  python inject.py --gen-keys

Libraries (open source): PyNaCl (libsodium) and argon2-cffi.
"""
import base64
import hashlib
import hmac
import os
from pathlib import Path

import nacl.secret
import nacl.utils
from argon2 import PasswordHasher
from argon2.exceptions import VerifyMismatchError

_ph = PasswordHasher()  # Argon2id with safe default parameters


# ------------------------------------------------------------------ keys

def generate_key_b64():
    return base64.b64encode(nacl.utils.random(32)).decode()


def _load_dotenv():
    for folder in (Path.cwd(), Path(__file__).resolve().parent):
        env = folder / ".env"
        if env.is_file():
            for line in env.read_text(encoding="utf-8").splitlines():
                line = line.strip()
                if line and not line.startswith("#") and "=" in line:
                    k, v = line.split("=", 1)
                    os.environ.setdefault(k.strip(), v.strip().strip('"').strip("'"))
            return env
    return None


def load_keys():
    """Return (master_key, hmac_key, master_key_version)."""
    _load_dotenv()
    try:
        master = base64.b64decode(os.environ["MASTER_KEY"])
        hkey = base64.b64decode(os.environ["HMAC_KEY"])
    except KeyError as e:
        raise SystemExit(f"Missing {e.args[0]}. Run:  python inject.py --gen-keys  and put the lines in a .env file")
    if len(master) != 32 or len(hkey) != 32:
        raise SystemExit("MASTER_KEY and HMAC_KEY must both be 32 bytes, base64 encoded")
    if master == hkey:
        raise SystemExit("MASTER_KEY and HMAC_KEY must be different")
    version = int(os.environ.get("MASTER_KEY_VERSION", "1"))
    return master, hkey, version


# ------------------------------------------------------------------ email (blind index)

def normalize_email(email):
    """'  Lea.Martin@Mail.TEST ' -> 'lea.martin@mail.test'. Always normalize before HMAC."""
    return email.strip().lower()


def email_index(hmac_key, email):
    """users.email_index - HMAC-SHA256 of the normalized email."""
    return hmac.new(hmac_key, normalize_email(email).encode(), hashlib.sha256).digest()


# ------------------------------------------------------------------ passwords

def hash_password(password):
    """users.password_hash - Argon2id (salt included in the result)."""
    return _ph.hash(password)


def verify_password(password_hash, password):
    try:
        return _ph.verify(password_hash, password)
    except VerifyMismatchError:
        return False


# ------------------------------------------------------------------ DEK (envelope encryption)

def new_dek():
    """A fresh random 32-byte data encryption key for one user."""
    return nacl.utils.random(nacl.secret.SecretBox.KEY_SIZE)


def wrap_dek(master_key, dek):
    """DEK.dek_chiffree - the DEK encrypted with the master key."""
    return bytes(nacl.secret.SecretBox(master_key).encrypt(dek))


def unwrap_dek(master_key, dek_chiffree):
    return bytes(nacl.secret.SecretBox(master_key).decrypt(bytes(dek_chiffree)))


# ------------------------------------------------------------------ field encryption

def encrypt(dek, text):
    """Any *_chiffre column. Output = nonce + ciphertext (XSalsa20-Poly1305). None stays None."""
    if text is None:
        return None
    return bytes(nacl.secret.SecretBox(dek).encrypt(text.encode("utf-8")))


def decrypt(dek, blob):
    if blob is None:
        return None
    return nacl.secret.SecretBox(dek).decrypt(bytes(blob)).decode("utf-8")


# ------------------------------------------------------------------ session / token

def hash_token(raw_token):
    """session.token_hash and token.token_hash - SHA-256 of the raw token.
    The raw token goes to the client (cookie or email link), only the hash is stored."""
    return hashlib.sha256(raw_token.encode()).digest()
