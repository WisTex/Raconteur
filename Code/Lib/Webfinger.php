<?php

namespace Code\Lib;

/**
 * @brief Fetch and return a webfinger for a resource
 *
 * @param string $resource - The resource (required)
 * @return mixed
 */
class Webfinger
{
    private static $server = EMPTY_STR;
    private static $resource = EMPTY_STR;

    public static function exec($resource)
    {
        if (!$resource) {
            return false;
        }

        self::parse_resource($resource);

        if (!(self::$server && self::$resource)) {
            return false;
        }

        if (!check_siteallowed(self::$server)) {
            logger('denied: ' . self::$server);
            return false;
        }

        logger('fetching resource: ' . self::$resource . ' from ' . self::$server, LOGGER_DEBUG, LOG_INFO);

        $url = 'https://' . self::$server . '/.well-known/webfinger?f=&resource=' . self::$resource;

        $s = Url::get($url, ['headers' => ['Accept: application/jrd+json, */*']]);

        if ($s['success']) {
            $j = json_decode($s['body'], true);
            return ($j);
        }

        return false;
    }

    public static function parse_resource($resource)
    {
        self::$resource = urlencode($resource);

        if (str_starts_with($resource, 'http')) {
            $m = parse_url($resource);
            if ($m) {
                if ($m['scheme'] !== 'https') {
                    return false;
                }
                self::$server = $m['host'] . (($m['port']) ? ':' . $m['port'] : '');
            } else {
                return false;
            }
        } elseif (str_starts_with($resource, 'tag:')) {
            $arr = explode(':', $resource); // split the tag
            $h = explode(',', $arr[1]); // split the host,date
            self::$server = $h[0];
        } else {
            $x = explode('@', $resource);
            if (!strlen($x[0])) {
                // e.g. @dan@pixelfed.org
                array_shift($x);
            }
            if (count($x) > 1) {
                self::$server = $x[1];
            } else {
                return false;
            }
            if (!str_starts_with($resource, 'acct:')) {
                self::$resource = urlencode('acct:' . $resource);
            }
        }
    }

    /**
     * @brief fetch a webfinger resource and return a zot6 discovery url if present
     *
     */

    public static function nomad_url($resource)
    {
        $arr = self::exec($resource);

        if (is_array($arr) && array_key_exists('links', $arr)) {
            foreach ($arr['links'] as $link) {
				if (array_key_exists('rel',$link) && in_array($link['rel'], [ PROTOCOL_NOMAD, PROTOCOL_ZOT6 ])) {
                    if (!empty($link['href'])) {
                        return $link['href'];
                    }
                }
            }
        }
        return false;
    }
}

