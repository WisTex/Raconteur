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

    public function RelmeValidate($otherUrl, $myUrl)
    {
        $links = [];
        list($resolvedProfileUrl, $isSecure, $redirectChain) = IndieWeb\relMeDocumentUrl($otherUrl);
        if ($isSecure) {
            $htmlResponse = Url::get($resolvedProfileUrl);
            if ($htmlResponse['success']) {
                $links = IndieWeb\relMeLinks($htmlResponse['body'], $resolvedProfileUrl);
            }
        }
        foreach($links as $link) {
            list($matches, $secure, $redirectChain) = IndieWeb\backlinkingRelMeUrlMatches($link, $myUrl);
            if ($matches && $secure) {
                return true;
            }
        }
        return false;
    }


}
