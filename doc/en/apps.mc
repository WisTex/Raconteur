Available apps
===============

### Categories

This app allows you to add named categories to your posts. The major difference between categories and hashtags is that category searches only return your own posts. They are personal to you. Hashtag searches search all posts across all channels on your website/instance.

### Channel Home

Installed by default. This app provides access to your channel home page.

### Clients

This app allows you to create and manage Oauth2 access credentials, which are typically used to provide authentication for mobile and third-party client applications.

### Comment Control

This app lets you control the ability to comment on your posts. You can choose to disable comments, disable comments after a specific date/time, and select different audiences who are given comment permission. For example 'anybody' or 'just my friends'. Disallowed comments will be rejected by your website/instance. Whether or not the sender is notified of these rejections is dependent on their own software capabilities.

### Connections

Installed by default. This app manages your connections (followers and following) as well as their permissions and filter settings.

### Content Filter

Allows you to specify different rules to incoming posts and comments, either on a per connection basis or as a global filter which applies to all incoming messages. Filtering can take place using hashtags, categories, regular expressions, text content, and language matching. Posts and comments matching the rules are rejected and will not be stored.

### Content Import

Requires the site administrator to install the 'content_import' addon. This allows you to migrate content from another instance of this channel. It should not be needed unless there were issues importing content and/or file storage when the channel was first imported.

### Directory

Installed by default. Lets you discover others in the network. This displays everybody known to your website/instance except those that choose to be "hidden".

### Drafts

This optional app allows you to save a post or comment you are composing and finish it later. After installing, click the 'disk' icon in the post/comment editor to save a draft. Click the app itself to display your current drafts, and 'Edit' from the article dropdown menu to continue editing.

Important: please use the Draft icon (looks like a floppy disk in the default theme) to continue saving the draft if you wish to continue working on it. As soon as you click 'Share' (posts) or 'Submit' (comments), it will be published.

### Events

Installed by default. This provides access to your personal calendar and events. Your friends' birthdays are automatically added to your calendar as well as any events that you have chosen to attend. If you wish to receive a reminder for other events, please select 'Add to Calendar' from the message menu of the desired post. 

### Expire Posts

This app allows you to automatically remove all your own posts at some point in the future.

### Files

Installed by default. This app provides access to your cloud storage and WebDAV resources (anything you upload).

### Followlist

This app is only present if the 'followlist' addon is installed by the site administrator. It allows you to bulk connect with a list of channels. Typically this will be the 'following' ActivityPub collection on another server. We also accept Mastodon contact export files.

### Friend Zoom

This provides a slider control at the top of your stream. Every connection may be assigned a value from 0-100. The default is 80 for new connections. Unknown people in your stream are 100. You are 0. You can adjust your friends anywhere within the slider control range and use it to zoom in to your close friends and zoom out to people/channels you are less familiar with or who post very frequently and tend to drown out everybody else.

### Future Posting

Set a post to be published at/after a certain date/time. Usually used for automatically posting while you are on vacation. The specified date/time must be at least fifteen minutes in the future in order for publishing to be delayed. Otherwise it is published immediately but with the provided date/time.

### Fuzzloc

Available if the 'fuzzloc' addon has been installed by the site administrator. When you are using browser location services to mark your post location, this allows you to set the min/max distance from your precise location you would like to display in your posts. A randomly generated location will be generated based on these parameters.  

### Gallery

Available if the 'gallery' addon has been installed by the site administrator. This provides an improved image browser to your photos than the stock 'Photos' app. Interactions with photos in your stream are modified slightly.

### Guest Pass

This app allows you to create guest accounts and/or create links which can access some of your private resources. For instance if you want to send links via email to let the grandparents see a private photo of their grandchildren.

### Hexit

Available if the 'hexit' addon has been installed by the site administrator. This provides an online hexadecimal/decimal converter. 

### Language

This is generally used by developers to test alternate languages and is rarely needed. The language of your website will usually depend on the language settings of your browser and the existence of available translations. This app allows you to quickly over-ride the defaults and specify a particular language.

### Lists

Installed by default. Some software refers to this functionality as 'Aspects' (Diaspora) or 'Circles' (Google+). This allows you to create/manage named lists of connections which you can view and/or share with as a single named group.

### Markup

Provides editor buttons for bold, italic, underline, etc. Otherwise the relevant codes will need to be entered manually. This software recognises Markdown, HTML, and bbcode.

### Notes

Provides a simple notepad on your stream page for making notes and reminders.

### NSFW

"Not Safe For Work". Available if the 'nsfw' addon has been installed on this site. By default this will collapse and hide posts matching any of your rules unless you are running in "safe mode". These rules are based on hashtags, text content, regular expressions, and language.

It is like the 'Content Filter' app except that all matching posts are present in your stream but require you to open each such message to view. Posts matching 'Content Filter' rules are rejected and will not be present in your stream.

You can select whether or not you are running in "safe mode" in your personal menu, and this setting is preserved separately for each login. This means your work computer can be in 'safe mode' and questionable content hidden, while your home computer may be configured to show everything by default.

### Photomap

If your uploaded photos have location support, this addon provides an optional map display of those locations in your 'Photos' app. This may require the site administrator to install a map provider addon such as 'openstreetmap'.

### Photos

Installed by default. This displays your uploaded photos and photo albums separately from other uploaded files.

### Post

This app lets you open a post editor at any time.

### Qrator

Available if the 'qrator' addon has been installed. This provides a page to generate QR codes for your requirements.

### Rainbow Tag

Available if the 'rainbowtag' addon has been installed on the site. Also requires the Tagadelic app. This converts the tag clouds provided by Tagadelic from monochrome into color.

### Search

One click interface to the search page.

### Secrets

This app provides basic end-to-end encryption using the Stanford Javascript Crypto Library and lets you assign a key and optional key hint, such as "grandma's anniversary yyyy-mm-dd". An encryption button is added to the post editor. Transmitting the actual key should be done using another service or based on common knowledge/experience. The encryption button will encrypt the entire contents of the message window, which may include basic bbcode constructs like bold and italic text. For best results, please restrict your content to plain text, as unsupported markup will be displayed verbatim. Links are permitted, but should probably be avoided since fetching them at the other end may leak secret knowledge. 

### Site Admin

If you are the site administrator, this app provides access to the admin control panel.


### Sites

Provides a categorised listing of all sites that this website/instance is aware of.

### Stream

Installed by default. This represents the conversations of you and your connections.

### Stream Order

Available if the 'stream_order' addon is installed on the site. Provides a setting on the Stream page to select between various sort orders, such as 'last commented date' (conversational), 'posted date' (also conversational) and 'date unthreaded' which lists every activity as a separate message.

### Suggest Channels

Analyses your own connections and those of your friends who have provided you with permission to see their connections. It returns a page of channels ranked by how many of your other friends are friends with the given channel, but where you are not.

### Tagadelic

Named after the Drupal module of the same name. This provides a tag cloud of your hashtags on your channel home page. Also useful with the 'rainbowtag' addon to give it some added colour.


### Tasks

Provides a very simple to-do list on your Stream page (and also the Tasks app page).

### Virtual Lists

This is like 'Lists', except you do not need to manage the list membership. By default, three dynamic lists are created: all connections, all Nomad (protocol) connections, and all ActivityPub (protocol) connections. These virtual lists may be used anywhere you can use a list; either as permission controls, post audiences, or stream filtering.

### Zotpost

Available if the 'zotpost' addon has been installed on the site. This configures the zotpost addon to automatically cross-post to your channel on another Zot6 or Nomad site.




