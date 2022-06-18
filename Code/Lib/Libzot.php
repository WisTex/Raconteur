<?php

namespace Code\Lib;

/**
 * @brief lowlevel implementation of Nomad protocol.
 *
 */

use App;
use Code\Web\HTTPSig;
use Code\Access\Permissions;
use Code\Access\PermissionLimits;
use Code\Access\PermissionRoles;
use Code\Lib\LibBlock;
use Code\Lib\Activity;
use Code\Lib\Channel;
use Code\Lib\ASCollection;
use Code\Lib\LDSignatures;
use Code\Daemon\Run;
use Code\Extend\Hook;

require_once('include/html2bbcode.php');
require_once('include/photo_factory.php');

class Libzot
{

    /**
     * @brief Generates a unique string for use as a zot guid.
     *
     * Generates a unique string for use as a zot guid using our DNS-based url, the
     * channel nickname and some entropy.
     * The entropy ensures uniqueness against re-installs where the same URL and
     * nickname are chosen.
     *
     * @note zot doesn't require this to be unique. Internally we use a whirlpool
     * hash of this guid and the signature of this guid signed with the channel
     * private key. This can be verified and should make the probability of
     * collision of the verified result negligible within the constraints of our
     * immediate universe.
     *
     * @param string $channel_nick a unique nickname of controlling entity
     * @returns string
     */

    public static function new_uid($channel_nick)
    {
        $rawstr = z_root() . '/' . $channel_nick . '.' . mt_rand();
        return (base64url_encode(hash('whirlpool', $rawstr, true), true));
    }


    /**
     * @brief Generates a portable hash identifier for a channel.
     *
     * Generates a portable hash identifier for the channel identified by $guid and
     * $pubkey.
     *
     * @note This ID is portable across the network but MUST be calculated locally
     * by verifying the signature and can not be trusted as an identity.
     *
     * @param string $guid
     * @param string $pubkey
     */

    public static function make_xchan_hash($guid, $pubkey)
    {
        return base64url_encode(hash('whirlpool', $guid . $pubkey, true));
    }

    /**
     * @brief Given a zot hash, return all distinct hubs.
     *
     * This function is used in building the zot discovery packet and therefore
     * should only be used by channels which are defined on this hub.
     *
     * @param string $hash - xchan_hash
     * @returns array of hubloc (hub location structures)
     *
     */

    public static function get_hublocs($hash)
    {

        /* Only search for active hublocs - e.g. those that haven't been marked deleted */

        $ret = q(
            "select * from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0 order by hubloc_url ",
            dbesc($hash)
        );

        return $ret;
    }

    /**
     * @brief Builds a notification packet.
     *
     * Builds a notification packet that you can either store in the queue with
     * a message array or call zot_zot to immediately zot it to the other side.
     *
     * @param array $channel
     *   sender channel structure
     * @param string $type
     *   packet type: one of 'activity', 'response', 'sync', 'purge', 'refresh', 'force_refresh', 'rekey'
     * @param array $recipients
     *   envelope recipients, array of portable_id's; empty for public posts
     * @param string msg
     *   optional message
     * @param string $remote_key
     *   optional public site key of target hub used to encrypt entire packet
     * @param string $methods
     *   optional comma separated list of encryption methods @ref self::best_algorithm()
     * @returns string json encoded zot packet
     */

    public static function build_packet($channel, $type = 'activity', $recipients = null, $msg = '', $encoding = 'activitystreams', $remote_key = null, $methods = '')
    {

        $data = [
            'type' => $type,
            'encoding' => $encoding,
            'sender' => $channel['channel_hash'],
            'site_id' => self::make_xchan_hash(z_root(), get_config('system', 'pubkey')),
            'version' => System::get_zot_revision(),
        ];

        if ($recipients) {
            $data['recipients'] = $recipients;
        }

        if ($msg) {
            $actor = Channel::url($channel);
            if ($encoding === 'activitystreams' && array_key_exists('actor', $msg) && is_string($msg['actor']) && $actor === $msg['actor']) {
                $msg = JSalmon::sign($msg, $actor, $channel['channel_prvkey']);
            }
            $data['data'] = $msg;
        } else {
            unset($data['encoding']);
        }

        logger('packet: ' . print_r($data, true), LOGGER_DATA, LOG_DEBUG);

        if ($remote_key) {
            $algorithm = self::best_algorithm($methods);
            if ($algorithm) {
                $data = Crypto::encapsulate(json_encode($data), $remote_key, $algorithm);
            }
        }

        return json_encode($data);
    }


    /**
     * @brief Choose best encryption function from those available on both sites.
     *
     * @param string $methods
     *   comma separated list of encryption methods
     * @return string first match from our site method preferences crypto_methods() array
     * of a method which is common to both sites; or an empty string if no matches are found.
     *
     * Failure to find a common algorithm is not an issue as our communications
     * take place primarily over https, so this is just redundant encryption in many cases.
     *
     * In any case, the receiver is free to reject unencrypted private content if they have
     * reason to distrust https.
     *
     * We are not using array_intersect() here because the specification for that function
     * does not guarantee the order of results. It probably returns entries in the correct
     * order for our needs and would simplify this function dramatically, but we cannot be
     * certain that it will always do so on all operating systems.
     *
     */

    public static function best_algorithm($methods)
    {

        $x = [
            'methods' => $methods,
            'result' => ''
        ];

        /**
         * @hooks zot_best_algorithm
         *   Called when negotiating crypto algorithms with remote sites.
         *   * \e string \b methods - comma separated list of encryption methods
         *   * \e string \b result - the algorithm to return
         */

        Hook::call('zot_best_algorithm', $x);

        if ($x['result']) {
            return $x['result'];
        }

        if ($methods) {
            // $x = their methods as an array
            $x = explode(',', $methods);
            if ($x) {
                // $y = our methods as an array
                $y = Crypto::methods();
                if ($y) {
                    foreach ($y as $yv) {
                        $yv = trim($yv);
                        if (in_array($yv, $x)) {
                            return ($yv);
                        }
                    }
                }
            }
        }

        return EMPTY_STR;
    }


    /**
     * @brief send a zot message
     *
     * @param string $url
     * @param array $data
     * @param array $channel (required if using zot6 delivery)
     * @param array $crypto (required if encrypted httpsig, requires hubloc_sitekey and site_crypto elements)
     * @return array see z_post_url() for returned data format
     * @see z_post_url()
     *
     */

    public static function zot($url, $data, $channel = null, $crypto = null)
    {

        if ($channel) {
            $headers = [
                'X-Zot-Token' => random_string(),
                'Digest' => HTTPSig::generate_digest_header($data),
                'Content-type' => 'application/x-zot+json',
                '(request-target)' => 'post ' . get_request_string($url)
            ];

            $h = HTTPSig::create_sig(
                $headers,
                $channel['channel_prvkey'],
                Channel::url($channel),
                false,
                'sha512',
                (($crypto) ? ['key' => $crypto['hubloc_sitekey'], 'algorithm' => self::best_algorithm($crypto['site_crypto'])] : false)
            );
        } else {
            $h = [];
        }

        $redirects = 0;

        return z_post_url($url, $data, $redirects, ((empty($h)) ? [] : ['headers' => $h]));
    }

    public static function nomad($url, $data, $channel = null, $crypto = null) {

        if ($channel) {
            $headers = [
                'X-Nomad-Token'    => random_string(),
                'Digest'           => HTTPSig::generate_digest_header($data),
                'Content-type'     => 'application/x-nomad+json',
                '(request-target)' => 'post ' . get_request_string($url)
            ];

            $h = HTTPSig::create_sig($headers,$channel['channel_prvkey'],Channel::url($channel),false,'sha512',
                (($crypto) ? [ 'key' => $crypto['hubloc_sitekey'], 'algorithm' => self::best_algorithm($crypto['site_crypto']) ] : false));
        }
        else {
            $h = [];
        }

        $redirects = 0;

        return z_post_url($url,$data,$redirects,((empty($h)) ? [] : [ 'headers' => $h ]));
    }




    /**
     * @brief Refreshes after permission changed or friending, etc.
     *
     *
     * refresh is typically invoked when somebody has changed permissions of a channel and they are notified
     * to fetch new permissions via a finger/discovery operation. This may result in a new connection
     * (abook entry) being added to a local channel and it may result in auto-permissions being granted.
     *
     * Friending in zot is accomplished by sending a refresh packet to a specific channel which indicates a
     * permission change has been made by the sender which affects the target channel. The hub controlling
     * the target channel does targeted discovery (in which a zot discovery request contains permissions for
     * the sender which were set by the local channel). These are decoded here, and if necessary an abook
     * structure (addressbook) is created to store the permissions assigned to this channel.
     *
     * Initially these abook structures are created with a 'pending' flag, so that no reverse permissions are
     * implied until this is approved by the owner channel. A channel can also auto-populate permissions in
     * return and send back a refresh packet of its own. This is used by forum and group communication channels
     * so that friending and membership in the channel's "club" is automatic.
     *
     * If $force is set when calling this function, this operation will be attempted even if our records indicate
     * the remote site is permanently down.
     *
     * @param array $them => xchan structure of sender
     * @param array $channel => local channel structure of target recipient, required for "friending" operations
     * @param array $force (optional) default false
     *
     * @return boolean
     *   * \b true if successful
     *   * otherwise \b false
     */

