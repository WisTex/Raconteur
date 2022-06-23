<?php

namespace Code\Lib;

use Code\Lib\Url;

class Nodeinfo
{

    public static function fetch($url)
    {
        $href = EMPTY_STR;
        $m = parse_url($url);
        if ($m['scheme'] && $m['host']) {
            $s = $m['scheme'] . '://' . $m['host'] . '/.well-known/nodeinfo';
            $n = Url::get($s);
            if ($n['success']) {
                $j = json_decode($n['body'], true);
                if ($j && $j['links']) {
                    // lemmy just sends one result
                    if (isset($j['links']['rel'])) {
                        if ($j['links']['rel'] === 'http://nodeinfo.diaspora.software/ns/schema/2.0' && isset($j['links']['href'])) {
                            $href = $j['links']['href'];
                        }
                    } else {
                        foreach ($j['links'] as $l) {
                            if (isset($l['rel']) && $l['rel'] === 'http://nodeinfo.diaspora.software/ns/schema/2.0' && isset($l['href'])) {
                                $href = $l['href'];
                            }
                        }
                    }
                }
            }
        }
        if ($href) {
            $n = Url::get($href);
            if ($n['success']) {
                return json_decode($n['body'], true);
            }
        }
        return [];
    }
}
