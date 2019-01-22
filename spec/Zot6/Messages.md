### Messages

Zot6 is primarily a transport and identification format. The semantics of message content are in many respects outside the scope of this document. Sites/servers MUST provide in their site discovery document what standardised message formats are acceptable.

The message formats of primary concern to us are 

1. ActivityStreams (ActivityStreams JSON-LD) (format='activitystreams')
2. Zot (format='zot')

When using ActivityStreams JSON-LD, a default @context of "https://www.w3.org/ns/activitystreams" is assumed. Activity objects only need to specify or provide a @context declaration if there are differences from the default. 

### Delivery

Delivery is a POST of envelope+data to the zot endpoint. HTTP Signatures are used to validate the sender. 

Delivery reports for private messages SHOULD be encrypted and MUST include results for host blocking.

Here is a sample activity...

````
{
    "type": "activity",
    "encoding": "activitystreams",
    "sender": "wXDz7WR51QHwAORaVR8-0wff06LBtBWvhd_zfDWTYEzaqaPfJ_fsK7nRaM4aVeKPmZklUAgtqs09zUzitwNT2w",
    "site_id": "gcwJ1OzIZbwtfgDcBYVYhwlUmjaxsgPyJezd-F2IS1F3IrlVsyOesNpm3hvoWemIBxoHmgIlMYKkhFeYihsqBQ",
    "recipients": {
         "xxWsqvZp3w-sr3FXrmb6wxmKZx6khMLjBCOafPdRT1lWzYmCPHeaDDBD9KwOqpOAt4lezIFQbyaLt9I3H54M9Q",
         "T5ni0wUAlYmyqlibiTQiS54PqLKXCL7XAJhOSKeJMqEXeKn46AkDPdCJZ4JUA05Vlhux25OLTkBPyV7L60JmyQ",
    },
    "version": "6.0",
    "data": {
        "type": "Create",
        "id": "https://example.org/item/e8a20a21dd7e0d8d2207a5df62d2168d65db7db08f1a91ca",
        "published": "2018-06-25T04:14:08Z",
        "actor": "https://example.org/channel/zapper",
        "object": {
            "type": "Article",
            "id": "https://example.org/item/e8a20a21dd7e0d8d2207a5df62d2168d65db7db08f1a91ca",
            "published": "2018-06-25T04:14:08Z",
            "content": "just another Zot6 message",
            "actor": "https://example.org/channel/zapper",
        },
    },
}
````

The sender\_id is the portable\_id of the sender. The site\_id is the portable\_id of the sender's website.
Recipients is a simple array of portable_id's of message recipients. This list MAY be filtered and contain only the recipients which are known to be available on the receiving website. 

Version is provided to resolve potential protocol version differences over time. Data contains the actual ActivityStreams (in this case) payload.


Receiving sites MUST verify the HTTP Signature provided and MUST reject (error 400) posts with no signature or invalid signatures. Discovery is used during the verification process and generates a locally stored portable\_id. If the portable\_id does not match the verified signer's portable\_id, the message MUST be rejected (error 400). 

If the recipients field is non-existent or empty, the post is considered public and may be delivered to any channel following the sender. If the recipient field has contents, the message MUST NOT be delivered to anybody except the listed recipients.

Upon successful delivery, a delivery report is generated and returned to the sending site.

