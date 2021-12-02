<?php

namespace Zotlabs\Web;

use DateTime;
use DateTimeZone;
use Zotlabs\Lib\ActivityStreams;
use Zotlabs\Lib\Activity;
use Zotlabs\Lib\Webfinger;
use Zotlabs\Lib\Zotfinger;
use Zotlabs\Lib\Libzot;
use Zotlabs\Lib\Crypto;
use Zotlabs\Lib\Keyutils;

/**
 * @brief Implements HTTP Signatures per draft-cavage-http-signatures-10.
 *
 * @see https://tools.ietf.org/html/draft-cavage-http-signatures-10
 */

class HTTPSig {

	/**
	 * @brief RFC5843
	 *
	 * @see https://tools.ietf.org/html/rfc5843
	 *
	 * @param string $body The value to create the digest for
	 * @param string $alg hash algorithm (one of 'sha256','sha512')
	 * @return string The generated digest header string for $body
	 */

	static function generate_digest_header($body,$alg = 'sha256') {

		$digest = base64_encode(hash($alg, $body, true));
		switch ($alg) {
			case 'sha512':
				return 'SHA-512=' . $digest;
			case 'sha256':
			default:
				return 'SHA-256=' . $digest;
				break;
		}
	}



	static function find_headers($data,&$body) {

		// decide if $data arrived via controller submission or curl
		// changes $body for the caller
		
		if (is_array($data) && array_key_exists('header',$data)) {
			if (! $data['success']) {
				$body = EMPTY_STR;
				return [];
			}

			if (! $data['header']) {
				$body = EMPTY_STR;
				return [];
			}
			
			$h = new HTTPHeaders($data['header']);
			$headers = $h->fetcharr();
			$body = $data['body'];
			$headers['(request-target)'] = $data['request_target'];
		}

		else {
			$headers = [];
			$headers['(request-target)'] = strtolower($_SERVER['REQUEST_METHOD']) . ' ' . $_SERVER['REQUEST_URI'];
			$headers['content-type'] = $_SERVER['CONTENT_TYPE'];
			$headers['content-length'] = $_SERVER['CONTENT_LENGTH'];

			foreach ($_SERVER as $k => $v) {
				if (strpos($k,'HTTP_') === 0) {
					$field = str_replace('_','-',strtolower(substr($k,5)));
					$headers[$field] = $v;
				}
			}
		}

		//logger('SERVER: ' . print_r($_SERVER,true), LOGGER_ALL);
		//logger('headers: ' . print_r($headers,true), LOGGER_ALL);

		return $headers;
	}


	// See draft-cavage-http-signatures-10

