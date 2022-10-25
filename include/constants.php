<?php
/**
 * @brief Constant with a HTML line break.
 *
 * Contains a HTML line break (br) element and a real carriage return with line
 * feed for the source.
 * This can be used in HTML and JavaScript wherever a line break is required.
 */
define ( 'EOL',                    '<br>' . "\r\n"        );
define ( 'EMPTY_STR',              ''                     );
define ( 'ATOM_TIME',              'Y-m-d\\TH:i:s\\Z'     ); // aka ISO 8601 "Zulu"
define ( 'TEMPLATE_BUILD_PATH',    'cache/smarty3' );

//define ( 'USE_BEARCAPS',           true);


// Many of these directory settings are no longer used, but may still be referenced in code.
// The only ones of consequence in 2021 are DIRECTORY_MODE_NORMAL and DIRECTORY_MODE_STANDALONE.

define ( 'DIRECTORY_MODE_NORMAL',      0x0000); // A directory client
define ( 'DIRECTORY_MODE_PRIMARY',     0x0001); // There can only be *one* primary directory server in a directory_realm.
define ( 'DIRECTORY_MODE_SECONDARY',   0x0002); // All other mirror directory servers
define ( 'DIRECTORY_MODE_STANDALONE',  0x0100); // A detached (off the grid) hub with itself as directory server.

// Types of xchan records. These are a superset of ActivityStreams Actor types

define ('XCHAN_TYPE_PERSON',           0);
define ('XCHAN_TYPE_GROUP',            1);
define ('XCHAN_TYPE_COLLECTION',       2);
define ('XCHAN_TYPE_SERVICE',          3);
define ('XCHAN_TYPE_ORGANIZATION',     4);
define ('XCHAN_TYPE_APPLICATION',      5);
define ('XCHAN_TYPE_UNKNOWN',        127);


/**
 *
 * Image storage quality. Lower numbers save space at cost of image detail.
 * For ease of upgrade, please do not change here. Change jpeg quality with
 * App::$config['system']['jpeg_quality'] = n;
 * in .htconfig.php, where n is netween 1 and 100, and with very poor results
 * below about 50
 */
define ( 'JPEG_QUALITY',            100  );

/**
 * App::$config['system']['png_quality'] from 0 (uncompressed) to 9
 */
define ( 'PNG_QUALITY',             8  );

/**
 * Language detection parameters
 */
define ( 'LANGUAGE_DETECT_MIN_LENGTH',     64 );
define ( 'LANGUAGE_DETECT_MIN_CONFIDENCE', 0.01 );


define ('MAX_EVENT_REPEAT_COUNT', 512);

/**
 * Default permissions for file-based storage (webDAV, etc.)
 * These files will be owned by the webserver who will need write
 * access to the "storage" folder.
 * Ideally you should make this 700, however some hosted platforms
 * may not let you change ownership of this directory so we're
 * defaulting to both owner-write and group-write privilege.
 * This should work for most cases without modification.
 * Over-ride this in your .htconfig.php if you need something
 * either more or less restrictive.
 */

if (! defined('STORAGE_DEFAULT_PERMISSIONS')) {
    define ( 'STORAGE_DEFAULT_PERMISSIONS',   0770 );
}

// imported followers for friend suggestions.

if (! defined('MAX_IMPORTED_FOLLOW')) {
    define ( 'MAX_IMPORTED_FOLLOW', 10);
}

/**
 *
 * An alternate way of limiting picture upload sizes. Specify the maximum pixel
 * length that pictures are allowed to be (for non-square pictures, it will apply
 * to the longest side). Pictures longer than this length will be resized to be
 * this length (on the longest side, the other side will be scaled appropriately).
 * Modify this value using
 *
 *    App::$config['system']['max_image_length'] = n;
 *
 * in .htconfig.php
 *
 * If you don't want to set a maximum length, set to -1. The default value is
 * defined by 'MAX_IMAGE_LENGTH' below.
 *
 */
define ( 'MAX_IMAGE_LENGTH',        -1  );


define ( 'PUBLIC_STREAM_NONE',       0  );
define ( 'PUBLIC_STREAM_SITE',       1  );
define ( 'PUBLIC_STREAM_FULL',       2  );


/**
 * log levels
 */

define ( 'LOGGER_NORMAL',          0 );
define ( 'LOGGER_TRACE',           1 );
define ( 'LOGGER_DEBUG',           2 );
define ( 'LOGGER_DATA',            3 );
define ( 'LOGGER_ALL',             4 );


