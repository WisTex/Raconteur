Federation
==========

The ActivityPub implementation in Zap strives to be compliant to the core spec where possible, while offering a range of services and features which normally aren't provided by ActivityPub projects.


Direct Messages

Direct Messages (DM) are differentiated from other private messaging using the zot:directMessage flag (boolean). This is compatible with the same facility provided by other projects in other namespaces and is not prefixed within activities so that these may potentially be aggregated.

Events

Events and RSVP are supported per AS-vocabulary with the exception that a Create/Event is not transmitted. Invite/Event is the primary method of sharing events. For compatibiliity with some legacy applications, RSVP responses of Accept/Event, Reject/Event, TentativeAccept/Event and TentativeReject/Event are accepted as valid RSVP activities. By default we send Accept/{Invite/Event} (and other RSVP responses) per AS-vocabulary. Events with no timezone (e.g. "all day events" or holidays) are sent with no 'Z' on the event times per RFC3339 Section 4.3. All event times are in UTC and timezone adjusted events are transmitted using Zulu time 'yyyy-mm-ddThh:mm:ssZ'. Event descriptions are sent using 'summary' and accepted using summary, title, and content in order of existence. These are converted internally to plaintext if they contain HTML. If 'location' contains coordinates, a map will typically be generated when rendered. 


Groups

Groups may be public or private. The initial thread starting post to a group is sent using a DM to the group and should be the only DM recipient. This helps preserve the sanctity of private groups and is a posting method available to most ActivityPub software, as opposed to bang tags (!groupname) which lack widespread support and normal @mentions which can create privacy issues and their associated drama.  It will be converted to an embedded post authored by the group Actor (and attributed to the original Actor) and resent to all members. Followups and replies to group posts use normal federation methods. The actor type is 'Group' and can be followed using Follow/Group *or* Join/Group, and unfollowed by Undo/Follow *or* Leave/Group.

Comments

Zap provides permission control and moderation of comments. By default comments are only accepted from existing connections. This can be changed by the individual. Other sites MAY use zot:commentPolicy (string) as a guide if they do not wish to provide comment abilities where it is known in advance they will be rejected. A Reject/Note activity will be sent if the comment is not permitted. There is currently no response for moderated content, but will likely also be represented by Reject/Note. 


Private Media

Private media MAY be accessed using OCAP or OpenWebAuth.


Permission System

The Zot permission system has years of historical use and is different than and the reverse of the typical ActivityPub project. We consider 'Follow' to be an anti-pattern which encourages pseudo anonymous stalking. A Follow activity by a Zap actor typically means the Zap actor will send activities to the recipient. It may also confer other permissions. Accept/Follow usually provides permission to receive content from the referenced actor, depending on their privacy settings. 


Delivery model

Zap uses the relay system pioneered by projects such as Friendica, Diaspora, and Hubzilla which attempts to keep entire conversations intact and keeps the conversation initiator in control of the privacy distribution. This is not guaranteed under ActivityPub where conversation members can cc: others who were not in the initial privacy group. We encourage projects to not allow additional recipients or not include their own followers in followups. Followups SHOULD have one recipient - the conversation owner or originator, and are relayed by the owner to the other conversation members. This normally requires the use of LD-Signatures but may also be accessible through authenticated fetch of the activity using HTTP signatures.

Content

Content can be rich multi-media and renders nicely as HTML.  Bbcode is used internally due to its ease of purification while still providing rich multi-media support. Simple content which contains very little markup is downgraded to a Note to retain compatibility with Mastodon. Complex markup is sent as Article (which unfortunately Mastodon does not render but only provides a link). Content is not obviously length-limited and authors MAY use up to the storage maximum of 24MB. In practice bbcode conversion limits the effective length to around 200KB and the default "maximum length of imported content" from other sites is 200KB. This can be changed on a per-site basis but this is rare. A Note may contain one or more images or links. The images are also added as an attachment for the benefit of Mastodon, but remain in the HTML source. When importing content from other sites, if the content contains an image attachment, the content is scanned to see if a link (a) or (img) tag containing that image is already present in the HTML. If not, an image tag is added inline to the end of the incoming content. Multiple images are supported using this mechanism.

Mastodon 'summary' does not invoke any special handling so 'summary' can be used for its intended purpose as a content summary. Mastodon 'sensitive' is honoured and results in invoking whatever mechanisms the user has selected to deal with this type of content. By default images are obscured and are 'click to view'. Sensitive text is not treated specially, but may be obscured using the NSFW plugin or filtered per connection based on string match, tags, patterns, languages, or other criteria.

Edits

Edited posts and comments are sent with Update/Note and an 'updated' timestamp along with the original 'published' timestamp. 

Announce

Announce and relay activities are supported on the inbound side but are not generated. Instead a new message is generated with an embedded rendering of the shared content as the message content. This message may (should) contain additional commentary in order to comply with the Fair Use provisions of copyright law.

Mastodon Custom Emojis

Mastodon Custom Emojis are only supported for post content. Display names and message titles are considered text only fields and embedded images (the mechanism behind custom emojis) are not supported in these locations.