<?php
namespace Code\Lib;

require_once('library/text_languagedetect/Text/LanguageDetect.php');

use Text_LanguageDetect;

/**
 * @see http://pear.php.net/package/Text_LanguageDetect
 * @param string $s A string to examine
 * @return string Language code in 2-letter ISO 639-1 (en, de, fr) format
 *
 * @TODO: The PEAR library is no longer being maintained and has had recent issues loading with composer (2020-06-29).
 * This project: https://github.com/patrickschur/language-detection *may* be useful as a replacement.
 */

class LanguageDetect
{

    const MINLENGTH = 48;
    const MINCONFIDENCE = 0.01;

    public function detect($string)
    {
        $detector = new Text_LanguageDetect();

        if (mb_strlen($string) < self::MINLENGTH) {
            return '';
        }

        try {
            // return 2-letter ISO 639-1 (en) language code
            $detector->setNameMode(2);
            $result = $detector->detectConfidence($string);
        } catch (Text_LanguageDetect_Exception $e) {
            // null operation
        }

        if (!($result && isset($result['language']))) {
            return '';
        }

        if ($result['confidence'] < self::MINCONFIDENCE) {
            return '';
        }

        return($result['language']);
    }
}