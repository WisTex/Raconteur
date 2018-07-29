<?php

    if(! class_exists('App')) {
        class App {
            static public $rtl;
            static public $strings = Array();
        }
    }

        if ($argc!=2) {
                print "Usage: ".$argv[0]." <hstrings.php>\n\n";
                return;
        }

        $phpfile = $argv[1];
        $pofile = dirname($phpfile)."/hmessages.po";

        if (!file_exists($phpfile)){
                print "Unable to find '$phpfile'\n";
                return;
        }

        include_once($phpfile);

        print "Out to '$pofile'\n";

        $out = "";
        $infile = file($pofile);
        $k="";
        $ink = False;
        foreach ($infile as $l) {

                if (!preg_match("/^msgstr\[[1-9]/",$l)) {
                        if ($k!="" && (substr($l,0,7)=="msgstr " || substr($l,0,8)=="msgstr[0")){
                                $ink = False;
                                $v = "";
                                if (isset(App::$strings[$k])) {
                                        $v = App::$strings[$k];
                                        if (is_array($v)) {
                                                $l = "";
                                                $n = 0;
                                                foreach ($v as &$value) {
                                                        $l .= "msgstr[".$n."] \"".str_replace('"','\"',$value)."\"\n";
                                                        $n++;
                                                }
                                        } else {
                                                $l = "msgstr \"".str_replace('"','\"',$v)."\"\n";
                                        }
                                }
                        }

                        if (substr($l,0,6)=="msgid_" || substr($l,0,7)=="msgstr[") $ink = False;

                        if ($ink) {
                                $k .= trim($l,"\"\r\n");
                                $k = str_replace('\"','"',$k);
                        }

                        if (substr($l,0,6)=="msgid "){
                                $k = str_replace("msgid ","",$l);
                                if ($k != '""' ) {
                                        $k = trim($k,"\"\r\n");
                                        $k = str_replace('\"','"',$k);
                                } else {
                                        $k = "";
                                }
                                $ink = True;
                        }

                        $out .= $l;
                }
        }
        file_put_contents($pofile, $out);
?>
