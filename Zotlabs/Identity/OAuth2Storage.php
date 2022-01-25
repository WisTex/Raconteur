<?php

namespace Zotlabs\Identity;

use OAuth2\Storage\Pdo;
use Zotlabs\Lib\Channel;
    
class OAuth2Storage extends Pdo
{

    /**
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function checkUserCredentials($username, $password)
    {
        if ($user = $this->getUser($username)) {
            return $this->checkPassword($user, $password);
        }

        return false;
    }

    /**
     * @param string $username
     * @return array|bool
     */
    public function getUserDetails($username)
    {
        return $this->getUser($username);
    }


    /**
     *
     * @param array $user
     * @param string $password
     * @return bool
     */
    protected function checkPassword($user, $password)
    {

        $x = account_verify_password($user, $password);
        return((array_key_exists('channel', $x) && ! empty($x['channel'])) ? true : false);
    }

    /**
     * @param string $username
     * @return array|bool
     */
    public function getUser($username)
    {

        $x = Channel::from_id($username);
        if (! $x) {
            return false;
        }

        $a = q(
            "select * from account where account_id = %d",
            intval($x['channel_account_id'])
        );

        $n = explode(' ', $x['channel_name']);

        return( [
            'webfinger'   => Channel::get_webfinger($x),
            'portable_id' => $x['channel_hash'],
            'email'       => $a[0]['account_email'],
            'username'    => $x['channel_address'],
            'user_id'     => $x['channel_id'],
            'name'        => $x['channel_name'],
            'firstName'   => ((count($n) > 1) ? $n[1] : $n[0]),
            'lastName'    => ((count($n) > 2) ? $n[count($n) - 1] : ''),
            'picture'     => $x['xchan_photo_l']
        ] );
    }

    public function scopeExists($scope)
    {
      // Report that the scope is valid even if it's not.
      // We will only return a very small subset no matter what.
      // @TODO: Truly validate the scope
      //    see vendor/bshaffer/oauth2-server-php/src/OAuth2/Storage/ScopeInterface.php and
      //        vendor/bshaffer/oauth2-server-php/src/OAuth2/Storage/Pdo.php
      //    for more info.
        return true;
    }

    public function getDefaultScope($client_id = null)
    {
      // Do not REQUIRE a scope
      //    see vendor/bshaffer/oauth2-server-php/src/OAuth2/Storage/ScopeInterface.php and
      //    for more info.
        return null;
    }

    public function getUserClaims($user_id, $claims)
    {
        // Populate the CLAIMS requested (if any).
        // @TODO: create a more reasonable/comprehensive list.
        // @TODO: present claims on the AUTHORIZATION screen

        $userClaims = [];
        $claims = explode(' ', trim($claims));
        $validclaims = [ "name", "preferred_username", "webfinger", "portable_id", "email", "picture", "firstName", "lastName" ];
        $claimsmap = [
            "webfinger"          => 'webfinger',
            "portable_id"        => 'portable_id',
            "name"               => 'name',
            "email"              => 'email',
            "preferred_username" => 'username',
            "picture"            => 'picture',
            "given_name"         => 'firstName',
            "family_name"        => 'lastName'
        ];
        $userinfo = $this->getUser($user_id);
        foreach ($validclaims as $validclaim) {
            if (in_array($validclaim, $claims)) {
                $claimkey = $claimsmap[$validclaim];
                $userClaims[$validclaim] = $userinfo[$claimkey];
            } else {
                $userClaims[$validclaim] = $validclaim;
            }
        }
        $userClaims["sub"] = $user_id;
        return $userClaims;
    }

    /**
     * plaintext passwords are bad!  Override this for your application
     *
     * @param string $username
     * @param string $password
     * @param string $firstName
     * @param string $lastName
     * @return bool
     */
    public function setUser($username, $password, $firstName = null, $lastName = null)
    {
        return true;
    }

    public function setClientDetails($client_id, $client_secret = null, $redirect_uri = null, $grant_types = null, $scope = null, $user_id = null, $client_name = null)
    {
        // if it exists, update it.
        if ($this->getClientDetails($client_id)) {
            $stmt = $this->db->prepare($sql = sprintf('UPDATE %s SET client_secret=:client_secret, redirect_uri=:redirect_uri, grant_types=:grant_types, scope=:scope, user_id=:user_id, client_name=:client_name where client_id=:client_id', $this->config['client_table']));
        } else {
            $stmt = $this->db->prepare(sprintf('INSERT INTO %s (client_id, client_secret, redirect_uri, grant_types, scope, user_id, client_name) VALUES (:client_id, :client_secret, :redirect_uri, :grant_types, :scope, :user_id, :client_name)', $this->config['client_table']));
        }

        return $stmt->execute(compact('client_id', 'client_secret', 'redirect_uri', 'grant_types', 'scope', 'user_id', 'client_name'));
    }




    public function checkRestrictedGrantType($client_id, $grant_type)
    {
        $details = $this->getClientDetails($client_id);
        if ($details['grant_types']) {
            $grant_types = explode(' ', $details['grant_types']);
            return in_array($grant_type, (array) $grant_types);
        }

        // if grant_types are not defined, then none are restricted
        return true;
    }
}