    public static function refresh($them, $channel = null, $force = false)
    {

        $hsig_valid = false;

        logger('them: ' . print_r($them, true), LOGGER_DATA, LOG_DEBUG);
        if ($channel) {
            logger('channel: ' . print_r($channel, true), LOGGER_DATA, LOG_DEBUG);
        }

        $url = null;

        if ($them['hubloc_id_url']) {
            $url = $them['hubloc_id_url'];
        } else {
            $r = null;

            // if they re-installed the server we could end up with the wrong record - pointing to a hash generated by the old install.
            // We'll order by reverse id to try and pick off the most recently created ones first and hopefully end up with the correct hubloc.
            // We are looking for the most recently created primary hub, and the most recently created if for some reason we do not have a primary.
            // hubloc_id_url is set to the channel home, which corresponds to an ActivityStreams actor id.

            $r = q(
                "select hubloc_id_url, hubloc_primary from hubloc where hubloc_hash = '%s' and hubloc_network in ('zot6','nomad') and hubloc_deleted = 0 order by hubloc_id desc",
                dbesc($them['xchan_hash'])
            );

            if ($r) {
                foreach ($r as $rr) {
                    if (intval($rr['hubloc_primary'])) {
                        $url = $rr['hubloc_id_url'];
                        break;
                    }
                }
                if (!$url) {
                    $url = $r[0]['hubloc_id_url'];
                }
            }
        }

        if (!$url) {
            logger('zot_refresh: no url');
            return false;
        }

        $m = parse_url($url);
        $site_url = unparse_url(['scheme' => $m['scheme'], 'host' => $m['host']]);


        $s = q(
            "select site_dead from site where site_url = '%s' limit 1",
            dbesc($site_url)
        );

        if ($s && intval($s[0]['site_dead']) && (!$force)) {
            logger('zot_refresh: site ' . $site_url . ' is marked dead and force flag is not set. Cancelling operation.');
            return false;
        }

        $record = Zotfinger::exec($url, $channel);

        // Check the HTTP signature

        $hsig = $record['signature'];
        if ($hsig && $hsig['signer'] === $url && $hsig['header_valid'] === true && $hsig['content_valid'] === true) {
            $hsig_valid = true;
        }

        if (!$hsig_valid) {
            logger('http signature not valid: ' . (($record['data']) ? print_r($hsig, true) : 'fetch failed'));
            return false;
        }

        // If we reach this point, the signature is valid and we can trust the channel discovery data, so try and store
        // the generic information in the returned discovery packet.

        logger('zot-info: ' . print_r($record, true), LOGGER_DATA, LOG_DEBUG);

        $x = self::import_xchan($record['data'], (($force) ? UPDATE_FLAGS_FORCED : UPDATE_FLAGS_UPDATED));

        if (!$x['success']) {
            return false;
        }

        // Here we handle discovery packets that return targeted permissions and require an abook (either an existing connection
        // or a new one)

        if ($channel && $record['data']['permissions']) {
            $old_read_stream_perm = their_perms_contains($channel['channel_id'], $x['hash'], 'view_stream');
            set_abconfig($channel['channel_id'], $x['hash'], 'system', 'their_perms', $record['data']['permissions']);

            if (array_key_exists('profile', $record['data']) && array_key_exists('next_birthday', $record['data']['profile'])) {
                $next_birthday = datetime_convert('UTC', 'UTC', $record['data']['profile']['next_birthday']);
            } else {
                $next_birthday = NULL_DATE;
            }

            $profile_assign = get_pconfig($channel['channel_id'], 'system', 'profile_assign', '');

            // Keep original perms to check if we need to notify them
            $previous_perms = get_all_perms($channel['channel_id'], $x['hash']);

            $r = q(
                "select * from abook where abook_xchan = '%s' and abook_channel = %d and abook_self = 0 limit 1",
                dbesc($x['hash']),
                intval($channel['channel_id'])
            );

            if ($r) {
                // connection exists

                // if the dob is the same as what we have stored (disregarding the year), keep the one
                // we have as we may have updated the year after sending a notification; and resetting
                // to the one we just received would cause us to create duplicated events.

                if (substr($r[0]['abook_dob'], 5) == substr($next_birthday, 5)) {
                    $next_birthday = $r[0]['abook_dob'];
                }

                $y = q(
                    "update abook set abook_dob = '%s'
                    where abook_xchan = '%s' and abook_channel = %d
                    and abook_self = 0 ",
                    dbescdate($next_birthday),
                    dbesc($x['hash']),
                    intval($channel['channel_id'])
                );

                if (!$y) {
                    logger('abook update failed');
                } else {
                    // if we were just granted read stream permission and didn't have it before, try to pull in some posts
                    if ((!$old_read_stream_perm) && (intval($permissions['view_stream']))) {
                        Run::Summon(['Onepoll', $r[0]['abook_id']]);
                    }
                }
            } else {
                // limit the ability to do connection spamming, this limit is per channel
                $lim = intval(get_config('system', 'max_connections_per_day', 50));
                if ($lim) {
                    $n = q(
                        "select count(abook_id) as total from abook where abook_channel = %d and abook_created > '%s'",
                        intval($channel['channel_id']),
                        dbesc(datetime_convert('UTC', 'UTC', 'now - 24 hours'))
                    );
                    if ($n && intval($n['total']) > $lim) {
                        logger('channel: ' . $channel['channel_id'] . ' too many new connections per day. This one from ' . $hsig['signer'], LOGGER_NORMAL, LOG_WARNING);
                        return false;
                    }
                }

                // check personal blocklists

                $blocked = LibBlock::fetch($channel['channel_id'], BLOCKTYPE_SERVER);
                if ($blocked) {
                    foreach ($blocked as $b) {
                        if (strpos($url, $b['block_entity']) !== false) {
                            logger('siteblock - follower denied');
                            return;
                        }
                    }
                }
                if (LibBlock::fetch_by_entity($channel['channel_id'], $x['hash'])) {
                    logger('actorblock - follower denied');
                    return;
                }

                $p = Permissions::connect_perms($channel['channel_id']);
                $my_perms = Permissions::serialise($p['perms']);

                $automatic = $p['automatic'];

                // new connection

                if ($my_perms) {
                    set_abconfig($channel['channel_id'], $x['hash'], 'system', 'my_perms', $my_perms);
                }

                $closeness = get_pconfig($channel['channel_id'], 'system', 'new_abook_closeness', 80);

                // check if it is a sub-channel (collection) and auto-friend if it is

                $is_collection = false;

                $cl = q(
                    "select channel_id from channel where channel_hash = '%s' and channel_parent = '%s' and channel_account_id = %d limit 1",
                    dbesc($x['hash']),
                    dbesc($channel['channel_hash']),
                    intval($channel['channel_account_id'])
                );
                if ($cl) {
                    $is_collection = true;
                    $automatic = true;
                    $closeness = 10;
                }

                $y = abook_store_lowlevel(
                    [
                        'abook_account' => intval($channel['channel_account_id']),
                        'abook_channel' => intval($channel['channel_id']),
                        'abook_closeness' => intval($closeness),
                        'abook_xchan' => $x['hash'],
                        'abook_profile' => $profile_assign,
                        'abook_created' => datetime_convert(),
                        'abook_updated' => datetime_convert(),
                        'abook_dob' => $next_birthday,
                        'abook_pending' => intval(($automatic) ? 0 : 1)
                    ]
                );

                if ($y) {
                    logger("New introduction received for {$channel['channel_name']}");
                    $new_perms = get_all_perms($channel['channel_id'], $x['hash']);

                    // Send a clone sync packet and a permissions update if permissions have changed

                    $new_connection = q(
                        "select * from abook left join xchan on abook_xchan = xchan_hash where abook_xchan = '%s' and abook_channel = %d and abook_self = 0 order by abook_created desc limit 1",
                        dbesc($x['hash']),
                        intval($channel['channel_id'])
                    );

                    if ($new_connection) {
                        if (!Permissions::PermsCompare($new_perms, $previous_perms)) {
                            Run::Summon(['Notifier', 'permissions_create', $new_connection[0]['abook_id']]);
                        }

                        if (!$is_collection) {
                            Enotify::submit(
                                [
                                    'type' => NOTIFY_INTRO,
                                    'from_xchan' => $x['hash'],
                                    'to_xchan' => $channel['channel_hash'],
                                    'link' => z_root() . '/connedit/' . $new_connection[0]['abook_id']
                                ]
                            );
                        }

                        if (intval($permissions['view_stream'])) {
                            if (
                                intval(get_pconfig($channel['channel_id'], 'perm_limits', 'send_stream') & PERMS_PENDING)
                                || (!intval($new_connection[0]['abook_pending']))
                            ) {
                                Run::Summon(['Onepoll', $new_connection[0]['abook_id']]);
                            }
                        }


                        // If there is a default group for this channel, add this connection to it
                        // for pending connections this will happens at acceptance time.

                        if (!intval($new_connection[0]['abook_pending'])) {
                            $default_group = $channel['channel_default_group'];
                            if ($default_group) {
                                $g = AccessList::rec_byhash($channel['channel_id'], $default_group);
                                if ($g) {
                                    AccessList::member_add($channel['channel_id'], '', $x['hash'], $g['id']);
                                }
                            }
                        }

                        unset($new_connection[0]['abook_id']);
                        unset($new_connection[0]['abook_account']);
                        unset($new_connection[0]['abook_channel']);
                        $abconfig = load_abconfig($channel['channel_id'], $new_connection[0]['abook_xchan']);
                        if ($abconfig) {
                            $new_connection[0]['abconfig'] = $abconfig;
                        }
                        Libsync::build_sync_packet($channel['channel_id'], ['abook' => $new_connection]);
                    }
                }
            }
            return true;
        } else {
            return true;
        }
        return false;
    }

    /**
     * @brief Look up if channel is known and previously verified.
     *
     * A guid and a url, both signed by the sender, distinguish a known sender at a
     * known location.
     * This function looks these up to see if the channel is known and therefore
     * previously verified. If not, we will need to verify it.
     *
     * @param array $arr an associative array which must contain:
     *  * \e string \b id => id of conversant
     *  * \e string \b id_sig => id signed with conversant's private key
     *  * \e string \b location => URL of the origination hub of this communication
     *  * \e string \b location_sig => URL signed with conversant's private key
     * @param bool $multiple (optional) default false
     *
     * @return array|null
     *   * null if site is denied or not found
     *   * otherwise an array with an hubloc record
     */

    public static function gethub($arr, $multiple = false)
    {

        if ($arr['id'] && $arr['id_sig'] && $arr['location'] && $arr['location_sig']) {
            if (!check_siteallowed($arr['location'])) {
                logger('denied site: ' . $arr['location']);
                return null;
            }

            $limit = (($multiple) ? '' : ' limit 1 ');

            $r = q(
                "select hubloc.*, site.site_crypto from hubloc left join site on hubloc_url = site_url
                    where hubloc_guid = '%s' and hubloc_guid_sig = '%s'
                    and hubloc_url = '%s' and hubloc_url_sig = '%s'
                    and hubloc_site_id = '%s' and hubloc_network in ('nomad','zot6')
                    and hubloc_deleted = 0
                    $limit",
                dbesc($arr['id']),
                dbesc($arr['id_sig']),
                dbesc($arr['location']),
                dbesc($arr['location_sig']),
                dbesc($arr['site_id'])
            );
            if ($r) {
                logger('Found', LOGGER_DEBUG);
                return (($multiple) ? $r : $r[0]);
            }
        }
        logger('Not found: ' . print_r($arr, true), LOGGER_DEBUG);

        return false;
    }


    public static function valid_hub($sender, $site_id)
    {

        $r = q(
            "select hubloc.*, site.site_crypto from hubloc left join site on hubloc_url = site_url where hubloc_hash = '%s' and hubloc_site_id = '%s' and hubloc_deleted = 0 limit 1",
            dbesc($sender),
            dbesc($site_id)
        );
        if (!$r) {
            return null;
        }

        if (!check_siteallowed($r[0]['hubloc_url'])) {
            logger('denied site: ' . $r[0]['hubloc_url']);
            return null;
        }

        if (!check_channelallowed($r[0]['hubloc_hash'])) {
            logger('denied channel: ' . $r[0]['hubloc_hash']);
            return null;
        }

        return $r[0];
    }

    /**
     * @brief Registers an unknown hub.
     *
     * A communication has been received which has an unknown (to us) sender.
     * Perform discovery based on our calculated hash of the sender at the
     * origination address. This will fetch the discovery packet of the sender,
     * which contains the public key we need to verify our guid and url signatures.
     *
     * @param array $arr an associative array which must contain:
     *  * \e string \b guid => guid of conversant
     *  * \e string \b guid_sig => guid signed with conversant's private key
     *  * \e string \b url => URL of the origination hub of this communication
     *  * \e string \b url_sig => URL signed with conversant's private key
     *
     * @return array An associative array with
     *  * \b success boolean true or false
     *  * \b message (optional) error string only if success is false
     */

    public static function register_hub($id)
    {

        $id_hash = false;
        $valid = false;
        $hsig_valid = false;

        $result = ['success' => false];

        if (!$id) {
            return $result;
        }

        $record = Zotfinger::exec($id);

        // Check the HTTP signature

        $hsig = $record['signature'];
        if ($hsig['signer'] === $id && $hsig['header_valid'] === true && $hsig['content_valid'] === true) {
            $hsig_valid = true;
        }
        if (!$hsig_valid) {
            logger('http signature not valid: ' . print_r($hsig, true));
            return $result;
        }

        $c = self::import_xchan($record['data']);
        if ($c['success']) {
            $result['success'] = true;
        } else {
            logger('Failure to verify zot packet');
        }

        return $result;
    }

    /**
     * @brief Takes an associative array of a fetch discovery packet and updates
     *   all internal data structures which need to be updated as a result.
     *
     * @param array $arr => json_decoded discovery packet
     * @param int $ud_flags
     *    Determines whether to create a directory update record if any changes occur, default is UPDATE_FLAGS_UPDATED
     *    $ud_flags = UPDATE_FLAGS_FORCED indicates a forced refresh where we unconditionally create a directory update record
     *      this typically occurs once a month for each channel as part of a scheduled ping to notify the directory
     *      that the channel still exists
     * @param array $ud_arr
     *    If set [typically by update_directory_entry()] indicates a specific update table row and more particularly
     *    contains a particular address (ud_addr) which needs to be updated in that table.
     *
     * @return array An associative array with:
     *   * \e boolean \b success boolean true or false
     *   * \e string \b message (optional) error string only if success is false
     */

