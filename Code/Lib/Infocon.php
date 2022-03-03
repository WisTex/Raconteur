<?php
namespace Code\Lib;

/**
 * Infocon class: extract information and configuration structures from source modules.
 */

use Exception;        
use Symfony\Component\Yaml\Yaml;


class Infocon {

    public static function from_file($name) {
        $info = NULL;
        if (file_exists($name)) {
            try {
                $info = Yaml::parseFile($name, Yaml::PARSE_DATETIME);
            }
            catch (Exception $e) {
                ;
            }
        }
        return $info;
    } 

    public static function from_str($str) {
        $info = NULL;
        if ($str) {
            try {
                $info = Yaml::parse($str, Yaml::PARSE_DATETIME);
            }
            catch (Exception $e) {
                ;
            }
        }
        return $info;
    }

    public static function from_c_comment($file) {

        $info = NULL;
        try {
            $code = file_get_contents($file);
        }
        catch (Exception $e) {
            ;
        }

        // Match and fetch the first C-style comment
        $result = preg_match("|/\*.*\*/|msU", $code, $matches);

        if ($result) {

            $lines = explode("\n", $matches[0]);
            foreach ($lines as $line) {
                $line = trim($line, "\t\n\r */");
                if ($line != "") {
                    list($k, $v) = array_map("trim", explode(":", $line, 2));
                    $k = strtolower($k);
                    // multiple lines with the same key are turned into an array
                    if (isset($info[$k])) {
                        if (is_array($info[$k])) {
                            $info[$k][] = $v;
                        }
                        else {
                            $info[$k] = [ $info[$k], $v ];
                        }
                    }
                    else {
                        $info[$k] = $v;
                    }
                }
            }
        }
        return $info;
    }

}