	static function verify($data,$key = '', $keytype = '') {

		$body      = $data;
		$headers   = null;

		$result = [
			'signer'         => '',
			'portable_id'    => '',
			'header_signed'  => false,
			'header_valid'   => false,
			'content_signed' => false,
			'content_valid'  => false
		];


		$headers = self::find_headers($data,$body);

		if (! $headers) {
			return $result;
		}

		if (is_array($body)) {
			btlogger('body is array!' . print_r($body,true));
		}


		$sig_block = null;

		if (array_key_exists('signature',$headers)) {
			$sig_block = self::parse_sigheader($headers['signature']);
		}
		elseif (array_key_exists('authorization',$headers)) {
			$sig_block = self::parse_sigheader($headers['authorization']);
		}

		if (! $sig_block) {
			logger('no signature provided.', LOGGER_DEBUG);
			return $result;
		}

		// Warning: This log statement includes binary data
		// logger('sig_block: ' . print_r($sig_block,true), LOGGER_DATA);

		$result['header_signed'] = true;

		$signed_headers = $sig_block['headers'];
		if (! $signed_headers) {
			$signed_headers = [ 'date' ];
		}
		$signed_data = '';
		foreach ($signed_headers as $h) {
			if (array_key_exists($h,$headers)) {
				$signed_data .= $h . ': ' . $headers[$h] . "\n";
			}
			if ($h === '(created)') {
				if ($sig_block['algorithm'] && (strpos($sig_block['algorithm'],'rsa') !== false || strpos($sig_block['algorithm'],'hmac') !== false || strpos($sig_block['algorithm'],'ecdsa') !== false)) {
					logger('created not allowed here');
					return $result;
				}
				if ((! isset($sig_block['(created)'])) || (! intval($sig_block['(created)'])) || intval($sig_block['(created)']) > time()) {
					logger('created in future');
					return $result;
				}
				$signed_data .= '(created): ' . $sig_block['(created)'] . "\n";
			}
			if ($h === '(expires)') {
				if ($sig_block['algorithm'] && (strpos($sig_block['algorithm'],'rsa') !== false || strpos($sig_block['algorithm'],'hmac') !== false || strpos($sig_block['algorithm'],'ecdsa') !== false)) {
					logger('expires not allowed here');
					return $result;
				}
				if ((! isset($sig_block['(expires)'])) || (! intval($sig_block['(expires)'])) || intval($sig_block['(expires)']) < time()) {
					logger('signature expired');
					return $result;
				}
				$signed_data .= '(expires): ' . $sig_block['(expires)'] . "\n";
			}
			if ($h === 'date') {
				$d = new DateTime($headers[$h]);
				$d->setTimeZone(new DateTimeZone('UTC'));
				$dplus = datetime_convert('UTC','UTC','now + 1 day');
				$dminus = datetime_convert('UTC','UTC','now - 1 day');
				$c = $d->format('Y-m-d H:i:s');
				if ($c > $dplus || $c < $dminus) {
					logger('bad time: ' . $c);
					return $result;
				}
			}
		}
		$signed_data = rtrim($signed_data,"\n");

		$algorithm = null;
		
		if ($sig_block['algorithm'] === 'rsa-sha256') {
			$algorithm = 'sha256';
		}
		if ($sig_block['algorithm'] === 'rsa-sha512') {
			$algorithm = 'sha512';
		}

		if (! array_key_exists('keyId',$sig_block)) {
			return $result;
		}

		$result['signer'] = $sig_block['keyId'];

		$fkey = self::get_key($key,$keytype,$result['signer']);

		if ($sig_block['algorithm'] === 'hs2019') {
			if (isset($fkey['algorithm'])) {
				if (strpos($fkey['algorithm'],'rsa-sha256') !== false) {
					$algorithm = 'sha256';
				}
				if (strpos($fkey['algorithm'],'rsa-sha512') !== false) {
					$algorithm = 'sha512';
				}
			}
		}
				

		if (! ($fkey && $fkey['public_key'])) {
			return $result;
		}

		$x = Crypto::verify($signed_data,$sig_block['signature'],$fkey['public_key'],$algorithm);

		logger('verified: ' . $x, LOGGER_DEBUG);

		if (! $x) {

			// try again, ignoring the local actor (xchan) cache and refetching the key
			// from its source
			
			$fkey = self::get_key($key,$keytype,$result['signer'],true);

			if ($fkey && $fkey['public_key']) {
				$y = Crypto::verify($signed_data,$sig_block['signature'],$fkey['public_key'],$algorithm);
				logger('verified: (cache reload) ' . $x, LOGGER_DEBUG);
			}

			if (! $y) {
				logger('verify failed for ' . $result['signer'] . ' alg=' . $algorithm . (($fkey['public_key']) ? '' : ' no key'));
				$sig_block['signature'] = base64_encode($sig_block['signature']);
				logger('affected sigblock: ' . print_r($sig_block,true));
				logger('headers: ' . print_r($headers,true));
				logger('server: ' . print_r($_SERVER,true));
				return $result;
			}
		}

		$result['portable_id'] = $fkey['portable_id'];
		$result['header_valid'] = true;

		if (in_array('digest',$signed_headers)) {
			$result['content_signed'] = true;
			$digest = explode('=', $headers['digest'], 2);
			if ($digest[0] === 'SHA-256') {
				$hashalg = 'sha256';
			}
			if ($digest[0] === 'SHA-512') {
				$hashalg = 'sha512';
			}

			if (base64_encode(hash($hashalg,$body,true)) === $digest[1]) {
				$result['content_valid'] = true;
			}

			logger('Content_Valid: ' . (($result['content_valid']) ? 'true' : 'false'));
			if (! $result['content_valid']) {
				logger('invalid content signature: data ' . print_r($data,true));
				logger('invalid content signature: headers ' . print_r($headers,true));
				logger('invalid content signature: body ' . print_r($body,true));
			}
		}

		return $result;
	}

	static function get_key($key,$keytype,$id,$force = false) {

		if ($key) {
			if (function_exists($key)) {
				return $key($id);
			}
			return [ 'public_key' => $key ];
		}

		if ($keytype === 'zot6') {
			$key = self::get_zotfinger_key($id,$force);
			if ($key) {
				return $key;
			}
		}
				

		if (strpos($id,'#') === false) {
			$key = self::get_webfinger_key($id,$force);
			if ($key) {
				return $key;
			}			
		}

		$key = self::get_activitystreams_key($id,$force);
		return $key;

	}


