

import libzot
import re
import json


class JSalmon:

    def sign(data,key_id,key,data_type = 'application/x-zot+json'):
        data = base64urlnopad_encode(data)
        encoding = 'base64url'
        algorithm = 'RSA-SHA256'

        data = re.sub(r'\s+',"",data)
        fields = data + "." + base64urlnopad_encode(data_type) + base64urlnopad_encode(encoding) + "." + base64urlnopad_encode(algorithm)
        signature = base64urlnopad_encode(rsa_sign(fields,key))
        return {
            'signed' : true,
            'data' : data,
            'data_type' : date_type,
            'encoding' : encoding,
            'sigs' = { 'value' : signature, 'key_id' : base64urlnopad_encode(key_id) }}


    def verify(x,key):
        if x['signed'] != True:
            return false

        signed_data = re.sub(r'\s+','', x['data'] + "." + base64urlnopad_encode(x['data_type']) + "." + base64urlnopad_encode(x['encoding']) + "." + base64urlnopad_encode(x['alg'])

        if rsa_verify(signed_data,base64urlnopad_decode(x['sigs']['value']),key):
            return true

        return false

    def unpack(data):
        return json.loads(base64urlnopad_decode(data))


        
