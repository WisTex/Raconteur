<?php
namespace Code\Lib;

require_once('library/text_languagedetect/Text/LanguageDetect.php');

use Text_LanguageDetect;
use Text_LanguageDetect_Exception;

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

    /**
     * Detect language from provided string.
     * When successful, return the 2-letter language code.
     * Lack of confidence in the result returns empty string.
     */
    public function detect(string $string,
    	int $minlength = self::MINLENGTH,
    	float $confidence = self::MINCONFIDENCE) : string
    {
        $detector = new Text_LanguageDetect();

        if (mb_strlen($string) < $minlength) {
            return '';
        }

        try {
            // return 2-letter ISO 639-1 language code (e.g.  'en') 
            $detector->setNameMode(2);
            $result = $detector->detectConfidence($string);
    		if (isset($result['language']) && $result['confidence'] >= $confidence) {
    			return $result['language'];
    		}
        } catch (Text_LanguageDetect_Exception $e) {
			logger('LanguageDetect Exception: ' . $e->getMessage());
    	}
        return '';
    }
}