	static function convertKey($key) {

		if (strstr($key,'RSA ')) { 
			return Keyutils::rsatopem($key);
		}
		elseif (substr($key,0,5) === 'data:') {
			return Keyutils::convertSalmonKey($key);
		}
		else {
			return $key;
		}

	}


	/**
	 * @brief
	 *
	 * @param string $id
	 * @return boolean|string
	 *   false if no pub key found, otherwise return the pub key
	 */

	static function get_activitystreams_key($id,$force = false) {

		// Check the local cache first, but remove any fragments like #main-key since these won't be present in our cached data

		$cache_url = ((strpos($id,'#')) ? substr($id,0,strpos($id,'#')) : $id);

		// $force is used to ignore the local cache and only use the remote data; for instance the cached key might be stale
		
		if (! $force) {
			$x = q("select * from xchan left join hubloc on xchan_hash = hubloc_hash where ( hubloc_addr = '%s' or hubloc_id_url = '%s' or hubloc_hash = '%s') order by hubloc_id desc",
				dbesc(str_replace('acct:','',$cache_url)),
				dbesc($cache_url),
				dbesc($cache_url)
			);

			if ($x) {
				$best = Libzot::zot_record_preferred($x);
			}

			if ($best && $best['xchan_pubkey']) {
				return [ 'portable_id' => $best['xchan_hash'], 'public_key' => $best['xchan_pubkey'] , 'algorithm' => get_xconfig($best['xchan_hash'],'system','signing_algorithm'), 'hubloc' => $best ];
			}
		}

		// The record wasn't in cache. Fetch it now. 

		$r = Activity::fetch($id);
		$signatureAlgorithm = EMPTY_STR;

		if ($r) {
			if (array_key_exists('publicKey',$r) && array_key_exists('publicKeyPem',$r['publicKey']) && array_key_exists('id',$r['publicKey'])) {
				if ($r['publicKey']['id'] === $id || $r['id'] === $id) {
					$portable_id = ((array_key_exists('owner',$r['publicKey'])) ? $r['publicKey']['owner'] : EMPTY_STR);
					
					// the w3c sec context has conflicting names and no defined values for this property except
					// "http://www.w3.org/2000/09/xmldsig#rsa-sha1"
					
					// Since the names conflict, it could mess up LD-signatures but we will accept both, and at this
					// time we will only look for the substrings 'rsa-sha256' and 'rsa-sha512' within those properties.
					// We will also accept a toplevel 'sigAlgorithm' regardless of namespace with the same constraints.
					// Default to rsa-sha256 if we can't figure out. If they're sending 'hs2019' we have to
					// look for something.
						
					if (isset($r['publicKey']['signingAlgorithm'])) {
						$signatureAlgorithm = $r['publicKey']['signingAlgorithm'];
						set_xconfig($portable_id,'system','signing_algorithm',$signatureAlgorithm);
					}
					if (isset($r['publicKey']['signatureAlgorithm'])) {
						$signatureAlgorithm = $r['publicKey']['signatureAlgorithm'];
						set_xconfig($portable_id,'system','signing_algorithm',$signatureAlgorithm);
					}

					if (isset($r['sigAlgorithm'])) {
						$signatureAlgorithm = $r['sigAlgorithm'];
						set_xconfig($portable_id,'system','signing_algorithm',$signatureAlgorithm);
					}

					return [ 'public_key' => self::convertKey($r['publicKey']['publicKeyPem']), 'portable_id' => $portable_id, 'algorithm' => (($signatureAlgorithm) ? $signatureAlgorithm : 'rsa-sha256'), 'hubloc' => [] ];
				}
			}
		}

		// No key was found
		
		return false;
	}


	static function get_webfinger_key($id,$force = false) {

		if (! $force) {
			$x = q("select * from xchan left join hubloc on xchan_hash = hubloc_hash where ( hubloc_addr = '%s' or hubloc_id_url = '%s' or hubloc_hash = '%s') order by hubloc_id desc",
				dbesc(str_replace('acct:','',$id)),
				dbesc($id),
				dbesc($id)
			);

			if ($x) {
				$best = Libzot::zot_record_preferred($x);
			}

			if ($best && $best['xchan_pubkey']) {
				return [ 'portable_id' => $best['xchan_hash'], 'public_key' => $best['xchan_pubkey'] , 'algorithm' => get_xconfig($best['xchan_hash'],'system','signing_algorithm'), 'hubloc' => $best ];
			}
		}

		$wf = Webfinger::exec($id);
		$key = [ 'portable_id' => '', 'public_key' => '', 'algorithm' => '', 'hubloc' => [] ];

		if ($wf) {
		 	if (array_key_exists('properties',$wf) && array_key_exists('https://w3id.org/security/v1#publicKeyPem',$wf['properties'])) {
				$key['public_key'] = self::convertKey($wf['properties']['https://w3id.org/security/v1#publicKeyPem']);
			}
			if (array_key_exists('links', $wf) && is_array($wf['links'])) {
				foreach ($wf['links'] as $l) {
					if (! (is_array($l) && array_key_exists('rel',$l))) {
						continue;
					}
					if ($l['rel'] === 'magic-public-key' && array_key_exists('href',$l) && $key['public_key'] === EMPTY_STR) {
						$key['public_key'] = self::convertKey($l['href']);
					}
				}
			}
		}

		return (($key['public_key']) ? $key : false);
	}