/**
 * registration policies
 */

define ( 'REGISTER_CLOSED',        0 );
define ( 'REGISTER_APPROVE',       1 );
define ( 'REGISTER_OPEN',          2 );


/**
 * site access policy
 */

define ( 'ACCESS_PRIVATE',         0 );
define ( 'ACCESS_PAID',            1 );
define ( 'ACCESS_FREE',            2 );
define ( 'ACCESS_TIERED',          3 );

/**
 * DB update return values
 */

define ( 'UPDATE_SUCCESS', 0);
define ( 'UPDATE_FAILED',  1);


define ( 'CLIENT_MODE_NORMAL', 0x0000);
define ( 'CLIENT_MODE_LOAD',   0x0001);
define ( 'CLIENT_MODE_UPDATE', 0x0002);


/**
 *
 * Channel pageflags
 *
 */

define ( 'PAGE_NORMAL',            0x0000 );
define ( 'PAGE_HIDDEN',            0x0001 );
define ( 'PAGE_AUTOCONNECT',       0x0002 );
define ( 'PAGE_APPLICATION',       0x0004 );
define ( 'PAGE_ALLOWCODE',         0x0008 );
define ( 'PAGE_PREMIUM',           0x0010 );
define ( 'PAGE_ADULT',             0x0020 );
define ( 'PAGE_CENSORED',          0x0040 ); // Site admin has blocked this channel from appearing in casual search results and site feeds
define ( 'PAGE_SYSTEM',            0x1000 );
define ( 'PAGE_HUBADMIN',          0x2000 ); // set this to indicate a preferred admin channel rather than the
// default channel of any accounts with the admin role.
define ( 'PAGE_REMOVED',           0x8000 );


/**
 * Photo usage types
 */

define ( 'PHOTO_NORMAL',           0x0000 );
define ( 'PHOTO_PROFILE',          0x0001 );
define ( 'PHOTO_XCHAN',            0x0002 );
define ( 'PHOTO_THING',            0x0004 );
define ( 'PHOTO_COVER',            0x0010 );

define ( 'PHOTO_ADULT',            0x0008 );
define ( 'PHOTO_FLAG_OS',          0x4000 );


define ( 'PHOTO_RES_ORIG',              0 );
define ( 'PHOTO_RES_1024',              1 );  // rectangular 1024 max width or height, floating height if not (4:3)
define ( 'PHOTO_RES_640',               2 );  // to accomodate SMBC vertical comic strips without scrunching the width
define ( 'PHOTO_RES_320',               3 );  // accordingly

define ( 'PHOTO_RES_PROFILE_300',       4 );  // square 300 px
define ( 'PHOTO_RES_PROFILE_80',        5 );  // square 80 px
define ( 'PHOTO_RES_PROFILE_48',        6 );  // square 48 px

define ( 'PHOTO_RES_COVER_1200',        7 );  // 1200w x 675h (16:9)
define ( 'PHOTO_RES_COVER_850',         8 );  // 850w x 478h
define ( 'PHOTO_RES_COVER_425',         9 );  // 425w x 239h


/**
 * Menu types
 */

define ( 'MENU_SYSTEM',          0x0001 );
define ( 'MENU_BOOKMARK',        0x0002 );

/**
 * Network and protocol family types
 */

define ( 'NETWORK_ZOT',              'zot');     // Zot!
define ( 'NETWORK_ZOT6',             'zot6');
define ( 'NETWORK_NOMAD',            'nomad');
define ( 'NETWORK_GNUSOCIAL',        'gnusoc');    // status.net, identi.ca, GNU-social, other OStatus implementations
define ( 'NETWORK_FEED',             'rss');    // RSS/Atom feeds with no known "post/notify" protocol
define ( 'NETWORK_DIASPORA',         'diaspora');    // Diaspora
define ( 'NETWORK_ACTIVITYPUB',      'activitypub');


/**
 * Permissions
 */

// 0 = Only you
define ( 'PERMS_PUBLIC'     , 0x0001 ); // anybody
define ( 'PERMS_NETWORK'    , 0x0002 ); // anybody in this network
define ( 'PERMS_SITE'       , 0x0004 ); // anybody on this site
define ( 'PERMS_CONTACTS'   , 0x0008 ); // any of my connections
define ( 'PERMS_SPECIFIC'   , 0x0080 ); // only specific connections
define ( 'PERMS_AUTHED'     , 0x0100 ); // anybody authenticated (could include visitors from other networks)
define ( 'PERMS_PENDING'    , 0x0200 ); // any connections including those who haven't yet been approved

