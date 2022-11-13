<?php

namespace Code\Module\Dev;

use Code\Web\Controller;

class Xchan extends Controller
{

    public function get()
    {

        $o = '<h3>' . t('Xchan Lookup') . '</h3>';

        $o .= '<form action="dev/xchan" method="get">';
        $o .= t('Lookup xchan beginning with (or webbie): ');
        $o .= '<input type="text" style="width:250px;" name="addr" value="' . $_GET['addr'] . '">';
        $o .= '<input type="submit" name="submit" value="' . t('Submit') . '"></form>';
        $o .= '<br><br>';

        if (x($_GET, 'addr')) {
            $addr = trim($_GET['addr']);
            $h = q("select * from hubloc where hubloc_hash like '%s%%' or hubloc_addr = '%s'",
                dbesc($addr),
                dbesc($addr)
            );
            if ($h) {
                $r = q(
                    "select * from xchan where xchan_hash = '%s'",
                    dbesc($h[0]['hubloc_hash']),
                );
            }

            if ($r) {
                foreach ($r as $rr) {
                    $o .= str_replace(array("\n", " "), array("<br>", "&nbsp;"), print_r($rr, true)) . EOL;

                    $s = q(
                        "select * from hubloc where hubloc_hash = '%s'",
                        dbesc($rr['xchan_hash'])
                    );

                    if ($s) {
                        foreach ($s as $rrr) {
                            $o .= str_replace(array("\n", " "), array("<br>", "&nbsp;"), print_r($rrr, true)) . EOL;
                        }
                    }
                }
            } else {
                notice(t('Not found.') . EOL);
            }
        }
        return $o;
    }
}