    public static function import_xchan($arr, $ud_flags = UPDATE_FLAGS_UPDATED, $ud_arr = null)
    {

        /**
         * @hooks import_xchan
         *   Called when processing the result of zot_finger() to store the result
         *   * \e array
         */
        Hook::call('import_xchan', $arr);

        $ret = array('success' => false);

        $changed = false;
        $what = '';

        if (!is_array($arr)) {
            logger('Not an array: ' . print_r($arr, true), LOGGER_DEBUG);
            return $ret;
        }

        if (!($arr['id'] && $arr['id_sig'])) {
            logger('No identity information provided. ' . print_r($arr, true));
            return $ret;
        }

        $xchan_hash = self::make_xchan_hash($arr['id'], $arr['public_key']);
        $arr['hash'] = $xchan_hash;

        $import_photos = false;

        $verified = false;

        if (!self::verify($arr['id'], $arr['id_sig'], $arr['public_key'])) {
            logger('Unable to verify channel signature for ' . $arr['address']);
            return $ret;
        } else {
            $verified = true;
        }

        if (!$verified) {
            $ret['message'] = t('Unable to verify channel signature');
            return $ret;
        }

        logger('import_xchan: ' . $xchan_hash, LOGGER_DEBUG);

        $r = q(
            "select * from xchan where xchan_hash = '%s' limit 1",
            dbesc($xchan_hash)
        );

        if (!array_key_exists('connect_url', $arr)) {
            $arr['connect_url'] = '';
        }

        if ($r) {
            if ($arr['photo'] && array_key_exists('updated', $arr['photo']) && $arr['photo']['updated'] > $r[0]['xchan_photo_date']) {
                $import_photos = true;
            }

            $hidden = (1 - intval($arr['searchable']));

            $hidden_changed = $adult_changed = $deleted_changed = $type_changed = 0;

            if (intval($r[0]['xchan_hidden']) != (1 - intval($arr['searchable']))) {
                $hidden_changed = 1;
            }
            if (intval($r[0]['xchan_selfcensored']) != intval($arr['adult_content'])) {
                $adult_changed = 1;
            }
            if (isset($arr['deleted']) && intval($r[0]['xchan_deleted']) != intval($arr['deleted'])) {
                $deleted_changed = 1;
            }

            $px = 0;
            if (isset($arr['channel_type'])) {
                if ($arr['channel_type'] === 'collection') {
                    $px = 2;
                } elseif ($arr['channel_type'] === 'group') {
                    $px = 1;
                }
            }
            if (array_key_exists('public_forum', $arr) && intval($arr['public_forum'])) {
                $px = 1;
            }

            if (intval($r[0]['xchan_type']) !== $px) {
                $type_changed = true;
            }

            if ($arr['protocols']) {
                set_xconfig($xchan_hash, 'system', 'protocols', implode(',',$arr['protocols']));
            }
            $collections = [];
            if (isset($arr['primary_location']['following'])) {
                $collections['following'] = $arr['primary_location']['following'];
            }
            if (isset($arr['primary_location']['followers'])) {
                $collections['followers'] = $arr['primary_location']['followers'];
            }
            if (isset($arr['primary_location']['wall'])) {
                $collections['wall'] = $arr['primary_location']['wall'];
            }
            if ($collections) {
                set_xconfig($xchan_hash, 'activitypub', 'collections', $collections);
            }

            if (isset($arr['cover_photo']) && isset($arr['cover_photo']['url']) && strlen($arr['cover_photo']['url'])) {
                set_xconfig($xchan_hash, 'system', 'cover_photo', $arr['cover_photo']['url']);
            }

            if (isset($arr['signing_algorithm']) && strlen($arr['signing_algorithm'])) {
                set_xconfig($xchan_hash, 'system', 'signing_algorithm', $arr['signing_algorithm']);
            }


            if (
                ($r[0]['xchan_name_date'] != $arr['name_updated'])
                || ($r[0]['xchan_connurl'] != $arr['primary_location']['connections_url'])
                || ($r[0]['xchan_addr'] != $arr['primary_location']['address'])
                || ($r[0]['xchan_follow'] != $arr['primary_location']['follow_url'])
                || ($r[0]['xchan_connpage'] != $arr['connect_url'])
                || ($r[0]['xchan_url'] != $arr['primary_location']['url'])
                || ($r[0]['xchan_updated'] < datetime_convert('UTC', 'UTC', 'now - 7 days'))
                || $hidden_changed || $adult_changed || $deleted_changed || $type_changed
            ) {
                $rup = q(
                    "update xchan set xchan_updated = '%s', xchan_name = '%s', xchan_name_date = '%s', xchan_connurl = '%s', xchan_follow = '%s',
                    xchan_connpage = '%s', xchan_hidden = %d, xchan_selfcensored = %d, xchan_deleted = %d, xchan_type = %d,
                    xchan_addr = '%s', xchan_url = '%s' where xchan_hash = '%s'",
                    dbesc(datetime_convert()),
                    dbesc(($arr['name']) ? unicode_trim(escape_tags($arr['name'])) : '-'),
                    dbesc($arr['name_updated']),
                    dbesc($arr['primary_location']['connections_url']),
                    dbesc($arr['primary_location']['follow_url']),
                    dbesc($arr['primary_location']['connect_url']),
                    intval(1 - intval($arr['searchable'])),
                    intval($arr['adult_content']),
                    intval($arr['deleted']),
                    intval($px),
                    dbesc(escape_tags($arr['primary_location']['address'])),
                    dbesc(escape_tags($arr['primary_location']['url'])),
                    dbesc($xchan_hash)
                );

                logger('Update: existing: ' . print_r($r[0], true), LOGGER_DATA, LOG_DEBUG);
                logger('Update: new: ' . print_r($arr, true), LOGGER_DATA, LOG_DEBUG);
                $what .= 'xchan ';
                $changed = true;
            }
        } else {
            $import_photos = true;

            if ($arr['channel_type'] === 'collection') {
                $px = 2;
            } elseif ($arr['channel_type'] === 'group') {
                $px = 1;
            } else {
                $px = 0;
            }

            if (array_key_exists('public_forum', $arr) && intval($arr['public_forum'])) {
                $px = 1;
            }

            $network = isset($arr['site']['protocol_version']) && intval($arr['site']['protocol_version']) > 10 ? 'nomad' : 'zot6';


            $x = xchan_store_lowlevel(
                [
                    'xchan_hash' => $xchan_hash,
                    'xchan_guid' => $arr['id'],
                    'xchan_guid_sig' => $arr['id_sig'],
                    'xchan_pubkey' => $arr['public_key'],
                    'xchan_photo_mimetype' => $arr['photo']['type'],
                    'xchan_photo_l' => $arr['photo']['url'],
                    'xchan_addr' => escape_tags($arr['primary_location']['address']),
                    'xchan_url' => escape_tags($arr['primary_location']['url']),
                    'xchan_connurl' => $arr['primary_location']['connections_url'],
                    'xchan_follow' => $arr['primary_location']['follow_url'],
                    'xchan_connpage' => $arr['connect_url'],
                    'xchan_name' => (($arr['name']) ? unicode_trim(escape_tags($arr['name'])) : '-'),
                    'xchan_network' => $network,
                    'xchan_updated' => datetime_convert(),
                    'xchan_photo_date' => $arr['photo']['updated'],
                    'xchan_name_date' => $arr['name_updated'],
                    'xchan_hidden' => intval(1 - intval($arr['searchable'])),
                    'xchan_selfcensored' => $arr['adult_content'],
                    'xchan_deleted' => $arr['deleted'],
                    'xchan_type' => $px
                ]
            );

            $what .= 'new_xchan';
            $changed = true;
        }

        if (isset($arr['cover_photo']) && isset($arr['cover_photo']['url']) && strlen($arr['cover_photo']['url'])) {
            set_xconfig($xchan_hash, 'system', 'cover_photo', $arr['cover_photo']['url']);
        }

        if ($import_photos) {
            require_once('include/photo_factory.php');

            // see if this is a channel clone that's hosted locally - which we treat different from other xchans/connections

            $local = q("select channel_id from channel where channel_hash = '%s' limit 1",
                dbesc($xchan_hash)
            );
            if (!$local) {
                $photos = import_remote_xchan_photo($arr['photo']['url'], $xchan_hash);
            }
            if ($photos) {
                if ($photos[4]) {
                    // importing the photo failed somehow. Leave the photo_date alone so we can try again at a later date.
                    $r = q(
                        "update xchan set xchan_updated = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s'
                        where xchan_hash = '%s'",
                        dbesc(datetime_convert()),
                        dbesc($photos[0]),
                        dbesc($photos[1]),
                        dbesc($photos[2]),
                        dbesc($photos[3]),
                        dbesc($xchan_hash)
                    );
                } else {
                    $r = q(
                        "update xchan set xchan_updated = '%s', xchan_photo_date = '%s', xchan_photo_l = '%s', xchan_photo_m = '%s', xchan_photo_s = '%s', xchan_photo_mimetype = '%s'
                        where xchan_hash = '%s'",
                        dbesc(datetime_convert()),
                        dbesc(datetime_convert('UTC', 'UTC', ((isset($arr['photo_updated'])) ? $arr['photo_updated'] : 'now'))),
                        dbesc($photos[0]),
                        dbesc($photos[1]),
                        dbesc($photos[2]),
                        dbesc($photos[3]),
                        dbesc($xchan_hash)
                    );
                }
                $what .= 'photo ';
                $changed = true;
            }
        }

        // what we are missing for true hub independence is for any changes in the primary hub to
        // get reflected not only in the hublocs, but also to update the URLs and addr in the appropriate xchan

        $s = Libsync::sync_locations($arr, $arr);

        if ($s) {
            if (isset($s['change_message']) && $s['change_message']) {
                $what .= $s['change_message'];
            }
            if (isset($s['changed']) && $s['changed']) {
                $changed = $s['changed'];
            }
            if (isset($s['message']) && $s['message']) {
                $ret['message'] .= $s['message'];
            }
        }

        // Which entries in the update table are we interested in updating?
        $address = ((isset($arr['address']) && $arr['address']) ? $arr['address'] : EMPTY_STR);
        if (isset($ud_arr) && isset($ud_arr['ud_addr'])) {
            $address = $ud_arr['ud_addr'];
        }


        if (array_key_exists('profile', $arr) && is_array($arr['profile'])) {
            $profile_changed = Libzotdir::import_directory_profile($xchan_hash, $arr['profile'], $address, $ud_flags, 1);
            if ($profile_changed) {
                $what .= 'profile ';
                $changed = true;
            }
        } else {
            logger('Profile not available - hiding');
            // they may have made it private
            $r = q(
                "delete from xprof where xprof_hash = '%s'",
                dbesc($xchan_hash)
            );
            $r = q(
                "delete from xtag where xtag_hash = '%s' and xtag_flags = 0",
                dbesc($xchan_hash)
            );
        }


        if (array_key_exists('site', $arr) && is_array($arr['site'])) {
            $profile_changed = self::import_site($arr['site']);
            if ($profile_changed) {
                $what .= 'site ';
                $changed = true;
            }
        }

        if (($changed) || ($ud_flags == UPDATE_FLAGS_FORCED)) {
            $guid = random_string() . '@' . App::get_hostname();
            Libzotdir::update_modtime($xchan_hash, $guid, $address, $ud_flags);
            logger('Changed: ' . $what, LOGGER_DEBUG);
        } elseif (!$ud_flags) {
            // nothing changed but we still need to update the updates record
            q(
                "update updates set ud_flags = ( ud_flags | %d ) where ud_addr = '%s' and not (ud_flags & %d) > 0 ",
                intval(UPDATE_FLAGS_UPDATED),
                dbesc($address),
                intval(UPDATE_FLAGS_UPDATED)
            );
        }

        if (!x($ret, 'message')) {
            $ret['success'] = true;
            $ret['hash'] = $xchan_hash;
        }

        logger('Result: ' . print_r($ret, true), LOGGER_DATA, LOG_DEBUG);
        return $ret;
    }

    /**
     * @brief Called immediately after sending a zot message which is using queue processing.
     *
     * Updates the queue item according to the response result and logs any information
     * returned to aid communications troubleshooting.
     *
     * @param string $hub - url of site we just contacted
     * @param array $arr - output of z_post_url()
     * @param array $outq - The queue structure attached to this request
     */

    public static function process_response($hub, $arr, $outq)
    {

        logger('remote: ' . print_r($arr, true), LOGGER_DATA);

        if (!$arr['success']) {
            logger('Failed: ' . $hub);
            return;
        }

        $x = json_decode($arr['body'], true);

        if (!$x) {
            logger('No json from ' . $hub);
            logger('Headers: ' . print_r($arr['header'], true), LOGGER_DATA, LOG_DEBUG);
        }

        $x = Crypto::unencapsulate($x, get_config('system', 'prvkey'));
        if (!is_array($x)) {
            $x = json_decode($x, true);
        }

        if (!is_array($x)) {
            logger('no useful response: ' . $x);
        }

        if ($x) {
            if (!$x['success']) {
                // handle remote validation issues

                $b = q(
                    "update dreport set dreport_result = '%s', dreport_time = '%s' where dreport_queue = '%s'",
                    dbesc(($x['message']) ? $x['message'] : 'unknown delivery error'),
                    dbesc(datetime_convert()),
                    dbesc($outq['outq_hash'])
                );
            }

            if (is_array($x) && array_key_exists('delivery_report', $x) && is_array($x['delivery_report'])) {
                foreach ($x['delivery_report'] as $xx) {
                    Hook::call('dreport_process', $xx);
                    if (is_array($xx) && array_key_exists('message_id', $xx) && DReport::is_storable($xx)) {
                        q(
                            "insert into dreport ( dreport_mid, dreport_site, dreport_recip, dreport_name, dreport_result, dreport_time, dreport_xchan, dreport_log ) values ( '%s', '%s', '%s','%s','%s','%s','%s','%s' ) ",
                            dbesc($xx['message_id']),
                            dbesc($xx['location']),
                            dbesc($xx['recipient']),
                            dbesc($xx['name']),
                            dbesc($xx['status']),
                            dbesc(datetime_convert('UTC', 'UTC', $xx['date'])),
                            dbesc($xx['sender']),
                            dbesc(EMPTY_STR)
                        );
                    }
                }

                // we have a more descriptive delivery report, so discard the per hub 'queue' report.

                q(
                    "delete from dreport where dreport_queue = '%s' ",
                    dbesc($outq['outq_hash'])
                );
            }
        }
        // update the timestamp for this site

        q(
            "update site set site_dead = 0, site_update = '%s' where site_url = '%s'",
            dbesc(datetime_convert()),
            dbesc(dirname($hub))
        );

        // synchronous message types are handled immediately
        // async messages remain in the queue until processed.

        if (intval($outq['outq_async'])) {
            Queue::remove($outq['outq_hash'], $outq['outq_channel']);
        }

        logger('zot_process_response: ' . print_r($x, true), LOGGER_DEBUG);
    }

    /**
     * @brief
     *
     * We received a notification packet (in mod_post) that a message is waiting for us, and we've verified the sender.
     * Check if the site is using zot6 delivery and includes a verified HTTP Signature, signed content, and a 'msg' field,
     * and also that the signer and the sender match.
     * If that happens, we do not need to fetch/pickup the message - we have it already and it is verified.
     * Translate it into the form we need for zot_import() and import it.
     *
     * Otherwise send back a pickup message, using our message tracking ID ($arr['secret']), which we will sign with our site
     * private key.
     * The entire pickup message is encrypted with the remote site's public key.
     * If everything checks out on the remote end, we will receive back a packet containing one or more messages,
     * which will be processed and delivered before this function ultimately returns.
     *
     * @param array $arr
     *     decrypted and json decoded notify packet from remote site
     * @return array from zot_import()
     * @see zot_import()
     *
     */

    public static function fetch($arr, $hub = null)
    {

        logger('zot_fetch: ' . print_r($arr, true), LOGGER_DATA, LOG_DEBUG);

        return self::import($arr, $hub);
    }

    /**
     * @brief Process incoming messages.
     *
     * Process incoming messages and import, update, delete as directed
     *
     * The message types handled here are 'activity' (e.g. posts), and 'sync'.
     *
     * @param array $arr
     *  'pickup' structure returned from remote site
     * @param string $sender_url
     *  the url specified by the sender in the initial communication.
     *  We will verify the sender and url in each returned message structure and
     *  also verify that all the messages returned match the site url that we are
     *  currently processing.
     *
     * @returns array
     *   Suitable for logging remotely, enumerating the processing results of each message/recipient combination
     *   * [0] => \e string $channel_hash
     *   * [1] => \e string $delivery_status
     *   * [2] => \e string $address
     */

