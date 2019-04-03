## Zot/6 aka Zot 2018

This document describes version 6 of the Zot protocol. This is a living document, last modified on 2018-09-14.

Zot is a **WebMTA** which provides a decentralised identity and communications protocol using HTTPS/JSON. 

Earlier revisions of the zot protocol dealt with the creation of nomadic identities and cross-domain authentication to enable a decentralised network with features rivaling large centralised providers.

Zot/6 builds on those concepts and streamlines many of the interactions, applying lessons learned over decades of building decentralised systems.

A reference implementation (a social media app) is provided at https://framagit.org/zot/zap ; the reference implementation is not yet 100% spec compliant or complete but may be used for basic compatibility testing and development purposes. 

### Differences from earlier Zot versions

1. Streamlined communications using direct (push) transfer. Earlier versions used a 'notify/pickup' delivery model.
2. The authentication component (Magic-Auth) has been spun off into a separate and independent specification ['OpenWebAuth'](spec/OpenWebAuth/Home.md).
3. Inclusion of ActivityStreams (JSON-LD) as a supported (primary) serialisation.
4. Dropping the requirements for implementations to support secondary serialisations.
5. Moving service discovery to "Accept-header" based service endpoints; where different representations can be selected by modification of the HTTPS request Accept: header. 
6. Separation of the "portable-id" from the signature algorithm used.

### Differences between Zot and other WebMTA services

Zot is architecturally different from other HTTPS-based "social communications" protocols such as OStatus, ActivityPub, and Diaspora. The primary differences are:

1. Support for nomadic identity where an identity is not permanently bound to a DNS server.
2. MUAs built on top of Zot are able to use and express detailed cross-domain permissions.
3. Encryption negotation for additional message protection in addition to HTTPS
4. Zot does not define an absolute payload format for content. Implementations MUST support ActivityStreams. Additional message types and serialisation formats MAY provide vendor specific enhancements.   
5. Federation between other WebMTA protocols is not hampered by unnecessary restrictions on 3rd party communications. Messages from incompatible systems may be relayed to other sites which do not support the 3rd party protocol.
6. Detailed delivery reporting is provided for auditing and troubleshooting; which is critical in a distributed communications service.

## Fundamental Concepts

### Identity

In Zot/6 decentralised identity is a core attribute of the system. This is somewhat different than (and often incompatible with) typical decentralised or P2P communications projects where messaging is the key component and identity is merely an atribution on a message.

An identity consists of two primary components.

1. An identity "claim" which is essentially a text string
2. An RSA based key with which to verify that identity

These will be described in detail later. A "valid" identity is an identity which has been verified using public key cryptographic techniques. Until verified, an identity is "claimed" (unverified). An "invalid" identity is an identity which failed verification or has been revoked. 

In Zot/6 claimed (unverified) identities are allowed to exist, and MAY be allowed permissions to perform operations. This allows cross communication to occur between Zot/6 and other communications systems with different concepts of identity and different methods of validation.

An implementation MAY choose to block unverified identities. This may significantly reduce the ability to interact with foreign systems, protocols, and networks.   

Invalid identities MUST NOT be allowed any permissions. By definition they are forgeries or have been revoked. 

### Channels

Channels are what would typically be referred to as "users" on other systems, although this notion is abstracted in Zot/6 and can take on other meanings. A channel is represented as an identity. 

### Location and Location Independence

A location is an identity and follows all the rules associated with an identity. The identity claim is typically the fully qualified "base" URL of the web service providing Zot/6 services, lowercased, with trailing slashes removed. International domain names are converted to "punycode".

A "location independent identity" is a valid channel identity paired with a valid location, with the additional property that the channel/location pair has been validated using the key of the channel identity. In layman's terms, the user signs his/her website with their own key.

The location independent model allows mobility of a channel to different or even multiple locations; which may all be valid simultaneously. This is referred to elsewhere as a "nomadic" identity. 

For the benefit of those that are new to this concept, this means in basic terms that a channel can appear at any location at any time as long as the location independent identity is valid.

### Linked identities

One or more channels/identities can be linked. In simple terms, this allows us to define a persistent token which refers to an identity whose claim string or public key (or both) have changed or is being merged from another system. In order to link identities, in addition to the requirement that both identities MUST validate, each identity MUST sign the other linked identity and all of these signatures MUST be valid.

In the case of providing controlled access to website resources, all linked identities SHOULD be folded or reduced to a common identifier so that an attempt to access a protected resource by any linked identity will succeed for a resource that has been made available to any of its linked identities.

### Nomadic Considerations

The location independent properties of Zot/6 place additional requirements on communications systems. For outgoing messages, delivery SHOULD be attempted to every linked and nomadic location associated with the identity being delivered to. The service MAY remove a valid linked or nomadic location from the list of delivery targets if the location is marked "dead" or "unreachable". A location MAY be marked "dead" or "unreachable" for a number of reasons; generally by a failure to communicate for more than 30 days, or by manual means if the site administrator has reason to believe the location has been shut down permanently. A site SHOULD retain the location record and automatically remove the "dead" or "unreachable" designation if valid communications are initiated from that location in the future.   


## Transport and Protocol Basics

Communications with Zot/6 take place primarily with https JSON requests. Requests are signed by the "sender" using HTTP-Signatures (draft-cavage-http-signatures-xx). If a payload is present, the signed headers MUST include a Digest header using SHA-256 or SHA-512 as specified in RFC5843. 

For HTTP requests which do not carry a payload any headers may be signed, but at least one header MUST be signed. 

Signed requests MAY be rejected if

- Digest verification fails
- The request has a JSON payload and the content is not signed
- public-key retrieval fails


Public keys are retrieved by utilising Zot/6 Discovery on the signature data's 'keyId' property and retrieving the public key from that document. Zot Discovery is described in a later chapter.

Receivers verifying these requests SHOULD verify that the signer has authority over the data provided; either by virtue of being the author or a relay agent (sender).