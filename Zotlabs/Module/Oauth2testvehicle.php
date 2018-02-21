<?php

namespace Zotlabs\Module;

class OAuth2TestVehicle extends \Zotlabs\Web\Controller {

	function get() {

		$o .= replace_macros(get_markup_template('oauth2testvehicle.tpl'), array(
			'$baseurl' => z_root(),
			/*
			endpoints => array(
			  array(
				  'path_to_endpoint',
				  array(
					  array('field_name_1', 'value'),
					  array('field_name_2', 'value'),
					  ...
					  ),
				  'submit_button_name',
				  'Description of API action'
			  )
			)
			 */
			'$endpoints' => array(
				array(
					'oauth2testvehicle',
					array(
						array(
							'action', 'create_db'
						)
					),
					'oauth2test_create_db',
					'Create the OAuth2 database tables'
				),
				array(
					'oauth2testvehicle',
					array(
						array(
							'action', 'delete_db'
						)
					),
					'oauth2test_delete_db',
					'Delete the OAuth2 database tables'
				)
			)
		));

		return $o;
	}

	function post() {

		logger(json_encode($_POST), LOGGER_DEBUG);

		switch ($_POST['action']) {
			
			case 'delete_db':
				$status = true;
				// Use the \OAuth2\Storage\Pdo class to create the OAuth2 tables
				// by passing it the database connection 
				$pdo = \DBA::$dba->db;
				$storage = new \Zotlabs\Storage\ZotOauth2Pdo($pdo);
				logger('Deleting existing database tables...', LOGGER_DEBUG);
				foreach ($storage->getConfig() as $key => $table) {
					logger('Deleting table ' . dbesc($table), LOGGER_DEBUG);
					$r = q("DROP TABLE IF EXISTS %s;", dbesc($table));
					if (!$r) {
						logger('Errors encountered deleting database table ' . $table . '.', LOGGER_DEBUG);
						$status = false;
					}
				}
				if (!$status) {
					notice('Errors encountered deleting database tables.' . EOL);
				} else {
					info('Database tables deleted successfully.' . EOL);
				}

				break;
				
			case 'create_db':
				$status = true;
				logger('Creating database tables...', LOGGER_DEBUG);
				@include('.htconfig.php');
				$pdo = \DBA::$dba->db;
				$storage = new \Zotlabs\Storage\ZotOauth2Pdo($pdo);
				foreach (explode(';', $storage->getBuildSql($db_data)) as $statement) {
					try {
						$result = $pdo->exec($statement);
					} catch (\PDOException $e) {
						$status = false;
						logger('Error executing database statement: ' . $statement, LOGGER_DEBUG);
					}
				}

				if (!$status) {
					notice('Errors encountered creating database tables.' . EOL);
				} else {
					info('Database tables created successfully.' . EOL);
				}

			default:
				break;
		}
	}

}
