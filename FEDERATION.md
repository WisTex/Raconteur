Federation
==========

The ActivityPub implementation in this project strives to be compliant to the core spec where possible, while offering a range of services and features which normally aren't provided by ActivityPub projects.

C2S

This project supports ActivityPub C2S. You may authenticate with HTTP basic-auth, OAuth2, or OpenWebAuth. There is no media upload endpoint since the (deprecated) specification of that service has no workarounds for working in memory-restricted environments and most mobile phone photos exceed PHP's default upload size limits. 

Direct Messages

Direct Messages (DM) are differentiated from other private messaging using the zot:directMessage flag (boolean). This is compatible with the same facility provided by other projects in other namespaces and is not prefixed within activities so that these may potentially be aggregated.

Events

Events and RSVP are supported per AS-vocabulary with the exception that a Create/Event is not transmitted. Invite/Event is the primary method of sharing events. For compatibiliity with some legacy applications, RSVP responses of Accept/Event, Reject/Event, TentativeAccept/Event and TentativeReject/Event are accepted as valid RSVP activities. By default we send Accept/{Invite/Event} (and other RSVP responses) per AS-vocabulary. Events with no timezone (e.g. "all day events" or holidays) are sent with no 'Z' on the event times per RFC3339 Section 4.3. All event times are in UTC and timezone adjusted events are transmitted using Zulu time 'yyyy-mm-ddThh:mm:ssZ'. Event descriptions are sent using 'summary' and accepted using summary, title, and content in order of existence. These are converted internally to plaintext if they contain HTML. If 'location' contains coordinates, a map will typically be generated when rendered. 


Nomadic Identity

Nomadic identity describes a mechanism where the data stored by your social network can be mirrored to multiple servers in near realtime and any of these servers can be used at any time should your "primary" server be unavailable (temporarily or permanently). The mechanisms for creating nomadic accounts and maintaining mirrors are currently not supported by ActivityPub but are available via the Nomad protocol. However, the instance information is provided to ActivityPub services which wish to support it in a more seamless manner. Otherwise DMs (for example) may need to be sent to all actor instances to ensure the nomadic actor receives them. There are many other examples. 

The actor record for nomadic accounts contains a 'copiedTo' property which is otherwise identical to Mastodon's 'movedTo' property. ActivityPub services wishing to support compatibility with this mechanism should ensure that each nomadic instance is "folded" into a single fediverse identity so that messages from the same actor across different instances are recognised as being the same author. Additionally, any posts sent to a nomadic account should be sent to all instances and private data should be made accessible to all actor instances. This includes historical data and posts as new instances can be created at any time.  

Groups

Groups may be public or private. The initial thread starting post to a group is sent using a DM to the group and should be the only DM recipient. This helps preserve the sanctity of private groups and is a posting method available to most ActivityPub software, as opposed to bang tags (!groupname) which lack widespread support and normal @mentions which can create privacy issues and their associated drama.  It will be converted to an embedded post authored by the group Actor (and attributed to the original Actor) and resent to all members. Followups and replies to group posts use normal federation methods. The actor type is 'Group' and can be followed using Follow/Group *or* Join/Group, and unfollowed by Undo/Follow *or* Leave/Group.

Update: as of June 2021 comments to a group are sent via normal methods, but an additional Announce activity is now sent to ActivityPub connections so that the comments will be seen in the home timeline on microblog sites (Mastodon, etc.). Conversational or macroblog sites with working conversation view should filter/hide this redundant post.

Update: as of 2021-04-08 @mentions are now permitted for posting to public and moderated groups but are not permitted for posting to  restricted or private groups. The group owner can over-ride this behaviour as desired based on the group's security and privacy expectations. DMs (and wall-to-wall posts) are still the recommended methods for posting to groups because they can be used for any groups without needing to remember which are public and which are private; and which may have allowed or disallowed posting via mentions. 

