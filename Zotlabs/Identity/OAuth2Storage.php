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

		$x = channelx_by_nick($username);
		if(! $x) {
			return false;
		}

		return( [
			'username'  => $x['channel_address'],
			'user_id'   => $x['channel_id'],
			'firstName' => $x['channel_name'],
			'lastName'  => '',
			'password'  => 'NotARealPassword'
		] );
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