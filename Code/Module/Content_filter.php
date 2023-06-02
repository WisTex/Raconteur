<?php

namespace Code\Module;

use Code\Lib\Apps;
use Code\Lib\Libsync;
use Code\Web\Controller;
use Code\Render\Theme;


class Content_filter extends Controller
{

    public function post()
    {

        if (!(local_channel() && Apps::system_app_installed(local_channel(), 'Content Filter'))) {
            return;
        }

        if ($_POST['content_filter-submit']) {
            $incl = ((x($_POST['message_filter_incl'])) ? htmlspecialchars_decode(trim($_POST['message_filter_incl']), ENT_QUOTES) : '');
            $excl = ((x($_POST['message_filter_excl'])) ? htmlspecialchars_decode(trim($_POST['message_filter_excl']), ENT_QUOTES) : '');
            $filter_moderate = (isset($_POST['filter_moderate']) ? intval($_POST['filter_moderate']) : 0);
            set_pconfig(local_channel(), 'system', 'message_filter_incl', $incl);
            set_pconfig(local_channel(), 'system', 'message_filter_excl', $excl);
            set_pconfig(local_channel(), 'system', 'filter_moderate', $filter_moderate);
            info(t('Content Filter settings updated.') . EOL);
        }

        Libsync::build_sync_packet();
    }


    public function get()
    {

        $desc = t('This app (when installed) allows you to filter incoming content from all sources or from specific connections. The filtering may be based on words, tags, regular expressions, or language');

        $text = '<div class="section-content-info-wrapper">' . $desc . '</div>';

        if (!(local_channel() && Apps::system_app_installed(local_channel(), 'Content Filter'))) {
            return $text;
        }

        $text .= EOL . t('The settings on this page apply to all incoming content. To edit the settings for individual connetions, see the similar settings on the Connection Edit page for that connection.') . EOL . EOL;

        $setting_fields = $text;

        $setting_fields .= replace_macros(Theme::get_template('field_checkbox.tpl'), array(
            '$field' => [
                'filter_moderate',
                t('Moderate filtered content'),
                get_pconfig(local_channel(), 'system', 'filter_moderate'),
                t('Default is to ignore or reject filtered content'),
                [ t('No'), t('Yes') ],
            ]
        ));
        $setting_fields .= replace_macros(Theme::get_template('field_textarea.tpl'), array(
            '$field' => [
                'message_filter_incl',
                t('Only import posts with this text'),
                get_pconfig(local_channel(), 'system', 'message_filter_incl', ''),
                t('words one per line or #tags, $categories, /patterns/, lang=xx, lang!=xx - leave blank to import all posts')
            ]
        ));
        $setting_fields .= replace_macros(Theme::get_template('field_textarea.tpl'), array(
            '$field' => [
                'message_filter_excl',
                t('Do not import posts with this text'),
                get_pconfig(local_channel(), 'system', 'message_filter_excl', ''),
                t('words one per line or #tags, $categories, /patterns/, lang=xx, lang!=xx - leave blank to import all posts')
            ]
        ));

        return replace_macros(Theme::get_template('generic_app_settings.tpl'), array(
            '$addon' => array('content_filter', '' . t('Content Filter Settings'), '', t('Submit')),
            '$content' => $setting_fields
        ));
    }
}
