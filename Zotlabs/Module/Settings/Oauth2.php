<?php

namespace Zotlabs\Module\Settings;


class Oauth2 {


	function post() {
	
		if(x($_POST,'remove')){
			check_form_security_token_redirectOnErr('/settings/oauth2', 'settings_oauth2');
			
			$key = $_POST['remove'];
			q("DELETE FROM tokens WHERE id='%s' AND uid=%d",
				dbesc($key),
				local_channel());
			goaway(z_root()."/settings/oauth2/");
			return;			
		}
	
		if((argc() > 2) && (argv(2) === 'edit' || argv(2) === 'add') && x($_POST,'submit')) {
			
			check_form_security_token_redirectOnErr('/settings/oauth2', 'settings_oauth2');
			
			$name   	= ((x($_POST,'name')) ? escape_tags(trim($_POST['name'])) : '');
			$secret		= ((x($_POST,'secret')) ? escape_tags(trim($_POST['secret'])) : '');
			$redirect	= ((x($_POST,'redirect')) ? escape_tags(trim($_POST['redirect'])) : '');
			$grant		= ((x($_POST,'grant')) ? escape_tags(trim($_POST['grant'])) : '');
			$scope		= ((x($_POST,'scope')) ? escape_tags(trim($_POST['scope'])) : '');

			$ok = true;
			if($name == '' || $secret == '') {
				$ok = false;
				notice( t('Name and Secret are required') . EOL);
			}
		
			if($ok) {
				if ($_POST['submit']==t("Update")){
					$r = q("UPDATE oauth_clients SET
								client_id = '%s',
								client_secret = '%s',
								redirect_uri = '%s',
								grant_types = '%s',
								scope = '%s', 
								user_id = '%s'
							WHERE client_id='%s'",
							dbesc($name),
							dbesc($secret),
							dbesc($redirect),
							dbesc($grant),
							dbesc($scope),
							dbesc(local_channel()),
							dbesc($name));
				} else {
					$r = q("INSERT INTO oauth_clients (client_id, client_secret, redirect_uri, grant_types, scope, user_id)
						VALUES ('%s','%s','%s','%s','%s','%s')",
						dbesc($name),
						dbesc($secret),
						dbesc($redirect),
						dbesc($grant),
						dbesc($scope),
						dbesc(local_channel())
					);
					$r = q("INSERT INTO xperm (xp_client, xp_channel, xp_perm) VALUES ('%s', %d, '%s') ",
						dbesc($name),
						intval(local_channel()),
						dbesc('all')
					);
				}
			}
			goaway(z_root()."/settings/oauth2/");
			return;
		}
	}

	function get() {
			
		if((argc() > 2) && (argv(2) === 'add')) {
			$tpl = get_markup_template("settings_oauth2_edit.tpl");
			$o .= replace_macros($tpl, array(
				'$form_security_token' => get_form_security_token("settings_oauth2"),
				'$title'	=> t('Add OAuth2 application'),
				'$submit'	=> t('Submit'),
				'$cancel'	=> t('Cancel'),
				'$name'		=> array('name', t('Name'), '', t('Name of application')),
				'$secret'	=> array('secret', t('Consumer Secret'), random_string(16), t('Automatically generated - change if desired. Max length 20')),
				'$redirect'	=> array('redirect', t('Redirect'), '', t('Redirect URI - leave blank unless your application specifically requires this')),
				'$grant'     => array('grant', t('Grant Types'), '', t('leave blank unless your application sepcifically requires this')),
				'$scope'     => array('scope', t('Authorization scope'), '', t('leave blank unless your application sepcifically requires this')),
			));
			return $o;
		}
			
		if((argc() > 3) && (argv(2) === 'edit')) {
			$r = q("SELECT * FROM oauth_clients WHERE client_id='%s' AND user_id= '%s'",
					dbesc(argv(3)),
					dbesc(local_channel())
			);
			
			if (! $r){
				notice(t('OAuth2 Application not found.'));
				return;
			}

			$app = $r[0];
				
			$tpl = get_markup_template("settings_oauth2_edit.tpl");
			$o .= replace_macros($tpl, array(
				'$form_security_token' => get_form_security_token("settings_oauth2"),
				'$title'	=> t('Add application'),
				'$submit'	=> t('Update'),
				'$cancel'	=> t('Cancel'),
				'$name'		=> array('name', t('Name'), $app['client_id'], t('Name of application')),
				'$secret'	=> array('secret', t('Consumer Secret'), $app['client_secret'], t('Automatically generated - change if desired. Max length 20')),
				'$redirect'	=> array('redirect', t('Redirect'), $app['redirect_uri'], t('Redirect URI - leave blank unless your application specifically requires this')),
				'$grant'     => array('grant', t('Grant Types'), $app['grant_types'], t('leave blank unless your application sepcifically requires this')),
				'$scope'     => array('scope', t('Authorization scope'), $app['scope'], t('leave blank unless your application sepcifically requires this')),
			));
			return $o;
		}
			
		if((argc() > 3) && (argv(2) === 'delete')) {
			check_form_security_token_redirectOnErr('/settings/oauth2', 'settings_oauth2', 't');
			
			$r = q("DELETE FROM oauth_clients WHERE client_id = '%s' AND user_id = '%s'",
					dbesc(argv(3)),
					dbesc(local_channel())
			);
			goaway(z_root()."/settings/oauth2/");
			return;			
		}
			

		$r = q("SELECT oauth_clients.*, oauth_access_tokens.access_token as oauth_token, (oauth_clients.user_id = '%s') AS my 
				FROM oauth_clients
				LEFT JOIN oauth_access_tokens ON oauth_clients.client_id=oauth_access_tokens.client_id
				WHERE oauth_clients.user_id IN ('%s',0)",
				dbesc(local_channel()),
				dbesc(local_channel())
		);
			
		$tpl = get_markup_template("settings_oauth2.tpl");
		$o .= replace_macros($tpl, array(
			'$form_security_token' => get_form_security_token("settings_oauth2"),
			'$baseurl'	=> z_root(),
			'$title'	=> t('Connected OAuth2 Apps'),
			'$add'		=> t('Add application'),
			'$edit'		=> t('Edit'),
			'$delete'		=> t('Delete'),
			'$consumerkey' => t('Client key starts with'),
			'$noname'	=> t('No name'),
			'$remove'	=> t('Remove authorization'),
			'$apps'		=> $r,
		));
		return $o;
			
	}

}