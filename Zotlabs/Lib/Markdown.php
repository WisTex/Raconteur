<?php
namespace Zotlabs\Lib;

/**
 * @brief Some functions for BB and markdown conversions
 */

use Michelf\MarkdownExtra;

use League\HTMLToMarkdown\HtmlConverter;
use League\HTMLToMarkdown\Environment;
use League\HTMLToMarkdown\Converter\ConverterInterface;
use League\HTMLToMarkdown\ElementInterface;

require_once("include/oembed.php");
require_once("include/event.php");
require_once("include/html2bbcode.php");
require_once("include/bbcode.php");


class Markdown
{

    /**
     * @brief Convert Markdown to bbcode.
     *
     * We don't want to support a bbcode specific markdown interpreter
     * and the markdown library we have is pretty good, but provides HTML output.
     * So we'll use that to convert to HTML, then convert the HTML back to bbcode,
     * and then clean up a few Diaspora specific constructs.
     *
     * @param string $s The message as Markdown
     * @param bool $use_zrl default false
     * @param array $options default empty
     * @return string The message converted to bbcode
     */

    public static function to_bbcode($s, $use_zrl = false, $options = [])
    {

        if (is_array($s)) {
            btlogger('markdown_to_bb called with array. ' . print_r($s, true), LOGGER_NORMAL, LOG_WARNING);
            return '';
        }

        $s = str_replace("&#xD;", "\r", $s);
        $s = str_replace("&#xD;\n&gt;", "", $s);

        $s = html_entity_decode($s, ENT_COMPAT, 'UTF-8');

        // if empty link text replace with the url
        $s = preg_replace("/\[\]\((.*?)\)/ism", '[$1]($1)', $s);

        $x = [
            'text' => $s,
            'zrl' => $use_zrl,
            'options' => $options
        ];

        /**
         * @hooks markdown_to_bb_init
         *   * \e string \b text - The message as Markdown and what will get returned
         *   * \e boolean \b zrl
         *   * \e array \b options
         */
        call_hooks('markdown_to_bb_init', $x);

        $s = $x['text'];

        // Escaping the hash tags
        $s = preg_replace('/\#([^\s\#])/', '&#35;$1', $s);

        $s = MarkdownExtra::defaultTransform($s);

        if ($options && $options['preserve_lf']) {
            $s = str_replace(["\r", "\n"], ["", '<br>'], $s);
        } else {
            $s = str_replace("\r", "", $s);
        }

        $s = str_replace('&#35;', '#', $s);

        $s = html2bbcode($s);

        // Convert everything that looks like a link to a link
        if ($use_zrl) {
            if (strpos($s, '[/img]') !== false) {
                $s = preg_replace_callback("/\[img\](.*?)\[\/img\]/ism", ['\\Zotlabs\\Lib\\Markdown', 'use_zrl_cb_img'], $s);
                $s = preg_replace_callback("/\[img\=([0-9]*)x([0-9]*)\](.*?)\[\/img\]/ism", ['\\Zotlabs\\Lib\\Markdown', 'use_zrl_cb_img_x'], $s);
            }
            $s = preg_replace_callback("/([^\]\=\{\/]|^)(https?\:\/\/)([a-zA-Z0-9\pL\:\/\-\?\&\;\.\=\_\~\#\%\$\!\+\,\@\(\)]+)/ismu", ['\\Zotlabs\\Lib\\Markdown', 'use_zrl_cb_link'], $s);
        } else {
            $s = preg_replace("/([^\]\=\{\/]|^)(https?\:\/\/)([a-zA-Z0-9\pL\:\/\-\?\&\;\.\=\_\~\#\%\$\!\+\,\@\(\)]+)/ismu", '$1[url=$2$3]$2$3[/url]', $s);
        }

        // remove duplicate adjacent code tags
        $s = preg_replace("/(\[code\])+(.*?)(\[\/code\])+/ism", "[code]$2[/code]", $s);

        /**
         * @hooks markdown_to_bb
         *   * \e string - The already converted message as bbcode
         */
        call_hooks('markdown_to_bb', $s);

        return $s;
    }

    public static function use_zrl_cb_link($match)
    {
        $res = '';
        $is_zid = is_matrix_url(trim($match[0]));

        if ($is_zid)
            $res = $match[1] . '[zrl=' . $match[2] . $match[3] . ']' . $match[2] . $match[3] . '[/zrl]';
        else
            $res = $match[1] . '[url=' . $match[2] . $match[3] . ']' . $match[2] . $match[3] . '[/url]';

        return $res;
    }