	static function get_zotfinger_key($id,$force = false) {

		if (! $force) {
			$x = q("select * from xchan left join hubloc on xchan_hash = hubloc_hash where ( hubloc_addr = '%s' or hubloc_id_url = '%s' ) and hubloc_network = 'zot6' order by hubloc_id desc",
				dbesc(str_replace('acct:','',$id)),
				dbesc($id)
			);

			if ($x) {
				$best = Libzot::zot_record_preferred($x);
			}

			if ($best && $best['xchan_pubkey']) {
				return [ 'portable_id' => $best['xchan_hash'], 'public_key' => $best['xchan_pubkey'] ,  'algorithm' => get_xconfig($best['xchan_hash'],'system','signing_algorithm'), 'hubloc' => $best ];
			}
		}

		$wf = Webfinger::exec($id);
		$key = [ 'portable_id' => '', 'public_key' => '', 'algorithm' => '', 'hubloc' => [] ];

		if ($wf) {
		 	if (array_key_exists('properties',$wf) && array_key_exists('https://w3id.org/security/v1#publicKeyPem',$wf['properties'])) {
				$key['public_key'] = self::convertKey($wf['properties']['https://w3id.org/security/v1#publicKeyPem']);
			}
			if (array_key_exists('links', $wf) && is_array($wf['links'])) {
				foreach ($wf['links'] as $l) {
					if (! (is_array($l) && array_key_exists('rel',$l))) {
						continue;
					}
					if ($l['rel'] === 'http://purl.org/zot/protocol/6.0' && array_key_exists('href',$l) && $l['href'] !== EMPTY_STR) {

						// The third argument to Zotfinger::exec() tells it not to verify signatures
						// Since we're inside a function that is fetching keys with which to verify signatures,
						// this is necessary to prevent infinite loops.
						
						$z = Zotfinger::exec($l['href'], null, false);
						if ($z) {
							$i = Libzot::import_xchan($z['data']);
							if ($i['success']) {
								$key['portable_id'] = $i['hash'];

								$x = q("select * from xchan left join hubloc on xchan_hash = hubloc_hash where hubloc_id_url = '%s' order by hubloc_id desc limit 1",
									dbesc($l['href'])
								);
								if ($x) {
									$key['hubloc'] = $x[0];
								}
								$key['algorithm'] = get_xconfig($i['hash'],'system','signing_algorithm');
							}
						}
					}
					if ($l['rel'] === 'magic-public-key' && array_key_exists('href',$l) && $key['public_key'] === EMPTY_STR) {
						$key['public_key'] = self::convertKey($l['href']);
					}
				}
			}
		}

		return (($key['public_key']) ? $key : false);
	}


	/**
	 * @brief
	 *
	 * @param array $head
	 * @param string $prvkey
	 * @param string $keyid (optional, default '')
	 * @param boolean $auth (optional, default false)
	 * @param string $alg (optional, default 'sha256')
	 * @param array $encryption [ 'key', 'algorithm' ] or false
	 * @return array
	 */
	static function create_sig($head, $prvkey, $keyid = EMPTY_STR, $auth = false, $alg = 'sha256', $encryption = false ) {

		$return_headers = [];

		if ($alg === 'sha256') {
			$algorithm = 'rsa-sha256';
		}
		if ($alg === 'sha512') {
			$algorithm = 'rsa-sha512';
		}

		$x = self::sign($head,$prvkey,$alg);

		$headerval = 'keyId="' . $keyid . '",algorithm="' . $algorithm . '",headers="' . $x['headers'] . '",signature="' . $x['signature'] . '"';

		if ($encryption) {
			$x = Crypto::encapsulate($headerval,$encryption['key'],$encryption['algorithm']);
			if (is_array($x)) {
				$headerval = 'iv="' . $x['iv'] . '",key="' . $x['key'] . '",alg="' . $x['alg'] . '",data="' . $x['data'] . '"';
			}
		}

		if ($auth) {
			$sighead = 'Authorization: Signature ' . $headerval;
		}
		else {
			$sighead = 'Signature: ' . $headerval;
		}

		if ($head) {
			foreach ($head as $k => $v) {
				// strip the request-target virtual header from the output headers
				if ($k === '(request-target)') {
					continue;
				}
				$return_headers[] = $k . ': ' . $v;
			}
		}
		$return_headers[] = $sighead;

		return $return_headers;
	}

