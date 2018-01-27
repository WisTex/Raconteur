<?php

namespace Zotlabs\Module;


class Email_validation extends \Zotlabs\Web\Controller {

	function post() {

		if($_POST['token']) {
			if(! account_approve(trim(basename($_POST['token'])))) {
				notice('Token verification failed.');
			}
		}

	}


	function get() {

		if(argc() > 1) {
			$email = hex2bin(argv(1));
		}

		$o = replace_macros(get_markup_template('email_validation.tpl'), [
			'$title' => t('Email Verification Required'),
			'$desc' => sprintf( t('A verification token was sent to your email address [%s]. Enter that token here to complete the account verification step. Please allow a few minutes for delivery, and check your spam folder if you do not see the message.'),$email),
			'$resend' => t('Resend Email'),
			'$email' => bin2hex($email),
			'$submit' => t('Submit'),
			'$token' => [ 'token', t('Validation token'),'','' ],
		]);
		
		return $o;

	}

}