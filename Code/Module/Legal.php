<?php
namespace Code\Module;

use App;
use Code\Lib\PConfig;
use Code\Web\Controller;
use Code\Render\Theme;

class Legal extends Controller
{
    public const LEGAL_SOURCE = 'doc/src/TermsOfService.mc';

    public function get() {
        $sys_channel = App::$sys_channel;
        $site_document = PConfig::Get($sys_channel['channel_id'],'system', 'legal');
        if (!$site_document) {
            $site_document = file_get_contents(self::LEGAL_SOURCE);
        }
        return replace_macros(Theme::get_template('legal.tpl'), ['$content' => bbcode($site_document)]);
    }

}