	/**
	 * @brief set headers
	 *
	 * @param array $headers
	 * @return void
	 */

	static function set_headers($headers) {
		if ($headers && is_array($headers)) {
			foreach ($headers as $h) {
				header($h);
			}
		} 
	}


	/**
	 * @brief
	 *
	 * @param array  $head
	 * @param string $prvkey
	 * @param string $alg (optional) default 'sha256'
	 * @return array
	 */

	static function sign($head, $prvkey, $alg = 'sha256') {

		$ret = [];

		$headers = '';
		$fields  = '';

		logger('signing: ' . print_r($head,true), LOGGER_DATA);

		if ($head) {
			foreach ($head as $k => $v) {
				$headers .= strtolower($k) . ': ' . trim($v) . "\n";
				if ($fields) {
					$fields .= ' ';
				}
				$fields .= strtolower($k);
			}
			// strip the trailing linefeed
			$headers = rtrim($headers,"\n");
		}

		$sig = base64_encode(Crypto::sign($headers,$prvkey,$alg));

		$ret['headers']   = $fields;
		$ret['signature'] = $sig;

		return $ret;
	}

	/**
	 * @brief
	 *
	 * @param string $header
	 * @return array associate array with
	 *   - \e string \b keyID
	 *   - \e string \b algorithm
	 *   - \e array  \b headers
	 *   - \e string \b signature
	 */

	static function parse_sigheader($header) {

		$ret = [];
		$matches = [];

		// if the header is encrypted, decrypt with (default) site private key and continue

		if (preg_match('/iv="(.*?)"/ism',$header,$matches)) {
			$header = self::decrypt_sigheader($header);
		}

		if (preg_match('/keyId="(.*?)"/ism',$header,$matches)) {
			$ret['keyId'] = $matches[1];
		}
		if (preg_match('/created=([0-9]*)/ism',$header,$matches)) {
			$ret['(created)'] = $matches[1];
		}
		if (preg_match('/expires=([0-9]*)/ism',$header,$matches)) {
			$ret['(expires)'] = $matches[1];
		}
		if (preg_match('/algorithm="(.*?)"/ism',$header,$matches)) {
			$ret['algorithm'] = $matches[1];
		}
		if (preg_match('/headers="(.*?)"/ism',$header,$matches)) {
			$ret['headers'] = explode(' ', $matches[1]);
		}
		if (preg_match('/signature="(.*?)"/ism',$header,$matches)) {
			$ret['signature'] = base64_decode(preg_replace('/\s+/','',$matches[1]));
		}

		if (($ret['signature']) && ($ret['algorithm']) && (! $ret['headers'])) {
			$ret['headers'] = [ 'date' ];
		}

 		return $ret;
	}


	/**
	 * @brief
	 *
	 * @param string $header
	 * @param string $prvkey (optional), if not set use site private key
	 * @return array|string associative array, empty string if failue
	 *   - \e string \b iv
	 *   - \e string \b key
	 *   - \e string \b alg
	 *   - \e string \b data
	 */

	static function decrypt_sigheader($header, $prvkey = null) {

		$iv = $key = $alg = $data = null;

		if (! $prvkey) {
			$prvkey = get_config('system', 'prvkey');
		}

		$matches = [];

		if (preg_match('/iv="(.*?)"/ism',$header,$matches)) {
			$iv = $matches[1];
		}
		if (preg_match('/key="(.*?)"/ism',$header,$matches)) {
			$key = $matches[1];
		}
		if (preg_match('/alg="(.*?)"/ism',$header,$matches)) {
			$alg = $matches[1];
		}
		if (preg_match('/data="(.*?)"/ism',$header,$matches)) {
			$data = $matches[1];
		}

		if ($iv && $key && $alg && $data) {
			return Crypto::unencapsulate([ 'encrypted' => true, 'iv' => $iv, 'key' => $key, 'alg' => $alg, 'data' => $data ] , $prvkey);
		}

		return '';
	}

}
