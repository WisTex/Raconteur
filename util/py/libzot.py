# libzot.py: crypto primitives to support Zot6/Nomad

import hashlib
import whirlpool
import base64
from cryptography.hazmat.backends import default_backend
from cryptography.hazmat.primitives.asymmetric import rsa, padding
from cryptography.hazmat.primitives import serialization, hashes
from cryptography.exceptions import UnsupportedAlgorithm, AlreadyFinalized, InvalidSignature, NotYetFinalized, AlreadyUpdated, InvalidKey

# base64url implementations which support "no padding"

def base64urlnopad_encode(data: bytes) -> str:
    return base64.urlsafe_b64encode(data).decode("utf-8").replace("=","");

def base64urlnopad_decode(data: str) -> bytes:
    # restore any missing padding before calling the (strict) base64 decoder
    if (data.find('=') == -1):
        data += "=" * (-len(data) % 4)
    return base64.urlsafe_b64decode(data)


def generate_rsa_keypair() -> (str, str):
    key = rsa.generate_private_key(
        public_exponent = 65537,
        key_size = 4096,
        backend = default_backend()
    )
    prvkey_pem = key.private_bytes(
        encoding = serialization.Encoding.PEM,
        format = serialization.PrivateFormat.TraditionalOpenSSL,
        encryption_algorithm = serialization.NoEncryption()
    )
    pubkey = key.public_key()
    pubkey_pem = pubkey.public_bytes(
        encoding = serialization.Encoding.PEM,
        format = serialization.PublicFormat.SubjectPublicKeyInfo,
    )
    # convert bytes to str
    prvkey_pem = prvkey_pem.decode("utf-8")
    pubkey_pem = pubkey_pem.decode("utf-8")
    return prvkey_pem, pubkey_pem

def zot_sign(data: str, prvkey: str) -> str:
    key = serialization.load_pem_private_key(prvkey.encode("ascii"),password = None)    
    rawsig = key.sign(hashlib.sha256(data.encode("utf-8")).hexdigest().encode("utf-8"), padding.PKCS1v15(), hashes.SHA256())
    return 'sha256.' + base64.b64encode(rawsig).decode("utf-8")

def zot_verify(data: str, sig: str, pubkey: str) -> bool:
    key = serialization.load_pem_public_key(pubkey.encode("ascii"))
    alg, signature = sig.split('.')
    if alg == 'sha256':
        hashed = hashlib.sha256(data.encode("utf-8")).hexdigest().encode("utf-8")
        algorithm = hashes.SHA256()
    elif alg == 'sha512':
        hashed = hashlib.sha256(data.encode("utf-8")).hexdigest().encode("utf-8")
        algorithm = hashes.SHA512()
    else:
        hashed = ""
        
    rawsig = base64.b64decode(signature)

    try:
        key.verify(rawsig, hashed, padding.PKCS1v15(), algorithm)
        return True

    except UnsupportedAlgorithm:
        pass
    except AlreadyFinalized:
        pass
    except InvalidSignature:
        pass
    except NotYetFinalized:
        pass
    except AlreadyUpdated:
        pass
    except InvalidKey:
        pass
    except BaseException:
        pass
    return False


def make_xchan_hash(id_str: str,id_pubkey: str) -> str:
    wp = whirlpool.new(id_str.encode("utf-8") + id_pubkey.encode("utf-8"))
    return base64urlnopad_encode(wp.digest());