Update: as of 2021-04-30 We will support incoming group posts of the form Create/Note/Collection or Add/Note/Collection where the target Collection|orderedCollection is the group outbox or "wall". This should match the behaviour of the Smithereen project and is documented in Fediverse Enhancement Proposal FEP-400e. We also provide this structure automatically on outbound group posts if the actor record contains 'wall' (sm:wall); and add a mention tag in the activity to support group/relay implementations that only trigger on mentions. 

Comments

This project provides permission control and moderation of comments. By default comments are only accepted from existing connections. This can be changed by the individual. Other sites MAY use zot:commentPolicy (string) as a guide if they do not wish to provide comment abilities where it is known in advance they will be rejected. A Reject/Note activity will be sent if the comment is not permitted. There is currently no response for moderated content, but will likely also be represented by Reject/Note.

'commentPolicy' can be any of

'authenticated' - matches the typical ActivityPub permissions

'contacts' - matches approved followers

'any connections' - matches followers regardless of approval

'site: foobar.com' - matches any actor or clone instance from 'foobar.com'

'public' - matches anybody at all, may require moderation if the network isn't known

'self' - matches the activity author only

'until=2001-01-01T00:00Z' - comments are closed after the date given. This can be supplied on its own or appended to any other commentPolicy string by preceding with a space; for example 'contacts until=2001-01-01T00:00Z'.


Expiring content

Activity objects may include an 'expires' field; after which time they are removed. The removal occurs with a federated Delete, but this is a best faith effort. We automatically delete any local objects we receive with an 'exires' field after it expires regardless of whether or not we receive a Delete activity. We also record external (3rd party) fetches of these items and send Delete activities to them as well. The expiration is specified as an ISO8601 date/time. 

Private Media

Private media MAY be accessed using OCAP or OpenWebAuth. Bearcaps are supported but not currently generated. 


Permission System

The Nomad permission system has years of historical use and is different than and the reverse of the typical ActivityPub project. We consider 'Follow' to be an anti-pattern which encourages pseudo anonymous stalking. A Follow activity by an actor on this project typically means the actor on this project will send activities to the recipient. It may also confer other permissions. Accept/Follow usually provides permission to receive content from the referenced actor, depending on their privacy settings. 


Delivery model

This project uses the relay system pioneered by projects such as Friendica, Diaspora, and Hubzilla which attempts to keep entire conversations intact and keeps the conversation initiator in control of the privacy distribution. This is not guaranteed under ActivityPub where conversation members can cc: others who were not in the initial privacy group. We encourage projects to not allow additional recipients or not include their own followers in followups. Followups SHOULD have one recipient - the conversation owner or originator, and are relayed by the owner to the other conversation members. This normally requires the use of LD-Signatures but may also be accessible through authenticated fetch of the activity using HTTP signatures. 

Content

Content may be rich multi-media and renders nicely as HTML. Multicode is used and stored internally. Multicode is html, markdown, and bbcode. The multicode interpreter allows you to switch between these or combine them at will. Content is not obviously length-limited and authors MAY use up to the storage maximum of 24MB. In practice markup conversion limits the effective length to around 200KB and the default "maximum length of imported content" from other sites is 200KB. This can be changed on a per-site basis but this is rare. A Note may contain one or more images, other media, and links. HTML purification primarily removes javascript and iframes and allows most legitimate tags and CSS styles through. Inline images are also added as attachments for the benefit of Mastodon (which strips most HTML), but remain in the HTML source. When importing content from other sites, if the content contains an image attachment, the content is scanned to see if a link (a) or (img) tag containing that image is already present in the HTML. If not, an image tag is added inline to the end of the incoming content. Multiple images are supported using this mechanism.

Mastodon 'summary' does not invoke any special handling so 'summary' can be used for its intended purpose as a content summary. Mastodon 'sensitive' is honoured and results in invoking whatever mechanisms the user has selected to deal with this type of content. By default images are obscured and are 'click to view'. Sensitive text is not treated specially, but may be obscured using the NSFW plugin or filtered per connection based on string match, tags, patterns, languages, or other criteria.