// Address book flags

define ( 'ABOOK_FLAG_BLOCKED'    , 0x0001);
define ( 'ABOOK_FLAG_IGNORED'    , 0x0002);
define ( 'ABOOK_FLAG_HIDDEN'     , 0x0004);
define ( 'ABOOK_FLAG_ARCHIVED'   , 0x0008);
define ( 'ABOOK_FLAG_PENDING'    , 0x0010);
define ( 'ABOOK_FLAG_UNCONNECTED', 0x0020);
define ( 'ABOOK_FLAG_SELF'       , 0x0080);
define ( 'ABOOK_FLAG_FEED'       , 0x0100);
define ( 'ABOOK_FLAG_CENSORED'   , 0x0200);


define ( 'MAIL_DELETED',       0x0001);
define ( 'MAIL_REPLIED',       0x0002);
define ( 'MAIL_ISREPLY',       0x0004);
define ( 'MAIL_SEEN',          0x0008);
define ( 'MAIL_RECALLED',      0x0010);
define ( 'MAIL_OBSCURED',      0x0020);


define ( 'ATTACH_FLAG_DIR',    0x0001);
define ( 'ATTACH_FLAG_OS',     0x0002);


define ( 'MENU_ITEM_ZID',       0x0001);
define ( 'MENU_ITEM_NEWWIN',    0x0002);
define ( 'MENU_ITEM_CHATROOM',  0x0004);



define ( 'SITE_TYPE_ZOT',           0);
define ( 'SITE_TYPE_NOTZOT',        1);
define ( 'SITE_TYPE_UNKNOWN',       2);

/**
 * Poll/Survey types
 */

define ( 'POLL_SIMPLE_RATING',   0x0001);  // 1-5
define ( 'POLL_TENSCALE',        0x0002);  // 1-10
define ( 'POLL_MULTIPLE_CHOICE', 0x0004);
define ( 'POLL_OVERWRITE',       0x8000);  // If you vote twice remove the prior entry


define ( 'UPDATE_FLAGS_UPDATED',  0x0001);
define ( 'UPDATE_FLAGS_FORCED',   0x0002);
define ( 'UPDATE_FLAGS_CENSORED', 0x0004);
define ( 'UPDATE_FLAGS_DELETED',  0x1000);


define ( 'DROPITEM_NORMAL',      0);
define ( 'DROPITEM_PHASE1',      1);
define ( 'DROPITEM_PHASE2',      2);


/**
 * Maximum number of "people who like (or don't like) this"  that we will list by name
 */

define ( 'MAX_LIKERS',    10);

/**
 * Communication timeout
 */

define ( 'ZCURL_TIMEOUT' , (-1));


/**
 * email notification options
 */

define ( 'NOTIFY_INTRO',    0x0001 );
define ( 'NOTIFY_CONFIRM',  0x0002 );
define ( 'NOTIFY_WALL',     0x0004 );
define ( 'NOTIFY_COMMENT',  0x0008 );
define ( 'NOTIFY_MAIL',     0x0010 );
define ( 'NOTIFY_SUGGEST',  0x0020 );
define ( 'NOTIFY_PROFILE',  0x0040 );
define ( 'NOTIFY_TAGSELF',  0x0080 );
define ( 'NOTIFY_TAGSHARE', 0x0100 );
define ( 'NOTIFY_POKE',     0x0200 );
define ( 'NOTIFY_LIKE',     0x0400 );
define ( 'NOTIFY_RESHARE',  0x0800 );
define ( 'NOTIFY_MODERATE', 0x1000 );
define ( 'NOTIFY_SYSTEM',   0x8000 );

/**
 * visual notification options
 */

define ( 'VNOTIFY_NETWORK',    0x0001 );
define ( 'VNOTIFY_CHANNEL',    0x0002 );
define ( 'VNOTIFY_MAIL',       0x0004 );
define ( 'VNOTIFY_EVENT',      0x0008 );
define ( 'VNOTIFY_EVENTTODAY', 0x0010 );
define ( 'VNOTIFY_BIRTHDAY',   0x0020 );
define ( 'VNOTIFY_SYSTEM',     0x0040 );
define ( 'VNOTIFY_INFO',       0x0080 );
define ( 'VNOTIFY_ALERT',      0x0100 );
define ( 'VNOTIFY_INTRO',      0x0200 );
define ( 'VNOTIFY_REGISTER',   0x0400 );
define ( 'VNOTIFY_FILES',      0x0800 );
define ( 'VNOTIFY_PUBS',       0x1000 );
define ( 'VNOTIFY_LIKE',       0x2000 );
define ( 'VNOTIFY_FORUMS',     0x4000 );
define ( 'VNOTIFY_REPORTS',    0x8000 );
define ( 'VNOTIFY_MODERATE',   0x10000);


