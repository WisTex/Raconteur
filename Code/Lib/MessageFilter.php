<?php

namespace Code\Lib;

require_once('include/html2plain.php');

class MessageFilter
{


    public static function evaluate($item, $incl, $excl)
    {


		$text = prepare_text($item['body'],((isset($item['mimetype'])) ? $item['mimetype'] : 'text/x-multicode'));
        $text = html2plain(($item['title']) ? $item['title'] . ' ' . $text : $text);

        $lang = null;

        if ((strpos($incl, 'lang=') !== false) || (strpos($excl, 'lang=') !== false) || (strpos($incl, 'lang!=') !== false) || (strpos($excl, 'lang!=') !== false)) {
            $lang = detect_language($text);
        }

        $tags = ((isset($item['term']) && is_array($item['term']) && count($item['term'])) ? $item['term'] : false);

        // exclude always has priority

        $exclude = (($excl) ? explode("\n", $excl) : null);

        if ($exclude) {
            foreach ($exclude as $word) {
                $word = trim($word);
                if (! $word) {
                    continue;
                }
                if (substr($word, 0, 1) === '#' && $tags) {
                    foreach ($tags as $t) {
                        if ((($t['ttype'] == TERM_HASHTAG) || ($t['ttype'] == TERM_COMMUNITYTAG)) && (($t['term'] === substr($word, 1)) || (substr($word, 1) === '*'))) {
                            return false;
                        }
                    }
                } elseif (substr($word, 0, 1) === '$' && $tags) {
                    foreach ($tags as $t) {
                        if (($t['ttype'] == TERM_CATEGORY) && (($t['term'] === substr($word, 1)) || (substr($word, 1) === '*'))) {
                            return false;
                        }
                    }
                } elseif (substr($word, 0, 2) === '?+') {
                    if (self::test_condition(substr($word, 2), $item['obj'])) {
                        return false;
                    }                                         
                } elseif (substr($word, 0, 1) === '?') {
                    if (self::test_condition(substr($word, 1), $item)) {
                        return false;
                    }                                         
                } elseif ((strpos($word, '/') === 0) && preg_match($word, $text)) {
                    return false;
                } elseif ((strpos($word, 'lang=') === 0) && ($lang) && (strcasecmp($lang, trim(substr($word, 5))) == 0)) {
                    return false;
                } elseif ((strpos($word, 'lang!=') === 0) && ($lang) && (strcasecmp($lang, trim(substr($word, 6))) != 0)) {
                    return false;
                } elseif (stristr($text, $word) !== false) {
                    return false;
                }
            }
        }

        $include = (($incl) ? explode("\n", $incl) : null);

        if ($include) {
            foreach ($include as $word) {
                $word = trim($word);
                if (! $word) {
                    continue;
                }
                if (substr($word, 0, 1) === '#' && $tags) {
                    foreach ($tags as $t) {
                        if ((($t['ttype'] == TERM_HASHTAG) || ($t['ttype'] == TERM_COMMUNITYTAG)) && (($t['term'] === substr($word, 1)) || (substr($word, 1) === '*'))) {
                            return true;
                        }
                    }
                } elseif (substr($word, 0, 1) === '$' && $tags) {
                    foreach ($tags as $t) {
                        if (($t['ttype'] == TERM_CATEGORY) && (($t['term'] === substr($word, 1)) || (substr($word, 1) === '*'))) {
                            return true;
                        }
                    }
                } elseif (substr($word, 0, 2) === '?+') {
                    if (self::test_condition(substr($word, 2), $item['obj'])) {
                        return true;
                    }                                         
                } elseif (substr($word, 0, 1) === '?') {
                    if (self::test_condition(substr($word, 1), $item)) {
                        return true;
                    }                                         
                } elseif ((strpos($word, '/') === 0) && preg_match($word, $text)) {
                    return true;
                } elseif ((strpos($word, 'lang=') === 0) && ($lang) && (strcasecmp($lang, trim(substr($word, 5))) == 0)) {
                    return true;
                } elseif ((strpos($word, 'lang!=') === 0) && ($lang) && (strcasecmp($lang, trim(substr($word, 6))) != 0)) {
                    return true;
                } elseif (stristr($text, $word) !== false) {
                    return true;
                }
            }
        } else {
            return true;
        }

        return false;
    }


    /**
     * @brief Test for Conditional Execution conditions. Shamelessly ripped off from Code/Render/Comanche
     *
     * This is extensible. The first version of variable testing supports tests of the forms:
     *
     * - ?foo ~= baz which will check if item.foo contains the string 'baz';
     * - ?foo == baz which will check if item.foo is the string 'baz';
     * - ?foo != baz which will check if item.foo is not the string 'baz';
     * - ?foo >= 3 which will check if item.foo is greater than or equal to 3;
     * - ?foo > 3 which will check if item.foo is greater than 3;
     * - ?foo <= 3 which will check if item.foo is less than or equal to 3;
     * - ?foo < 3 which will check if item.foo is less than 3;
     *
     * - ?foo {} baz which will check if 'baz' is an array element in item.foo
     * - ?foo {*} baz which will check if 'baz' is an array key in item.foo
     * - ?foo which will check for a return of a true condition for item.foo;
     *
     * The values 0, '', an empty array, and an unset value will all evaluate to false.
     *
     * @param string $s
     * @param array $item
     * @return bool
     */
    public static function test_condition($s,$item)
    {
    
        if (preg_match('/(.*?)\s\~\=\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if (stripos($x, trim($matches[2])) !== false) {
                return true;
            }
            return false;
        }

        if (preg_match('/(.*?)\s\=\=\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if ($x == trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/(.*?)\s\!\=\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if ($x != trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/(.*?)\s\>\=\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if ($x >= trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/(.*?)\s\<\=\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if ($x <= trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/(.*?)\s\>\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if ($x > trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/(.*?)\s\>\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if ($x < trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/[\$](.*?)\s\{\}\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if (is_array($x) && in_array(trim($matches[2]), $x)) {
                return true;
            }
            return false;
        }

        if (preg_match('/(.*?)\s\{\*\}\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if (is_array($x) && array_key_exists(trim($matches[2]), $x)) {
                return true;
            }
            return false;
        }

        if (preg_match('/(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if ($x) {
                return true;
            }
            return false;
        }
        return false;
    }




}
