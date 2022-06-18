<?php

namespace Code\Module\Admin;

use Code\Render\Theme;


class Security
{

    public function post()
    {
        check_form_security_token_redirectOnErr('/admin/security', 'admin_security');

        $allowed_email = ((x($_POST, 'allowed_email')) ? notags(trim($_POST['allowed_email'])) : '');
        set_config('system', 'allowed_email', $allowed_email);

        $not_allowed_email = ((x($_POST, 'not_allowed_email')) ? notags(trim($_POST['not_allowed_email'])) : '');
        set_config('system', 'not_allowed_email', $not_allowed_email);

        $anonymous_comments = ((x($_POST, 'anonymous_comments')) ? intval($_POST['anonymous_comments']) : 0);
        set_config('system', 'anonymous_comments', $anonymous_comments);

        $use_hs2019 = ((x($_POST, 'use_hs2019')) ? intval($_POST['use_hs2019']) : 0);
        set_config('system', 'use_hs2019', $use_hs2019);

        $block_public_search = ((x($_POST, 'block_public_search')) ? 1 : 0);
        set_config('system', 'block_public_search', $block_public_search);

        $block_public_dir = ((x($_POST, 'block_public_directory')) ? true : false);
        set_config('system', 'block_public_directory', $block_public_dir);

        $localdir_hide = ((x($_POST, 'localdir_hide')) ? 1 : 0);
        set_config('system', 'localdir_hide', $localdir_hide);

        $cloud_noroot = ((x($_POST, 'cloud_noroot')) ? 1 : 0);
        set_config('system', 'cloud_disable_siteroot', 1 - $cloud_noroot);

        $cloud_disksize = ((x($_POST, 'cloud_disksize')) ? 1 : 0);
        set_config('system', 'cloud_report_disksize', $cloud_disksize);

        $thumbnail_security = ((x($_POST, 'thumbnail_security')) ? intval($_POST['thumbnail_security']) : 0);
        set_config('system', 'thumbnail_security', $thumbnail_security);

        $inline_pdf = ((x($_POST, 'inline_pdf')) ? intval($_POST['inline_pdf']) : 0);
        set_config('system', 'inline_pdf', $inline_pdf);

        $ws = $this->trim_array_elems(explode("\n", $_POST['allowed_sites']));
        set_config('system', 'allowed_sites', $ws);

        $bs = $this->trim_array_elems(explode("\n", $_POST['denied_sites']));
        set_config('system', 'denied_sites', $bs);

        $wc = $this->trim_array_elems(explode("\n", $_POST['allowed_channels']));
        set_config('system', 'allowed_channels', $wc);

        $bc = $this->trim_array_elems(explode("\n", $_POST['denied_channels']));
        set_config('system', 'denied_channels', $bc);

        $ws = $this->trim_array_elems(explode("\n", $_POST['pubstream_allowed_sites']));
        set_config('system', 'pubstream_allowed_sites', $ws);

        $bs = $this->trim_array_elems(explode("\n", $_POST['pubstream_denied_sites']));
        set_config('system', 'pubstream_denied_sites', $bs);

        $wc = $this->trim_array_elems(explode("\n", $_POST['pubstream_allowed_channels']));
        set_config('system', 'pubstream_allowed_channels', $wc);

        $bc = $this->trim_array_elems(explode("\n", $_POST['pubstream_denied_channels']));
        set_config('system', 'pubstream_denied_channels', $bc);

        $embed_sslonly = ((x($_POST, 'embed_sslonly')) ? true : false);
        set_config('system', 'embed_sslonly', $embed_sslonly);

        $we = $this->trim_array_elems(explode("\n", $_POST['embed_allow']));
        set_config('system', 'embed_allow', $we);

        $be = $this->trim_array_elems(explode("\n", $_POST['embed_deny']));
        set_config('system', 'embed_deny', $be);

        $ts = ((x($_POST, 'transport_security')) ? true : false);
        set_config('system', 'transport_security_header', $ts);

        $cs = ((x($_POST, 'content_security')) ? true : false);
        set_config('system', 'content_security_policy', $cs);

        goaway(z_root() . '/admin/security');
    }