    public static function import($arr, $hub = null)
    {

        $env = $arr;
        $private = false;
        $return = [];

        $result = null;

        logger('Notify: ' . print_r($env, true), LOGGER_DATA, LOG_DEBUG);

        if (!is_array($env)) {
            logger('decode error');
            return;
        }

        $message_request = false;

        $has_data = array_key_exists('data', $env) && $env['data'];
        $data = (($has_data) ? $env['data'] : false);

        $AS = null;

        if ($env['encoding'] === 'activitystreams') {
            $AS = new ActivityStreams($data);
            if (
                $AS->is_valid() && $AS->type === 'Announce' && is_array($AS->obj)
                && array_key_exists('object', $AS->obj) && array_key_exists('actor', $AS->obj)
            ) {
                // This is a relayed/forwarded Activity (as opposed to a shared/boosted object)
                // Reparse the encapsulated Activity and use that instead
                logger('relayed activity', LOGGER_DEBUG);
                $AS = new ActivityStreams($AS->obj);
            }

            if (!$AS->is_valid()) {
                logger('Activity rejected: ' . print_r($data, true));
                return;
            }

            // compatibility issue with like of Hubzilla "new friend" activities which is very difficult to fix

            if ($AS->implied_create && is_array($AS->obj) && array_key_exists('type', $AS->obj) && ActivityStreams::is_an_actor($AS->obj['type'])) {
                logger('create/person activity rejected. ' . print_r($data, true));
                return false;
            }

            if (is_array($AS->obj)) {
                $arr = Activity::decode_note($AS);
            } else {
                $arr = [];
            }

            logger($AS->debug(), LOGGER_DATA);
        }

        // There is nothing inherently wrong with getting a message-id which isn't a canonical URI/URL, but
        // at the present time (2019/02) during the Hubzilla transition to zot6 it is likely to cause lots of duplicates for
        // messages arriving from different protocols and sources with different message-id semantics. This
        // restriction can be relaxed once most Hubzilla sites are upgraded to > 4.0.
        // Don't check sync packets since they have a different encoding

        if ($arr && $env['type'] !== 'sync') {
            if (strpos($arr['mid'], 'http') === false && strpos($arr['mid'], 'x-zot') === false) {
                if (strpos($arr['mid'], 'bear:') === false) {
                    logger('activity rejected: legacy message-id');
                    return;
                }
            }

            if ($arr['verb'] === 'Create' && ActivityStreams::is_an_actor($arr['obj_type'])) {
                logger('activity rejected: create actor');
                return;
            }
        }

        $deliveries = null;

        if (array_key_exists('recipients', $env) && count($env['recipients'])) {
            logger('specific recipients');
            logger('recipients: ' . print_r($env['recipients'], true), LOGGER_DEBUG);

            $recip_arr = [];
            foreach ($env['recipients'] as $recip) {
                $recip_arr[] = $recip;
            }

            $r = false;
            if ($recip_arr) {
                stringify_array_elms($recip_arr, true);
                $recips = implode(',', $recip_arr);
                $r = q("select channel_hash as hash from channel where channel_hash in ( " . $recips . " ) and channel_removed = 0 ");
            }

            if (!$r) {
                logger('recips: no recipients on this site');
                return;
            }

            // Response messages will inherit the privacy of the parent

            if ($env['type'] !== 'response') {
                $private = true;
            }

            $deliveries = ids_to_array($r, 'hash');

            // We found somebody on this site that's in the recipient list.
        } else {
            logger('public post');


            // Public post. look for any site members who are or may be accepting posts from this sender
            // and who are allowed to see them based on the sender's permissions
            // @fixme;

            $deliveries = self::public_recips($env, $AS);
        }

        $deliveries = array_unique($deliveries);

        if (!$deliveries) {
            logger('No deliveries on this site');
            return;
        }

        if ($has_data) {
            if (in_array($env['type'], ['activity', 'response'])) {

                if (!(is_array($AS->actor) && isset($AS->actor['id']))) {
                    logger('No author!');
                    return;
                }

                $r = q(
                    "select hubloc_hash, hubloc_network, hubloc_url from hubloc where hubloc_id_url = '%s' and hubloc_deleted = 0",
                    dbesc($AS->actor['id'])
                );
                if (! $r) {
                    // Author is unknown to this site. Perform channel discovery and try again.
                    $z = discover_by_webbie($AS->actor['id']);
                    if ($z) {
                        $r = q(
                            "select hubloc_hash, hubloc_network, hubloc_url from hubloc where hubloc_id_url = '%s' and hubloc_deleted = 0",
                            dbesc($AS->actor['id'])
                        );
                    }
                }

                if ($r) {
                    $r = self::zot_record_preferred($r);
                    $arr['author_xchan'] = $r['hubloc_hash'];
                }

                if (!$arr['author_xchan']) {
                    logger('No author!');
                    return;
                }

                $s = q(
                    "select hubloc_hash, hubloc_url from hubloc where hubloc_id_url = '%s' and hubloc_network in ('zot6','nomad') and hubloc_deleted = 0 limit 1",
                    dbesc($env['sender'])
                );

                // in individual delivery, change owner if needed
                if ($s) {
                    $arr['owner_xchan'] = $s[0]['hubloc_hash'];
                } else {
                    $arr['owner_xchan'] = $env['sender'];
                }

                if ($private && (!intval($arr['item_private']))) {
                    $arr['item_private'] = 1;
                }
                if ($arr['mid'] === $arr['parent_mid']) {
                    if (is_array($AS->obj) && array_key_exists('commentPolicy', $AS->obj)) {
                        $p = strstr($AS->obj['commentPolicy'], 'until=');

                        // if until= is the same as the creation date, set the item_nocomment flag
                        // as comments were already closed before the post was even sent.

                        if ($p !== false) {
                            $comments_closed_at = datetime_convert('UTC', 'UTC', substr($p, 6));
                            if ($comments_closed_at === $arr['created']) {
                                $arr['item_nocomment'] = 1;
                            } else {
                                $arr['comments_closed'] = $comments_closed_at;
                                $arr['comment_policy'] = trim(str_replace($p, '', $AS->obj['commentPolicy']));
                            }
                        } else {
                            $arr['comment_policy'] = $AS->obj['commentPolicy'];
                        }
                    }
                }
                if (isset($AS->data['hubloc']) && $AS->data['hubloc']) {
                    $arr['item_verified'] = true;
                }

                if (!array_key_exists('comment_policy', $arr)) {
                    $arr['comment_policy'] = 'authenticated';
                }
                if (isset($AS->meta['signed_data'])) {
                    IConfig::Set($arr, 'activitypub', 'signed_data', $AS->meta['signed_data'], false);
                    $j = json_decode($AS->meta['signed_data'], true);
                    if ($j) {
                        IConfig::Set($arr, 'activitypub', 'rawmsg', json_encode(JSalmon::unpack($j['data'])), true);
                    }
                }

                logger('Activity received: ' . print_r($arr, true), LOGGER_DATA, LOG_DEBUG);
                logger('Activity recipients: ' . print_r($deliveries, true), LOGGER_DATA, LOG_DEBUG);

                $relay = (($env['type'] === 'response') ? true : false);

                $result = self::process_delivery($env['sender'], $AS, $arr, $deliveries, $relay, false, $message_request);
            } elseif ($env['type'] === 'sync') {
                $arr = json_decode($data, true);

                logger('Channel sync received: ' . print_r($arr, true), LOGGER_DATA, LOG_DEBUG);
                logger('Channel sync recipients: ' . print_r($deliveries, true), LOGGER_DATA, LOG_DEBUG);

                if ($env['encoding'] === 'red') {
                    $result = Libsync::process_channel_sync_delivery($env['sender'], $arr, $deliveries);
                } else {
                    logger('unsupported sync packet encoding ignored.');
                }
            }
        }
        if ($result) {
            $return = array_merge($return, $result);
        }
        return $return;
    }


    public static function is_top_level($env, $act)
    {
        if ($env['encoding'] === 'zot' && array_key_exists('flags', $env) && in_array('thread_parent', $env['flags'])) {
            return true;
        }
        if ($act) {
            if (in_array($act->type, ['Like', 'Dislike'])) {
                return false;
            }
            $x = self::find_parent($env, $act);
            if ($x === $act->id || (is_array($act->obj) && array_key_exists('id', $act->obj) && $x === $act->obj['id'])) {
                return true;
            }
        }
        return false;
    }


    public static function find_parent($env, $act)
    {
        if ($act) {
            if (in_array($act->type, ['Like', 'Dislike']) && is_array($act->obj)) {
                return $act->obj['id'];
            }
            if ($act->parent_id) {
                return $act->parent_id;
            }
        }
        return false;
    }


    /**
     * @brief
     *
     * A public message with no listed recipients can be delivered to anybody who
     * has PERMS_NETWORK for that type of post, PERMS_AUTHED (in-network senders are
     * by definition authenticated) or PERMS_SITE and is one the same site,
     * or PERMS_SPECIFIC and the sender is a contact who is granted permissions via
     * their connection permissions in the address book.
     * Here we take a given message and construct a list of hashes of everybody
     * on the site that we should try and deliver to.
     * Some of these will be rejected, but this gives us a place to start.
     *
     * @param array $msg
     * @return NULL|array
     */

    public static function public_recips($msg, $act)
    {

        $check_mentions = false;
        $include_sys = false;

        if ($msg['type'] === 'activity') {
            $public_stream_mode = intval(get_config('system', 'public_stream_mode', PUBLIC_STREAM_NONE));
            if ($public_stream_mode === PUBLIC_STREAM_FULL) {
                $include_sys = true;
            }

            $perm = 'send_stream';

            if (self::is_top_level($msg, $act)) {
                $check_mentions = true;
            }
        } elseif ($msg['type'] === 'mail') {
            $perm = 'post_mail';
        }

        // channels which we will deliver this post to
        $r = [];

        $c = q("select channel_id, channel_hash from channel where channel_removed = 0");

        if ($c) {
            foreach ($c as $cc) {
                // top level activity sent to ourself: ignore. Clones will get a sync activity
                // which is a true clone of the original item. Everything else is a duplicate.

                if ($check_mentions && $cc['channel_hash'] === $msg['sender']) {
                    continue;
                }

                if (perm_is_allowed($cc['channel_id'], $msg['sender'], $perm)) {
                    $r[] = $cc['channel_hash'];
                }
            }
        }

        if ($include_sys) {
            $sys = Channel::get_system();
            if ($sys) {
                $r[] = $sys['channel_hash'];
            }
        }

        // add channels that are following tags
        // these will be enumerated and validated in tgroup_check()

        $ft = q(
            "select channel_hash as hash from channel left join pconfig on pconfig.uid = channel_id where cat = 'system' and k = 'followed_tags' and channel_hash != '%s' and channel_removed = 0",
            dbesc($msg['sender'])
        );
        if ($ft) {
            foreach ($ft as $t) {
                $r[] = $t['hash'];
            }
        }

        // look for any public mentions on this site
        // They will get filtered by tgroup_check() so we don't need to check permissions now

        if ($check_mentions) {
            // It's a top level post. Look at the tags. See if any of them are mentions and are on this hub.
            if ($act && $act->obj) {
                if (is_array($act->obj['tag']) && $act->obj['tag']) {
                    foreach ($act->obj['tag'] as $tag) {
                        if ($tag['type'] === 'Mention' && (strpos($tag['href'], z_root()) !== false)) {
                            $address = basename($tag['href']);
                            if ($address) {
                                $z = q(
                                    "select channel_hash as hash from channel where channel_address = '%s'
                                    and channel_hash != '%s' and channel_removed = 0 limit 1",
                                    dbesc($address),
                                    dbesc($msg['sender'])
                                );
                                if ($z) {
                                    $r[] = $z[0]['hash'];
                                }
                            }
                        }
                        if ($tag['type'] === 'topicalCollection' && strpos($tag['name'], App::get_hostname())) {
                            $address = substr($tag['name'], 0, strpos($tag['name'], '@'));
                            if ($address) {
                                $z = q(
                                    "select channel_hash as hash from channel where channel_address = '%s'
                                    and channel_hash != '%s' and channel_removed = 0 limit 1",
                                    dbesc($address),
                                    dbesc($msg['sender'])
                                );
                                if ($z) {
                                    $r[] = $z[0]['hash'];
                                }
                            }
                        }
                    }
                }
            }
        } else {
            // This is a comment. We need to find any parent with ITEM_UPLINK set. But in fact, let's just return
            // everybody that stored a copy of the parent. This way we know we're covered. We'll check the
            // comment permissions when we deliver them.

            $thread_parent = self::find_parent($msg, $act);

            if ($thread_parent) {
                $z = q(
                    "select channel_hash as hash from channel left join item on channel.channel_id = item.uid where ( item.thr_parent = '%s' OR item.parent_mid = '%s' ) ",
                    dbesc($thread_parent),
                    dbesc($thread_parent)
                );
                if ($z) {
                    foreach ($z as $zv) {
                        $r[] = $zv['hash'];
                    }
                }
            }
        }

        // There are probably a lot of duplicates in $r at this point. We need to filter those out.

        if ($r) {
            $r = array_values(array_unique($r));
        }

        logger('public_recips: ' . print_r($r, true), LOGGER_DATA, LOG_DEBUG);
        return $r;
    }


    /**
     * @brief
     *
     * @param array $sender
     * @param ActivityStreams object $act
     * @param array $msg_arr
     * @param array $deliveries
     * @param bool $relay
     * @param bool $public (optional) default false
     * @param bool $request (optional) default false
     * @return array
     */

