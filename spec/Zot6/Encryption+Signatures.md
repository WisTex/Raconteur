### Encryption

Sites provide in their site discovery document an array containing 0 or more encryption algorithms which it is willing to accept in order of preference. Sites sending encrypted documents MUST use this list to determine the most suitable algorithm to both parties. If a suitable algorithm cannot be negotiated, the site MAY fall back to plaintext (unencrypted data) but if the communications channel is not secured with SSL the sending site MUST NOT use plaintext and a receiving site MAY ignore or reject the communication if it contains private or sensitive information.

If the receiving site does not support the provided algorithm, it MUST return error 400.

Encrypted information is encapsulated into a JSON array/object with the following components:

````
'encrypted' => true
'key'       => The encryption key, base64urlencoded
'iv'        => The encryption initialisation vector, base64urlencoded
'alg'       => The encryption algorithm used
'data'      => The encrypted payload, base64urlencoded
````

The 'encrypted' boolean flag indicates this is a cryptographic structure and requires decryption to extract the information. 'alg' is required. Other elements may be change as necessary to support different mechanisms/algorithms. For instance some mechanisms may require an 'hmac' field. The elements shown support a wide variety of standard encryption algorithms. 

The key and iv are psuedo-random byte sequences, encrypted with the RSA public key of the recipient prior to base64urlencoding. The recipient key used in most cases (by default) will be the remote site public key. In certain circumstances (where specified) the RSA public key will be that of the target channel or recipient.

Both 'key' and 'iv' MAY be padded to 255 chars. The amount of padding necessary is dependent on the encryption algorithm. The incoming site MUST strip additional padding on both parameters to limit the maximum length supported by the chosen algorithm. For example, the aes256cbc algorithm (not recommended) uses a key length of 32 bytes and an iv of 16 bytes.

Algorithm names for common algorithms are the lowercase algorithm names used by the openssl library with punctuation removed. The openssl 'aes-256-ctr' algorithm for example is designated as 'aes256ctr'.

Uncommon algorithms which are unsupported by openssl may be used, but the exact algorithm names are undefined by this document. 


### Signatures

Identity provenance is provided using HTTP Signatures (draft-cavage-http-signatures-10 is the relevant specification currently). If using encrypted transport, the HTTP Signature MAY be encrypted using the same negotiated algorithm as is used in the message for envelope protection. See [HTTP Signatures](spec/HTTPSignatures/Home). If a site uses negotiated encryption as described in the preceding section, it MUST be capable of decrypting the HTTP Signatures. 

In several places of the communications where verification is associated with a third party which is not the sender of the relevant HTTP packet, signed data/objects are specified/required. Two signature methods may be used, depending on whether the signed data is a single value or a JSON object. The method used for single values is referred to here as SimpleSignatures. The object signature method used is traditionally known as salmon magic signatures, using the JSON serialisation.

#### Simple Signatures

A data value is signed with an appropriate RSA method and hash algorithm; for instance 'sha256' which indicates an RSA keypair signature using the designated RSA private key  and the 'sha256' hash algorithm. The result is base64url encoded, prepended with the algorithm name and a period (0x2e) and sent as an additional data item where specified. 

````
"foo_sig": "sha256.EvGSD2vi8qYcveHnb-rrlok07qnCXjn8YSeCDDXlbhILSabgvNsPpbe..."
````

To verify, the element content is separated at the first period to extract the algorithm and signature. The signature is base64urldecoded and verified using the appropriate method and hash algorithm using the designated RSA public key (in the case of RSA signatures). The appropriate key to use is defined elsewhere in the Zot protocol depending on the context of the signature.

Implementations MUST support RSA-SHA256 signatures. They MAY support additional signature methods. 

#### Salmon "magic envelope" Signatures with JSON serialisation.

````
{
  "signed": true,
  "data": "PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGl...",
  "data_type": "application/x-zot+json",
  "encoding": "base64url",
  "alg": "RSA-SHA256",
  "sigs": [
    {
    "value": "EvGSD2vi8qYcveHnb-rrlok07qnCXjn8YSeCDDXlbhILSabgvNsPpbe...",
    "key_id": "4k8ikoyC2Xh+8BiIeQ+ob7Hcd2J7/Vj3uM61dy9iRMI"
    }
  ]
}
````

The boolean "signed" element is not defined in the magic envelope specification. This is a boolean flag which indicates the current element is a signed object and requires verification and unpacking to retrieve the actual element content.  

The signed data is retrieved by unpacking the magic signature 'data' component. Unpacking is accomplished by stripping all whitespace characters (0x0d 0x0a 0x20 and 0x09) and applying base64 "url" decoding.

The key_id is the base64urlencoded identifier of the signer, which when applied to the Zot 'Discovery' process will result in locating the public key. This is typically the server base url or the channel "home" url. Webfinger identifiers (acct:user@domain) MAY also be used if the resultant webfinger document contains a discoverable public key (salmon-public-key or Webid key). 

Verification is performed by using the magic envelope verification method. First remove all whitespace characters from the 'data' value. Append the following fields together separated with a period (0x2e). 

data . data_type . encoding . algorithm

This is the "signed data". Verification will take place using the RSA verify function using the signed data, the base64urldecoded sigs.value,  algorithm specified in 'alg' and the public key found by performing Zot discovery on the base64urldecoded sigs.key_id. 

When performing Zot discovery for keys, it is important to verify that the principal returned in the discovery response matches the principal in the key_id and that the discovery response is likewise signed and validated.  

Some historical variants of magic signatures emit base64 url encoding with or without padding.

In this specification, encoding MUST be the string "base64url", indicating the url safe base64 encoding as described in RFC4648, and without any trailing padding using equals (=) characters

The unpacked data (once verified) may contain a single value or a compound object. If it contains a single value, this becomes the value of the enclosing element. Otherwise it is merged back as a compound object. 

Example: source document

````
{ 
    "guid": {
      "signed": true,
      "data": "PD94bWwgdmVyc2lvbj0nMS4wJyBlbmNvZGl...",
      "data_type": "application/x-zot+json",
      "encoding": "base64url",
      "alg": "RSA-SHA256",
      "sigs": [
        {
        "value": "EvGSD2vi8qYcveHnb-rrlok07qnCXjn8YSeCDDXlbhILSabgvNsPpbe...",
        "key_id": "4k8ikoyC2Xh+8BiIeQ+ob7Hcd2J7/Vj3uM61dy9iRMI"
        }
      ]
    },
    "address": "foo@bar"
}
````

Decoding the data parameter (and assuming successful signature verification) results in

````
"abc12345"
````

Merging with the original document produces

````
{
    "guid": "abc12345",
    "address": "foo@bar"
}
````

Example using a signed object containing multiple elements:


Decoding the data parameter (and assuming successful signature verification) results in

````
{
    "guid": "abc12345",
    "name": "Barbara Jenkins"
}
````

Merging this with the original document produces

````
{
    "guid": {
        "guid": "abc12345",
        "name": "Barbara Jenkins"
    },
    "address": "foo@bar"
}
````