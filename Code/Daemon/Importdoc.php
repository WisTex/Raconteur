<?php

namespace Code\Daemon;

class Importdoc implements DaemonInterface
{

    public function run(int $argc, array $argv): void
    {

        require_once('include/help.php');

        $this->update_docs_dir('doc/*');
    }

    public function update_docs_dir($s)
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
                    if (str_starts_with(z_mime_content_type($fi), 'text')) {
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
                drop_item($iv['id'], DROPITEM_NORMAL, true);
            }
        }
    }
}
