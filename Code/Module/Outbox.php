<?php

namespace Code\Module;

use App;
use Code\Web\Controller;
use Code\Lib\ActivityStreams;
use Code\Lib\LDSignatures;
use Code\Lib\ThreadListener;
use Code\Web\HTTPSig;
use Code\Lib\Activity;
use Code\Lib\ActivityPub;
use Code\Lib\Config;
use Code\Lib\Channel;

/**
 * Implements an ActivityPub outbox.
 */
class Outbox extends Controller
{

    public function post()
    {
        if (argc() < 2) {
            killme();
        }

        $channel = Channel::from_username(argv(1));
        if (!$channel) {
            killme();
        }

        if (intval($channel['channel_system'])) {
            killme();
        }

        // At this point there is unlikely to be an authenticated observer using the C2S ActivityPub API.
        // Mostly we're protecting the page from malicious mischief until the project's OAuth2 interface
        // is linked to this page.

        $observer = App::get_observer();
        if (!$observer) {
            killme();
        }

        if ($observer['xchan_hash'] !== $channel['channel_hash']) {
            if (!perm_is_allowed($channel['channel_id'], $observer['xchan_hash'], 'post_wall')) {
                logger('outbox post permission denied to ' . $observer['xchan_name']);
                killme();
            }
        }
// disable C2S until we've fully tested it.
        return;


        $data = file_get_contents('php://input');
        if (!$data) {
            return;
        }

        logger('outbox_activity: ' . jindent($data), LOGGER_DATA);

        $AS = new ActivityStreams($data);

        if (!$AS->is_valid()) {
            return;
        }

        if (!PConfig::Get($channel['channel_id'], 'system', 'activitypub', Config::Get('system', 'activitypub', ACTIVITYPUB_ENABLED))) {
            return;
        }

        logger('outbox_channel: ' . $channel['channel_address'], LOGGER_DEBUG);

//      switch ($AS->type) {
//          case 'Follow':
//              if (is_array($AS->obj) && array_key_exists('type', $AS->obj) && ActivityStreams::is_an_actor($AS->obj['type']) && isset($AS->obj['id'])) {
//                  // do follow activity
//                  Activity::follow($channel,$AS);
//              }
//              break;
//          case 'Invite':
//              if (is_array($AS->obj) && array_key_exists('type', $AS->obj) && $AS->obj['type'] === 'Group') {
//                  // do follow activity
//                  Activity::follow($channel,$AS);
//              }
//              break;
//          case 'Join':
//              if (is_array($AS->obj) && array_key_exists('type', $AS->obj) && $AS->obj['type'] === 'Group') {
//                  // do follow activity
//                  Activity::follow($channel,$AS);
//              }
//              break;
//          case 'Accept':
//              // Activitypub for wordpress sends lowercase 'follow' on accept.
//              // https://github.com/pfefferle/wordpress-activitypub/issues/97
//              // Mobilizon sends Accept/"Member" (not in vocabulary) in response to Join/Group
//              if (is_array($AS->obj) && array_key_exists('type', $AS->obj) && in_array($AS->obj['type'], ['Follow','follow', 'Member'])) {
//                  // do follow activity
//                  Activity::follow($channel,$AS);
//              }
//              break;
//          case 'Reject':
//          default:
//              break;
//
//      }

        // These activities require permissions

        $item = null;

        switch ($AS->type) {
            case 'Update':
                if (is_array($AS->obj) && array_key_exists('type', $AS->obj) && ActivityStreams::is_an_actor($AS->obj['type'])) {
                    // pretend this is an old cache entry to force an update of all the actor details
                    $AS->obj['cached'] = true;
                    $AS->obj['updated'] = datetime_convert('UTC', 'UTC', '1980-01-01', ATOM_TIME);
                    Activity::actor_store($AS->obj['id'], $AS->obj);
                    break;
                }
            case 'Accept':
                if (is_array($AS->obj) && array_key_exists('type', $AS->obj) && (ActivityStreams::is_an_actor($AS->obj['type']) || $AS->obj['type'] === 'Member')) {
                    break;
                }
            case 'Create':
            case 'Like':
            case 'Dislike':
            case 'Announce':
            case 'Reject':
            case 'TentativeAccept':
            case 'TentativeReject':
            case 'Add':
            case 'Arrive':
            case 'Block':
            case 'Flag':
            case 'Ignore':
            case 'Invite':
            case 'Listen':
            case 'Move':
            case 'Offer':
            case 'Question':
            case 'Read':
            case 'Travel':
            case 'View':
            case 'emojiReaction':
            case 'EmojiReaction':
            case 'EmojiReact':
                // These require a resolvable object structure
                if (is_array($AS->obj)) {
                    // The boolean flag enables html cache of the item
                    $item = Activity::decode_note($AS, true);
                } else {
                    logger('unresolved object: ' . print_r($AS->obj, true));
                }
                break;
            case 'Undo':
                if ($AS->obj && is_array($AS->obj) && array_key_exists('type', $AS->obj) && $AS->obj['type'] === 'Follow') {
                    // do unfollow activity
                    Activity::unfollow($channel, $AS);
                    break;
                }
            case 'Leave':
                if ($AS->obj && is_array($AS->obj) && array_key_exists('type', $AS->obj) && $AS->obj['type'] === 'Group') {
                    // do unfollow activity
                    Activity::unfollow($channel, $AS);
                    break;
                }
            case 'Tombstone':
            case 'Delete':
                Activity::drop($channel, $observer_hash, $AS);
                break;
            case 'Move':
                if (
                    $observer_hash && $observer_hash === $AS->actor
                    && is_array($AS->obj) && array_key_exists('type', $AS->obj) && ActivityStream::is_an_actor($AS->obj['type'])
                    && is_array($AS->tgt) && array_key_exists('type', $AS->tgt) && ActivityStream::is_an_actor($AS->tgt['type'])
                ) {
                    ActivityPub::move($AS->obj, $AS->tgt);
                }
                break;
            case 'Add':
            case 'Remove':
            default:
                break;
        }

        if ($item) {
            logger('parsed_item: ' . print_r($item, true), LOGGER_DATA);
            Activity::store($channel, $observer_hash, $AS, $item);
        }

        http_status_exit(200, 'OK');
        return;
    }