    public static function process_delivery($sender, $act, $msg_arr, $deliveries, $relay, $public = false, $request = false)
    {

        $result = [];

        // logger('msg_arr: ' . print_r($msg_arr,true),LOGGER_ALL);

        // If an upstream hop used ActivityPub, set the identities to zot6 nomadic identities where applicable
        // else things could easily get confused

        $msg_arr['author_xchan'] = Activity::find_best_identity($msg_arr['author_xchan']);
        $msg_arr['owner_xchan'] = Activity::find_best_identity($msg_arr['owner_xchan']);

        // We've validated the sender. Now make sure that the sender is the owner or author

        if (!$public) {
            if ($sender != $msg_arr['owner_xchan'] && $sender != $msg_arr['author_xchan']) {
                logger("Sender $sender is not owner {$msg_arr['owner_xchan']} or author {$msg_arr['author_xchan']} - mid {$msg_arr['mid']}");
                return;
            }
        }

        if ($act->implied_create) {
            logger('implied create activity. Not delivering/storing.');
            return;
        }

        foreach ($deliveries as $d) {
            $local_public = $public;

            // if any further changes are to be made, change a copy and not the original
            $arr = $msg_arr;

            $DR = new DReport(z_root(), $sender, $d, $arr['mid']);

            $channel = Channel::from_hash($d);

            if (!$channel) {
                $DR->update('recipient not found');
                $result[] = $DR->get();
                continue;
            }

            $DR->set_name($channel['channel_name'] . ' <' . Channel::get_webfinger($channel) . '>');

//          if ($act->type === 'Tombstone') {
//              $r = q("select * from item where mid in ( '%s', '%s' ) and uid = %d",
//                  dbesc($act->id),
//                  dbesc(str_replace('/activity/','/item/',$act->id))
//                  intval($channel['channel_id'])
//              );
//              if ($r) {
//                  if (($r[0]['author_xchan'] === $sender) || ($r[0]['owner_xchan'] === $sender)) {
//                      drop_item($r[0]['id'],false);
//                  }
//                  $DR->update('item deleted');
//                  $result[] = $DR->get();
//                  continue;
//              }
//              $DR->update('deleted item not found');
//              $result[] = $DR->get();
//              continue;
//          }

            if (($act) && ($act->obj) && (!is_array($act->obj))) {
                // The initial object fetch failed using the sys channel credentials.
                // Try again using the delivery channel credentials.
                // We will also need to re-parse the $item array,
                // but preserve any values that were set during anonymous parsing.

                $o = Activity::fetch($act->obj, $channel);
                if ($o) {
                    $act->obj = $o;
                    $arr = array_merge(Activity::decode_note($act), $arr);
                } else {
                    $DR->update('Incomplete or corrupt activity');
                    $result[] = $DR->get();
                    continue;
                }
            }

            /**
             * We need to block normal top-level message delivery from our clones, as the delivered
             * message doesn't have ACL information in it as the cloned copy does. That copy
             * will normally arrive first via sync delivery, but this isn't guaranteed.
             * There's a chance the current delivery could take place before the cloned copy arrives
             * hence the item could have the wrong ACL and *could* be used in subsequent deliveries or
             * access checks.
             */

            if ($sender === $channel['channel_hash'] && $arr['author_xchan'] === $channel['channel_hash'] && $arr['mid'] === $arr['parent_mid']) {
                $DR->update('self delivery ignored');
                $result[] = $DR->get();
                continue;
            }

            // allow public postings to the sys channel regardless of permissions, but not
            // for comments travelling upstream. Wait and catch them on the way down.
            // They may have been blocked by the owner.

            if (intval($channel['channel_system']) && (!$arr['item_private']) && (!$relay)) {
                $local_public = true;

                if (!check_pubstream_channelallowed($sender)) {
                    $local_public = false;
                    continue;
                }

                // don't allow pubstream posts if the sender even has a clone on a pubstream denied site

                $siteallowed = true;
                $h = q(
                    "select hubloc_url from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0",
                    dbesc($sender)
                );
                if ($h) {
                    foreach ($h as $hub) {
                        if (!check_pubstream_siteallowed($hub['hubloc_url'])) {
                            $siteallowed = false;
                            break;
                        }
                    }
                }
                if (!$siteallowed) {
                    $local_public = false;
                    continue;
                }

                $r = q(
                    "select xchan_selfcensored from xchan where xchan_hash = '%s' limit 1",
                    dbesc($sender)
                );
                // don't import sys channel posts from selfcensored authors
                if ($r && (intval($r[0]['xchan_selfcensored']))) {
                    $local_public = false;
                    continue;
                }
                if (!MessageFilter::evaluate($arr, get_config('system', 'pubstream_incl'), get_config('system', 'pubstream_excl'))) {
                    $local_public = false;
                    continue;
                }
            }

            // perform pre-storage check to see if it's "likely" that this is a group or collection post

            $tag_delivery = tgroup_check($channel['channel_id'], $arr);

            $perm = 'send_stream';
            if (($arr['mid'] !== $arr['parent_mid']) && ($relay)) {
                $perm = 'post_comments';
            }

            // This is our own post, possibly coming from a channel clone

            if ($arr['owner_xchan'] == $d) {
                $arr['item_wall'] = 1;
            } else {
                $arr['item_wall'] = 0;
            }

            $friendofriend = false;

            if ((!$tag_delivery) && (!$local_public)) {
                $allowed = (perm_is_allowed($channel['channel_id'], $sender, $perm));

                $blocked = LibBlock::fetch($channel['channel_id'], BLOCKTYPE_SERVER);
                if ($blocked) {
                    $h = q(
                        "select hubloc_url from hubloc where hubloc_hash = '%s' and hubloc_deleted = 0",
                        dbesc($sender)
                    );
                    if ($h) {
                        foreach ($h as $hub) {
                            foreach ($blocked as $b) {
                                if (strpos($hub['hubloc_url'], $b['block_entity']) !== false) {
                                    $allowed = false;
                                }
                            }
                        }
                    }
                }

                $permit_mentions = intval(PConfig::Get($channel['channel_id'], 'system', 'permit_all_mentions') && i_am_mentioned($channel, $arr));

                if (!$allowed) {
                    if ($perm === 'post_comments') {
                        $parent = q(
                            "select * from item where mid = '%s' and uid = %d limit 1",
                            dbesc($arr['parent_mid']),
                            intval($channel['channel_id'])
                        );
                        if ($parent) {
                            $allowed = can_comment_on_post($sender, $parent[0]);
                        }
                        if ((!$allowed) && $permit_mentions) {
                            if ($parent && $parent[0]['owner_xchan'] === $channel['channel_hash']) {
                                $allowed = false;
                            } else {
                                $allowed = true;
                            }
                        }
                        if ($parent && absolutely_no_comments($parent[0])) {
                            $allowed = false;
                        }
                    } elseif ($permit_mentions) {
                        $allowed = true;
                    }
                }
                if ($request) {
                    // Conversation fetches (e.g. $request == true) take place for
                    //   a) new comments on expired posts
                    //   b) hyperdrive (friend-of-friend) conversations


                    // over-ride normal connection permissions for hyperdrive (friend-of-friend) conversations
                    // (if hyperdrive is enabled).
                    // If $allowed is already true, this is probably the conversation of a direct friend or a
                    // conversation fetch for a new comment on an expired post
                    // Comments of all these activities are allowed and will only be rejected (later) if the parent
                    // doesn't exist.

                    if ($perm === 'send_stream') {
                        if (get_pconfig($channel['channel_id'], 'system', 'hyperdrive', true)) {
                            $allowed = true;
                        }
                    } else {
                        $allowed = true;
                    }

                    $friendofriend = true;
                }

                if (intval($arr['item_private']) === 2) {
                    if (!perm_is_allowed($channel['channel_id'], $sender, 'post_mail')) {
                        $allowed = false;
                    }
                }

                if (get_abconfig($channel['channel_id'], $sender, 'system', 'block_announce', false)) {
                    if ($arr['verb'] === 'Announce' || strpos($arr['body'], '[/share]')) {
                        $allowed = false;
                    }
                }

                if (!$allowed) {
                    logger("permission denied for delivery to channel {$channel['channel_id']} {$channel['channel_address']}");
                    $DR->update('permission denied');
                    $result[] = $DR->get();
                    continue;
                }
            }

            if ($arr['mid'] !== $arr['parent_mid']) {
                if (perm_is_allowed($channel['channel_id'], $sender, 'moderated') && $relay) {
                    $arr['item_blocked'] = ITEM_MODERATED;
                }

                // check source route.
                // We are only going to accept comments from this sender if the comment has the same route as the top-level-post,
                // this is so that permissions mismatches between senders apply to the entire conversation
                // As a side effect we will also do a preliminary check that we have the top-level-post, otherwise
                // processing it is pointless.

                // The original author won't have a token in their copy of the message

                $prnt = ((strpos($arr['parent_mid'], 'token=') !== false) ? substr($arr['parent_mid'], 0, strpos($arr['parent_mid'], '?')) : '');

                $r = q(
                    "select id, parent_mid, mid, owner_xchan, item_private, obj_type from item where mid = '%s' and uid = %d limit 1",
                    dbesc($arr['parent_mid']),
                    intval($channel['channel_id'])
                );
                if (!$r) {
                    $r = q(
                        "select id, parent_mid, mid, owner_xchan, item_private, obj_type from item where mid = '%s' and uid = %d limit 1",
                        dbesc($prnt),
                        intval($channel['channel_id'])
                    );
                }

                if ($r) {
                    // if this is a multi-threaded conversation, preserve the threading information
                    if ($r[0]['parent_mid'] !== $r[0]['mid']) {
                        $arr['thr_parent'] = $arr['parent_mid'];
                        $arr['parent_mid'] = $r[0]['parent_mid'];
                        if ($act->replyto) {
                            q(
                                "update item set replyto = '%s' where id = %d",
                                dbesc($act->replyto),
                                intval($r[0]['id'])
                            );
                        }
                    }

                    if ($r[0]['obj_type'] === 'Question') {
                        // If this is a poll response, convert the obj_type to our (internal-only) "Answer" type
                        if ($arr['obj_type'] === 'Note' && $arr['title'] && (!$arr['content'])) {
                            $arr['obj_type'] = 'Answer';
                        }
                    }
                } else {
                    // We don't seem to have a copy of this conversation or at least the parent
                    // - so request a copy of the entire conversation to date.
                    // Don't do this if it's a relay post as we're the ones who are supposed to
                    // have the copy and we don't want the request to loop.
                    // Also don't do this if this comment came from a conversation request packet.
                    // It's possible that comments are allowed but posting isn't and that could
                    // cause a conversation fetch loop.
                    // We'll also check the send_stream permission - because if it isn't allowed,
                    // the top level post is unlikely to be imported and
                    // this is just an exercise in futility.

                    if (
                        (!$relay) && (!$request) && (!$local_public)
                        && perm_is_allowed($channel['channel_id'], $sender, 'send_stream')
                    ) {
                        $reports = self::fetch_conversation($channel, $arr['mid']);

                        // extract our delivery report from the fetched conversation
                        // if we can find it.
                        logger('fetch_report for ' . $arr['mid'], LOGGER_ALL);
                        logger('fetch_report: ' . print_r($reports, true), LOGGER_ALL);

                        if ($reports && is_array($reports)) {
                            $found_report = false;
                            foreach ($reports as $report) {
                                if ($report['message_id'] === $arr['mid']) {
                                    $found_report = true;
                                    $DR->update($report['status']);
                                }
                            }
                            if (!$found_report) {
                                $DR->update('conversation fetch failed');
                            }
                        } else {
                            $DR->update('conversation fetch failed');
                        }
                    } else {
                        $DR->update('comment parent not found');
                    }
                    $result[] = $DR->get();
                    continue;
                }

            }

            // This is used to fetch allow/deny rules if either the sender
            // or  owner is  a connection. post_is_importable() evaluates all of them
            $abook = q(
                "select * from abook where abook_channel = %d and ( abook_xchan = '%s' OR abook_xchan = '%s' )",
                intval($channel['channel_id']),
                dbesc($arr['owner_xchan']),
                dbesc($arr['author_xchan'])
            );

            if (isset($arr['item_deleted']) && intval($arr['item_deleted'])) {
                // set these just in case we need to store a fresh copy of the deleted post.
                // This could happen if the delete got here before the original post did.

                $arr['aid'] = $channel['channel_account_id'];
                $arr['uid'] = $channel['channel_id'];

                $item_id = self::delete_imported_item($sender, $act, $arr, $channel['channel_id'], $relay);
                $DR->update(($item_id) ? 'deleted' : 'delete_failed');
                $result[] = $DR->get();

                if ($relay && $item_id) {
                    logger('process_delivery: invoking relay');
                    Run::Summon(['Notifier', 'relay', intval($item_id)]);
                    $DR->update('relayed');
                    $result[] = $DR->get();
                }
                continue;
            }

            // reactions such as like and dislike could have an mid with /activity/ in it.
            // Check for both forms in order to prevent duplicates.

            $r = q(
                "select * from item where mid in ('%s','%s') and uid = %d limit 1",
                dbesc($arr['mid']),
                dbesc(str_replace(z_root() . '/activity/', z_root() . '/item/', $arr['mid'])),
                intval($channel['channel_id'])
            );

            if ($r) {
                // We already have this post.
                $item_id = $r[0]['id'];

                if (intval($r[0]['item_deleted'])) {
                    // It was deleted locally.
                    $DR->update('update ignored');
                    $result[] = $DR->get();

                    continue;
                } // Maybe it has been edited?
                elseif ($arr['edited'] > $r[0]['edited']) {
                    $arr['id'] = $r[0]['id'];
                    $arr['uid'] = $channel['channel_id'];
                    if (post_is_importable($channel['channel_id'], $arr, $abook)) {
                        $item_result = self::update_imported_item($sender, $arr, $r[0], $channel['channel_id'], $tag_delivery);
                        $DR->update('updated');
                        $result[] = $DR->get();
                    } else {
                        $DR->update('update ignored');
                        $result[] = $DR->get();
                    }
                } else {
                    $DR->update('update ignored');
                    $result[] = $DR->get();

                    // We need this line to ensure wall-to-wall comments are relayed (by falling through to the relay bit),
                    // and at the same time not relay any other relayable posts more than once, because to do so is very wasteful.
                    if (!intval($r[0]['item_origin'])) {
                        continue;
                    }
                }
            } else {
                $arr['aid'] = $channel['channel_account_id'];
                $arr['uid'] = $channel['channel_id'];

                // if it's a sourced post, call the post_local hooks as if it were
                // posted locally so that crosspost connectors will be triggered.

                if (check_item_source($arr['uid'], $arr)) {
                    /**
                     * @hooks post_local
                     *   Called when an item has been posted on this machine via mod/item.php (also via API).
                     *   * \e array with an item
                     */
                    Hook::call('post_local', $arr);
                }

                $item_id = 0;

                Activity::rewrite_mentions($arr);


                $maxlen = get_max_import_size();

                if ($maxlen && mb_strlen($arr['body']) > $maxlen) {
                    $arr['body'] = mb_substr($arr['body'], 0, $maxlen, 'UTF-8');
                    logger('message length exceeds max_import_size: truncated');
                }

                if ($maxlen && mb_strlen($arr['summary']) > $maxlen) {
                    $arr['summary'] = mb_substr($arr['summary'], 0, $maxlen, 'UTF-8');
                    logger('message summary length exceeds max_import_size: truncated');
                }

                if (post_is_importable($arr['uid'], $arr, $abook)) {
                    // Strip old-style hubzilla bookmarks
                    if (strpos($arr['body'], "#^[") !== false) {
                        $arr['body'] = str_replace("#^[", "[", $arr['body']);
                    }

                    $item_result = item_store($arr);
                    if ($item_result['success']) {
                        $item_id = $item_result['item_id'];
                        $parr = [
                            'item_id' => $item_id,
                            'item' => $arr,
                            'sender' => $sender,
                            'channel' => $channel
                        ];
                        /**
                         * @hooks activity_received
                         *   Called when an activity (post, comment, like, etc.) has been received from a zot source.
                         *   * \e int \b item_id
                         *   * \e array \b item
                         *   * \e array \b sender
                         *   * \e array \b channel
                         */
                        Hook::call('activity_received', $parr);
                    }
                    $DR->update(($item_id) ? 'posted' : 'storage failed: ' . $item_result['message']);
                    $result[] = $DR->get();
                } else {
                    $DR->update('post ignored');
                    $result[] = $DR->get();
                }
            }

            // preserve conversations with which you are involved from expiration

            $stored = (($item_result && $item_result['item']) ? $item_result['item'] : false);
            if (
                (is_array($stored)) && ($stored['id'] != $stored['parent'])
                && ($stored['author_xchan'] === $channel['channel_hash'])
            ) {
                retain_item($stored['item']['parent']);
            }

            if ($relay && $item_id) {
                logger('Invoking relay');
                Run::Summon(['Notifier', 'relay', intval($item_id)]);
                $DR->addto_update('relayed');
                $result[] = $DR->get();
            }
        }

        if (!$deliveries) {
            $result[] = array('', 'no recipients', '', $arr['mid']);
        }

        logger('Local results: ' . print_r($result, true), LOGGER_DEBUG);

        return $result;
    }

