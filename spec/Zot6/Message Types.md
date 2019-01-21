## Message Types

### purge

This message type has no payload or encoding. If the message has recipients, it is considered an "unfriend" action. The sender's relationship with the recipients is severed. The exact actions which are undertaken as a result are implementation specific. Generally, the sender's permissions to the receiver are revoked. Permissions granted to the sender by the receiver MAY be left intact. 

If the message has no recipients, it is considered a notification that the sender identity no longer exists. Receiving sites MUST mark the channel as unavailable and discontinue further communications. They SHOULD destroy all public content attributed to the sender and MAY remove the connection and private content from connected channels. 

The response to this message is 

````
{
  'success': true
}
````

or

````
{
  'success': false,
  'message': 'optional error message or reason'
}
````

Servers SHOULD only provide a single recipient when using a targetted message as the single return response may be ambiguous.


### refresh

This message has no payload or encoding. It is a message to the receiving server that important channel information has changed. The receiving server MUST process a zot discovery operation and update any locally stored information which has changed. If the message has recipients, this action should be accomplished by the receiver using a signed discovery fetch, signed by the recipient. Response is the same as for a purge message. 

A targetted refresh message (e.g. containing recipients) is commonly used to indicate a change in permissions granted to the recipient by the sender. If no permissions have previously been associated with this sender, it is considered to be a "friend request", meaning some permissions are available which may not have been available before. The receiving channel SHOULD store the updated permissions and SHOULD add the sender to the receiver's known connections. It MAY notify the recipient that a new friend exists and put the connection into a 'pending' state until the request has been reviewed/accepted/rejected by the recipient. 

### rekey

The rekey message is sent with no recipients. The message indicates the sender has changed their public key. The key change MUST be signed with both the old and new private keys and these signatures MUST validate or the operation MUST fail.
The 'update' boolean flag (if present) indicates the old portable\_id associated with that key is to be changed, and the old portable\_id discarded. If 'update' is false, a new portable\_id is generated and the old and new identities are linked. This means that both portable\_id's are valid nomadic identifiers for the same channel. Response is the same as for the purge message. 

### activity

Message encoding: activitystreams

This message type is used for normal communications. If recipients are specified, the message is private. If no recipients are specified, the message is public. The recipient list MAY be filtered and contain only the recipients which are known to be available on the receiving website. A message MUST NOT be sent by the sender if the message is private and the recipient list for a particular receiving site is empty.


````
{
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
}
````

Upon delivery, a delivery report is generated and returned to the sending site.

````
{
    "success": true,
    "delivery_report": [
        {
            "location": "https://zap.macgirvin.com",
            "sender": "T5ni0wUAlYmyqlibiTQiS54PqLKXCL7XAJhOSKeJMqEXeKn46AkDPdCJZ4JUA05Vlhux25OLTkBPyV7L60JmyQ",
            "recipient": "xxWsqvZp3w-sr3FXrmb6wxmKZx6khMLjBCOafPdRT1lWzYmCPHeaDDBD9KwOqpOAt4lezIFQbyaLt9I3H54M9Q",
            "name": "System",
            "message_id": "https://zap.macgirvin.com/item/ebaa483a2e8331a21a68b9d9e4a72a079c260162d2e37edc",
            "status": "posted",
            "date": "2018-06-26 05:19:48"
        },
        {
            "location": "https://zap.macgirvin.com",
            "sender": "T5ni0wUAlYmyqlibiTQiS54PqLKXCL7XAJhOSKeJMqEXeKn46AkDPdCJZ4JUA05Vlhux25OLTkBPyV7L60JmyQ",
            "recipient": "wXDz7WR51QHwAORaVR8-0wff06LBtBWvhd_zfDWTYEzaqaPfJ_fsK7nRaM4aVeKPmZklUAgtqs09zUzitwNT2w",
            "name": "Zapper",
            "message_id": "https://zap.macgirvin.com/item/ebaa483a2e8331a21a68b9d9e4a72a079c260162d2e37edc",
            "status": "posted",
            "date": "2018-06-26 05:19:48"
        },
        {
            "location": "https://zap.macgirvin.com",
            "sender": "T5ni0wUAlYmyqlibiTQiS54PqLKXCL7XAJhOSKeJMqEXeKn46AkDPdCJZ4JUA05Vlhux25OLTkBPyV7L60JmyQ",
            "recipient": "T5ni0wUAlYmyqlibiTQiS54PqLKXCL7XAJhOSKeJMqEXeKn46AkDPdCJZ4JUA05Vlhux25OLTkBPyV7L60JmyQ",
            "name": "Bopper",
            "message_id": "https://zap.macgirvin.com/item/ebaa483a2e8331a21a68b9d9e4a72a079c260162d2e37edc",
            "status": "update ignored",
            "date": "2018-06-26 05:19:48"
        }
    ]
}
````

### response

The response message is used to send a comment or reply "upstream" to the sender. The sender will then redeliver the message to all downstream recipients. It is the same as the activity message type except that it MUST have one and only one recipient - the sender of the activity that this activity references. This entire activity SHOULD be signed by the sender of the response and encapsulated as a json salmon magic envelope as described in [[Encryption/Signatures]]. 


### sync

Sync messages are used between nomadic clones to synchronise changed data structures. These messages are sent to nomadic instances of the sender as private messages and SHOULD be encrypted with additional encryption beyond HTTPS transport encryption as the messages may contain private keys.

Implementations MAY provide sync messages and they MAY attempt to co-exist with sync packets created by other implementations. If their data synchronisation needs cannot be mapped to the sync structures provided by others, they MUST provide a unique encoding type (recommend the name of the implementation using only the letters [a-z] of the US-ASCII character set). If a site receives a sync message with an unknown encoding and data format it MUST be ignored. 

Basic sync information includes any local changes to personal settings, profile settings, and changes in the social graph. Implementations MAY support syncing of all available information including uploaded files and photos, events, and other application specific data structures.