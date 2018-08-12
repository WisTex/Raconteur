<?php

namespace Zotlabs\Identity;


class OAuth2Storage extends \OAuth2\Storage\Pdo {

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

		$x = account_verify_password($user,$password);
		return((array_key_exists('channel',$x) && ! empty($x['channel'])) ? true : false);

    }

    /**
     * @param string $username
     * @return array|bool
     */
    public function getUser($username)
    {

		$x = channelx_by_n($username);
		if(! $x) {
			return false;
		}

		return( [
                        'webbie'    => $x['channel_address'].'@'.\App::get_hostname(),
                        'zothash'   => $x['channel_hash'],
			'username'  => $x['channel_address'],
			'user_id'   => $x['channel_id'],
			'name'      => $x['channel_name'],
			'firstName' => $x['channel_name'],
			'lastName'  => '',
			'password'  => 'NotARealPassword'
		] );
    }

    public function scopeExists($scope) {
      // Report that the scope is valid even if it's not.
      // We will only return a very small subset no matter what.
      // @TODO: Truly validate the scope
      //    see vendor/bshaffer/oauth2-server-php/src/OAuth2/Storage/ScopeInterface.php and
      //        vendor/bshaffer/oauth2-server-php/src/OAuth2/Storage/Pdo.php
      //    for more info.
      return true;
    }

    public function getDefaultScope($client_id=null) {
      // Do not REQUIRE a scope
      //    see vendor/bshaffer/oauth2-server-php/src/OAuth2/Storage/ScopeInterface.php and
      //    for more info.
      return null;
    }

    public function getUserClaims ($user_id, $claims) {
      // Populate the CLAIMS requested (if any).
      // @TODO: create a more reasonable/comprehensive list.
      // @TODO: present claims on the AUTHORIZATION screen

        $userClaims = Array();
        $claims = explode (' ', trim($claims));
        $validclaims = Array ("name","preferred_username","zothash");
        $claimsmap = Array (
                            "zotwebbie" => 'webbie',
                            "zothash" => 'zothash',
                            "name" => 'name',
                            "preferred_username" => "username"
                           );
        $userinfo = $this->getUser($user_id);
        foreach ($validclaims as $validclaim) {
            if (in_array($validclaim,$claims)) {
              $claimkey = $claimsmap[$validclaim];
              $userClaims[$validclaim] = $userinfo[$claimkey];
            } else {
              $userClaims[$validclaim] = $validclaim;
            }
        }
        $userClaims["sub"]=$user_id;
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

}
