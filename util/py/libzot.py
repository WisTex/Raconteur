
import whirlpool
import base64

# base64url implementations which are designed to support "no padding"

def base64urlnopad_encode(data: bytes) -> str:
    return base64.urlsafe_b64encode(data).decode("utf-8").replace("=","");

def base64urlnopad_decode(data: str) -> bytes:
    # restore any missing padding before calling the (strict) base64 decoder
    if (data.find('=') == -1):
        data += "=" * (-len(data) % 4)
    return base64.urlsafe_b64decode(data)

def make_xchan_hash(id_str: str,id_pubkey: str) -> str:
    wp = whirlpool.new(id_str.encode("utf-8") + id_pubkey.encode("utf-8"))
    return base64urlnopad_encode(wp.digest());

