<?php


use Zotlabs\Extend\Hook;

/** @file */
/*
html2bbcode.php
Converter for HTML to BBCode
Made by: Mike@piratenpartei.de
Originally made for the syncom project: http://wiki.piratenpartei.de/Syncom
                    https://github.com/annando/Syncom
*/

function node2bbcode(&$doc, $oldnode, $attributes, $startbb, $endbb)
{
    do {
        $done = node2bbcodesub($doc, $oldnode, $attributes, $startbb, $endbb);
    } while ($done);
}

function node2bbcodesub(&$doc, $oldnode, $attributes, $startbb, $endbb)
{
    $savestart = str_replace('$', '\x01', $startbb);
    $replace = false;

    $xpath = new DomXPath($doc);

    $list = $xpath->query("//" . $oldnode);
    foreach ($list as $oldNode) {
        $attr = [];
        if ($oldNode->attributes->length) {
            foreach ($oldNode->attributes as $attribute) {
                $attr[$attribute->name] = $attribute->value;
            }
        }

        $replace = true;

        $startbb = $savestart;

        $i = 0;

        foreach ($attributes as $attribute => $value) {
            $startbb = str_replace('\x01' . ++$i, '$1', $startbb);

            if (strpos('*' . $startbb, '$1') > 0) {
                if ($replace and (@$attr[$attribute] != '')) {
                    $startbb = preg_replace($value, $startbb, $attr[$attribute], -1, $count);

                    // If nothing could be changed
                    if ($count == 0) {
                        $replace = false;
                    }
                } else {
                    $replace = false;
                }
            } else {
                if (@$attr[$attribute] != $value) {
                    $replace = false;
                }
            }
        }

        if ($replace) {
            $StartCode = $oldNode->ownerDocument->createTextNode($startbb);
            $EndCode = $oldNode->ownerDocument->createTextNode($endbb);

            $oldNode->parentNode->insertBefore($StartCode, $oldNode);

            if ($oldNode->hasChildNodes()) {
                foreach ($oldNode->childNodes as $child) {
                    $newNode = $child->cloneNode(true);
                    $oldNode->parentNode->insertBefore($newNode, $oldNode);
                }
            }

            $oldNode->parentNode->insertBefore($EndCode, $oldNode);
            $oldNode->parentNode->removeChild($oldNode);
        }
    }
    return($replace);
}

function deletenode(&$doc, $node)
{
    $xpath = new DomXPath($doc);
    $list = $xpath->query("//" . $node);
    foreach ($list as $child) {
        $child->parentNode->removeChild($child);
    }
}


function svg2bbcode($match)
{

    $params = $match[1];
    $s = $match[2];

    $output =  '<svg' . (($params) ? $params : ' width="100%" height="480" ') . '>' . $s . '</svg>';

    $purify = new SvgSanitizer();
    if ($purify->loadXML($s)) {
        $purify->sanitize();
        $output = $purify->saveSVG();
        $output = preg_replace("/\<\?xml(.*?)\>/", '', $output);
        $output = preg_replace("/\<\!\-\-(.*?)\-\-\>/", '', $output);
        $output = str_replace(['<','>'], ['[',']'], $output);
        return $output;
    }
    return EMPTY_STR;
}

