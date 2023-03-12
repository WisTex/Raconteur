This directory contains local copies of the w3.org jsonld documents

To update the activitystreams document run:
curl -o activitystreams.jsonld -L -H 'Accept: application/ld+json' https://www.w3.org/ns/activitystreams

To update the identity document run:
curl -o identity-v1.jsonld -L -H 'Accept: application/ld+json' https://w3id.org/identity/v1

To update the security document run:
curl -o security-v1.jsonld -L -H 'Accept: application/ld+json' https://w3id.org/security/v1
