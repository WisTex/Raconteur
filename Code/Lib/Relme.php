<?php
namespace Code\Lib;

use IndieWeb;

class Relme
{
    protected $channel = null;

    public function setChannel($channel)
    {
        $this->channel = $channel;
        return $this;
    }

    public function getChannel()
    {
        return $this->channel;
    }

    public function getLinksFrom($url)
    {
        $output = '';
        $results = IndieWeb\relMeDocumentUrl($url);
        $canon = $results[0];
        $results = Url::get($canon);
        if ($results['success']) {
            $headers = '<pre>' . htmlspecialchars(print_r($results['header'], true)) . '</pre>';
            $output .= $headers;
            $links = IndieWeb\relMeLinks($results['body'], $canon);
        }
        $output .= print_r($links, true);
        return $output;
    }


    public function checkLinks(array $links, $url = null)
    {
        if ($url) {
            $checkUrl = IndieWeb\relMeDocumentUrl($url);
            $url = $checkUrl[0];
        }
        else {
            $url = z_root() . '/channel/' . $this->channel['channel_address'];
        }

        if (!$links || !$url) {
            return false;
        }
        foreach($links as $link) {
            list($matches, $secure, $redirectChain) = IndieWeb\backlinkingRelMeUrlMatches($link, $url);
            if ($matches && $secure) {
                return true;
            }
        }
        return false;
    }


}