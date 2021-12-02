<?php

namespace Zotlabs\Web;

class HTTPHeaders
{

    private $in_progress = [];
    private $parsed = [];

    public function __construct($headers)
    {

        $lines = explode("\n", str_replace("\r", '', $headers));
        if ($lines) {
            foreach ($lines as $line) {
                if (preg_match('/^\s+/', $line, $matches) && trim($line)) {
                    if (isset($this->in_progress['k'])) {
                        $this->in_progress['v'] .= ' ' . ltrim($line);
                        continue;
                    }
                } else {
                    if (isset($this->in_progress['k'])) {
                        $this->parsed[] = [$this->in_progress['k'] => $this->in_progress['v']];
                        $this->in_progress = [];
                    }
                    $key = strtolower(substr($line, 0, strpos($line, ':')));
                    if ($key) {
                        $this->in_progress['k'] = $key;
                        $this->in_progress['v'] = ltrim(substr($line, strpos($line, ':') + 1));
                    }
                }

            }
            if (isset($this->in_progress['k'])) {
                $this->parsed[] = [$this->in_progress['k'] => $this->in_progress['v']];
                $this->in_progress = [];
            }
        }
    }

    public function fetch()
    {
        return $this->parsed;
    }

    public function fetcharr()
    {
        $ret = [];
        if ($this->parsed) {
            foreach ($this->parsed as $x) {
                foreach ($x as $y => $z) {
                    $ret[$y] = $z;
                }
            }
        }
        return $ret;
    }


}