/**
 * Tag/term types
 */

define ( 'TERM_UNKNOWN',      0 );
define ( 'TERM_HASHTAG',      1 );
define ( 'TERM_MENTION',      2 );
define ( 'TERM_CATEGORY',     3 );
define ( 'TERM_PCATEGORY',    4 );
define ( 'TERM_FILE',         5 );
define ( 'TERM_SAVEDSEARCH',  6 );
define ( 'TERM_THING',        7 );
define ( 'TERM_BOOKMARK',     8 );
define ( 'TERM_HIERARCHY',    9 );
define ( 'TERM_COMMUNITYTAG', 10 );
define ( 'TERM_FORUM',        11 );
define ( 'TERM_EMOJI',        12 );
define ( 'TERM_QUOTED',       13 );

define ( 'TERM_OBJ_POST',    1 );
define ( 'TERM_OBJ_PHOTO',   2 );
define ( 'TERM_OBJ_PROFILE', 3 );
define ( 'TERM_OBJ_CHANNEL', 4 );
define ( 'TERM_OBJ_OBJECT',  5 );
define ( 'TERM_OBJ_THING',   6 );
define ( 'TERM_OBJ_APP',     7 );


/**
 * various namespaces we may need to parse
 */
define ( 'PROTOCOL_NOMAD',            'http://purl.org/nomad' );
define ( 'PROTOCOL_ZOT',              'http://purl.org/zot/protocol' );
define ( 'PROTOCOL_ZOT6',             'http://purl.org/zot/protocol/6.0' );
define ( 'NAMESPACE_ZOT',             'http://purl.org/zot' );
define ( 'NAMESPACE_DFRN' ,           'http://purl.org/macgirvin/dfrn/1.0' );
define ( 'NAMESPACE_THREAD' ,         'http://purl.org/syndication/thread/1.0' );
define ( 'NAMESPACE_TOMB' ,           'http://purl.org/atompub/tombstones/1.0' );
define ( 'NAMESPACE_ACTIVITY',        'http://activitystrea.ms/spec/1.0/' );
define ( 'NAMESPACE_ACTIVITY_SCHEMA', 'http://activitystrea.ms/schema/1.0/' );
define ( 'NAMESPACE_MEDIA',           'http://purl.org/syndication/atommedia' );
define ( 'NAMESPACE_SALMON_ME',       'http://salmon-protocol.org/ns/magic-env' );
define ( 'NAMESPACE_OSTATUSSUB',      'http://ostatus.org/schema/1.0/subscribe' );
define ( 'NAMESPACE_GEORSS',          'http://www.georss.org/georss' );
define ( 'NAMESPACE_POCO',            'http://portablecontacts.net/spec/1.0' );
define ( 'NAMESPACE_FEED',            'http://schemas.google.com/g/2010#updates-from' );
define ( 'NAMESPACE_OSTATUS',         'http://ostatus.org/schema/1.0' );
define ( 'NAMESPACE_STATUSNET',       'http://status.net/schema/api/1/' );
define ( 'NAMESPACE_ATOM1',           'http://www.w3.org/2005/Atom' );
define ( 'NAMESPACE_YMEDIA',          'http://search.yahoo.com/mrss/' );

// We should be using versioned jsonld contexts so that signatures will be slightly more reliable.
// Why signatures are unreliable by design is a problem nobody seems to care about
// "because it's a W3C standard". .

// Anyway, if you use versioned contexts, communication with Mastodon fails. Have not yet investigated
// the reason for the dependency but for the current time, use the standard non-versioned context.
//define ( 'ACTIVITYSTREAMS_JSONLD_REV', 'https://www.w3.org/ns/activitystreams-history/v1.8.jsonld' );

define ( 'ACTIVITYSTREAMS_JSONLD_REV', 'https://www.w3.org/ns/activitystreams' );

define ( 'ZOT_APSCHEMA_REV', '/apschema/v1.21' );

/**
 * activity stream defines
 */

define ( 'ACTIVITY_PUBLIC_INBOX',  'https://www.w3.org/ns/activitystreams#Public' );