    public static function use_zrl_cb_img($match)
    {
        $res = '';
        $is_zid = is_matrix_url(trim($match[1]));

        if ($is_zid)
            $res = '[zmg]' . $match[1] . '[/zmg]';
        else
            $res = $match[0];

        return $res;
    }

    public static function use_zrl_cb_img_x($match)
    {
        $res = '';
        $is_zid = is_matrix_url(trim($match[3]));

        if ($is_zid)
            $res = '[zmg=' . $match[1] . 'x' . $match[2] . ']' . $match[3] . '[/zmg]';
        else
            $res = $match[0];

        return $res;
    }

    /**
     * @brief
     *
     * @param array $match
     * @return string
     */

    public static function from_bbcode_share($match)
    {

        $matches = [];
        $attributes = $match[1];

        $author = "";
        preg_match("/author='(.*?)'/ism", $attributes, $matches);
        if ($matches[1] != "")
            $author = urldecode($matches[1]);

        $link = "";
        preg_match("/link='(.*?)'/ism", $attributes, $matches);
        if ($matches[1] != "")
            $link = $matches[1];

        $avatar = "";
        preg_match("/avatar='(.*?)'/ism", $attributes, $matches);
        if ($matches[1] != "")
            $avatar = $matches[1];

        $profile = "";
        preg_match("/profile='(.*?)'/ism", $attributes, $matches);
        if ($matches[1] != "")
            $profile = $matches[1];

        $posted = "";
        preg_match("/posted='(.*?)'/ism", $attributes, $matches);
        if ($matches[1] != "")
            $posted = $matches[1];

        // message_id is never used, do we still need it?
        $message_id = "";
        preg_match("/message_id='(.*?)'/ism", $attributes, $matches);
        if ($matches[1] != "")
            $message_id = $matches[1];

        if (!$message_id) {
            preg_match("/guid='(.*?)'/ism", $attributes, $matches);
            if ($matches[1] != "")
                $message_id = $matches[1];
        }


        $reldate = datetime_convert('UTC', date_default_timezone_get(), $posted, 'r');

        $headline = '';

        if ($avatar != "")
            $headline .= '[url=' . zid($profile) . '][img]' . $avatar . '[/img][/url]';

        // Bob Smith wrote the following post 2 hours ago

        $fmt = sprintf(t('%1$s wrote the following %2$s %3$s'),
            '[url=' . zid($profile) . ']' . $author . '[/url]',
            '[url=' . zid($link) . ']' . t('post') . '[/url]',
            $reldate
        );

        $headline .= $fmt . "\n\n";

        $text = $headline . trim($match[2]);

        return $text;
    }


    /**
     * @brief Convert bbcode to Markdown.
     *
     * @param string $Text The message as bbcode
     * @param array $options default empty
     * @return string The message converted to Markdown
     */

    public static function from_bbcode($Text, $options = [])
    {

        /*
         * Transform #tags, strip off the [url] and replace spaces with underscore
         */

        $Text = preg_replace_callback('/#\[([zu])rl\=(.*?)\](.*?)\[\/[(zu)]rl\]/i',
            create_function('$match', 'return \'#\'. str_replace(\' \', \'_\', $match[3]);'), $Text);

        $Text = preg_replace('/#\^\[([zu])rl\=(.*?)\](.*?)\[\/([zu])rl\]/i', '[$1rl=$2]$3[/$4rl]', $Text);

        // Converting images with size parameters to simple images. Markdown doesn't know it.
        $Text = preg_replace("/\[img\=([0-9]*)x([0-9]*)\](.*?)\[\/img\]/ism", '[img]$3[/img]', $Text);

        $Text = preg_replace_callback("/\[share(.*?)\](.*?)\[\/share\]/ism", ['\\Zotlabs\\Lib\\Markdown', 'from_bbcode_share'], $Text);

        $x = ['bbcode' => $Text, 'options' => $options];

        /**
         * @hooks bb_to_markdown_bb
         *   * \e string \b bbcode - The message as bbcode and what will get returned
         *   * \e array \b options
         */
        call_hooks('bb_to_markdown_bb', $x);

        $Text = $x['bbcode'];

        // Convert it to HTML - don't try oembed
        $Text = bbcode($Text, ['tryoembed' => false]);

        // Now convert HTML to Markdown

        $Text = self::from_html($Text);

        //html2markdown adds backslashes infront of hashes after a new line. remove them
        $Text = str_replace("\n\#", "\n#", $Text);


        // If the text going into bbcode() has a plain URL in it, i.e.
        // with no [url] tags around it, it will come out of parseString()
        // looking like: <http://url.com>, which gets removed by strip_tags().
        // So take off the angle brackets of any such URL
        $Text = preg_replace("/<http(.*?)>/is", "http$1", $Text);

        // Remove empty zrl links
        $Text = preg_replace("/\[zrl\=\].*?\[\/zrl\]/is", "", $Text);

        $Text = trim($Text);

        /**
         * @hooks bb_to_markdown
         *   * \e string - The already converted message as bbcode and what will get returned
         */
        call_hooks('bb_to_markdown', $Text);

        return $Text;
    }


