<?php
namespace Code\Lib;

use Code\Lib\Config;
use Code\Lib\Activity;

class Url {

    /**
     * @brief fetches an URL.
     *
     * @param string $url
     *    URL to fetch
     * @param array $opts (optional parameters) associative array with:
     *  * \b timeout => int seconds, default system config value or 60 seconds
     *  * \b headers => array of additional header fields
     *  * \b http_auth => username:password
     *  * \b novalidate => do not validate SSL certs, default is to validate using our CA list
     *  * \b nobody => only return the header
     *  * \b filep => stream resource to write body to. header and body are not returned when using this option.
     *  * \b custom => custom request method: e.g. 'PUT', 'DELETE'
     *  * \b cookiejar => cookie file (write)
     *  * \b cookiefile => cookie file (read)
     *  * \b session => boolean; append session cookie *if* $url is our own site
     * @param int $redirects default 0
     *    internal use, recursion counter
     *
     * @return array an associative array with:
     *  * \e int \b return_code => HTTP return code or 0 if timeout or failure
     *  * \e boolean \b success => boolean true (if HTTP 2xx result) or false
     *  * \e string \b debug => diagnostics if failure
     *  * \e string \b header => HTTP headers
     *  * \e string \b body => fetched content
     */
    static public function get(string $url, array $opts = [], int $redirects = 0): array
    {
        $ret = array('return_code' => 0, 'success' => false, 'header' => "", 'body' => "");
        $passthru = false;

        $ch = curl_init($url);
        if (($redirects > 8) || (! $ch)) {
            return $ret;
        }

        if (! array_key_exists('request_target', $opts)) {
            $opts['request_target'] = 'get ' . get_request_string($url);
        }


        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CAINFO, self::get_capath());
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $ciphers = @get_config('system', 'curl_ssl_ciphers');
        if ($ciphers) {
            curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, $ciphers);
        }

        if (x($opts, 'filep')) {
            curl_setopt($ch, CURLOPT_FILE, $opts['filep']);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $passthru = true;
        }

        if (x($opts, 'useragent')) {
            curl_setopt($ch, CURLOPT_USERAGENT, $opts['useragent']);
        } else {
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible)");
        }

        if (x($opts, 'upload')) {
            curl_setopt($ch, CURLOPT_UPLOAD, $opts['upload']);
        }

        if (x($opts, 'infile')) {
            curl_setopt($ch, CURLOPT_INFILE, $opts['infile']);
        }

        if (x($opts, 'infilesize')) {
            curl_setopt($ch, CURLOPT_INFILESIZE, $opts['infilesize']);
        }

        if (x($opts, 'readfunc')) {
            curl_setopt($ch, CURLOPT_READFUNCTION, $opts['readfunc']);
        }

        // When using the session option and fetching from our own site,
        // append the PHPSESSID cookie to any existing headers.
        // Don't add to $opts['headers'] so that the cookie does not get
        // sent to other sites via redirects

        $instance_headers = ((array_key_exists('headers', $opts) && is_array($opts['headers'])) ? $opts['headers'] : []);

        if (x($opts, 'session')) {
            if (str_starts_with($url, z_root())) {
                $instance_headers[] = 'Cookie: PHPSESSID=' . session_id();
            }
        }
        if ($instance_headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $instance_headers);
        }


        if (x($opts, 'nobody')) {
            curl_setopt($ch, CURLOPT_NOBODY, $opts['nobody']);
        }

        if (x($opts, 'custom')) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $opts['custom']);
        }

        if (x($opts, 'timeout') && intval($opts['timeout'])) {
            curl_setopt($ch, CURLOPT_TIMEOUT, intval($opts['timeout']));
        } else {
            $curl_time = intval(get_config('system', 'curl_timeout', 60));
            curl_setopt($ch, CURLOPT_TIMEOUT, $curl_time);
        }

        if (x($opts, 'connecttimeout') && intval($opts['connecttimeout'])) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, intval($opts['connecttimeout']));
        } else {
            $curl_contime = intval(@get_config('system', 'curl_connecttimeout', 60));
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $curl_contime);
        }


        if (x($opts, 'http_auth')) {
            // "username" . ':' . "password"
            curl_setopt($ch, CURLOPT_USERPWD, $opts['http_auth']);
        }

        if (x($opts, 'cookiejar')) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $opts['cookiejar']);
        }
        if (x($opts, 'cookiefile')) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $opts['cookiefile']);
        }

        if (x($opts, 'cookie')) {
            curl_setopt($ch, CURLOPT_COOKIE, $opts['cookie']);
        }

        $validate_ssl = ((x($opts, 'novalidate') && intval($opts['novalidate'])) ? false : true);
        if ($validate_ssl && self::ssl_exception($url)) {
            $validate_ssl = false;
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $validate_ssl);

        $prx = @get_config('system', 'proxy');
        if (strlen($prx)) {
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
            curl_setopt($ch, CURLOPT_PROXY, $prx);
            $prxusr = @get_config('system', 'proxyuser');
            if (strlen($prxusr)) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $prxusr);
            }
        }

        // don't let curl abort the entire application
        // if it throws any errors.

        $s = curl_exec($ch);

        $base = $s;
        $curl_info = curl_getinfo($ch);
        $http_code = $curl_info['http_code'];
        //logger('fetch_url:' . $http_code . ' data: ' . $s);
        $header = '';

        // For file redirects, set curl to follow location (indicated by $passthru).
        // Then just return success or failure.

        if ($passthru) {
            if ($http_code >= 200 && $http_code < 300) {
                $ret['success'] = true;
            }
            $ret['return_code'] = $http_code;
            curl_close($ch);
            return $ret;
        }


        // Pull out multiple headers, e.g. proxy and continuation headers
        // allow for HTTP/2.x without fixing code

        while (preg_match('/^HTTP\/[1-3][\.0-9]* [1-5][0-9][0-9]/', $base)) {
            $chunk = substr($base, 0, strpos($base, "\r\n\r\n") + 4);
            $header .= $chunk;
            $base = substr($base, strlen($chunk));
        }

        if ($http_code == 301 || $http_code == 302 || $http_code == 303 || $http_code == 307 || $http_code == 308) {
            $matches = [];
            preg_match('/(Location:|URI:)(.*?)\n/i', $header, $matches);
            $newurl = trim(array_pop($matches));
            if (str_starts_with($newurl, '/')) {
                // We received a redirect to a relative path.
                // Find the base component of the original url and re-assemble it with the new location
                $base = @parse_url($url);
                if ($base) {
                    unset($base['path']);
                    unset($base['query']);
                    unset($base['fragment']);
                    $newurl = unparse_url($base) . $newurl;
                }
            }
            if ($newurl) {
                curl_close($ch);
                return self::get($newurl, $opts, ++$redirects);
            }
        }

        $rc = intval($http_code);
        $ret['return_code'] = $rc;
        $ret['success'] = (($rc >= 200 && $rc <= 299) ? true : false);
        if (! $ret['success']) {
            $ret['error'] = curl_error($ch);
            $ret['debug'] = $curl_info;
            logger('error: ' . $url . ': ' . $ret['error'], LOGGER_DEBUG);
            logger('debug: ' . self::format_error($ret, true), LOGGER_DATA);
        }
        $ret['body'] = substr($s, strlen($header));
        $ret['header'] = $header;
        $ret['request_target'] = $opts['request_target'];

        curl_close($ch);
        return($ret);
    }

    /**
     * @brief Does a curl post request.
     *
     * @param string $url
     *    URL to post
     * @param mixed $params
     *   The full data to post in a HTTP "POST" operation. This parameter can
     *   either be passed as a urlencoded string like 'para1=val1&para2=val2&...'
     *   or as an array with the field name as key and field data as value. If value
     *   is an array, the Content-Type header will be set to multipart/form-data.
     * @param array $opts (optional parameters)
     *    'timeout' => int seconds, default system config value or 60 seconds
     *    'http_auth' => username:password
     *    'novalidate' => do not validate SSL certs, default is to validate using our CA list
     *    'filep' => stream resource to write body to. header and body are not returned when using this option.
     *    'custom' => custom request method: e.g. 'PUT', 'DELETE'
     * @param int $redirects = 0
     *    internal use, recursion counter
     *
     * @return array an associative array with:
     *  * \e int \b return_code => HTTP return code or 0 if timeout or failure
     *  * \e boolean \b success => boolean true (if HTTP 2xx result) or false
     *  * \e string \b header => HTTP headers
     *  * \e string \b debug => diagnostics if failure
     *  * \e string \b body => content
     *  * \e string \b debug => from curl_info()
     */

    static public function post($url, $params, $opts = [], $redirects = 0)
    {

        $ret = ['return_code' => 0, 'success' => false, 'header' => '', 'body' => ''];

        $ch = curl_init($url);
        if (($redirects > 8) || (! $ch)) {
            return $ret;
        }

        if (! array_key_exists('request_target', $opts)) {
            $opts['request_target'] = 'post ' . get_request_string($url);
        }


        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_CAINFO, self::get_capath());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $ciphers = get_config('system', 'curl_ssl_ciphers');
        if ($ciphers) {
            curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, $ciphers);
        }

        if (x($opts, 'filep')) {
            curl_setopt($ch, CURLOPT_FILE, $opts['filep']);
            curl_setopt($ch, CURLOPT_HEADER, false);
        }

        if (x($opts, 'useragent')) {
            curl_setopt($ch, CURLOPT_USERAGENT, $opts['useragent']);
        } else {
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible)");
        }


        $instance_headers = ((array_key_exists('headers', $opts) && is_array($opts['headers'])) ? $opts['headers'] : []);

        if (x($opts, 'session')) {
            if (str_starts_with($url, z_root())) {
                $instance_headers[] = 'Cookie: PHPSESSID=' . session_id();
            }
        }
        if ($instance_headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $instance_headers);
        }


        if (x($opts, 'nobody')) {
            curl_setopt($ch, CURLOPT_NOBODY, $opts['nobody']);
        }

        if (x($opts, 'custom')) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $opts['custom']);
            curl_setopt($ch, CURLOPT_POST, 0);
        }

        if (x($opts, 'timeout') && intval($opts['timeout'])) {
            curl_setopt($ch, CURLOPT_TIMEOUT, intval($opts['timeout']));
        } else {
            $curl_time = intval(@get_config('system', 'curl_post_timeout', 90));
            curl_setopt($ch, CURLOPT_TIMEOUT, $curl_time);
        }

        if (x($opts, 'connecttimeout') && intval($opts['connecttimeout'])) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, intval($opts['connecttimeout']));
        } else {
            $curl_contime = intval(@get_config('system', 'curl_post_connecttimeout', 90));
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $curl_contime);
        }

        if (x($opts, 'http_auth')) {
            // "username" . ':' . "password"
            curl_setopt($ch, CURLOPT_USERPWD, $opts['http_auth']);
        }

        if (x($opts, 'cookiejar')) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $opts['cookiejar']);
        }
        if (x($opts, 'cookiefile')) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $opts['cookiefile']);
        }
        if (x($opts, 'cookie')) {
            curl_setopt($ch, CURLOPT_COOKIE, $opts['cookie']);
        }

        $validate_ssl = ((x($opts, 'novalidate') && intval($opts['novalidate'])) ? false : true);
        if ($validate_ssl && self::ssl_exception($url)) {
            $validate_ssl = false;
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $validate_ssl);

        $prx = get_config('system', 'proxy');
        if (strlen($prx)) {
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
            curl_setopt($ch, CURLOPT_PROXY, $prx);
            $prxusr = get_config('system', 'proxyuser');
            if (strlen($prxusr)) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $prxusr);
            }
        }

        // don't let curl abort the entire application
        // if it throws any errors.

        $s = curl_exec($ch);

        $base = $s;
        $curl_info = curl_getinfo($ch);
        $http_code = $curl_info['http_code'];

        $header = '';

        // Pull out multiple headers, e.g. proxy and continuation headers
        // allow for HTTP/2.x without fixing code

        while (preg_match('/^HTTP\/[1-3][\.0-9]* [1-5][0-9][0-9]/', $base)) {
            $chunk = substr($base, 0, strpos($base, "\r\n\r\n") + 4);
            $header .= $chunk;
            $base = substr($base, strlen($chunk));
        }

        // would somebody take lighttpd and just shoot it?

        if ($http_code == 417) {
            curl_close($ch);
            if ($opts) {
                if ($opts['headers']) {
                    $opts['headers'][] = 'Expect:';
                } else {
                    $opts['headers'] = array('Expect:');
                }
            } else {
                $opts = array('headers' => array('Expect:'));
            }
            return self::post($url, $params, $opts, ++$redirects);
        }

        if ($http_code == 301 || $http_code == 302 || $http_code == 303 || $http_code == 307 || $http_code == 308) {
            $matches = [];
            preg_match('/(Location:|URI:)(.*?)\n/', $header, $matches);
            $newurl = trim(array_pop($matches));
            if (str_starts_with($newurl, '/')) {
                // We received a redirect to a relative path.
                // Find the base component of the original url and re-assemble it with the new location
                $base = @parse_url($url);
                if ($base) {
                    unset($base['path']);
                    unset($base['query']);
                    unset($base['fragment']);
                    $newurl = unparse_url($base) . $newurl;
                }
            }
            if ($newurl) {
                curl_close($ch);
                if ($http_code == 303) {
                    return self::get($newurl, $opts, ++$redirects);
                } else {
                    return self::post($newurl, $params, $opts, ++$redirects);
                }
            }
        }
        $rc = intval($http_code);
        $ret['return_code'] = $rc;
        $ret['success'] = (($rc >= 200 && $rc <= 299) ? true : false);
        if (! $ret['success']) {
            $ret['error'] = curl_error($ch);
            $ret['debug'] = $curl_info;
            logger('error: ' . $url . ': ' . $ret['error'], LOGGER_DEBUG);
            logger('debug: ' . self::format_error($ret,true), LOGGER_DATA);
        }

        $ret['body'] = substr($s, strlen($header));
        $ret['header'] = $header;
        $ret['request_target'] = $opts['request_target'];

        curl_close($ch);
        return($ret);
    }

    static public function ssl_exception($domain) {
        $excepts = Config::Get('system', 'ssl_exceptions', []);
        if (!$excepts) {
            return false;
        }
        $excepts = Activity::force_array($excepts);
        foreach($excepts as $except) {
            if (stristr($domain, $except) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @brief Returns path to CA file.
     *
     * @return string
     */
    static public function get_capath()
    {
        return PROJECT_BASE . '/library/cacert.pem';
    }

    static public function format_error($ret, $verbose = false)
    {
        $output = EMPTY_STR;
        if (isset($ret['debug'])) {
            $output .= datetime_convert() . EOL;
            $output .= t('url: ') . $ret['debug']['url'] . EOL;
            $output .= t('http_code: ') . ((isset($ret['debug']['http_code'])) ? $ret['debug']['http_code'] : 0) . EOL;
            $output .= t('error_code: ') . $ret['debug']['error_code'] . EOL;
            $output .= t('error_string: ') . $ret['error'] . EOL;
            $output .= t('content-type: ') . $ret['debug']['content_type'] . EOL;
            if ($verbose) {
              $output .= t('request-header: ') . $ret['debug']['request_header'] . EOL;
            }
        }
        return $output;
    }

}
