<?php

/** @file */

require_once "html2bbcode.php";

function breaklines($line, $level, $wraplength = 75)
{

    if ($wraplength == 0) {
        $wraplength = 2000000;
    }

    //  return($line);

    $wraplen = $wraplength - $level;

    $newlines = [];

    do {
        $oldline = $line;

        $subline = substr($line, 0, $wraplen);

        $pos = strrpos($subline, ' ');

        if ($pos == 0) {
            $pos = strpos($line, ' ');
        }

        if (($pos > 0) and strlen($line) > $wraplen) {
            $newline = trim(substr($line, 0, $pos));
            if ($level > 0) {
                        $newline = str_repeat(">", $level) . ' ' . $newline;
            }

            $newlines[] = $newline . " ";
            $line = substr($line, $pos + 1);
        }
    } while ((strlen($line) > $wraplen) and !($oldline == $line));

    if ($level > 0) {
        $line = str_repeat(">", $level) . ' ' . $line;
    }

    $newlines[] = $line;


    return(implode("\n", $newlines));
}

function quotelevel($message, $wraplength = 75)
{
    $lines = explode("\n", $message);

    $newlines = [];
    $level = 0;
    foreach ($lines as $line) {
        $line = trim($line);
        $startquote = false;
        while (strpos("*" . $line, '[quote]') > 0) {
            $level++;
            $pos = strpos($line, '[quote]');
            $line = substr($line, 0, $pos) . substr($line, $pos + 7);
            $startquote = true;
        }

        $currlevel = $level;

        while (strpos("*" . $line, '[/quote]') > 0) {
            $level--;
            if ($level < 0) {
                $level = 0;
            }

            $pos = strpos($line, '[/quote]');
            $line = substr($line, 0, $pos) . substr($line, $pos + 8);
        }

        if (!$startquote or ($line != '')) {
            $newlines[] = breaklines($line, $currlevel, $wraplength);
        }
    }
    return(implode("\n", $newlines));
}

function collecturls($message)
{
    $pattern = '/<a.*?href="(.*?)".*?>(.*?)<\/a>/is';
    preg_match_all($pattern, $message, $result, PREG_SET_ORDER);

    $urls = [];
    $ignore = false;
    foreach ($result as $treffer) {
        // A list of some links that should be ignored
        $list = ["/user/", "/tag/", "/group/", "/profile/", "/channel/", "/search?search=", "/search?tag=", "mailto:", "/u/", "/node/",
                "//facebook.com/profile.php?id=", "//plus.google.com/"];
        foreach ($list as $listitem) {
            if (str_contains($treffer[1], $listitem)) {
                $ignore = true;
            }
        }

        if ((str_contains($treffer[1], "//plus.google.com/")) and (str_contains($treffer[1], "/posts"))) {
                $ignore = false;
        }

        if (!$ignore) {
            $urls[$treffer[1]] = $treffer[1];
        }
    }
    return($urls);
}