    public function get()
    {

        $allowedsites = get_config('system', 'allowed_sites');
        $allowedsites_str = ((is_array($allowedsites)) ? implode("\n", $allowedsites) : '');

        $deniedsites = get_config('system', 'denied_sites');
        $deniedsites_str = ((is_array($deniedsites)) ? implode("\n", $deniedsites) : '');


        $allowedchannels = get_config('system', 'allowed_channels');
        $allowedchannels_str = ((is_array($allowedchannels)) ? implode("\n", $allowedchannels) : '');

        $deniedchannels = get_config('system', 'denied_channels');
        $deniedchannels_str = ((is_array($deniedchannels)) ? implode("\n", $deniedchannels) : '');

        $psallowedsites = get_config('system', 'pubstream_allowed_sites');
        $psallowedsites_str = ((is_array($psallowedsites)) ? implode("\n", $psallowedsites) : '');

        $psdeniedsites = get_config('system', 'pubstream_denied_sites');
        $psdeniedsites_str = ((is_array($psdeniedsites)) ? implode("\n", $psdeniedsites) : '');


        $psallowedchannels = get_config('system', 'pubstream_allowed_channels');
        $psallowedchannels_str = ((is_array($psallowedchannels)) ? implode("\n", $psallowedchannels) : '');

        $psdeniedchannels = get_config('system', 'pubstream_denied_channels');
        $psdeniedchannels_str = ((is_array($psdeniedchannels)) ? implode("\n", $psdeniedchannels) : '');

        $allowedembeds = get_config('system', 'embed_allow');
        $allowedembeds_str = ((is_array($allowedembeds)) ? implode("\n", $allowedembeds) : '');

        $deniedembeds = get_config('system', 'embed_deny');
        $deniedembeds_str = ((is_array($deniedembeds)) ? implode("\n", $deniedembeds) : '');

        $embed_coop = intval(get_config('system', 'embed_coop'));

        if ((!$allowedembeds) && (!$deniedembeds)) {
            $embedhelp1 = t("By default, unfiltered HTML is allowed in embedded media. This is inherently insecure.");
        }

        $embedhelp2 = t("The recommended setting is to only allow unfiltered HTML from the following sites:");
        $embedhelp3 = t("https://youtube.com/<br>https://www.youtube.com/<br>https://youtu.be/<br>https://vimeo.com/<br>https://soundcloud.com/<br>");
        $embedhelp4 = t("All other embedded content will be filtered, <strong>unless</strong> embedded content from that site is explicitly blocked.");

        $t = Theme::get_template('admin_security.tpl');
        return replace_macros($t, array(
            '$title' => t('Administration'),
            '$page' => t('Security'),
            '$form_security_token' => get_form_security_token('admin_security'),
            '$block_public_search' => array('block_public_search', t("Block public search"), get_config('system', 'block_public_search', 1), t("Prevent access to search content unless you are currently authenticated.")),
            '$block_public_dir' => ['block_public_directory', t('Block directory from visitors'), get_config('system', 'block_public_directory', true), t('Only allow authenticated access to directory.')],
            '$localdir_hide' => ['localdir_hide', t('Hide local directory'), intval(get_config('system', 'localdir_hide')), t('Only use the global directory')],
            '$cloud_noroot' => ['cloud_noroot', t('Provide a cloud root directory'), 1 - intval(get_config('system', 'cloud_disable_siteroot', true)), t('The cloud root directory lists all channel names which provide public files. Otherwise only the names of connections are shown.')],
            '$cloud_disksize' => ['cloud_disksize', t('Show total disk space available to cloud uploads'), intval(get_config('system', 'cloud_report_disksize')), ''],
            '$use_hs2019' => ['use_hs2019', t('Use hs2019 HTTP-Signature specification'), intval(get_config('system', 'use_hs2019', false)), t('This is not yet supported by many fediverse servers.')],
            '$thumbnail_security' => ['thumbnail_security', t("Allow SVG thumbnails in file browser"), get_config('system', 'thumbnail_security', 0), t("WARNING: SVG images may contain malicious code.")],

            '$inline_pdf' => ['inline_pdf', t("Allow embedded (inline) PDF files"), get_config('system', 'inline_pdf', 0), ''],
            '$anonymous_comments' => ['anonymous_comments', t('Permit anonymous comments'), intval(get_config('system', 'anonymous_comments')), t('Moderation will be performed by channels that select this comment option.')],
            '$transport_security' => array('transport_security', t('Set "Transport Security" HTTP header'), intval(get_config('system', 'transport_security_header')), ''),
            '$content_security' => array('content_security', t('Set "Content Security Policy" HTTP header'), intval(get_config('system', 'content_security_policy')), ''),
            '$allowed_email' => array('allowed_email', t("Allowed email domains"), get_config('system', 'allowed_email'), t("Comma separated list of domains which are allowed in email addresses for registrations to this site. Wildcards are accepted. Empty to allow any domains")),
            '$not_allowed_email' => array('not_allowed_email', t("Not allowed email domains"), get_config('system', 'not_allowed_email'), t("Comma separated list of domains which are not allowed in email addresses for registrations to this site. Wildcards are accepted. Empty to allow any domains, unless allowed domains have been defined.")),
            '$allowed_sites' => array('allowed_sites', t('Allow communications only from these sites'), $allowedsites_str, t('One site per line. Leave empty to allow communication from anywhere by default')),
            '$denied_sites' => array('denied_sites', t('Block communications from these sites'), $deniedsites_str, ''),
            '$allowed_channels' => array('allowed_channels', t('Allow communications only from these channels'), $allowedchannels_str, t('One channel (hash) per line. Leave empty to allow communication from any channel by default')),
            '$denied_channels' => array('denied_channels', t('Block communications from these channels'), $deniedchannels_str, ''),

            '$psallowed_sites' => array('pubstream_allowed_sites', t('Allow public stream communications only from these sites'), $psallowedsites_str, t('One site per line. Leave empty to allow communication from anywhere by default')),
            '$psdenied_sites' => array('pubstream_denied_sites', t('Block public stream communications from these sites'), $psdeniedsites_str, ''),
            '$psallowed_channels' => array('pubstream_allowed_channels', t('Allow public stream communications only from these channels'), $psallowedchannels_str, t('One channel (hash) per line. Leave empty to allow communication from any channel by default')),
            '$psdenied_channels' => array('pubstream_denied_channels', t('Block public stream communications from these channels'), $psdeniedchannels_str, ''),


            '$embed_sslonly' => array('embed_sslonly', t('Only allow embeds from secure (SSL) websites and links.'), intval(get_config('system', 'embed_sslonly')), ''),
            '$embed_allow' => array('embed_allow', t('Allow unfiltered embedded HTML content only from these domains'), $allowedembeds_str, t('One site per line. By default embedded content is filtered.')),
            '$embed_deny' => array('embed_deny', t('Block embedded HTML from these domains'), $deniedembeds_str, ''),

//          '$embed_coop'     => array('embed_coop', t('Cooperative embed security'), $embed_coop, t('Enable to share embed security with other compatible sites/hubs')),

            '$submit' => t('Submit')
        ));
    }


    public function trim_array_elems($arr)
    {
        $narr = [];

        if ($arr && is_array($arr)) {
            for ($x = 0; $x < count($arr); $x++) {
                $y = trim($arr[$x]);
                if ($y) {
                    $narr[] = $y;
                }
            }
        }
        return $narr;
    }
}
