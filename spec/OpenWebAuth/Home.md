### OpenWebAuth

OpenWebAuth provides a light-weight form of cross-domain authentication between websites on the open web. The principals involved in the authentication may or may not have any pre-existing relationship. 

OpenWebAuth utilises webfinger (RFC7033) and HTTP Signatures (draft-cavage-http-signatures-09) with a simple token generation service to provide seamless and interaction free authentication between diverse websites.

For example, on website podunk.edu a member has made a video available privately to bob@example.com. In order for bob@example.com to verify his identity and view the protected video, he must establish proof of his identity to podunk.edu.

At a high level, for an actor to visit another site as an authenticated viewer, he/she first redirects to a service which can create digital signatures on their behalf and which is provided a destination URL. This service must have access to their private signing key. 

The public key is stored on a webfinger compatible service or provided directly as a webfinger property. The webfinger resource is provided as the keyID of an HTTP Signature 'Authorization' header.

There is very little concensus on providing public keys in webfinger except for the salmon magic-public-key. For those that prefer a higher level key format a property name of 'https://w3id.org/security/v1#publicKeyPem' MAY be used and although unofficial, aligns with JSON-LD and ActivityPub. Servers MAY look at other webfinger resources if there is no usable public key found in the webfinger JRD document. These discovery mechanisms are outside the scope of this document.  

A webfinger request to the baseurl of the destination URL returns an entry for an OpenWebAuth service endpoint. For example:

````
rel: https://purl.org/openwebauth/v1
type: application/json
href: https://example.com/openwebauth
````

The redirector signs an HTTPS GET request to the OpenWebAuth endpoint using HTTP Signatures, which returns a json document:

````
{
  'success': true,
  'encrypted_token': 'bgU50kUhtlMV5gKo1ce'
}
````

The 'token' is a single use access token; generally a unique hash value of 16 to 56 chars in length (this is consistent with RSA OAEP encryption using a 1024-bit RSA key). The resulting token will very likely be used in a URL, so the characters MUST be in the range of [a-zA-Z0-9]. To generate the 'encrypted\_token' this token is first encrypted with the actor's public key using RSA encryption, and the result base64\_url encoded. 

If the Signature cannot be validated, the OpenWebAuth service returns

````
{
    'success': false,
    'message': 'Reason'
}
````

'message' is not required. 

If an encrypted_token is returned, this token is decrypted:

base64\_url decode the 'encrypted_token'
decrypt this result with the RSA private key belonging to the original actor, which results in a plaintext 'token'.

added to the destination URL as a query parameter 'owt' (OpenWebToken).

303 https://example.com/some/url?owt=abc123


The user's browser session is now redirected to the destination URL (with the token provided) and is authenticated and allowed access to various web resources depending on the permissions granted to their webfinger identity. 

Notes: All interactions MUST take place over https: transport with valid certificates. HTTP Signatures offer no protection against replay attacks. The OpenWebAuth token service MUST discard tokens after first use and SHOULD discard unused tokens within a few minutes of generation.