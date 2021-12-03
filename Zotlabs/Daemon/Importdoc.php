<?php

namespace Zotlabs\Daemon;

class Importdoc
{

    public static function run($argc, $argv)
    {

        require_once('include/help.php');

        self::update_docs_dir('doc/*');
    }

    public static function update_docs_dir($s)
    {
        $f = basename($s);
        $d = dirname($s);
        if ($s === 'doc/html') {
            return;
        }
        $files = glob("$d/$f");
        if ($files) {
            foreach ($files as $fi) {
                if ($fi === 'doc/html') {
                    continue;
                }
                if (is_dir($fi)) {
                    self::update_docs_dir("$fi/*");
                } else {
                    // don't update media content
                    if (strpos(z_mime_content_type($fi), 'text') === 0) {
                        store_doc_file($fi);
                    }
                }
            }
        }
        // remove old files that weren't updated (indicates they were most likely deleted).
        $i = q(
            "select * from item where item_type = 5 and edited < %s - INTERVAL %s",
            db_utcnow(),
            db_quoteinterval('14 DAY')
        );
        if ($i) {
            foreach ($i as $iv) {
                drop_item($iv['id'], false, DROPITEM_NORMAL, true);
            }
        }
    }
}