function html2plain($html, $wraplength = 75, $compact = false)
{

    $message = str_replace("\r", "", $html);

    if (! $message) {
        return $message;
    }
    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;


    $tmp_message = mb_convert_encoding($message, 'HTML-ENTITIES', "UTF-8");
    if ($tmp_message === false) {
        logger('mb_convert_encoding failed: ' . $message);
        return EMPTY_STR;
    }

    @$doc->loadHTML($tmp_message);

    $xpath = new DOMXPath($doc);
    $list = $xpath->query("//pre");
    foreach ($list as $node) {
        $node->nodeValue = str_replace("\n", "\r", htmlspecialchars($node->nodeValue));
    }

    $message = $doc->saveHTML();
    $message = str_replace(["\n<", ">\n", "\r", "\n", "\xC3\x82\xC2\xA0"], ["<", ">", "<br>", " ", ""], $message);
    $message = preg_replace('= \s*=i', " ", $message);

    // Collecting all links
    $urls = collecturls($message);

    @$doc->loadHTML($message);

    node2bbcode($doc, 'html', [], '', '');
    node2bbcode($doc, 'body', [], '', '');

    // MyBB-Auszeichnungen
    /*
    node2bbcode($doc, 'span', array('style'=>'text-decoration: underline;'), '_', '_');
    node2bbcode($doc, 'span', array('style'=>'font-style: italic;'), '/', '/');
    node2bbcode($doc, 'span', array('style'=>'font-weight: bold;'), '*', '*');

    node2bbcode($doc, 'strong', [], '*', '*');
    node2bbcode($doc, 'b', [], '*', '*');
    node2bbcode($doc, 'i', [], '/', '/');
    node2bbcode($doc, 'u', [], '_', '_');
    */

    if ($compact) {
        node2bbcode($doc, 'blockquote', [], "»", "«");
    } else {
        node2bbcode($doc, 'blockquote', [], '[quote]', "[/quote]\n");
    }

    node2bbcode($doc, 'br', [], "\n", '');

    node2bbcode($doc, 'span', [], "", "");
    node2bbcode($doc, 'pre', [], "", "");
    node2bbcode($doc, 'div', [], "\r", "\r");
    node2bbcode($doc, 'p', [], "\n", "\n");

    //node2bbcode($doc, 'ul', [], "\n[list]", "[/list]\n");
    //node2bbcode($doc, 'ol', [], "\n[list=1]", "[/list]\n");
    node2bbcode($doc, 'li', [], "\n* ", "\n");

    node2bbcode($doc, 'hr', [], "\n" . str_repeat("-", 70) . "\n", "");

    node2bbcode($doc, 'tr', [], "\n", "");
    node2bbcode($doc, 'td', [], "\t", "");

    node2bbcode($doc, 'h1', [], "\n\n*", "*\n");
    node2bbcode($doc, 'h2', [], "\n\n*", "*\n");
    node2bbcode($doc, 'h3', [], "\n\n*", "*\n");
    node2bbcode($doc, 'h4', [], "\n\n*", "*\n");
    node2bbcode($doc, 'h5', [], "\n\n*", "*\n");
    node2bbcode($doc, 'h6', [], "\n\n*", "*\n");

    // Problem: there is no reliable way to detect if it is a link to a tag or profile
    //node2bbcode($doc, 'a', array('href'=>'/(.+)/'), ' $1 ', '', true);
    node2bbcode($doc, 'a', ['href' => '/(.+)/', 'rel' => 'oembed'], ' $1 ', '');
    //node2bbcode($doc, 'img', array('alt'=>'/(.+)/'), '$1', '');
    //node2bbcode($doc, 'img', array('title'=>'/(.+)/'), '$1', '');
    //node2bbcode($doc, 'img', [], '', '');
    if (!$compact) {
        node2bbcode($doc, 'img', ['src' => '/(.+)/'], '[img]$1', '[/img]');
    } else {
        node2bbcode($doc, 'img', ['src' => '/(.+)/'], '', '');
    }

    node2bbcode($doc, 'iframe', ['src' => '/(.+)/'], ' $1 ', '');

    $message = $doc->saveHTML();

    if (!$compact) {
        $message = str_replace("[img]", "", $message);
        $message = str_replace("[/img]", "", $message);
    }

    // was ersetze ich da?
    // Irgendein stoerrisches UTF-Zeug
    $message = str_replace(chr(194) . chr(160), ' ', $message);

    $message = str_replace("&nbsp;", " ", $message);

    // Aufeinanderfolgende DIVs
    $message = preg_replace('=\r *\r=i', "\n", $message);
    $message = str_replace("\r", "\n", $message);

    $message = strip_tags($message);

    $message = html_entity_decode($message, ENT_QUOTES, 'UTF-8');

    if (!$compact) {
        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach ($urls as $id => $url) {
            if ($url && !str_contains($message, $url)) {
                $message .= "\n" . $url . " ";
            }
        }
    }

    do {
        $oldmessage = $message;
        $message = str_replace("\n\n\n", "\n\n", $message);
    } while ($oldmessage != $message);

    $message = quotelevel(trim($message), $wraplength);

    return(trim($message));
}
