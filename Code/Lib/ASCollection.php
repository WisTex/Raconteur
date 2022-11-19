<?php

namespace Code\Lib;

use Code\Lib\ActivityStreams;
use Code\Lib\Activity;

/**
 * Class for dealing with fetching ActivityStreams collections (ordered or unordered, normal or paged).
 * Construct with either an existing object or url and an optional channel to sign requests.
 * $direction is 0 (default) to fetch from the beginning, and 1 to fetch from the end and reverse order the resultant array.
 * An optional limit to the number of records returned may also be specified.
 * Use $class->get() to return an array of collection members.
 */
class ASCollection
{

    private $channel = null;
    private $nextpage = null;
    private $limit = 0;
    private $direction = 0;  // 0 = forward, 1 = reverse
    private $data = [];
    private $history = [];


    public function __construct($obj, $channel = null, $direction = 0, $limit = 0)
    {

        $this->channel = $channel;
        $this->direction = $direction;
        $this->limit = $limit;

        if (is_array($obj)) {
            $data = $obj;
        }

        if (is_string($obj)) {
            $data = Activity::fetch($obj, $channel);
            $this->history[] = $obj;
        }

        if (!is_array($data)) {
            return;
        }

        if (!in_array($data['type'], ['Collection', 'OrderedCollection'])) {
            return;
        }

        if ($this->direction) {
            if (array_key_exists('last', $data) && $data['last']) {
                $this->nextpage = $data['last'];
            }
        } else {
            if (array_key_exists('first', $data) && $data['first']) {
                $this->nextpage = $data['first'];
            }
        }

        if (isset($data['items']) && is_array($data['items'])) {
            $this->data = (($this->direction) ? array_reverse($data['items']) : $data['items']);
        } elseif (isset($data['orderedItems']) && is_array($data['orderedItems'])) {
            $this->data = (($this->direction) ? array_reverse($data['orderedItems']) : $data['orderedItems']);
        }

        if ($this->limit) {
            if (count($this->data) > $this->limit) {
                $this->data = array_slice($this->data, 0, $this->limit);
                return;
            }
        }

        do {
            $x = $this->next();
        } while ($x);
    }

    public function get()
    {
        return $this->data;
    }

    public function next()
    {

        if (!$this->nextpage) {
            return false;
        }

        if (is_array($this->nextpage)) {
            $data = $this->nextpage;
        }

        if (is_string($this->nextpage)) {
            if (in_array($this->nextpage, $this->history)) {
                // recursion detected
                return false;
            }
            $data = Activity::fetch($this->nextpage, $this->channel);
            $this->history[] = $this->nextpage;
        }

        if (!is_array($data)) {
            return false;
        }

        if (!in_array($data['type'], ['CollectionPage', 'OrderedCollectionPage'])) {
            return false;
        }

        $this->setnext($data);

        if (isset($data['items']) && is_array($data['items'])) {
            $this->data = array_merge($this->data, (($this->direction) ? array_reverse($data['items']) : $data['items']));
        } elseif (isset($data['orderedItems']) && is_array($data['orderedItems'])) {
            $this->data = array_merge($this->data, (($this->direction) ? array_reverse($data['orderedItems']) : $data['orderedItems']));
        }

        if ($this->limit) {
            if (count($this->data) > $this->limit) {
                $this->data = array_slice($this->data, 0, $this->limit);
                $this->nextpage = false;
                return true;
            }
        }

        return true;
    }

    public function setnext($data)
    {
        if ($this->direction) {
            if (array_key_exists('prev', $data) && $data['prev']) {
                $this->nextpage = $data['prev'];
            } elseif (array_key_exists('first', $data) && $data['first']) {
                $this->nextpage = $data['first'];
            } else {
                $this->nextpage = false;
            }
        } else {
            if (array_key_exists('next', $data) && $data['next']) {
                $this->nextpage = $data['next'];
            } elseif (array_key_exists('last', $data) && $data['last']) {
                $this->nextpage = $data['last'];
            } else {
                $this->nextpage = false;
            }
        }
        logger('nextpage: ' . $this->nextpage, LOGGER_DEBUG);
    }
}
