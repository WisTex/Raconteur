<?php

namespace Zotlabs\Lib;

use App;
use Zotlabs\Lib\Channel;

class ServiceClass {        

    /**
     * @brief Called when creating a new channel.
     *
     * Checks the account's service class and number of current channels to determine
     * whether creating a new channel is within the current service class constraints.
     *
     * @param int $account_id
     *     Account_id used for this request
     *
     * @returns associative array with:
     *  * \e boolean \b success boolean true if creating a new channel is allowed for this account
     *  * \e string \b message (optional) if success is false, optional error text
     *  * \e int \b total_identities
     */
    public static function identity_check_service_class($account_id)
    {
        $ret = array('success' => false, 'message' => '');

        $r = q(
            "select count(channel_id) as total from channel where channel_account_id = %d and channel_removed = 0 ",
            intval($account_id)
        );
        if (! ($r && count($r))) {
            $ret['total_identities'] = 0;
            $ret['message'] = t('Unable to obtain identity information from database');
            return $ret;
        }

        $ret['total_identities'] = intval($r[0]['total']);

        if (! self::account_allows($account_id, 'total_identities', $r[0]['total'])) {
            $ret['message'] .= self::upgrade_message();
            return $ret;
        }

        $ret['success'] = true;

        return $ret;
    }



    /**
     * @brief Checks for accounts that have past their expiration date.
     *
     * If the account has a service class which is not the site default,
     * the service class is reset to the site default and expiration reset to never.
     * If the account has no service class it is expired and subsequently disabled.
     * called from include/poller.php as a scheduled task.
     *
     * Reclaiming resources which are no longer within the service class limits is
     * not the job of this function, but this can be implemented by plugin if desired.
     * Default behaviour is to stop allowing additional resources to be consumed.
     */
    public static function downgrade_accounts()
    {

        $r = q(
            "select * from account where not ( account_flags & %d ) > 0 
    		and account_expires > '%s' 
    		and account_expires < %s ",
            intval(ACCOUNT_EXPIRED),
            dbesc(NULL_DATE),
            db_getfunc('UTC_TIMESTAMP')
        );

        if (! $r) {
            return;
        }

        $basic = get_config('system', 'default_service_class');

        foreach ($r as $rr) {
            if (($basic) && ($rr['account_service_class']) && ($rr['account_service_class'] != $basic)) {
                $x = q(
                    "UPDATE account set account_service_class = '%s', account_expires = '%s'
    				where account_id = %d",
                    dbesc($basic),
                    dbesc(NULL_DATE),
                    intval($rr['account_id'])
                );
                $ret = [ 'account' => $rr ];
                call_hooks('account_downgrade', $ret);
                logger('downgrade_accounts: Account id ' . $rr['account_id'] . ' downgraded.');
            } else {
                $x = q(
                    "UPDATE account SET account_flags = (account_flags | %d) where account_id = %d",
                    intval(ACCOUNT_EXPIRED),
                    intval($rr['account_id'])
                );
                $ret = [ 'account' => $rr ];
                call_hooks('account_downgrade', $ret);
                logger('downgrade_accounts: Account id ' . $rr['account_id'] . ' expired.');
            }
        }
    }


    /**
     * @brief Check service_class restrictions.
     *
     * If there are no service_classes defined, everything is allowed.
     * If $usage is supplied, we check against a maximum count and return true if
     * the current usage is less than the subscriber plan allows. Otherwise we
     * return boolean true or false if the property is allowed (or not) in this
     * subscriber plan. An unset property for this service plan means the property
     * is allowed, so it is only necessary to provide negative properties for each
     * plan, or what the subscriber is not allowed to do.
     *
     * Like account_service_class_allows() but queries directly by account rather
     * than channel. Service classes are set for accounts, so we look up the
     * account for the channel and fetch the service class restrictions of the
     * account.
     *
     * @see account_service_class_allows() if you have a channel_id already
     * @see service_class_fetch()
     *
     * @param int $uid The channel_id to check
     * @param string $property The service class property to check for
     * @param string|bool $usage (optional) The value to check against
     * @return bool
     */
    public static function allows($uid, $property, $usage = false)
    {
        $limit = self::fetch($uid, $property);

        if ($limit === false) {
            return true; // No service class set => everything is allowed
        }
        
        $limit = engr_units_to_bytes($limit);
        if ($usage === false) {
            // We use negative values for not allowed properties in a subscriber plan
            return (($limit) ? (bool) $limit : true);
        } else {
            return (((intval($usage)) < intval($limit)) ? true : false);
        }
    }

