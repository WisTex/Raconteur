<?php

namespace Zotlabs\Lib;

/**
 * @brief MarkdownSoap class.
 *
 * Purify Markdown for storage
 * @code{.php}
 *   $x = new MarkdownSoap($string_to_be_cleansed);
 *   $text = $x->clean();
 * @endcode
 * What this does:
 * 1. extracts code blocks and privately escapes them from processing
 * 2. Run html purifier on the content
 * 3. put back the code blocks
 * 4. run htmlspecialchars on the entire content for safe storage
 *
 * At render time:
 * @code{.php}
 *    $markdown = \Zotlabs\Lib\MarkdownSoap::unescape($text);
 *    $html = \Michelf\MarkdownExtra::DefaultTransform($markdown);
 * @endcode
 */
class MarkdownSoap
{

    /**
     * @var string
     */
    private $str;
    /**
     * @var string
     */
    private $token;


    public function __construct($s)
    {
        $this->str = $s;
        $this->token = random_string(20);
    }

    public function clean()
    {

        $x = $this->extract_code($this->str);

        $x = $this->purify($x);

        $x = $this->putback_code($x);

        $x = $this->escape($x);

        return $x;
    }

    /**
     * @brief Extracts code blocks and privately escapes them from processing.
     *
     * @param string $s
     * @return string
     * @see encode_code()
     * @see putback_code()
     *
     */
    public function extract_code($s)
    {

        $text = preg_replace_callback('{
					(?:\n\n|\A\n?)
					(	            # $1 = the code block -- one or more lines, starting with a space/tab
					  (?>
						[ ]{' . '4' . '}  # Lines must start with a tab or a tab-width of spaces
						.*\n+
					  )+
					)
					((?=^[ ]{0,' . '4' . '}\S)|\Z)	# Lookahead for non-space at line-start, or end of doc
				}xm',
            [$this, 'encode_code'], $s);

        return $text;
    }

    public function encode_code($matches)
    {
        return $this->token . ';' . base64_encode($matches[0]) . ';';
    }

    public function decode_code($matches)
    {
        return base64_decode($matches[1]);
    }

    /**
     * @brief Put back the code blocks.
     *
     * @param string $s
     * @return string
     * @see extract_code()
     * @see decode_code()
     *
     */
    public function putback_code($s)
    {
        $text = preg_replace_callback('{' . $this->token . '\;(.*?)\;}xm', [$this, 'decode_code'], $s);
        return $text;
    }

    public function purify($s)
    {
        $s = $this->protect_autolinks($s);
        $s = purify_html($s);
        $s = $this->unprotect_autolinks($s);
        return $s;
    }

    public function protect_autolinks($s)
    {
        $s = preg_replace('/\<(https?\:\/\/)(.*?)\>/', '[$1$2]($1$2)', $s);
        return $s;
    }

    public function unprotect_autolinks($s)
    {
        return $s;
    }

    public function escape($s)
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * @brief Converts special HTML entities back to characters.
     *
     * @param string $s
     * @return string
     */
    public static function unescape($s)
    {
        return htmlspecialchars_decode($s, ENT_QUOTES);
    }
}
