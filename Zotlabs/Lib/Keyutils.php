<?php

namespace Zotlabs\Lib;


require_once('library/asn1.php');

/**
 * Keyutils
 * Convert RSA keys between various formats
 */


class Keyutils {

	static public function DerToPem($Der, $Private=false) {
	    // Encode:
    	$Der = base64_encode($Der);
	    // Split lines:
	    $lines = str_split($Der, 65);
    	$body = implode("\n", $lines);
	    // Get title:
	    $title = $Private? 'RSA PRIVATE KEY' : 'PUBLIC KEY';
    	// Add wrapping:
		$return "-----BEGIN {$title}-----\n" . $body . "\n" . "-----END {$title}-----\n";
	}

	static public function DerToRsa($Der) {
	    // Encode:
    	$Der = base64_encode($Der);
	    // Split lines:
    	$lines = str_split($Der, 64);
	    $body = implode("\n", $lines);
    	// Get title:
	    $title = 'RSA PUBLIC KEY';
    	// Add wrapping:
		$return "-----BEGIN {$title}-----\n" . $body . "\n" . "-----END {$title}-----\n";
	}


	static public function pkcs8_encode($Modulus,$PublicExponent) {
		// Encode key sequence
		$modulus = new ASNValue(ASNValue::TAG_INTEGER);
		$modulus->SetIntBuffer($Modulus);
		$publicExponent = new ASNValue(ASNValue::TAG_INTEGER);
		$publicExponent->SetIntBuffer($PublicExponent);
		$keySequenceItems = array($modulus, $publicExponent);
		$keySequence = new ASNValue(ASNValue::TAG_SEQUENCE);
		$keySequence->SetSequence($keySequenceItems);
		//Encode bit string
		$bitStringValue = $keySequence->Encode();
		$bitStringValue = chr(0x00) . $bitStringValue; //Add unused bits byte
		$bitString = new ASNValue(ASNValue::TAG_BITSTRING);
		$bitString->Value = $bitStringValue;
		//Encode body
		$bodyValue = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00" . $bitString->Encode();
		$body = new ASNValue(ASNValue::TAG_SEQUENCE);
		$body->Value = $bodyValue;
		//Get DER encoded public key:
		return $body->Encode();
	}


	static function pkcs1_encode($Modulus,$PublicExponent) {
		// Encode key sequence
		$modulus = new ASNValue(ASNValue::TAG_INTEGER);
		$modulus->SetIntBuffer($Modulus);
		$publicExponent = new ASNValue(ASNValue::TAG_INTEGER);
		$publicExponent->SetIntBuffer($PublicExponent);
		$keySequenceItems = array($modulus, $publicExponent);
		$keySequence = new ASNValue(ASNValue::TAG_SEQUENCE);
		$keySequence->SetSequence($keySequenceItems);
		// Encode bit string
		return $keySequence->Encode();
	}


	// http://stackoverflow.com/questions/27568570/how-to-convert-raw-modulus-exponent-to-rsa-public-key-pem-format

	static public function metopem($m,$e) {
		$der = self::pkcs8_encode($m,$e);
		return self::DerToPem($der,false);
	}	


	static public function pubrsatome($key,&$m,&$e) {

		$lines = explode("\n",$key);
		unset($lines[0]);
		unset($lines[count($lines)]);
		$x = base64_decode(implode('',$lines));

		$r = ASN_BASE::parseASNString($x);

		$m = base64url_decode($r[0]->asnData[0]->asnData);
		$e = base64url_decode($r[0]->asnData[1]->asnData);
	}


	static public function rsatopem($key) {
		self::pubrsatome($key,$m,$e);
		return self::metopem($m,$e);
	}

	static public function pemtorsa($key) {
		self::pemtome($key,$m,$e);
		return self::metorsa($m,$e);
	}

	static public function pemtome($key,&$m,&$e) {
		$lines = explode("\n",$key);
		unset($lines[0]);
		unset($lines[count($lines)]);
		$x = base64_decode(implode('',$lines));

		$r = ASN_BASE::parseASNString($x);

		$m = base64url_decode($r[0]->asnData[1]->asnData[0]->asnData[0]->asnData);
		$e = base64url_decode($r[0]->asnData[1]->asnData[0]->asnData[1]->asnData);
	}

	static public function metorsa($m,$e) {
		$der = self::pkcs1_encode($m,$e);
		return self::DerToRsa($der);
	}	



	static public function salmon_key($pubkey) {
		self::pemtome($pubkey,$m,$e);
		return 'RSA' . '.' . base64url_encode($m,true) . '.' . base64url_encode($e,true) ;
	}


	static public function convert_salmon_key($key) {

		if(strstr($key,','))
			$rawkey = substr($key,strpos($key,',')+1);
		else
			$rawkey = substr($key,5);

		$key_info = explode('.',$rawkey);

		$m = base64url_decode($key_info[1]);
		$e = base64url_decode($key_info[2]);
			
		return self::metopem($m,$e);

	}

}