define ( 'ACTIVITY_POST',        'Create' );
define ( 'ACTIVITY_CREATE',      'Create' );
define ( 'ACTIVITY_UPDATE',      'Update' );
define ( 'ACTIVITY_LIKE',        'Like' );
define ( 'ACTIVITY_DISLIKE',     'Dislike' );
define ( 'ACTIVITY_SHARE',       'Announce' );
define ( 'ACTIVITY_FOLLOW',      'Follow' );
define ( 'ACTIVITY_IGNORE',      'Ignore');

define ( 'ACTIVITY_OBJ_COMMENT', 'Note' );
define ( 'ACTIVITY_OBJ_NOTE',    'Note' );
define ( 'ACTIVITY_OBJ_ARTICLE', 'Article' );
define ( 'ACTIVITY_OBJ_PERSON',  'Person' );
define ( 'ACTIVITY_OBJ_PHOTO',   'Image');
define ( 'ACTIVITY_OBJ_P_PHOTO', 'Icon' );
define ( 'ACTIVITY_OBJ_PROFILE', 'Profile');
define ( 'ACTIVITY_OBJ_EVENT',   'Event' );
define ( 'ACTIVITY_OBJ_POLL',    'Question');
define ( 'ACTIVITY_OBJ_FILE',    'Document');


define ( 'ACTIVITY_REACT',       NAMESPACE_ZOT   . '/activity/react' );
define ( 'ACTIVITY_AGREE',       NAMESPACE_ZOT   . '/activity/agree' );
define ( 'ACTIVITY_DISAGREE',    NAMESPACE_ZOT   . '/activity/disagree' );
define ( 'ACTIVITY_ABSTAIN',     NAMESPACE_ZOT   . '/activity/abstain' );
define ( 'ACTIVITY_ATTEND',      NAMESPACE_ZOT   . '/activity/attendyes' );
define ( 'ACTIVITY_ATTENDNO',    NAMESPACE_ZOT   . '/activity/attendno' );
define ( 'ACTIVITY_ATTENDMAYBE', NAMESPACE_ZOT   . '/activity/attendmaybe' );
define ( 'ACTIVITY_POLLRESPONSE', NAMESPACE_ZOT  . '/activity/pollresponse' );

define ( 'ACTIVITY_OBJ_HEART',   NAMESPACE_ZOT   . '/activity/heart' );

define ( 'ACTIVITY_FRIEND',      NAMESPACE_ACTIVITY_SCHEMA . 'make-friend' );
define ( 'ACTIVITY_REQ_FRIEND',  NAMESPACE_ACTIVITY_SCHEMA . 'request-friend' );
define ( 'ACTIVITY_UNFRIEND',    NAMESPACE_ACTIVITY_SCHEMA . 'remove-friend' );
define ( 'ACTIVITY_JOIN',        NAMESPACE_ACTIVITY_SCHEMA . 'join' );

define ( 'ACTIVITY_FAVORITE',    NAMESPACE_ACTIVITY_SCHEMA . 'favorite' );
//define ( 'ACTIVITY_CREATE',      NAMESPACE_ACTIVITY_SCHEMA . 'create' );
define ( 'ACTIVITY_DELETE',      NAMESPACE_ACTIVITY_SCHEMA . 'delete' );
define ( 'ACTIVITY_WIN',         NAMESPACE_ACTIVITY_SCHEMA . 'win' );
define ( 'ACTIVITY_LOSE',        NAMESPACE_ACTIVITY_SCHEMA . 'lose' );
define ( 'ACTIVITY_TIE',         NAMESPACE_ACTIVITY_SCHEMA . 'tie' );
define ( 'ACTIVITY_COMPLETE',    NAMESPACE_ACTIVITY_SCHEMA . 'complete' );
define ( 'ACTIVITY_TAG',         NAMESPACE_ACTIVITY_SCHEMA . 'tag' );

define ( 'ACTIVITY_POKE',        NAMESPACE_ZOT . '/activity/poke' );
define ( 'ACTIVITY_MOOD',        NAMESPACE_ZOT . '/activity/mood' );


