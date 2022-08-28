<?php

namespace Code\Lib;

require_once('include/html2plain.php');

class MessageFilter
{

    public static function evaluate($item, $incl, $excl, $opts = [])
    {

        // Option: plaintext
        // Improve language detection by providing a plaintext version of $item['body'] which has no markup constructs/tags.

        if (array_key_exists('plaintext', $opts)) {
            $text = $opts['plaintext'];
        }
        else {
            $text = $item['body'];
        }

        $lang = null;

        // Language matching is a bit tricky, because the language can be ambiguous (detect_language() returns '').
        // If the language is ambiguous, the message will pass (be accepted) regardless of language rules.

        if (str_contains($incl, 'lang=')
                || str_contains($excl, 'lang=')
                || str_contains($incl, 'lang!=')
                || str_contains($excl, 'lang!=')) {
            $detector = new LanguageDetect();
            $lang = $detector->detect($text);
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
                if (isset($lang) && ((str_starts_with($word, 'lang=')) || (str_starts_with($word, 'lang!=')))) {
                    if (! strlen($lang)) {
                        // Result is ambiguous. As we are matching deny rules only at this time, continue tests.
                        // Any matching deny rule concludes testing.
                        continue;
                    }
                    if (str_starts_with($word, 'lang=') && strcasecmp($lang, trim(substr($word, 5))) == 0) {
                        return false;
                    } elseif (str_starts_with($word, 'lang!=') && strcasecmp($lang, trim(substr($word, 6))) != 0) {
                        return false;
                    }
                }
                elseif (str_starts_with($word, '#') && $tags) {
                    foreach ($tags as $t) {
                        if ((($t['ttype'] == TERM_HASHTAG) || ($t['ttype'] == TERM_COMMUNITYTAG)) && (($t['term'] === substr($word, 1)) || (substr($word, 1) === '*'))) {
                            return false;
                        }
                    }
                } elseif (str_starts_with($word, '$') && $tags) {
                    foreach ($tags as $t) {
                        if (($t['ttype'] == TERM_CATEGORY) && (($t['term'] === substr($word, 1)) || (substr($word, 1) === '*'))) {
                            return false;
                        }
                    }
                } elseif (str_starts_with($word, '?+')) {
                    if (self::test_condition(substr($word, 2), $item['obj'])) {
                        return false;
                    }
                } elseif (str_starts_with($word, '?')) {
                    if (self::test_condition(substr($word, 1), $item)) {
                        return false;
                    }
                } elseif ((str_starts_with($word, '/')) && preg_match($word, $item['body'])) {
                    return false;
                } elseif (stristr($item['body'], $word) !== false) {
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
                if (isset($lang) && ((str_starts_with($word, 'lang=')) || (str_starts_with($word, 'lang!=')))) {
                    // lang= or lang!= match
                    if (! strlen($lang))  {
                        // Result is ambiguous. However we are checking allow rules
                        // and an ambiguous language is always permitted.
                        return true;
                    }
                    if (str_starts_with($word, 'lang=') && strcasecmp($lang, trim(substr($word, 5))) == 0) {
                        return true;
                    } elseif (str_starts_with($word, 'lang!=') && strcasecmp($lang, trim(substr($word, 6))) != 0) {
                        return true;
                    }
                }
                elseif (str_starts_with($word, '#') && $tags) {
                    // #hashtag match
                    foreach ($tags as $t) {
                        if ((($t['ttype'] == TERM_HASHTAG) || ($t['ttype'] == TERM_COMMUNITYTAG)) && (($t['term'] === substr($word, 1)) || (substr($word, 1) === '*'))) {
                            return true;
                        }
                    }
                } elseif (str_starts_with($word, '$') && $tags) {
                    // $category match
                    foreach ($tags as $t) {
                        if (($t['ttype'] == TERM_CATEGORY) && (($t['term'] === substr($word, 1)) || (substr($word, 1) === '*'))) {
                            return true;
                        }
                    }
                } elseif (str_starts_with($word, '?+')) {
                    // ?+field item.obj field match
                    if (self::test_condition(substr($word, 2), $item['obj'])) {
                        return true;
                    }
                } elseif (str_starts_with($word, '?')) {
                    // ?item match
                    if (self::test_condition(substr($word, 1), $item)) {
                        return true;
                    }
                } elseif ((str_starts_with($word, '/')) && preg_match($word, $text)) {
                    // /regular expression match/
                    return true;
                } elseif (stristr($text, $word) !== false) {
                    // anything else - text match (case insensitive)
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
     * - ?!foo which will check for a return of a false condition for item.foo;
     *
     * The values 0, '', an empty array, and an unset value will all evaluate to false.
     *
     * @param string $s
     * @param array $item
     * @return bool
     */
    public static function test_condition($s,$item)
    {

        if (preg_match('/(.*?)\s~=\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if (stripos($x, trim($matches[2])) !== false) {
                return true;
            }
            return false;
        }

        if (preg_match('/(.*?)\s==\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if ($x == trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/(.*?)\s!=\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if ($x != trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/(.*?)\s>=\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if ($x >= trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/(.*?)\s<=\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if ($x <= trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/(.*?)\s>\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if ($x > trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/(.*?)\s>\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if ($x < trim($matches[2])) {
                return true;
            }
            return false;
        }

        if (preg_match('/\$(.*?)\s\{}\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if (is_array($x) && in_array(trim($matches[2]), $x)) {
                return true;
            }
            return false;
        }

        if (preg_match('/(.*?)\s\{\*}\s(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if (is_array($x) && array_key_exists(trim($matches[2]), $x)) {
                return true;
            }
            return false;
        }

        // Ordering of this check (for falsiness) with relation to the following one (check for truthiness) is important.
        if (preg_match('/!(.*?)$/', $s, $matches)) {
            $x = ((array_key_exists(trim($matches[1]),$item)) ? $item[trim($matches[1])] : EMPTY_STR);
            if (!$x) {
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