function html2bbcode($message)
{

    if (! $message) {
        return EMPTY_STR;
    }

    $message = str_replace("\r", "", $message);

    $message = str_replace(
        array(
                    "<li><p>",
                    "</p></li>"),
        array(
                    "<li>",
                    "</li>"),
        $message
    );

    // remove namespaces
    $message = preg_replace('=<(\w+):(.+?)>=', '<removeme>', $message);
    $message = preg_replace('=</(\w+):(.+?)>=', '</removeme>', $message);

    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;

    $tmp_message = mb_convert_encoding($message, 'HTML-ENTITIES', "UTF-8");

    if (! $tmp_message) {
        logger('mb_convert_encoding failed: ' . $message);
        return EMPTY_STR;
    }

    @$doc->loadHTML($tmp_message);

    deletenode($doc, 'style');
    deletenode($doc, 'head');
    deletenode($doc, 'title');
    deletenode($doc, 'meta');
    deletenode($doc, 'xml');
    deletenode($doc, 'removeme');

    $xpath = new DomXPath($doc);
    $list = $xpath->query("//pre");
    foreach ($list as $node) {
        $node->nodeValue = str_replace("\n", "\r", $node->nodeValue);
    }

    $message = $doc->saveHTML();
    $message = str_replace(array("\n<", ">\n", "\r", "\n", "\xC3\x82\xC2\xA0"), array("<", ">", "<br>", " ", ""), $message);
    $message = preg_replace('= [\s]*=i', " ", $message);
    @$doc->loadHTML($message);

    node2bbcode($doc, 'html', [], "", "");
    node2bbcode($doc, 'body', [], "", "");

    // Outlook-Quote - Variant 1
    node2bbcode($doc, 'p', array('class' => 'MsoNormal', 'style' => 'margin-left:35.4pt'), '[quote]', '[/quote]');

    // Outlook-Quote - Variant 2
    node2bbcode($doc, 'div', array('style' => 'border:none;border-left:solid blue 1.5pt;padding:0cm 0cm 0cm 4.0pt'), '[quote]', '[/quote]');

    // MyBB-Stuff
    node2bbcode($doc, 'span', array('style' => 'text-decoration: underline;'), '[u]', '[/u]');
    node2bbcode($doc, 'span', array('style' => 'font-style: italic;'), '[i]', '[/i]');
    node2bbcode($doc, 'span', array('style' => 'font-weight: bold;'), '[b]', '[/b]');

    /*node2bbcode($doc, 'font', array('face'=>'/([\w ]+)/', 'size'=>'/(\d+)/', 'color'=>'/(.+)/'), '[font=$1][size=$2][color=$3]', '[/color][/size][/font]');
    node2bbcode($doc, 'font', array('size'=>'/(\d+)/', 'color'=>'/(.+)/'), '[size=$1][color=$2]', '[/color][/size]');
    node2bbcode($doc, 'font', array('face'=>'/([\w ]+)/', 'size'=>'/(.+)/'), '[font=$1][size=$2]', '[/size][/font]');
    node2bbcode($doc, 'font', array('face'=>'/([\w ]+)/', 'color'=>'/(.+)/'), '[font=$1][color=$3]', '[/color][/font]');
    node2bbcode($doc, 'font', array('face'=>'/([\w ]+)/'), '[font=$1]', '[/font]');
    node2bbcode($doc, 'font', array('size'=>'/(\d+)/'), '[size=$1]', '[/size]');
    node2bbcode($doc, 'font', array('color'=>'/(.+)/'), '[color=$1]', '[/color]');
*/
    // Untested
    //node2bbcode($doc, 'span', array('style'=>'/.*font-size:\s*(.+?)[,;].*font-family:\s*(.+?)[,;].*color:\s*(.+?)[,;].*/'), '[size=$1][font=$2][color=$3]', '[/color][/font][/size]');
    //node2bbcode($doc, 'span', array('style'=>'/.*font-size:\s*(\d+)[,;].*/'), '[size=$1]', '[/size]');
    //node2bbcode($doc, 'span', array('style'=>'/.*font-size:\s*(.+?)[,;].*/'), '[size=$1]', '[/size]');

    node2bbcode($doc, 'span', array('style' => '/.*color:\s*(.+?)[,;].*/'), '[color="$1"]', '[/color]');
    //node2bbcode($doc, 'span', array('style'=>'/.*font-family:\s*(.+?)[,;].*/'), '[font=$1]', '[/font]');

    //node2bbcode($doc, 'div', array('style'=>'/.*font-family:\s*(.+?)[,;].*font-size:\s*(\d+?)pt.*/'), '[font=$1][size=$2]', '[/size][/font]');
    //node2bbcode($doc, 'div', array('style'=>'/.*font-family:\s*(.+?)[,;].*font-size:\s*(\d+?)px.*/'), '[font=$1][size=$2]', '[/size][/font]');
    //node2bbcode($doc, 'div', array('style'=>'/.*font-family:\s*(.+?)[,;].*/'), '[font=$1]', '[/font]');

    node2bbcode($doc, 'strong', [], '[b]', '[/b]');
    node2bbcode($doc, 'em', [], '[i]', '[/i]');
    node2bbcode($doc, 'b', [], '[b]', '[/b]');
    node2bbcode($doc, 'i', [], '[i]', '[/i]');
    node2bbcode($doc, 'u', [], '[u]', '[/u]');
    node2bbcode($doc, 's', [], '[s]', '[/s]');

    node2bbcode($doc, 'big', [], "[size=large]", "[/size]");
    node2bbcode($doc, 'small', [], "[size=small]", "[/size]");

    node2bbcode($doc, 'blockquote', [], '[quote]', '[/quote]');

    node2bbcode($doc, 'br', [], "\n", '');

    node2bbcode($doc, 'p', array('class' => 'MsoNormal'), "\n", "");
    node2bbcode($doc, 'div', array('class' => 'MsoNormal'), "\r", "");

    node2bbcode($doc, 'span', [], "", "");

    node2bbcode($doc, 'span', [], "", "");
    node2bbcode($doc, 'pre', [], "", "");
    node2bbcode($doc, 'div', [], "\r", "\r");
    node2bbcode($doc, 'p', [], "\n", "\n");

    node2bbcode($doc, 'ul', [], "[list]", "[/list]");
    node2bbcode($doc, 'ol', [], "[list=1]", "[/list]");
    node2bbcode($doc, 'li', [], "[*]", "");

    node2bbcode($doc, 'hr', [], "[hr]", "");

//  node2bbcode($doc, 'table', [], "", "");
//  node2bbcode($doc, 'tr', [], "\n", "");
//  node2bbcode($doc, 'td', [], "\t", "");

    node2bbcode($doc, 'table', [], "[table]", "[/table]");
    node2bbcode($doc, 'th', [], "[th]", "[/th]");
    node2bbcode($doc, 'tr', [], "[tr]", "[/tr]");
    node2bbcode($doc, 'td', [], "[td]", "[/td]");

    node2bbcode($doc, 'h1', [], "\n\n[h1]", "[/h1]\n");
    node2bbcode($doc, 'h2', [], "\n\n[h2]", "[/h2]\n");
    node2bbcode($doc, 'h3', [], "\n\n[h3]", "[/h3]\n");
    node2bbcode($doc, 'h4', [], "\n\n[h4]", "[/h4]\n");
    node2bbcode($doc, 'h5', [], "\n\n[h5]", "[/h5]\n");
    node2bbcode($doc, 'h6', [], "\n\n[h6]", "[/h6]\n");

    node2bbcode($doc, 'a', array('href' => '/(.+)/'), '[url=$1]', '[/url]');

    node2bbcode($doc, 'img', array('src' => '/(.+)/', 'width' => '/(\d+)/', 'height' => '/(\d+)/'), '[img=$2x$3]$1', '[/img]');
    node2bbcode($doc, 'img', array('src' => '/(.+)/'), '[img]$1', '[/img]');


    node2bbcode($doc, 'video', array('src' => '/(.+)/', 'poster' => '/(.+)/'), '[video poster=&quot;$2&quot;]$1', '[/video]');
    node2bbcode($doc, 'video', array('src' => '/(.+)/'), '[video]$1', '[/video]');
    node2bbcode($doc, 'audio', array('src' => '/(.+)/'), '[audio]$1', '[/audio]');
//  node2bbcode($doc, 'iframe', array('src'=>'/(.+)/'), '[iframe]$1', '[/iframe]');

    node2bbcode($doc, 'code', [], '[code]', '[/code]');

    $message = $doc->saveHTML();

    // I'm removing something really disturbing
    // Don't know exactly what it is
    $message = str_replace(chr(194) . chr(160), ' ', $message);

    $message = str_replace("&nbsp;", " ", $message);

    // removing multiple DIVs
    $message = preg_replace('=\r *\r=i', "\n", $message);
    $message = str_replace("\r", "\n", $message);

    Hook::call('html2bbcode', $message);

    $message = strip_tags($message);

    $message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');

    $message = str_replace(array("<"), array("&lt;"), $message);

    // remove quotes if they don't make sense
    $message = preg_replace('=\[/quote\][\s]*\[quote\]=i', "\n", $message);

    $message = preg_replace('=\[quote\]\s*=i', "[quote]", $message);
    $message = preg_replace('=\s*\[/quote\]=i', "[/quote]", $message);

    do {
        $oldmessage = $message;
        $message = str_replace("\n \n", "\n\n", $message);
    } while ($oldmessage != $message);

    do {
        $oldmessage = $message;
        $message = str_replace("\n\n\n", "\n\n", $message);
    } while ($oldmessage != $message);

    do {
        $oldmessage = $message;
        $message = str_replace(
            array(
                    "[/size]\n\n",
                    "\n[hr]",
                    "[hr]\n",
                    "\n[list",
                    "[/list]\n",
                    "\n[/",
                    "[list]\n",
                    "[list=1]\n",
                    "\n[*]"),
            array(
                    "[/size]\n",
                    "[hr]",
                    "[hr]",
                    "[list",
                    "[/list]",
                    "[/",
                    "[list]",
                    "[list=1]",
                    "[*]"),
            $message
        );
    } while ($message != $oldmessage);

    $message = str_replace(
        array('[b][b]', '[/b][/b]', '[i][i]', '[/i][/i]'),
        array('[b]', '[/b]', '[i]', '[/i]'),
        $message
    );

    // Handling Yahoo style of mails
    //  $message = str_replace('[hr][b]From:[/b]', '[quote][b]From:[/b]', $message);

    $message = htmlspecialchars($message, ENT_COMPAT, 'UTF-8', false);
    return(trim($message));
}