Edits

Edited posts and comments are sent with Update/Note and an 'updated' timestamp along with the original 'published' timestamp. 

Announce

Announce and relay activities use two mechanisms. As well as the Announce activity, a new message is generated with an embedded rendering of the shared content as the message content. This message may (should) contain additional commentary in order to comply with the Fair Use provisions of copyright law. The other reason is our use of comment permissions. Comments to Announce activities are sent to the author (who typically accepts comments only from connections). Comment to embedded forwards are sent to the sender. This difference in behaviour allows groups to work correctly in the presence of comment permissions.

Discussion (2021-04-17): In the email world this type of conflict is resolved by the use of the reply-to header (e.g. in this case reply to the group rather than to the author) as well as the concept of a 'sender' which is different than 'from' (the author). We will soon be modelling the first one in ActivityPub with the use of 'replyTo'. If you see 'replyTo' in an activity it indicates that replies SHOULD go to that address rather than the author's inbox. We will implement this first and come up with a proposal for 'sender' if this gets any traction. If enough projects support these constructs we can eliminate the multiple relay mechanisms and in the process make ActivityPub much more versatile when it comes to organisational and group communications. Our primary use case for 'sender' is to provide an ActivityPub origin to a message that was imported from another system entirely (such as Diaspora or from RSS source). In this case we would set 'attributedTo' to the remote identity that authored the content, and 'sender' to the person that posted it in ActivityPub. 

Emoji Reactions

We consider a reply message containing exactly one emoji and no other text or markup to be an emoji reaction. We indicate this state internally on receipt but do nothing to identify it specifically to downstream recipients.

Mastodon Custom Emojis

Mastodon Custom Emojis are only supported for post content and are not considered as emoji reactions. Display names and message titles (ActivityStreams "name" field) are considered text only fields and embedded images (the mechanism behind custom emojis) are not supported in these locations.


Mentions and private mentions

By default the mention format is '@Display Name', but other options are available, such as '@username' and both '@Display Name (username)'. Mentions may also contain an exclamation character, for example '@!Display Name'. This indicates a Direct or private message to 'Display Name' and post addressing/privacy are altered accordingly. All incoming and outgoing content to your stream is re-written to display mentions in your chosen style. This mechanism may need to be adopted by other projects to provide consistency as there are strong feelings on both sides of the debate about which form should be prevalent in the fediverse.  

(Mastodon) Comment Notifications

Our projects send comment notifications if somebody replies to a post you either created or have previously interacted with in some way. They also are able to send a "mention" notification if you were mentioned in the post. This differs from Mastodon which does not appear to support comment notifications at all and only provides mention notifications. For this reason, Mastodon users don't typically get notified unless the author they are replying to is mentioned in the post. We provide this mention in the 'tag' field of the generated Activity, but normally don't include it in the message body, as we don't actually require mentions that were created for the sole purpose of triggering a comment notification.

Conversation Completion

(2021-04-17) It's easy to fetch missing pieces of a conversation going "upstream", but there is no agreed-on method to fetch a complete conversation from the viewpoint of the origin actor and upstream fetching only provides a single conversation branch, rather than the entire tree. We provide 'context' as a URL to a collection containing the entire conversation (all known branches) as seen by its creator. This requires special treatment and is very similar to ostatus:conversation in that if context is present, it needs to be replicated in conversation descendants. We still support ostatus:conversation but usage is deprecated. We do not use 'replies' to achieve the same purposes because 'replies' only applies to direct descendants at any point in the conversation tree. 

Site Actors

(2021-08-25) An actor record of type 'Service' is now available from an ActivityStreams fetch of the domain root. This was discussed recently in the Socialhub as it may open some novel applications which involve communication with sites and site administators; and also provides a simple ActivityPub centric method for discovering very basic information about a site that doesn't involve platform-centric APIS. At present this is just a skeleton which will be filled in as we better define the ways we see it being used.