````
 {
    "success": true,
    "delivery_report": [
        {
            "location": "https://zap.macgirvin.com",
            "sender": "T5ni0wUAlYmyqlibiTQiS54PqLKXCL7XAJhOSKeJMqEXeKn46AkDPdCJZ4JUA05Vlhux25OLTkBPyV7L60JmyQ",
            "recipient": "xxWsqvZp3w-sr3FXrmb6wxmKZx6khMLjBCOafPdRT1lWzYmCPHeaDDBD9KwOqpOAt4lezIFQbyaLt9I3H54M9Q",
            "name": "System ",
            "message_id": "https://zap.macgirvin.com/item/ebaa483a2e8331a21a68b9d9e4a72a079c260162d2e37edc",
            "status": "posted",
            "date": "2018-06-26 05:19:48"
        },
        {
            "location": "https://zap.macgirvin.com",
            "sender": "T5ni0wUAlYmyqlibiTQiS54PqLKXCL7XAJhOSKeJMqEXeKn46AkDPdCJZ4JUA05Vlhux25OLTkBPyV7L60JmyQ",
            "recipient": "wXDz7WR51QHwAORaVR8-0wff06LBtBWvhd_zfDWTYEzaqaPfJ_fsK7nRaM4aVeKPmZklUAgtqs09zUzitwNT2w",
            "name": "Zapper ",
            "message_id": "https://zap.macgirvin.com/item/ebaa483a2e8331a21a68b9d9e4a72a079c260162d2e37edc",
            "status": "posted",
            "date": "2018-06-26 05:19:48"
        },
        {
            "location": "https://zap.macgirvin.com",
            "sender": "T5ni0wUAlYmyqlibiTQiS54PqLKXCL7XAJhOSKeJMqEXeKn46AkDPdCJZ4JUA05Vlhux25OLTkBPyV7L60JmyQ",
            "recipient": "T5ni0wUAlYmyqlibiTQiS54PqLKXCL7XAJhOSKeJMqEXeKn46AkDPdCJZ4JUA05Vlhux25OLTkBPyV7L60JmyQ",
            "name": "Bopper ",
            "message_id": "https://zap.macgirvin.com/item/ebaa483a2e8331a21a68b9d9e4a72a079c260162d2e37edc",
            "status": "update ignored",
            "date": "2018-06-26 05:19:48"
        }
    ]
}
````

### Followups

Followups to any post (replies, likes, reactions, etc.) MUST be sent as a private activity (single recipient) to the sender of the original with a message type 'response'. This is referred to as an "upstream delivery". 

Additionally these activities MUST provide an 'inReplyTo' element set to the id of the activity that is the object of the response. Implementations SHOULD support multi-level likes. Servers MAY support multi-level comments. 


The original sender MUST resend the followups to all of the original message recipients using message type 'activity'. This is referred to as a "downstream delivery". 

This single-source mechanism ensures the original sender's privacy settings are respected and conversations are kept intact for all recipients of the original message. 

### Multi-level support

If multi-level comments are not supported, the receiver MUST re-parent the multi-level comment to the original conversation head, flattening the conversation to one level. The receiving server SHOULD store the correct target id, even if the comment is re-parented. 

If multi-level likes are not supported, the incoming "like" activity for a non-parent conversation node MAY be dropped or rejected, rather than being re-parented to a potentially unrelated activity than was intended.   


### Portable IDs

Portable IDs are intended to provide location-independent identifiers across the network. A portable ID can only be trusted if it has been verified or if it has been generated on this site. Verification is performed during the [[Discovery]] process. 

The portable ID uses an obtained public\_key. In order to ensure that the portable ID calculation is portable, the calculation MUST be performed on a PKCS#8 public\_key. If the source key is provided in another format (such as a salmon [modulus/exponent] key or PKCS#1), the key MUST be converted to PKCS#8 format prior to calculating the portable ID.   

Here are the steps for generating a portable\_id.

1. Via [[Discovery]], obtain the zot-info packet for a network URI. The network URI will often be presented as the signer keyID in a signed message, but may also be provided as an acct: URI submitted by site users.
2. The zot-info packet contains everything necessary to verify an identity claim.
3. With the public\_key, verify the id\_sig against the id (claimed identity).
4. With the public\_key, verify the location->url\_sig against the location->url **for the discovery site**.
5. With site->sitekey, verify the site->site\_sig against the site->url.
6. Concatenate the id and the public\_key. Perform a whirlpool hash function on this concatenated string and base64_url encode the result. 
7. This is the channel portable\_id.
8. Concatenate the url and site->sitekey. Perform a whirlpool hash function on this concatenated string and base64_url encode the result. 
9. This is the portable site\_id. Check that it matches the location->site\_id in the discovery packet. 
10. If any verification step fails, discard the results and return an error. 

This is generally considered an expensive calculation; hence the results should be stored as a channel/location pair. 

When receiving a Zot message, use the stored results (if available) for the signer's keyID when checking the HTTP Signature. If the HTTP Signature validates, and the keyID matches the location->id\_url for this location, and the sender portable\_id matches the calculated/stored portable\_id for this channel/location pair, the nomadic sender has been validated.

If no stored results are available for the keyID, perform [[Discovery]] as described.