define ( 'ACTIVITY_OBJ_ACTIVITY',NAMESPACE_ACTIVITY_SCHEMA . 'activity' );
define ( 'ACTIVITY_OBJ_ALBUM',   NAMESPACE_ACTIVITY_SCHEMA . 'photo-album' );
define ( 'ACTIVITY_OBJ_GROUP',   NAMESPACE_ACTIVITY_SCHEMA . 'group' );
define ( 'ACTIVITY_OBJ_GAME',    NAMESPACE_ACTIVITY_SCHEMA . 'game' );
define ( 'ACTIVITY_OBJ_WIKI',    NAMESPACE_ACTIVITY_SCHEMA . 'wiki' );
define ( 'ACTIVITY_OBJ_TAGTERM', NAMESPACE_ZOT  . '/activity/tagterm' );
define ( 'ACTIVITY_OBJ_THING',   NAMESPACE_ZOT  . '/activity/thing' );
define ( 'ACTIVITY_OBJ_LOCATION',NAMESPACE_ZOT  . '/activity/location' );
// define ( 'ACTIVITY_OBJ_FILE',    NAMESPACE_ZOT  . '/activity/file' );
define ( 'ACTIVITY_OBJ_CARD',    NAMESPACE_ZOT  . '/activity/card' );

/**
 * Account Flags
 */

define ( 'ACCOUNT_OK',           0x0000 );
define ( 'ACCOUNT_UNVERIFIED',   0x0001 );
define ( 'ACCOUNT_BLOCKED',      0x0002 );
define ( 'ACCOUNT_EXPIRED',      0x0004 );
define ( 'ACCOUNT_REMOVED',      0x0008 );
define ( 'ACCOUNT_PENDING',      0x0010 );

/**
 * Account roles
 */

define ( 'ACCOUNT_ROLE_SYSTEM',    0x0002 );
define ( 'ACCOUNT_ROLE_DEVELOPER', 0x0004 );
define ( 'ACCOUNT_ROLE_ADMIN',     0x1000 );

/**
 * Item visibility
 */

define ( 'ITEM_VISIBLE',         0x0000);
define ( 'ITEM_HIDDEN',          0x0001);
define ( 'ITEM_BLOCKED',         0x0002);
define ( 'ITEM_MODERATED',       0x0004);
define ( 'ITEM_SPAM',            0x0008);
define ( 'ITEM_DELETED',         0x0010);
define ( 'ITEM_UNPUBLISHED',     0x0020);
define ( 'ITEM_WEBPAGE',         0x0040);   // is a static web page, not a conversational item
define ( 'ITEM_DELAYED_PUBLISH', 0x0080);
define ( 'ITEM_BUILDBLOCK',      0x0100);   // Named thusly to make sure nobody confuses this with ITEM_BLOCKED
define ( 'ITEM_PDL',             0x0200);   // Page Description Language - e.g. Comanche
define ( 'ITEM_BUG',             0x0400);   // Is a bug, can be used by the internal bug tracker
define ( 'ITEM_PENDING_REMOVE',  0x0800);   // deleted, notification period has lapsed
define ( 'ITEM_DOC',             0x1000);   // hubzilla only, define here so that item import does the right thing
define ( 'ITEM_CARD',            0x2000);
define ( 'ITEM_ARTICLE',         0x4000);


define ( 'ITEM_TYPE_POST',       0 );
define ( 'ITEM_TYPE_BLOCK',      1 );
define ( 'ITEM_TYPE_PDL',        2 );
define ( 'ITEM_TYPE_WEBPAGE',    3 );
define ( 'ITEM_TYPE_BUG',        4 );
define ( 'ITEM_TYPE_DOC',        5 );
define ( 'ITEM_TYPE_CARD',       6 );
define ( 'ITEM_TYPE_ARTICLE',    7 );
define ( 'ITEM_TYPE_MAIL',       8 );
define ( 'ITEM_TYPE_CUSTOM',     9 );
define ( 'ITEM_TYPE_REPORT',     10 );

define ( 'ITEM_IS_STICKY',       1000 );

define ( 'BLOCKTYPE_CHANNEL',    0 );
define ( 'BLOCKTYPE_SERVER',     1 );

define ( 'DBTYPE_MYSQL',    0 );
define ( 'DBTYPE_POSTGRES', 1 );

define ( 'HUBLOC_OFFLINE',  1 );

if (! defined('DEFAULT_PLATFORM_ICON')) {
    define( 'DEFAULT_PLATFORM_ICON', '/images/z1-32.png' );
}

if (! defined('DEFAULT_NOTIFY_ICON')) {
    define( 'DEFAULT_NOTIFY_ICON', '/images/z1-64.png' );
}

if (! defined('DEFAULT_COVER_PHOTO')) {
    define('DEFAULT_COVER_PHOTO','pexels-7599590');
}

if (! defined('DEFAULT_PROFILE_PHOTO')) {
    define('DEFAULT_PROFILE_PHOTO','rainbow_man');
}

