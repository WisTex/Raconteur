<?php

if (! class_exists('App')) {
    class App
    {
        public static $rtl;
        public static $strings = array();
    }
}

if ($argc != 2) {
        print 'Usage: ' . $argv[0] . " <strings.php>\n\n";
        return;
}

        $phpfile = $argv[1];
        $pofile = dirname($phpfile) . '/messages.po';

if (!file_exists($phpfile)) {
        print "Unable to find '$phpfile'\n";
        return;
}

        include_once($phpfile);

        print "Out to '$pofile'\n";

        $out = '';
        $infile = file($pofile);
        $k = '';
        $c = '';
        $ink = false;
foreach ($infile as $l) {
        $l = trim($l, ' ');
    if (!preg_match('/^msgstr\[[1-9]/', $l)) {
        if ($k != '' && (substr($l, 0, 7) == 'msgstr ' || substr($l, 0, 8) == 'msgstr[0')) {
            $ink = false;
            $k = stripcslashes($k);
            $v = '';
            if (isset(App::$strings[$k])) {
                        $v = App::$strings[$k];
            } else {
                                $k = '__ctx:' . $c . '__ ' . $k;
                if (isset(App::$strings[$k])) {
                            $v = App::$strings[$k];
                            $c = '';
                }
            }
            if (!empty($v)) {
                if (is_array($v)) {
                                $l = '';
                                $n = 0;
                    foreach ($v as &$value) {
                        $l .= 'msgstr[' . $n . "] \"" . addcslashes($value, "\"\n") . "\"\n";
                        $n++;
                    }
                } else {
                                $l = "msgstr \"" . addcslashes($v, "\"\n") . "\"\n";
                }
            }
        }

        if (substr($l, 0, 6) == 'msgid_' || substr($l, 0, 7) == 'msgstr[') {
            $ink = false;
        }

        if ($ink) {
            preg_match('/^"(.*)"$/', $l, $m);
            $k .= $m[1];
        }

        if (substr($l, 0, 6) == 'msgid ') {
                    preg_match('/^msgid "(.*)"$/', $l, $m);
                    $k = $m[1];
                    $ink = true;
        }

        if (substr($l, 0, 8) == 'msgctxt ') {
                preg_match('/^msgctxt "(.*)"$/', $l, $m);
                $c = $m[1];
        }

                    $out .= $l;
    }
}
        file_put_contents($pofile, $out);