    /**
     * @brief Check service class restrictions by account.
     *
     * If there are no service_classes defined, everything is allowed.
     * If $usage is supplied, we check against a maximum count and return true if
     * the current usage is less than the subscriber plan allows. Otherwise we
     * return boolean true or false if the property is allowed (or not) in this
     * subscriber plan. An unset property for this service plan means the property
     * is allowed, so it is only necessary to provide negative properties for each
     * plan, or what the subscriber is not allowed to do.
     *
     * Like service_class_allows() but queries directly by account rather than channel.
     *
     * @see service_class_allows() if you have a channel_id instead of an account_id
     * @see account_service_class_fetch()
     *
     * @param int $aid The account_id to check
     * @param string $property The service class property to check for
     * @param int|bool $usage (optional) The value to check against
     * @return bool
     */
    public static function account_allows($aid, $property, $usage = false)
    {

        $limit = self::account_fetch($aid, $property);

        if ($limit === false) {
            return true; // No service class is set => everything is allowed
        }
        
        $limit = engr_units_to_bytes($limit);

        if ($usage === false) {
            // We use negative values for not allowed properties in a subscriber plan
            return (($limit) ? (bool) $limit : true);
        } else {
            return (((intval($usage)) < intval($limit)) ? true : false);
        }
    }

    /**
     * @brief Queries a service class value for a channel and property.
     *
     * Service classes are set for accounts, so look up the account for this channel
     * and fetch the service classe of the account.
     *
     * If no service class is available it returns false and everything should be
     * allowed.
     *
     * @see account_service_class_fetch()
     *
     * @param int $uid The channel_id to query
     * @param string $property The service property name to check for
     * @return bool|int
     *
     * @todo Should we merge this with account_service_class_fetch()?
     */
    public static function fetch($uid, $property)
    {


        if ($uid == local_channel()) {
            $service_class = App::$account['account_service_class'];
        } else {
            $r = q(
                "select account_service_class 
    			from channel c, account a 
    			where c.channel_account_id = a.account_id and c.channel_id = %d limit 1",
                intval($uid)
            );
            if ($r) {
                $service_class = $r[0]['account_service_class'];
            }
        }
        if (! $service_class) {
            return false; // everything is allowed
        }
        $arr = get_config('service_class', $service_class);

        if (! is_array($arr) || (! count($arr))) {
            return false;
        }

        return((array_key_exists($property, $arr)) ? $arr[$property] : false);
    }

    /**
     * @brief Queries a service class value for an account and property.
     *
     * Like service_class_fetch() but queries by account rather than channel.
     *
     * @see service_class_fetch() if you have channel_id.
     * @see account_service_class_allows()
     *
     * @param int $aid The account_id to query
     * @param string $property The service property name to check for
     * @return bool|int
     */
    public static function account_fetch($aid, $property)
    {

        $r = q(
            "select account_service_class as service_class from account where account_id = %d limit 1",
            intval($aid)
        );
        if ($r !== false && count($r)) {
            $service_class = $r[0]['service_class'];
        }

        if (! x($service_class)) {
            return false; // everything is allowed
        }

        $arr = get_config('service_class', $service_class);

        if (! is_array($arr) || (! count($arr))) {
            return false;
        }

        return((array_key_exists($property, $arr)) ? $arr[$property] : false);
    }


    public static function upgrade_link($bbcode = false)
    {
        $l = get_config('service_class', 'upgrade_link');
        if (! $l) {
            return '';
        }
        if ($bbcode) {
            $t = sprintf('[zrl=%s]' . t('Click here to upgrade.') . '[/zrl]', $l);
        } else {
            $t = sprintf('<a href="%s">' . t('Click here to upgrade.') . '</div>', $l);
        }
        return $t;
    }

    public static function upgrade_message($bbcode = false)
    {
        $x = self::upgrade_link($bbcode);
        return t('This action exceeds the limits set by your subscription plan.') . (($x) ? ' ' . $x : '') ;
    }

    public static function upgrade_bool_message($bbcode = false)
    {
        $x = self::upgrade_link($bbcode);
        return t('This action is not available under your subscription plan.') . (($x) ? ' ' . $x : '') ;
    }
}
