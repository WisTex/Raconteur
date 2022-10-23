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
        $arr = ['uid' => $uid, 'feature' => $feature, 'enabled' => $x];
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
                    'advanced_profiles',
                    t('Advanced Profiles'),
                    t('Additional profile sections and selections'),
                    false,
                    get_config('feature_lock', 'advanced_profiles'),
                    self::level('advanced_profiles', 1),
                ],


                [
                    'private_notes',
                    t('Private Notes'),
                    t('Enables a tool to store notes and reminders (note: not encrypted)'),
                    false,
                    get_config('feature_lock', 'private_notes'),
                    self::level('private_notes', 1),
                ],


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


              [
                  'permcats',
                  t('Permission Categories'),
                  t('Create custom connection permission limits'),
                  true,
                  get_config('feature_lock','permcats'),
                  self::level('permcats',2),
              ],


                [
                    'oauth2_clients',
                    t('OAuth2 Clients'),
                    t('Manage OAuth2 authenticatication tokens for mobile and remote apps.'),
                    false,
                    get_config('feature_lock', 'oauth2_clients'),
                    self::level('oauth2_clients', 1),
                ],


            ],

            // Post composition
            'composition' => [

                t('Post Composition Features'),


                [
                    'content_encrypt',
                    t('Browser Encryption'),
                    t('Provide optional browser-to-browser encryption of content with a shared secret key'),
                    true,
                    get_config('feature_lock', 'content_encrypt'),
                    self::level('content_encrypt', 3),
                ],



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
