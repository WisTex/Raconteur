<?php

namespace Code\Lib;

use App;
use Code\Extend\Hook;
    

class Features {

    public static function enabled($uid, $feature)
    {

        $x = get_config('feature_lock', $feature);
        if ($x === false) {
            $x = get_pconfig($uid, 'feature', $feature);
            if ($x === false) {
                $x = get_config('feature', $feature);
                if ($x === false) {
                    $x = self::get_default($feature);
                }
            }
        }
        $arr = array('uid' => $uid, 'feature' => $feature, 'enabled' => $x);
        Hook::call('feature_enabled', $arr);
        return($arr['enabled']);
    }

    public static function get_default($feature)
    {
        $f = Features::get(false);
        foreach ($f as $cat) {
            foreach ($cat as $feat) {
                if (is_array($feat) && $feat[0] === $feature) {
                    return $feat[3];
                }
            }
        }
        return false;
    }


    public static function level($feature, $def)
    {
        $x = get_config('feature_level', $feature);
        if ($x !== false) {
            return intval($x);
        }
        return $def;
    }

    public static function get($filtered = true, $level = (-1))
    {

        $account = App::get_account();

        $arr = [

            // General
            'general' => [

                t('General Features'),

                [
                    'start_menu',
                    t('New Member Links'),
                    t('Display new member quick links menu'),
                    (($account && $account['account_created'] > datetime_convert('', '', 'now - 30 days')) ? true : false),
                    get_config('feature_lock', 'start_menu'),
                    self::level('start_menu', 1),
                ],

                [
                    'advanced_profiles',
                    t('Advanced Profiles'),
                    t('Additional profile sections and selections'),
                    false,
                    get_config('feature_lock', 'advanced_profiles'),
                    self::level('advanced_profiles', 1),
                ],


    //          [
    //              'profile_export',
    //              t('Profile Import/Export'),
    //              t('Save and load profile details across sites/channels'),
    //              false,
    //              get_config('feature_lock','profile_export'),
    //              self::level('profile_export',3),
    //          ],

    //          [
    //              'webpages',
    //              t('Web Pages'),
    //              t('Provide managed web pages on your channel'),
    //              false,
    //              get_config('feature_lock','webpages'),
    //              self::level('webpages',3),
    //          ],

    //          [
    //              'wiki',
    //              t('Wiki'),
    //              t('Provide a wiki for your channel'),
    //              false,
    //              get_config('feature_lock','wiki'),
    //              self::level('wiki',2),
    //          ],

    /*
                [
                    'hide_rating',
                    t('Hide Rating'),
                    t('Hide the rating buttons on your channel and profile pages. Note: People can still rate you somewhere else.'),
                    false,
                    get_config('feature_lock','hide_rating'),
                    self::level('hide_rating',3),
                ],
    */
                [
                    'private_notes',
                    t('Private Notes'),
                    t('Enables a tool to store notes and reminders (note: not encrypted)'),
                    false,
                    get_config('feature_lock', 'private_notes'),
                    self::level('private_notes', 1),
                ],


    //          [
    //              'cards',
    //              t('Cards'),
    //              t('Create personal planning cards'),
    //              false,
    //              get_config('feature_lock','cards'),
    //              self::level('cards',1),
    //          ],


                [
                    'articles',
                    t('Articles'),
                    t('Create interactive articles'),
                    false,
                    get_config('feature_lock', 'articles'),
                    self::level('articles', 1),
                ],

    //          [
    //              'nav_channel_select',
    //              t('Navigation Channel Select'),
    //              t('Change channels directly from within the navigation dropdown menu'),
    //              false,
    //              get_config('feature_lock','nav_channel_select'),
    //              self::level('nav_channel_select',3),
    //          ],

                [
                    'photo_location',
                    t('Photo Location'),
                    t('If location data is available on uploaded photos, link this to a map.'),
                    false,
                    get_config('feature_lock', 'photo_location'),
                    self::level('photo_location', 2),
                ],


    //          [
    //              'ajaxchat',
    //              t('Access Controlled Chatrooms'),
    //              t('Provide chatrooms and chat services with access control.'),
    //              true,
    //              get_config('feature_lock','ajaxchat'),
    //              self::level('ajaxchat',1),
    //          ],


    //          [
    //              'smart_birthdays',
    //              t('Smart Birthdays'),
    //              t('Make birthday events timezone aware in case your friends are scattered across the planet.'),
    //              true,
    //              get_config('feature_lock','smart_birthdays'),
    //              self::level('smart_birthdays',2),
    //          ],

                [
                    'event_tz_select',
                    t('Event Timezone Selection'),
                    t('Allow event creation in timezones other than your own.'),
                    false,
                    get_config('feature_lock', 'event_tz_select'),
                    self::level('event_tz_select', 2),
                ],


    //          [
    //              'premium_channel',
    //              t('Premium Channel'),
    //              t('Allows you to set restrictions and terms on those that connect with your channel'),
    //              false,
    //              get_config('feature_lock','premium_channel'),
    //              self::level('premium_channel',4),
    //          ],

                [
                    'advanced_dirsearch',
                    t('Advanced Directory Search'),
                    t('Allows creation of complex directory search queries'),
                    false,
                    get_config('feature_lock', 'advanced_dirsearch'),
                    self::level('advanced_dirsearch', 4),
                ],

                [
                    'advanced_theming',
                    t('Advanced Theme and Layout Settings'),
                    t('Allows fine tuning of themes and page layouts'),
                    false,
                    get_config('feature_lock', 'advanced_theming'),
                    self::level('advanced_theming', 4),
                ],
            ],


            'access_control' => [
                t('Access Control and Permissions'),

                [
                    'groups',
                    t('Privacy Groups'),
                    t('Enable management and selection of privacy groups'),
                    false,
                    get_config('feature_lock', 'groups'),
                    self::level('groups', 0),
                ],

    //          [
    //              'multi_profiles',
    //              t('Multiple Profiles'),
    //              t('Ability to create multiple profiles'),
    //              false,
    //              get_config('feature_lock','multi_profiles'),
    //              self::level('multi_profiles',3),
    //          ],


              [
                  'permcats',
                  t('Permission Categories'),
                  t('Create custom connection permission limits'),
                  true,
                  get_config('feature_lock','permcats'),
                  self::level('permcats',2),
              ],

    //          [
    //              'oauth_clients',
    //              t('OAuth1 Clients'),
    //              t('Manage OAuth1 authenticatication tokens for mobile and remote apps.'),
    //              false,
    //              get_config('feature_lock','oauth_clients'),
    //              self::level('oauth_clients',1),
    //          ],

                [
                    'oauth2_clients',
                    t('OAuth2 Clients'),
                    t('Manage OAuth2 authenticatication tokens for mobile and remote apps.'),
                    false,
                    get_config('feature_lock', 'oauth2_clients'),
                    self::level('oauth2_clients', 1),
                ],

    //          [
    //              'access_tokens',
    //              t('Access Tokens'),
    //              t('Create access tokens so that non-members can access private content.'),
    //              false,
    //              get_config('feature_lock','access_tokens'),
    //              self::level('access_tokens',2),
    //          ],

            ],

            // Post composition
            'composition' => [

                t('Post Composition Features'),

    //          [
    //              'large_photos',
    //              t('Large Photos'),
    //              t('Include large (1024px) photo thumbnails in posts. If not enabled, use small (640px) photo thumbnails'),
    //              false,
    //              get_config('feature_lock','large_photos'),
    //              self::level('large_photos',1),
    //          ],

    //          [
    //              'channel_sources',
    //              t('Channel Sources'),
    //              t('Automatically import channel content from other channels or feeds'),
    //              false,
    //              get_config('feature_lock','channel_sources'),
    //              self::level('channel_sources',3),
    //          ],

                [
                    'content_encrypt',
                    t('Browser Encryption'),
                    t('Provide optional browser-to-browser encryption of content with a shared secret key'),
                    true,
                    get_config('feature_lock', 'content_encrypt'),
                    self::level('content_encrypt', 3),
                ],

    //          [
    //              'consensus_tools',
    //              t('Enable Voting Tools'),
    //              t('Provide a class of post which others can vote on'),
    //              false,
    //              get_config('feature_lock','consensus_tools'),
    //              self::level('consensus_tools',3),
    //          ],

    //          [
    //              'disable_comments',
    //              t('Disable Comments'),
    //              t('Provide the option to disable comments for a post'),
    //              false,
    //              get_config('feature_lock','disable_comments'),
    //              self::level('disable_comments',2),
    //          ],

    //          [
    //              'delayed_posting',
    //              t('Delayed Posting'),
    //              t('Allow posts to be published at a later date'),
    //              false,
    //              get_config('feature_lock','delayed_posting'),
    //              self::level('delayed_posting',2),
    //          ],

    //          [
    //              'content_expire',
    //              t('Content Expiration'),
    //              t('Remove posts/comments and/or private messages at a future time'),
    //              false,
    //              get_config('feature_lock','content_expire'),
    //              self::level('content_expire',1),
    //          ],

                [
                    'suppress_duplicates',
                    t('Suppress Duplicate Posts/Comments'),
                    t('Prevent posts with identical content to be published with less than two minutes in between submissions.'),
                    true,
                    get_config('feature_lock', 'suppress_duplicates'),
                    self::level('suppress_duplicates', 1),
                ],

                [
                    'auto_save_draft',
                    t('Auto-save drafts of posts and comments'),
                    t('Automatically saves post and comment drafts in local browser storage to help prevent accidental loss of compositions'),
                    true,
                    get_config('feature_lock', 'auto_save_draft'),
                    self::level('auto_save_draft', 1),
                ],

            ],

            // Network Tools
            'net_module' => [

                t('Network and Stream Filtering'),

                [
                    'archives',
                    t('Search by Date'),
                    t('Ability to select posts by date ranges'),
                    false,
                    get_config('feature_lock', 'archives'),
                    self::level('archives', 1),
                ],


                [
                    'savedsearch',
                    t('Saved Searches'),
                    t('Save search terms for re-use'),
                    false,
                    get_config('feature_lock', 'savedsearch'),
                    self::level('savedsearch', 2),
                ],

                [
                    'order_tab',
                    t('Alternate Stream Order'),
                    t('Ability to order the stream by last post date, last comment date or unthreaded activities'),
                    false,
                    get_config('feature_lock', 'order_tab'),
                    self::level('order_tab', 2),
                ],

                [
                    'name_tab',
                    t('Contact Filter'),
                    t('Ability to display only posts of a selected contact'),
                    false,
                    get_config('feature_lock', 'name_tab'),
                    self::level('name_tab', 1),
                ],

                [
                    'forums_tab',
                    t('Forum Filter'),
                    t('Ability to display only posts of a specific forum'),
                    false,
                    get_config('feature_lock', 'forums_tab'),
                    self::level('forums_tab', 1),
                ],

                [
                    'personal_tab',
                    t('Personal Posts Filter'),
                    t('Ability to display only posts that you\'ve interacted on'),
                    false,
                    get_config('feature_lock', 'personal_tab'),
                    self::level('personal_tab', 1),
                ],

                [
                    'affinity',
                    t('Affinity Tool'),
                    t('Filter stream activity by depth of relationships'),
                    false,
                    get_config('feature_lock', 'affinity'),
                    self::level('affinity', 1),
                ],

                [
                    'suggest',
                    t('Suggest Channels'),
                    t('Show friend and connection suggestions'),
                    false,
                    get_config('feature_lock', 'suggest'),
                    self::level('suggest', 1),
                ],

                [
                    'connfilter',
                    t('Connection Filtering'),
                    t('Filter incoming posts from connections based on keywords/content'),
                    false,
                    get_config('feature_lock', 'connfilter'),
                    self::level('connfilter', 3),
                ],


            ],

            // Item tools
            'tools' => [

                t('Post/Comment Tools'),

                [
                    'commtag',
                    t('Community Tagging'),
                    t('Ability to tag existing posts'),
                    false,
                    get_config('feature_lock', 'commtag'),
                    self::level('commtag', 1),
                ],

                [
                    'categories',
                    t('Post Categories'),
                    t('Add categories to your posts'),
                    false,
                    get_config('feature_lock', 'categories'),
                    self::level('categories', 1),
                ],

                [
                    'emojis',
                    t('Emoji Reactions'),
                    t('Add emoji reaction ability to posts'),
                    true,
                    get_config('feature_lock', 'emojis'),
                    self::level('emojis', 1),
                ],

                [
                    'filing',
                    t('Saved Folders'),
                    t('Ability to file posts under folders'),
                    false,
                    get_config('feature_lock', 'filing'),
                    self::level('filing', 2),
                ],

                [
                    'dislike',
                    t('Dislike Posts'),
                    t('Ability to dislike posts/comments'),
                    false,
                    get_config('feature_lock', 'dislike'),
                    self::level('dislike', 1),
                ],

    //          [
    //              'star_posts',
    //              t('Star Posts'),
    //              t('Ability to mark special posts with a star indicator'),
    //              false,
    //              get_config('feature_lock','star_posts'),
    //              self::level('star_posts',1),
    //          ],
    //
                [
                    'tagadelic',
                    t('Tag Cloud'),
                    t('Provide a personal tag cloud on your channel page'),
                    false,
                    get_config('feature_lock', 'tagadelic'),
                    self::level('tagadelic', 2),
                ],
            ],
        ];

        $x = [ 'features' => $arr, ];
        Hook::call('get_features', $x);

        $arr = $x['features'];

        // removed any locked features and remove the entire category if this makes it empty

        if ($filtered) {
            $narr = [];
            foreach ($arr as $k => $x) {
                $narr[$k] = [ $arr[$k][0] ];
                $has_items = false;
                for ($y = 0; $y < count($arr[$k]); $y++) {
                    $disabled = false;
                    if (is_array($arr[$k][$y])) {
                        if ($arr[$k][$y][4] !== false) {
                            $disabled = true;
                        }
                        if (! $disabled) {
                            $has_items = true;
                            $narr[$k][$y] = $arr[$k][$y];
                        }
                    }
                }
                if (! $has_items) {
                    unset($narr[$k]);
                }
            }
        } else {
            $narr = $arr;
        }

        return $narr;
    }


    

}
