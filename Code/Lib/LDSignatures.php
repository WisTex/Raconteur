<?php

namespace Code\Lib;

use Exception;
use Code\Lib\Activity;
use Code\Lib\Channel;

require_once('library/jsonld/jsonld.php');

class LDSignatures
{


    public static function verify($data, $pubkey)
    {

        $ohash = self::hash(self::signable_options($data['signature']));
        $dhash = self::hash(self::signable_data($data));

        $x = Crypto::verify($ohash . $dhash, base64_decode($data['signature']['signatureValue']), $pubkey);
        logger('LD-verify: ' . intval($x));

        return $x;
    }

    public static function sign($data, $channel)
    {

        $options = [
            'type' => 'RsaSignature2017',
            'nonce' => random_string(64),
            'creator' => Channel::url($channel),
            'created' => datetime_convert('UTC', 'UTC', 'now', 'Y-m-d\TH:i:s\Z')
        ];

        $ohash = self::hash(self::signable_options($options));
        $dhash = self::hash(self::signable_data($data));
        $options['signatureValue'] = base64_encode(Crypto::sign($ohash . $dhash, $channel['channel_prvkey']));

        return $options;
    }


    public static function signable_data($data)
    {

        $newdata = [];
        if ($data) {
            foreach ($data as $k => $v) {
                if (!in_array($k, ['signature'])) {
                    $newdata[$k] = $v;
                }
            }
        }
        return json_encode($newdata, JSON_UNESCAPED_SLASHES);
    }


    public static function signable_options($options)
    {

        $newopts = ['@context' => 'https://w3id.org/identity/v1'];
        if ($options) {
            foreach ($options as $k => $v) {
                if (!in_array($k, ['type', 'id', 'signatureValue'])) {
                    $newopts[$k] = $v;
                }
            }
        }
        return json_encode($newopts, JSON_UNESCAPED_SLASHES);
    }

    public static function hash($obj)
    {

        return hash('sha256', self::normalise($obj));
    }

    public static function normalise($data)
    {
        if (is_string($data)) {
            $data = json_decode($data);
        }

        if (!is_object($data)) {
            return '';
        }

        jsonld_set_document_loader('jsonld_document_loader');
        try {
            $d = jsonld_normalize($data, ['algorithm' => 'URDNA2015', 'format' => 'application/nquads']);
        } catch (Exception $e) {
            // Don't log the exception - this can exhaust memory
            // logger('normalise error:' . print_r($e,true));
            logger('normalise error: ' . print_r($data, true));
        }
        return $d;
    }
}