    public function get()
    {

        if (observer_prohibited(true)) {
            killme();
        }

        if (argc() < 2) {
            killme();
        }

        $channel = Channel::from_username(argv(1));
        if (!$channel) {
            killme();
        }

//      if (intval($channel['channel_system'])) {
//          killme();
//      }

        if (ActivityStreams::is_as_request()) {
            $sigdata = HTTPSig::verify(($_SERVER['REQUEST_METHOD'] === 'POST') ? file_get_contents('php://input') : EMPTY_STR);
            if ($sigdata['portable_id'] && $sigdata['header_valid']) {
                $portable_id = $sigdata['portable_id'];
                if (!check_channelallowed($portable_id)) {
                    http_status_exit(403, 'Permission denied');
                }
                if (!check_siteallowed($sigdata['signer'])) {
                    http_status_exit(403, 'Permission denied');
                }
                observer_auth($portable_id);
            } elseif (Config::get('system', 'require_authenticated_fetch', false)) {
                http_status_exit(403, 'Permission denied');
            }

            $observer_hash = get_observer_hash();

            $params = [];

            $params['begin'] = ((x($_REQUEST, 'date_begin')) ? $_REQUEST['date_begin'] : NULL_DATE);
            $params['end'] = ((x($_REQUEST, 'date_end')) ? $_REQUEST['date_end'] : '');
            $params['type'] = 'json';
            $params['pages'] = ((x($_REQUEST, 'pages')) ? intval($_REQUEST['pages']) : 0);
            $params['top'] = ((x($_REQUEST, 'top')) ? intval($_REQUEST['top']) : 0);
            $params['direction'] = ((x($_REQUEST, 'direction')) ? dbesc($_REQUEST['direction']) : 'desc'); // unimplemented
            $params['cat'] = ((x($_REQUEST, 'cat')) ? escape_tags($_REQUEST['cat']) : '');
            $params['compat'] = 1;


            $total = items_fetch(
                [
                    'total' => true,
                    'wall' => '1',
                    'datequery' => $params['end'],
                    'datequery2' => $params['begin'],
                    'direction' => dbesc($params['direction']),
                    'pages' => $params['pages'],
                    'order' => dbesc('post'),
                    'top' => $params['top'],
                    'cat' => $params['cat'],
                    'compat' => $params['compat']
                ],
                $channel,
                $observer_hash,
                CLIENT_MODE_NORMAL,
                App::$module
            );

            if ($total) {
                App::set_pager_total($total);
                App::set_pager_itemspage(100);
            }

            if (App::$pager['unset'] && $total > 100) {
                $ret = Activity::paged_collection_init($total, App::$query_string);
            } else {
                $items = items_fetch(
                    [
                        'wall' => '1',
                        'datequery' => $params['end'],
                        'datequery2' => $params['begin'],
                        'records' => intval(App::$pager['itemspage']),
                        'start' => intval(App::$pager['start']),
                        'direction' => dbesc($params['direction']),
                        'pages' => $params['pages'],
                        'order' => dbesc('post'),
                        'top' => $params['top'],
                        'cat' => $params['cat'],
                        'compat' => $params['compat']
                    ],
                    $channel,
                    $observer_hash,
                    CLIENT_MODE_NORMAL,
                    App::$module
                );

                if ($items && $observer_hash) {
                    // check to see if this observer is a connection. If not, register any items
                    // belonging to this channel for notification of deletion/expiration

                    $x = q(
                        "select abook_id from abook where abook_channel = %d and abook_xchan = '%s'",
                        intval($channel['channel_id']),
                        dbesc($observer_hash)
                    );
                    if (!$x) {
                        foreach ($items as $item) {
                            if (strpos($item['mid'], z_root()) === 0) {
                                ThreadListener::store($item['mid'], $observer_hash);
                            }
                        }
                    }
                }

                $ret = Activity::encode_item_collection($items, App::$query_string, 'OrderedCollection', true, $total);
            }

            as_return_and_die($ret, $channel);
        }
    }
}