    public static function hyperdrive_enabled($channel, $item)
    {

        if (get_pconfig($channel['channel_id'], 'system', 'hyperdrive', true)) {
            return true;
        }
        return false;
    }

    public static function fetch_conversation($channel, $mid)
    {

        // Use Zotfinger to create a signed request

        logger('fetching conversation: ' . $mid, LOGGER_DEBUG);

        $a = Zotfinger::exec($mid, $channel);

        logger('received conversation: ' . print_r($a, true), LOGGER_DATA);

        if (!$a) {
            return false;
        }

        if ($a['data']['type'] !== 'OrderedCollection') {
            return false;
        }

        $obj = new ASCollection($a['data'], $channel);
        $items = $obj->get();

        if (!$items) {
            return false;
        }

        $ret = [];


        $signer = q(
            "select hubloc_hash, hubloc_url from hubloc where hubloc_id_url = '%s' and hubloc_network in ('zot6','nomad') and hubloc_deleted = 0 limit 1",
            dbesc($a['signature']['signer'])
        );


        foreach ($items as $activity) {
            $AS = new ActivityStreams($activity);
            if (
                $AS->is_valid() && $AS->type === 'Announce' && is_array($AS->obj)
                && array_key_exists('object', $AS->obj) && array_key_exists('actor', $AS->obj)
            ) {
                // This is a relayed/forwarded Activity (as opposed to a shared/boosted object)
                // Reparse the encapsulated Activity and use that instead
                logger('relayed activity', LOGGER_DEBUG);
                $AS = new ActivityStreams($AS->obj);
            }

            if (!$AS->is_valid()) {
                logger('FOF Activity rejected: ' . print_r($activity, true));
                continue;
            }
            $arr = Activity::decode_note($AS);

            // logger($AS->debug());

            $r = hubloc_id_query($AS->actor['id'], 1);

            if (!$r) {
                $y = import_author_xchan(['url' => $AS->actor['id']]);
                if ($y) {
                    $r = hubloc_id_query($AS->actor['id'], 1);
                }
                if (!$r) {
                    logger('FOF Activity: no actor');
                    continue;
                }
            }

            if (array_path_exists('actor/id', $AS->obj) && $AS->obj['actor']['id'] !== $AS->actor['id']) {
                $y = import_author_xchan(['url' => $AS->obj['actor']['id']]);
                if (!$y) {
                    logger('FOF Activity: no object actor');
                    continue;
                }
            }


            if ($r) {
                $r = self::zot_record_preferred($r);
                $arr['author_xchan'] = $r['hubloc_hash'];
            }

            // The default condition for owner_xchan is a top level post
            // which is not being relayed. Wall-to-wall posts should get
            // set correctly because they will have 'replyto'.

            $arr['owner_xchan'] = $arr['author_xchan'];

            // replyTo trumps everything.

            if (isset($arr['replyto']) && $arr['replyto']) {
                $arr['owner_xchan'] = $arr['replyto'];
            }
            elseif ($arr['mid'] !== $arr['parent_mid']) {
                // Inherit owner and replyto from the parent.
                $r = q("select * from item where mid = '%s' and uid = %d",
                    dbesc($arr['parent_mid']),
                    intval($channel['channel_id'])
                );
                if ($r) {
                    $arr['owner_xchan'] = $r[0]['owner_xchan'];
                    $arr['replyto'] = $r[0]['replyto'];
                }
            }
            if (
                (isset($AS->meta) && isset($AS->meta['hubloc']) && $AS->meta['hubloc'])
                || ($arr['author_xchan'] === $arr['owner_xchan'])
            ) {
                $arr['item_verified'] = true;
            }

            if (isset($AS->meta) && isset($AS->meta['signed_data']) && $AS->meta['signed_data']) {
                IConfig::Set($arr, 'activitystreams', 'signed_data', $AS->meta['signed_data'], false);
            }

            logger('FOF Activity received: ' . print_r($arr, true), LOGGER_DATA, LOG_DEBUG);
            logger('FOF Activity recipient: ' . $channel['channel_hash'], LOGGER_DATA, LOG_DEBUG);

            $result = self::process_delivery($arr['owner_xchan'], $AS, $arr, [$channel['channel_hash']], false, false, true);
            if ($result) {
                $ret = array_merge($ret, $result);
            }
        }

        return $ret;
    }


    /**
     * @brief Remove community tag.
     *
     * @param array $sender an associative array with
     *   * \e string \b hash a xchan_hash
     * @param array $arr an associative array
     *   * \e int \b verb
     *   * \e int \b obj_type
     *   * \e int \b mid
     * @param int $uid
     */

    public static function remove_community_tag($sender, $arr, $uid)
    {

        if (!(activity_match($arr['verb'], ACTIVITY_TAG) && ($arr['obj_type'] == ACTIVITY_OBJ_TAGTERM))) {
            return;
        }

        logger('remove_community_tag: invoked');

        if (!get_pconfig($uid, 'system', 'blocktags')) {
            logger('Permission denied.');
            return;
        }

        $r = q(
            "select * from item where mid = '%s' and uid = %d limit 1",
            dbesc($arr['mid']),
            intval($uid)
        );
        if (!$r) {
            logger('No item');
            return;
        }

        if (($sender != $r[0]['owner_xchan']) && ($sender != $r[0]['author_xchan'])) {
            logger('Sender not authorised.');
            return;
        }

        $i = $r[0];

        if ($i['target']) {
            $i['target'] = json_decode($i['target'], true);
        }
        if ($i['object']) {
            $i['object'] = json_decode($i['object'], true);
        }
        if (!($i['target'] && $i['object'])) {
            logger('No target/object');
            return;
        }

        $message_id = $i['target']['id'];

        $r = q(
            "select id from item where mid = '%s' and uid = %d limit 1",
            dbesc($message_id),
            intval($uid)
        );
        if (!$r) {
            logger('No parent message');
            return;
        }

        q(
            "delete from term where uid = %d and oid = %d and otype = %d and ttype in  ( %d, %d ) and term = '%s' and url = '%s'",
            intval($uid),
            intval($r[0]['id']),
            intval(TERM_OBJ_POST),
            intval(TERM_HASHTAG),
            intval(TERM_COMMUNITYTAG),
            dbesc($i['object']['title']),
            dbesc(get_rel_link($i['object']['link'], 'alternate'))
        );
    }

    /**
     * @brief Updates an imported item.
     *
     * @param array $sender
     * @param array $item
     * @param array $orig
     * @param int $uid
     * @param bool $tag_delivery
     * @see item_store_update()
     *
     */

    public static function update_imported_item($sender, $item, $orig, $uid, $tag_delivery)
    {

        // If this is a comment being updated, remove any privacy information
        // so that item_store_update will set it from the original.

        if ($item['mid'] !== $item['parent_mid']) {
            unset($item['allow_cid']);
            unset($item['allow_gid']);
            unset($item['deny_cid']);
            unset($item['deny_gid']);
            unset($item['item_private']);
        }

        // we need the tag_delivery check for downstream flowing posts as the stored post
        // may have a different owner than the one being transmitted.

        if (($sender != $orig['owner_xchan'] && $sender != $orig['author_xchan']) && (!$tag_delivery)) {
            logger('sender is not owner or author');
            return;
        }


        $x = item_store_update($item);

        // If we're updating an event that we've saved locally, we store the item info first
        // because event_addtocal will parse the body to get the 'new' event details

        if ($orig['resource_type'] === 'event') {
            $res = event_addtocal($orig['id'], $uid);
            if (!$res) {
                logger('update event: failed');
            }
        }

        if (!$x['item_id']) {
            logger('update_imported_item: failed: ' . $x['message']);
        } else {
            logger('update_imported_item');
        }

        return $x;
    }

    /**
     * @brief Deletes an imported item.
     *
     * @param array $sender
     *   * \e string \b hash a xchan_hash
     * @param array $item
     * @param int $uid
     * @param bool $relay
     * @return bool|int post_id
     */

    public static function delete_imported_item($sender, $act, $item, $uid, $relay)
    {

        logger('invoked', LOGGER_DEBUG);

        $ownership_valid = false;
        $item_found = false;
        $post_id = 0;

        if ($item['verb'] === 'Tombstone') {
            // The id of the deleted thing is the item mid (activity id)
            $mid = $item['mid'];
        } else {
            // The id is the object id if the type is Undo or Delete
            $mid = ((is_array($act->obj)) ? $act->obj['id'] : $act->obj);
        }

        // we may have stored either the object id or the activity id if it was a response activity (like, dislike, etc.)

        $r = q(
            "select * from item where ( author_xchan = '%s' or owner_xchan = '%s' or source_xchan = '%s' )
            and mid in ('%s','%s')  and uid = %d limit 1",
            dbesc($sender),
            dbesc($sender),
            dbesc($sender),
            dbesc($mid),
            dbesc(str_replace('/activity/', '/item/', $mid)),
            intval($uid)
        );

        if ($r) {
            $stored = $r[0];
            // we proved ownership in the sql query
            $ownership_valid = true;

            $post_id = $stored['id'];
            $item_found = true;
        } else {
            // this will fail with an ownership issue, so explain the real reason
            logger('delete received for non-existent item or not owned by sender - ignoring.');
        }

        if ($ownership_valid === false) {
            logger('delete_imported_item: failed: ownership issue');
            return false;
        }

        if ($stored['resource_type'] === 'event') {
            $i = q(
                "SELECT * FROM event WHERE event_hash = '%s' AND uid = %d LIMIT 1",
                dbesc($stored['resource_id']),
                intval($uid)
            );
            if ($i) {
                if ($i[0]['event_xchan'] === $sender) {
                    q(
                        "delete from event where event_hash = '%s' and uid = %d",
                        dbesc($stored['resource_id']),
                        intval($uid)
                    );
                } else {
                    logger('delete linked event: not owner');
                    return;
                }
            }
        }
        if ($item_found) {
            if (intval($stored['item_deleted'])) {
                logger('delete_imported_item: item was already deleted');
                if (!$relay) {
                    return false;
                }

                // This is a bit hackish, but may have to suffice until the notification/delivery loop is optimised
                // a bit further. We're going to strip the ITEM_ORIGIN on this item if it's a comment, because
                // it was already deleted, and we're already relaying, and this ensures that no other process or
                // code path downstream can relay it again (causing a loop). Since it's already gone it's not coming
                // back, and we aren't going to (or shouldn't at any rate) delete it again in the future - so losing
                // this information from the metadata should have no other discernible impact.

                if (($stored['id'] != $stored['parent']) && intval($stored['item_origin'])) {
                    q(
                        "update item set item_origin = 0 where id = %d and uid = %d",
                        intval($stored['id']),
                        intval($stored['uid'])
                    );
                }
            } else {
                if ($stored['id'] !== $stored['parent']) {
                    q(
                        "update item set commented = '%s', changed = '%s' where id = %d",
                        dbesc(datetime_convert()),
                        dbesc(datetime_convert()),
                        intval($stored['parent'])
                    );
                }
            }

            // Use phased deletion to set the deleted flag, call both tag_deliver and the notifier to notify downstream channels
            // and then clean up after ourselves with a cron job after several days to do the delete_item_lowlevel() (DROPITEM_PHASE2).

            drop_item($post_id, false, DROPITEM_PHASE1);
            tag_deliver($uid, $post_id);
        }

        return $post_id;
    }


    /**
     * @brief Processes delivery of profile.
     *
     * @param array $sender an associative array
     *   * \e string \b hash a xchan_hash
     * @param array $arr
     * @param array $deliveries (unused)
     * @see import_directory_profile()
     */

    public static function process_profile_delivery($sender, $arr, $deliveries)
    {

        logger('process_profile_delivery', LOGGER_DEBUG);

        $r = q(
            "select xchan_addr from xchan where xchan_hash = '%s' limit 1",
            dbesc($sender['hash'])
        );
        if ($r) {
            Libzotdir::import_directory_profile($sender, $arr, $r[0]['xchan_addr'], UPDATE_FLAGS_UPDATED, 0);
        }
    }

    /**
     * @brief Checks for a moved channel and sets the channel_moved flag.
     *
     * Currently the effect of this flag is to turn the channel into 'read-only' mode.
     * New content will not be processed (there was still an issue with blocking the
     * ability to post comments as of 10-Mar-2016).
     * We do not physically remove the channel at this time. The hub admin may choose
     * to do so, but is encouraged to allow a grace period of several days in case there
     * are any issues migrating content. This packet will generally be received by the
     * original site when the basic channel import has been processed.
     *
     * This will only be executed on the old location
     * if a new location is reported and there is only one location record.
     * The rest of the hubloc syncronisation will be handled within
     * sync_locations
     *
     * @param string $sender_hash A channel hash
     * @param array $locations
     */

