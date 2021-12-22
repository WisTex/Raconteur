

from libzot import *
import re
import json


class JSalmon:

    def sign(data,key_id,key,data_type = 'application/x-nomad+json'):
        data = base64urlnopad_encode(data.encode("utf-8"))
        encoding = 'base64url'
        algorithm = 'RSA-SHA256'

        data = re.sub(r'\s+',"",data)
        fields = data + "." + base64urlnopad_encode(data_type.encode("utf-8")) + "." + base64urlnopad_encode(encoding.encode("utf-8")) + "." + base64urlnopad_encode(algorithm.encode("utf-8"))
        signature = base64urlnopad_encode(rsa_sign(fields,key).encode("utf-8"))
        return {
            'signed' : True,
            'data' : data,
            'data_type' : data_type,
            'encoding' : encoding,
            'alg'  : algorithm,
            'sigs' : { 'value' : signature, 'key_id' : base64urlnopad_encode(key_id.encode("utf-8")) }}


    def verify(x,key):
        if x['signed'] != True:
            return False

        signed_data = re.sub(r'\s+','', x['data'] + "." + base64urlnopad_encode(x['data_type'].encode("utf-8")) + "." + base64urlnopad_encode(x['encoding'].encode("utf-8")) + "." + base64urlnopad_encode(x['alg'].encode("utf-8")))

        binsig = base64urlnopad_decode(x['sigs']['value'])
        
        if rsa_verify(signed_data,binsig,key) == True:
            return True

        return False

    def unpack(data):
        return json.loads(base64urlnopad_decode(data))


        
#if __name__=="__main__":
#    prvkey,pubkey = generate_rsa_keypair()

#    s = JSalmon.sign('abc123','mykeyid',prvkey)
#    print (s)

#    if JSalmon.verify(s,pubkey):
#        print ('verified')
#    else:
#        print ('failed')
