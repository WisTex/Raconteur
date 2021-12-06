<?php

namespace Zotlabs\Web;

class HttpMeta
{

    private $vars = null;
    private $og = null;

    public function __construct()
    {

        $this->vars = [];
        $this->og = [];
        $this->ogproperties = [];
    }

    //Set Meta Value
    //   Mode:
    //      0 = Default - set if no value currently exists
    //  1 = Overwrite - replace existing value(s)
    //  2 = Multi - append to the array of values
    public function set($property, $value, $mode = 0)
    {
        $ogallowsmulti = ['image', 'audio', 'video'];
        if (strpos($property, 'og:') === 0) {
            $count = 0;
            foreach ($this->og as $ogk => $ogdata) {
                if (strpos($ogdata['property'], $property) === 0) {
                    if ($mode == 1) {
                        unset($this->og[$ogk]);
                        unset($this->ogproperties[$property]);
                    } elseif ($mode == 0) {
                        return;
                    } elseif ($value == $ogdata['value']) {
                        return;
                    } else {
                        $count++;
                    }
                }
            }

            if ($value !== null) {
                //mode = 1 with value === null will delete the property entirely.
                $components = explode(':', $property);
                $ogp = $components[1];

                if (!$count || in_array($ogp, $ogallowsmulti)) {
                    $this->og[] = ['property' => $property, 'value' => $value];
                    $this->ogproperties[$property] = $property;
                }
            }
        } else {
            $this->vars[$property] = $value;
        }
    }

    public function check_required()
    {
        if (
            in_array('og:title', $this->ogproperties)
            && in_array('og:type', $this->ogproperties)
            && (in_array('og:image', $this->ogproperties)
                || in_array('og:image:url', $this->ogproperties))
            && (array_key_exists('og:url', $this->ogproperties)
                || array_key_exists('og:url:secure_url', $this->ogproperties))
            && array_key_exists('og:description', $this->ogproperties)
        ) {
            return true;
        }
        return false;
    }

    public function get_field($field)
    {
        if (strpos($field, 'og:') === 0) {
            foreach ($this->og as $ogdata) {
                if (strpos($ogdata['property'], $field) === 0) {
                    $arr[$field][] = $ogdata['value'];
                }
            }
        } else {
            $arr = $this->vars;
        }

        if (isset($arr) && is_array($arr) && array_key_exists($field, $arr) && $arr[$field]) {
            return $arr[$field];
        }
        return false;
    }


    public function get()
    {
        // use 'name' for most meta fields, and 'property' for opengraph properties
        $o = '';
        if ($this->vars) {
            foreach ($this->vars as $k => $v) {
                $o .= '<meta name="' . htmlspecialchars($k, ENT_COMPAT, 'UTF-8', false) . '" content="' . htmlspecialchars($v, ENT_COMPAT, 'UTF-8', false) . '" />' . "\r\n";
            }
        }
        if ($this->check_required()) {
            foreach ($this->og as $ogdata) {
                $o .= '<meta property="' . htmlspecialchars($ogdata['property'], ENT_COMPAT, 'UTF-8', false) . '" content="' . htmlspecialchars($ogdata['value'], ENT_COMPAT, 'UTF-8', false) . '" />' . "\r\n";
            }
        }
        if ($o) {
            return "\r\n" . $o;
        }
        return $o;
    }
}