    /**
     * @brief Convert a HTML text into Markdown.
     *
     * This function uses the library league/html-to-markdown for this task.
     *
     * If the HTML text can not get parsed it will return an empty string.
     *
     * @param string $html The HTML code to convert
     * @return string Markdown representation of the given HTML text, empty on error
     */

    public static function from_html($html, $options = [])
    {
        $markdown = '';

        if (!$options) {
            $options = [
                'header_style' => 'setext', // Set to 'atx' to output H1 and H2 headers as # Header1 and ## Header2
                'suppress_errors' => true,  // Set to false to show warnings when loading malformed HTML
                'strip_tags' => false,      // Set to true to strip tags that don't have markdown equivalents. N.B. Strips tags, not their content. Useful to clean MS Word HTML output.
                'bold_style' => '**',       // DEPRECATED: Set to '__' if you prefer the underlined style
                'italic_style' => '*',      // DEPRECATED: Set to '_' if you prefer the underlined style
                'remove_nodes' => '',       // space-separated list of dom nodes that should be removed. example: 'meta style script'
                'hard_break' => false,      // Set to true to turn <br> into `\n` instead of `  \n`
                'list_item_style' => '-',   // Set the default character for each <li> in a <ul>. Can be '-', '*', or '+'
            ];
        }

        $environment = Environment::createDefaultEnvironment($options);
        $environment->addConverter(new TableConverter());
        $converter = new HtmlConverter($environment);

        try {
            $markdown = $converter->convert($html);
        } catch (InvalidArgumentException $e) {
            logger("Invalid HTML. HTMLToMarkdown library threw an exception.");
        }

        return $markdown;
    }
}

// Tables are not an official part of the markdown specification.
// This interface was suggested as a workaround.
// author: Mark Hamstra
// https://github.com/Mark-H/Docs


class TableConverter implements ConverterInterface
{
	/**
	 * @param ElementInterface $element
	 *
	 * @return string
	 */
	public function convert(ElementInterface $element)
	{
		switch ($element->getTagName()) {
			case 'tr':
				$line = [];
				$i = 1;
				foreach ($element->getChildren() as $td) {
					$i++;
					$v = $td->getValue();
					$v = trim($v);
					if ($i % 2 === 0 || $v !== '') {
						$line[] = $v;
					}
				}
				return '| ' . implode(' | ', $line) . " |\n";
			case 'td':
			case 'th':
				return trim($element->getValue());
			case 'tbody':
				return trim($element->getValue());
			case 'thead':
				$headerLine = reset($element->getChildren())->getValue();
				$headers = explode(' | ', trim(trim($headerLine, "\n"), '|'));
				$hr = [];
				foreach ($headers as $td) {
					$length = strlen(trim($td)) + 2;
					$hr[] = str_repeat('-', $length > 3 ? $length : 3);
				}
				$hr = '|' . implode('|', $hr) . '|';
				return $headerLine . $hr . "\n";
			case 'table':
				$inner = $element->getValue();
				if (strpos($inner, '-----') === false) {
					$inner = explode("\n", $inner);
					$single = explode(' | ', trim($inner[0], '|'));
					$hr = [];
					foreach ($single as $td) {
						$length = strlen(trim($td)) + 2;
						$hr[] = str_repeat('-', $length > 3 ? $length : 3);
					}
					$hr = '|' . implode('|', $hr) . '|';
					array_splice($inner, 1, 0, $hr);
					$inner = implode("\n", $inner);
				}
				return trim($inner) . "\n\n";
		}
		return $element->getValue();
	}
	/**
	 * @return string[]
	 */
	public function getSupportedTags()
	{
		return array('table', 'tr', 'thead', 'td', 'tbody');
	}
}
