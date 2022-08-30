<?php
namespace Code\Module;

use App;
use Code\Lib\PConfig;
use Code\Web\Controller;

class Legal extends Controller
{
    const LEGAL_SOURCE = 'doc/en/TermsOfService.mc';

    public function get() {
      $sys_channel = App::$sys_channel;
      $site_document = PConfig::Get($sys_channel['channel_id'],'system', 'legal');
      if (!$site_document) {
          $site_document = file_get_contents($this::LEGAL_SOURCE);
      }
      $html = bbcode($site_document);
      return $html;
    }

}