    public static function check_location_move($sender_hash, $locations)
    {

        if (!$locations) {
            return;
        }

        if (count($locations) != 1) {
            return;
        }

        $loc = $locations[0];

        $r = q(
            "select * from channel where channel_hash = '%s' limit 1",
            dbesc($sender_hash)
        );

        if (!$r) {
            return;
        }

        if ($loc['url'] !== z_root()) {
            $x = q(
                "update channel set channel_moved = '%s' where channel_hash = '%s' limit 1",
                dbesc($loc['url']),
                dbesc($sender_hash)
            );

            // federation plugins may wish to notify connections
            // of the move on singleton networks

            $arr = [
                'channel' => $r[0],
                'locations' => $locations
            ];
            /**
             * @hooks location_move
             *   Called when a new location has been provided to a UNO channel (indicating a move rather than a clone).
             *   * \e array \b channel
             *   * \e array \b locations
             */
            Hook::call('location_move', $arr);
        }
    }


    /**
     * @brief Returns an array with all known distinct hubs for this channel.
     *
     * @param array $channel an associative array which must contain
     *  * \e string \b channel_hash the hash of the channel
     * @return array an array with associative arrays
     * @see self::get_hublocs()
     */

    public static function encode_locations($channel)
    {
        $ret = [];

        $x = self::get_hublocs($channel['channel_hash']);

        if ($x && count($x)) {
            foreach ($x as $hub) {
                // if this is a local channel that has been deleted, the hubloc is no good
                // - make sure it is marked deleted so that nobody tries to use it.

                if (intval($channel['channel_removed']) && $hub['hubloc_url'] === z_root()) {
                    $hub['hubloc_deleted'] = 1;
                }

                $ret[] = [
                    'host' => $hub['hubloc_host'],
                    'address' => $hub['hubloc_addr'],
                    'id_url' => $hub['hubloc_id_url'],
                    'primary' => (intval($hub['hubloc_primary']) ? true : false),
                    'url' => $hub['hubloc_url'],
                    'url_sig' => $hub['hubloc_url_sig'],
                    'site_id' => $hub['hubloc_site_id'],
                    'callback' => $hub['hubloc_callback'],
                    'sitekey' => $hub['hubloc_sitekey'],
                    'deleted' => (intval($hub['hubloc_deleted']) ? true : false)
                ];
            }
        }

        return $ret;
    }


    /**
     * @brief
     *
     * @param array $arr
     * @param string $pubkey
     * @return bool true if updated or inserted
     */

    public static function import_site($arr)
    {

        if (!(is_array($arr) && isset($arr['url']) && isset($arr['site_sig']))) {
            return false;
        }

        if (!self::verify($arr['url'], $arr['site_sig'], $arr['sitekey'])) {
            logger('Bad url_sig');
            return false;
        }

        $update = false;
        $exists = false;

        $r = q(
            "select * from site where site_url = '%s' limit 1",
            dbesc($arr['url'])
        );
        if ($r) {
            $exists = true;
            $siterecord = $r[0];
        }

        $register_policy = 0;
        if ($arr['register_policy'] == 'closed') {
            $register_policy = REGISTER_CLOSED;
        }
        if ($arr['register_policy'] == 'open') {
            $register_policy = REGISTER_OPEN;
        }
        if ($arr['register_policy'] == 'approve') {
            $register_policy = REGISTER_APPROVE;
        }

        $access_policy = 0;
        if (array_key_exists('access_policy', $arr)) {
            if ($arr['access_policy'] === 'private') {
                $access_policy = ACCESS_PRIVATE;
            }
            if ($arr['access_policy'] === 'paid') {
                $access_policy = ACCESS_PAID;
            }
            if ($arr['access_policy'] === 'free') {
                $access_policy = ACCESS_FREE;
            }
            if ($arr['access_policy'] === 'tiered') {
                $access_policy = ACCESS_TIERED;
            }
        }

        // don't let insecure sites register as public hubs

        if (strpos($arr['url'], 'https://') === false) {
            $access_policy = ACCESS_PRIVATE;
        }

        if ($access_policy != ACCESS_PRIVATE) {
            $x = z_fetch_url($arr['url'] . '/siteinfo.json');
            if (!$x['success']) {
                $access_policy = ACCESS_PRIVATE;
            }
        }

        $site_about = EMPTY_STR;
        $site_logo = EMPTY_STR;
        $sitename = EMPTY_STR;

        $url = htmlspecialchars(strtolower($arr['url']), ENT_COMPAT, 'UTF-8', false);
        $sellpage = htmlspecialchars($arr['sellpage'], ENT_COMPAT, 'UTF-8', false);
        $site_location = htmlspecialchars($arr['location'], ENT_COMPAT, 'UTF-8', false);
        $sitename = htmlspecialchars($arr['sitename'], ENT_COMPAT, 'UTF-8', false);
        $site_project = htmlspecialchars($arr['project'], ENT_COMPAT, 'UTF-8', false);
        $site_crypto = ((array_key_exists('encryption', $arr) && is_array($arr['encryption'])) ? htmlspecialchars(implode(',', $arr['encryption']), ENT_COMPAT, 'UTF-8', false) : '');
        $site_version = ((array_key_exists('version', $arr)) ? htmlspecialchars($arr['version'], ENT_COMPAT, 'UTF-8', false) : '');
        if (array_key_exists('about', $arr) && $arr['about']) {
            $site_about = html2bbcode(purify_html($arr['about']));
        }
        if (array_key_exists('logo', $arr) && $arr['logo']) {
            $site_logo = $arr['logo'];
        } elseif (file_exists('images/' . strtolower($site_project) . '.png')) {
            $site_logo = z_root() . '/images/' . strtolower($site_project) . '.png';
        } else {
            $site_logo = z_root() . '/images/default_profile_photos/red_koala_trans/300.png';
        }

        set_sconfig($url, 'system', 'about', $site_about);
        set_sconfig($url, 'system', 'logo', $site_logo);
        set_sconfig($url, 'system', 'sitename', $sitename);

        $site_flags = $site_directory;

        if (array_key_exists('zot', $arr)) {
            set_sconfig($arr['url'], 'system', 'zot_version', $arr['zot']);
        }

        if ($exists) {
            if (
                ($siterecord['site_flags'] != $site_flags)
                || ($siterecord['site_access'] != $access_policy)
                || ($siterecord['site_sellpage'] != $sellpage)
                || ($siterecord['site_location'] != $site_location)
                || ($siterecord['site_register'] != $register_policy)
                || ($siterecord['site_project'] != $site_project)
                || ($siterecord['site_crypto'] != $site_crypto)
                || ($siterecord['site_version'] != $site_version)
            ) {
                $update = true;

                //          logger('import_site: input: ' . print_r($arr,true));
                //          logger('import_site: stored: ' . print_r($siterecord,true));

                $r = q(
                    "update site set site_dead = 0, site_location = '%s', site_flags = %d, site_access = %d, site_register = %d, site_update = '%s', site_sellpage = '%s', site_type = %d, site_project = '%s', site_version = '%s', site_crypto = '%s'
                    where site_url = '%s'",
                    dbesc($site_location),
                    intval($site_flags),
                    intval($access_policy),
                    intval($register_policy),
                    dbesc(datetime_convert()),
                    dbesc($sellpage),
                    intval(SITE_TYPE_ZOT),
                    dbesc($site_project),
                    dbesc($site_version),
                    dbesc($site_crypto),
                    dbesc($url)
                );
                if (!$r) {
                    logger('Update failed. ' . print_r($arr, true));
                }
            } else {
                // update the timestamp to indicate we communicated with this site
                q(
                    "update site set site_dead = 0, site_update = '%s' where site_url = '%s'",
                    dbesc(datetime_convert()),
                    dbesc($url)
                );
            }
        } else {
            $update = true;

            $r = site_store_lowlevel(
                [
                    'site_location' => $site_location,
                    'site_url' => $url,
                    'site_access' => intval($access_policy),
                    'site_flags' => intval($site_flags),
                    'site_update' => datetime_convert(),
                    'site_register' => intval($register_policy),
                    'site_sellpage' => $sellpage,
                    'site_type' => intval(SITE_TYPE_ZOT),
                    'site_project' => $site_project,
                    'site_version' => $site_version,
                    'site_crypto' => $site_crypto
                ]
            );

            if (!$r) {
                logger('Record create failed. ' . print_r($arr, true));
            }
        }

        return $update;
    }

    /**
     * @brief Returns path to /rpost
     *
     * @param array $observer
     *   * \e string \b xchan_url
     * @return string
     * @todo We probably should make rpost discoverable.
     *
     */
    public static function get_rpost_path($observer)
    {
        if (!$observer) {
            return EMPTY_STR;
        }

        if (in_array($observer['xchan_network'],['zot6','nomad'])) {
            $parsed = parse_url($observer['xchan_url']);
            return $parsed['scheme'] . '://' . $parsed['host'] . (($parsed['port']) ? ':' . $parsed['port'] : '') . '/rpost?f=';
        }
        return EMPTY_STR;
    }

    /**
     * @brief
     *
     * @param array $x
     * @return bool|string return false or a hash
     */

    public static function import_author_zot($x)
    {

        // Check that we have both a hubloc and xchan record - as occasionally storage calls will fail and
        // we may only end up with one; which results in posts with no author name or photo and are a bit
        // of a hassle to repair. If either or both are missing, do a full discovery probe.

        if (!array_key_exists('id', $x)) {
            return import_author_activitypub($x);
        }

        $hash = self::make_xchan_hash($x['id'], $x['key']);

        $desturl = $x['url'];

        $found_primary = false;

        $r1 = q(
            "select hubloc_url, hubloc_updated, site_dead from hubloc left join site on
            hubloc_url = site_url where hubloc_guid = '%s' and hubloc_guid_sig = '%s' and hubloc_primary = 1 and hubloc_deleted = 0 limit 1",
            dbesc($x['id']),
            dbesc($x['id_sig'])
        );
        if ($r1) {
            $found_primary = true;
        }

        $r2 = q(
            "select xchan_hash from xchan where xchan_guid = '%s' and xchan_guid_sig = '%s' limit 1",
            dbesc($x['id']),
            dbesc($x['id_sig'])
        );

        $primary_dead = false;

        if ($r1 && intval($r1[0]['site_dead'])) {
            $primary_dead = true;
        }

        // We have valid and somewhat fresh information. Always true if it is our own site.

        if ($r1 && $r2 && ($r1[0]['hubloc_updated'] > datetime_convert('UTC', 'UTC', 'now - 1 week') || $r1[0]['hubloc_url'] === z_root())) {
            logger('in cache', LOGGER_DEBUG);
            return $hash;
        }

        logger('not in cache or cache stale - probing: ' . print_r($x, true), LOGGER_DEBUG, LOG_INFO);

        // The primary hub may be dead. Try to find another one associated with this identity that is
        // still alive. If we find one, use that url for the discovery/refresh probe. Otherwise, the dead site
        // is all we have and there is no point probing it. Just return the hash indicating we have a
        // cached entry and the identity is valid. It's just unreachable until they bring back their
        // server from the grave or create another clone elsewhere.

        if ($primary_dead || !$found_primary) {
            logger('dead or site - ignoring', LOGGER_DEBUG, LOG_INFO);

            $r = q(
                "select hubloc_id_url from hubloc left join site on hubloc_url = site_url
                where hubloc_hash = '%s' and hubloc_deleted = 0 and site_dead = 0",
                dbesc($hash)
            );
            if ($r) {
                logger('found another site that is not dead: ' . $r[0]['hubloc_id_url'], LOGGER_DEBUG, LOG_INFO);
                $desturl = $r[0]['hubloc_id_url'];
            } else {
                return $hash;
            }
        }

        $them = ['hubloc_id_url' => $desturl];
        if (self::refresh($them)) {
            return $hash;
        }

        return false;
    }

