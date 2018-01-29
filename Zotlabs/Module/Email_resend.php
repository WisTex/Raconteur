<?php

namespace Zotlabs\Module;


class Email_resend extends \Zotlabs\Web\Controller {

	function post() {

		if($_POST['token']) {
			if(! account_approve(trim($_POST['token']))) {
				notice(t('Token verification failed.'));
			}
		}

	}


	function get() {

		if(argc() > 1) {
			$result = false;
			$email = hex2bin(argv(1));

			if($email) {
				$result = verify_email_address( [ 'resend' => true, 'email' => $email ] );
			}

			if($result) {
				notice(t('Email verification resent'));
			}
			else {
				notice(t('Unable to resend email verification message.'));
			}

			goaway(z_root() . '/email_validation/' . bin2hex($email));

		}

		// @todo - one can provide a form here to resend the mail
		// after directing to here if a succesful login was attempted from an unverified address.


	}

}