    public static function zotinfo($arr)
    {

        $ret = [];

        $zhash = ((x($arr, 'guid_hash')) ? $arr['guid_hash'] : '');
        $zguid = ((x($arr, 'guid')) ? $arr['guid'] : '');
        $zguid_sig = ((x($arr, 'guid_sig')) ? $arr['guid_sig'] : '');
        $zaddr = ((x($arr, 'address')) ? $arr['address'] : '');
        $ztarget = ((x($arr, 'target_url')) ? $arr['target_url'] : '');
        $zsig = ((x($arr, 'target_sig')) ? $arr['target_sig'] : '');
        $zkey = ((x($arr, 'key')) ? $arr['key'] : '');
        $mindate = ((x($arr, 'mindate')) ? $arr['mindate'] : '');
        $token = ((x($arr, 'token')) ? $arr['token'] : '');
        $feed = ((x($arr, 'feed')) ? intval($arr['feed']) : 0);

        if ($ztarget) {
            $t = q("select * from hubloc where hubloc_id_url = '%s' and hubloc_network in ('nomad','zot6') and hubloc_deleted = 0 order by hubloc_id desc limit 1",
                dbesc($ztarget)
            );
            if ($t) {
                $ztarget_hash = $t[0]['hubloc_hash'];
            } else {
                // should probably perform discovery of the requestor (target) but if they actually had
                // permissions we would know about them and we only want to know who they are to
                // enumerate their specific permissions

                $ztarget_hash = EMPTY_STR;
            }
        }

        $r = null;

        if (strlen($zhash)) {
            $r = q(
                "select channel.*, xchan.* from channel left join xchan on channel_hash = xchan_hash
                where channel_hash = '%s' limit 1",
                dbesc($zhash)
            );
        } elseif (strlen($zguid) && strlen($zguid_sig)) {
            $r = q(
                "select channel.*, xchan.* from channel left join xchan on channel_hash = xchan_hash
                where channel_guid = '%s' and channel_guid_sig = '%s' limit 1",
                dbesc($zguid),
                dbesc($zguid_sig)
            );
        } elseif (strlen($zaddr)) {
            if (strpos($zaddr, '[system]') === false) {       /* normal address lookup */
                $r = q(
                    "select channel.*, xchan.* from channel left join xchan on channel_hash = xchan_hash
                    where ( channel_address = '%s' or xchan_addr = '%s' ) limit 1",
                    dbesc($zaddr),
                    dbesc($zaddr)
                );
            } else {

                /**
                 * The special address '[system]' will return a system channel if one has been defined,
                 * Or the first valid channel we find if there are no system channels.
                 *
                 * This is used by magic-auth if we have no prior communications with this site - and
                 * returns an identity on this site which we can use to create a valid hub record so that
                 * we can exchange signed messages. The precise identity is irrelevant. It's the hub
                 * information that we really need at the other end - and this will return it.
                 *
                 */

                $r = q("select channel.*, xchan.* from channel left join xchan on channel_hash = xchan_hash
                    where channel_system = 1 order by channel_id limit 1");
                if (!$r) {
                    $r = q("select channel.*, xchan.* from channel left join xchan on channel_hash = xchan_hash
                        where channel_removed = 0 order by channel_id limit 1");
                }
            }
        } else {
            $ret['message'] = 'Invalid request';
            return ($ret);
        }

        if (!$r) {
            $ret['message'] = 'Item not found.';
            return ($ret);
        }

        $e = $r[0];

        $id = $e['channel_id'];

        $sys_channel = (intval($e['channel_system']) ? true : false);
        $special_channel = (($e['channel_pageflags'] & PAGE_PREMIUM) ? true : false);
        $adult_channel = (($e['channel_pageflags'] & PAGE_ADULT) ? true : false);
        $censored = (($e['channel_pageflags'] & PAGE_CENSORED) ? true : false);
        $searchable = (($e['channel_pageflags'] & PAGE_HIDDEN) ? false : true);
        $deleted = (intval($e['xchan_deleted']) ? true : false);

        if ($deleted || $censored || $sys_channel) {
            $searchable = false;
        }

        // now all forums (public, restricted, and private) set the public_forum flag. So it really means "is a group"
        // and has nothing to do with accessibility.

        $role = get_pconfig($e['channel_id'], 'system', 'permissions_role');
        $rolesettings = PermissionRoles::role_perms($role);

        $channel_type = isset($rolesettings['channel_type']) ? $rolesettings['channel_type'] : 'normal';

        //  This is for birthdays and keywords, but must check access permissions
        $p = q(
            "select * from profile where uid = %d and is_default = 1",
            intval($e['channel_id'])
        );

        $profile = [];

        if ($p) {
            if (!intval($p[0]['publish'])) {
                $searchable = false;
            }

            $profile['description'] = $p[0]['pdesc'];
            $profile['birthday'] = $p[0]['dob'];
            if (($profile['birthday'] != '0000-00-00') && (($bd = z_birthday($p[0]['dob'], $e['channel_timezone'])) !== '')) {
                $profile['next_birthday'] = $bd;
            }

            if ($age = age($p[0]['dob'], $e['channel_timezone'], '')) {
                $profile['age'] = $age;
            }
            $profile['gender'] = $p[0]['gender'];
            $profile['marital'] = $p[0]['marital'];
            $profile['sexual'] = $p[0]['sexual'];
            $profile['locale'] = $p[0]['locality'];
            $profile['region'] = $p[0]['region'];
            $profile['postcode'] = $p[0]['postal_code'];
            $profile['country'] = $p[0]['country_name'];
            $profile['about'] = ((Channel::is_system($e['channel_id'])) ? get_config('system', 'siteinfo') : $p[0]['about']);
            $profile['homepage'] = $p[0]['homepage'];
            $profile['hometown'] = $p[0]['hometown'];

            if ($p[0]['keywords']) {
                $tags = [];
                $k = explode(' ', $p[0]['keywords']);
                if ($k) {
                    foreach ($k as $kk) {
                        if (trim($kk, " \t\n\r\0\x0B,")) {
                            $tags[] = trim($kk, " \t\n\r\0\x0B,");
                        }
                    }
                }
                if ($tags) {
                    $profile['keywords'] = $tags;
                }
            }
        }

        $cover_photo = Channel::get_cover_photo($e['channel_id'], 'array');

        // Communication details

        $ret['id'] = $e['xchan_guid'];
        $ret['id_sig'] = self::sign($e['xchan_guid'], $e['channel_prvkey']);

        $ret['primary_location'] = [
            'address' => $e['xchan_addr'],
            'url' => $e['xchan_url'],
            'connections_url' => $e['xchan_connurl'],
            'follow_url' => $e['xchan_follow'],
            'wall' => z_root() . '/outbox/' . $e['channel_address'],
            'followers' => z_root() . '/followers/' . $e['channel_address'],
            'following' => z_root() . '/following/' . $e['channel_address']
        ];

        $ret['public_key'] = $e['xchan_pubkey'];
        $ret['signing_algorithm'] = 'rsa-sha256';
        $ret['username'] = $e['channel_address'];
        $ret['name'] = $e['xchan_name'];
        $ret['name_updated'] = $e['xchan_name_date'];
        $ret['photo'] = [
            'url' => $e['xchan_photo_l'],
            'type' => $e['xchan_photo_mimetype'],
            'updated' => $e['xchan_photo_date']
        ];

        if ($cover_photo) {
            $ret['cover_photo'] = [
                'url' => $cover_photo['url'],
                'type' => $cover_photo['type'],
                'updated' => $cover_photo['updated']
            ];
        }

        $ret['channel_role'] = get_pconfig($e['channel_id'], 'system', 'permissions_role', 'custom');
        $ret['channel_type'] = $channel_type;
        $ret['protocols'] = ['nomad','zot6'];
        if (get_pconfig($e['channel_id'], 'system', 'activitypub', get_config('system', 'activitypub', ACTIVITYPUB_ENABLED))) {
            $ret['protocols'][] = 'activitypub';
        }
        $ret['searchable'] = $searchable;
        $ret['adult_content'] = $adult_channel;

        $ret['comments'] = map_scope(PermissionLimits::Get($e['channel_id'], 'post_comments'));

        if ($deleted) {
            $ret['deleted'] = $deleted;
        }

        if (intval($e['channel_removed'])) {
            $ret['deleted_locally'] = true;
        }

        // premium or other channel desiring some contact with potential followers before connecting.
        // This is a template - %s will be replaced with the follow_url we discover for the return channel.

        if ($special_channel) {
            $ret['connect_url'] = (($e['xchan_connpage']) ? $e['xchan_connpage'] : z_root() . '/connect/' . $e['channel_address']);
        }

        // This is a template for our follow url, %s will be replaced with a webbie
        if (!isset($ret['follow_url'])) {
            $ret['follow_url'] = z_root() . '/follow?f=&url=%s';
        }

        $permissions = get_all_perms($e['channel_id'], $ztarget_hash, false);

        if ($ztarget_hash) {
            $permissions['connected'] = false;
            $b = q(
                "select * from abook where abook_xchan = '%s' and abook_channel = %d limit 1",
                dbesc($ztarget_hash),
                intval($e['channel_id'])
            );
            if ($b) {
                $permissions['connected'] = true;
            }
        }

        if ($permissions['view_profile']) {
            $ret['profile'] = $profile;
        }


        $concise_perms = [];
        if ($permissions) {
            foreach ($permissions as $k => $v) {
                if ($v) {
                    $concise_perms[] = $k;
                }
            }
            $permissions = implode(',', $concise_perms);
        }

        $ret['permissions'] = $permissions;
        $ret['permissions_for'] = $ztarget;


        // array of (verified) hubs this channel uses

        $x = self::encode_locations($e);
        if ($x) {
            $ret['locations'] = $x;
        }
        $ret['site'] = self::site_info();

        Hook::call('zotinfo', $ret);

        return ($ret);
    }


    public static function site_info($force = false)
    {

        $signing_key = get_config('system', 'prvkey');
        $sig_method = get_config('system', 'signature_algorithm', 'sha256');

        $ret = [];
        $ret['site'] = [];
        $ret['site']['url'] = z_root();
        $ret['site']['site_sig'] = self::sign(z_root(), $signing_key);
        $ret['site']['post'] = z_root() . '/zot';
        $ret['site']['openWebAuth'] = z_root() . '/owa';
        $ret['site']['authRedirect'] = z_root() . '/magic';
        $ret['site']['sitekey'] = get_config('system', 'pubkey');

        $ret['site']['encryption'] = Crypto::methods();
        $ret['signature_algorithm'] = $sig_method;
        $ret['site']['zot'] = System::get_zot_revision();
        $ret['site']['protocol_version'] = System::get_zot_revision();



        $register_policy = intval(get_config('system', 'register_policy'));

        if ($register_policy == REGISTER_CLOSED) {
            $ret['site']['register_policy'] = 'closed';
        }
        if ($register_policy == REGISTER_APPROVE) {
            $ret['site']['register_policy'] = 'approve';
        }
        if ($register_policy == REGISTER_OPEN) {
            $ret['site']['register_policy'] = 'open';
        }

        $access_policy = intval(get_config('system', 'access_policy'));

        if ($access_policy == ACCESS_PRIVATE) {
            $ret['site']['access_policy'] = 'private';
        }
        if ($access_policy == ACCESS_PAID) {
            $ret['site']['access_policy'] = 'paid';
        }
        if ($access_policy == ACCESS_FREE) {
            $ret['site']['access_policy'] = 'free';
        }
        if ($access_policy == ACCESS_TIERED) {
            $ret['site']['access_policy'] = 'tiered';
        }

        $ret['site']['admin'] = get_config('system', 'admin_email');

        $visible_plugins = [];

        $r = q("select * from addon where hidden = 0");
        if ($r) {
            foreach ($r as $rr) {
                $visible_plugins[] = $rr['aname'];
            }
        }

        $ret['site']['about'] = bbcode(get_config('system', 'siteinfo'), ['export' => true]);
        $ret['site']['plugins'] = $visible_plugins;
        $ret['site']['sitehash'] = get_config('system', 'location_hash');
        $ret['site']['sellpage'] = get_config('system', 'sellpage');
        $ret['site']['location'] = get_config('system', 'site_location');
        $ret['site']['sitename'] = System::get_site_name();
        $ret['site']['logo'] = System::get_site_icon();
        $ret['site']['project'] = System::get_project_name();
        $ret['site']['version'] = System::get_project_version();

        return $ret['site'];
    }

    /**
     * @brief
     *
     * @param array $hub
     * @param string $sitekey (optional, default empty)
     *
     * @return string hubloc_url
     */

    public static function update_hub_connected($hub, $site_id = '')
    {

        if ($site_id) {
            /*
             * This hub has now been proven to be valid.
             * Any hub with the same URL and a different sitekey cannot be valid.
             * Get rid of them (mark them deleted). There's a good chance they were re-installs.
             */

            q(
                "update hubloc set hubloc_deleted = 1, hubloc_error = 1 where hubloc_hash = '%s' and hubloc_url = '%s' and hubloc_site_id != '%s' ",
                dbesc($hub['hubloc_hash']),
                dbesc($hub['hubloc_url']),
                dbesc($site_id)
            );
        } else {
            $site_id = $hub['hubloc_site_id'];
        }

        // $sender['sitekey'] is a new addition to the protocol to distinguish
        // hublocs coming from re-installed sites. Older sites will not provide
        // this field and we have to still mark them valid, since we can't tell
        // if this hubloc has the same sitekey as the packet we received.
        // Update our DB to show when we last communicated successfully with this hub
        // This will allow us to prune dead hubs from using up resources

        $t = datetime_convert('UTC', 'UTC', 'now - 15 minutes');

        $r = q(
            "update hubloc set hubloc_connected = '%s' where hubloc_id = %d and hubloc_site_id = '%s' and hubloc_connected < '%s' ",
            dbesc(datetime_convert()),
            intval($hub['hubloc_id']),
            dbesc($site_id),
            dbesc($t)
        );

        // a dead hub came back to life - reset any tombstones we might have

        if (intval($hub['hubloc_error']) || intval($hub['hubloc_deleted'])) {
            q(
                "update hubloc set hubloc_error = 0, hubloc_deleted = 0 where hubloc_id = %d and hubloc_site_id = '%s' ",
                intval($hub['hubloc_id']),
                dbesc($site_id)
            );
            if (intval($hub['hubloc_orphancheck'])) {
                q(
                    "update hubloc set hubloc_orphancheck = 0 where hubloc_id = %d and hubloc_site_id = '%s' ",
                    intval($hub['hubloc_id']),
                    dbesc($site_id)
                );
            }
            q(
                "update xchan set xchan_orphan = 0 where xchan_orphan = 1 and xchan_hash = '%s'",
                dbesc($hub['hubloc_hash'])
            );
        }

        // this site obviously isn't dead because they are trying to communicate with us.
        q(
            "update site set site_dead = 0 where site_dead = 1 and site_url = '%s' ",
            dbesc($hub['hubloc_url'])
        );

        return $hub['hubloc_url'];
    }


    public static function sign($data, $key, $alg = 'sha256')
    {
        if (!$key) {
            return 'no key';
        }
        $sig = '';
        openssl_sign($data, $sig, $key, $alg);
        return $alg . '.' . base64url_encode($sig);
    }

    public static function verify($data, $sig, $key)
    {

        $verify = 0;

        $x = explode('.', $sig, 2);

        if ($key && count($x) === 2) {
            $alg = $x[0];
            $signature = base64url_decode($x[1]);

            $verify = @openssl_verify($data, $signature, $key, $alg);

            if ($verify === (-1)) {
                while ($msg = openssl_error_string()) {
                    logger('openssl_verify: ' . $msg, LOGGER_NORMAL, LOG_ERR);
                }
                btlogger('openssl_verify: key: ' . $key, LOGGER_DEBUG, LOG_ERR);
            }
        }
        return (($verify > 0) ? true : false);
    }


    public static function is_zot_request()
    {
        $x = getBestSupportedMimeType([ 'application/x-zot+json', 'application/x-nomad+json' ]);
        return (($x) ? true : false);
    }


    public static function zot_record_preferred($arr, $check = 'hubloc_network')
    {

        if (!$arr) {
            return $arr;
        }
        foreach ($arr as $v) {
            if($v[$check] === 'nomad') {
                return $v;
            }
        }
        foreach ($arr as $v) {
            if ($v[$check] === 'zot6') {
                return $v;
            }
        }
        return $arr[0];
    }

    public static function update_cached_hubloc($hubloc)
    {
        if ($hubloc['hubloc_updated'] > datetime_convert('UTC', 'UTC', 'now - 1 week') || $hubloc['hubloc_url'] === z_root()) {
            return;
        }
        self::refresh(['hubloc_id_url' => $hubloc['hubloc_id_url']]);
    }
}
