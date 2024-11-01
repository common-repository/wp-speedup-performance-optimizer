<?php

global $ibinc_cache_stop;

$ibinc_cache_stop = false;

// If no-cache header support is enabled and the browser explicitly requests a fresh page, do not cache
if ($ibinc_cache_nocache &&
    ((!empty($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] == 'no-cache') ||
     (!empty($_SERVER['HTTP_PRAGMA']) && $_SERVER['HTTP_PRAGMA'] == 'no-cache'))) return ibinc_cache_exit();

// Do not cache post request (comments, plugins and so on)
if ($_SERVER["REQUEST_METHOD"] == 'POST') return ibinc_cache_exit();

// Try to avoid enabling the cache if sessions are managed with request parameters and a session is active
if (defined('SID') && SID != '') return ibinc_cache_exit();

$ibinc_uri = $_SERVER['REQUEST_URI'];
$ibinc_qs = strpos($ibinc_uri, '?');

if ($ibinc_qs !== false) {
    if ($ibinc_cache_strip_qs) $ibinc_uri = substr($ibinc_uri, 0, $ibinc_qs);
    else if (!$ibinc_cache_cache_qs) return ibinc_cache_exit();
}

if (strpos($ibinc_uri, 'robots.txt') !== false) return ibinc_cache_exit();

// Checks for rejected url
if ($ibinc_cache_reject !== false) {
    foreach($ibinc_cache_reject as $uri) {
        if (substr($uri, 0, 1) == '"') {
            if ($uri == '"' . $ibinc_uri . '"') return ibinc_cache_exit();
        }
        if (substr($ibinc_uri, 0, strlen($uri)) == $uri) return ibinc_cache_exit();
    }
}

if ($ibinc_cache_reject_agents !== false) {
    $ibinc_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    foreach ($ibinc_cache_reject_agents as $ibinc_a) {
        if (strpos($ibinc_agent, $ibinc_a) !== false) return ibinc_cache_exit();
    }
}

// Do nested cycles in this order, usually no cookies are specified
if ($ibinc_cache_reject_cookies !== false) {
    foreach ($ibinc_cache_reject_cookies as $ibinc_c) {
        foreach ($_COOKIE as $n=>$v) {
            if (substr($n, 0, strlen($ibinc_c)) == $ibinc_c) return ibinc_cache_exit();
        }
    }
}

// Do not use or cache pages when a wordpress user is logged on

foreach ($_COOKIE as $n=>$v) {
// If it's required to bypass the cache when the visitor is a commenter, stop.
    if ($ibinc_cache_comment && substr($n, 0, 15) == 'comment_author_') return ibinc_cache_exit();

    // SHIT!!! This test cookie makes to cache not work!!!
    if ($n == 'wordpress_test_cookie') continue;
    // wp 2.5 and wp 2.3 have different cookie prefix, skip cache if a post password cookie is present, also
    if (substr($n, 0, 14) == 'wordpressuser_' || substr($n, 0, 10) == 'wordpress_' || substr($n, 0, 12) == 'wp-postpass_') {
        return ibinc_cache_exit();
    }
}

// Do not cache WP pages, even if those calls typically don't go throught this script
if (strpos($ibinc_uri, '/wp-') !== false) return ibinc_cache_exit();

// Multisite
if (function_exists('is_multisite') && is_multisite() && strpos($ibinc_uri, '/files/') !== false) return ibinc_cache_exit();

// Prefix host, and for wordpress 'pretty URLs' strip trailing slash (e.g. '/my-post/' -> 'my-site.com/my-post')
$ibinc_uri = rtrim($_SERVER['HTTP_HOST'] . $ibinc_uri, '/');

// The name of the file with html and other data
$ibinc_cache_name = md5($ibinc_uri);
$hc_file = $ibinc_cache_path . $ibinc_cache_name . ibinc_mobile_type() . '.dat';

if (!file_exists($hc_file)) {
    ibinc_cache_start(false);
    return;
}

$hc_file_time = @filemtime($hc_file);
$hc_file_age = time() - $hc_file_time;

if ($hc_file_age > $ibinc_cache_timeout) {
    ibinc_cache_start();
    return;
}

$hc_invalidation_time = @filemtime($ibinc_cache_path . '_global.dat');
if ($hc_invalidation_time && $hc_file_time < $hc_invalidation_time) {
    ibinc_cache_start();
    return;
}

if (array_key_exists("HTTP_IF_MODIFIED_SINCE", $_SERVER)) {
    $if_modified_since = strtotime(preg_replace('/;.*$/', '', $_SERVER["HTTP_IF_MODIFIED_SINCE"]));
    if ($if_modified_since >= $hc_file_time) {
        header($_SERVER['SERVER_PROTOCOL'] . " 304 Not Modified");
        flush();
        die();
    }
}

// Load it and check is it's still valid
$ibinc_cache_data = @unserialize(file_get_contents($hc_file));

if (!$ibinc_cache_data) {
    ibinc_cache_start();
    return;
}

if ($ibinc_cache_data['type'] == 'home' || $ibinc_cache_data['type'] == 'archive') {

    $hc_invalidation_archive_file =  @filemtime($ibinc_cache_path . '_archives.dat');
    if ($hc_invalidation_archive_file && $hc_file_time < $hc_invalidation_archive_file) {
        ibinc_cache_start();
        return;
    }
}

// Valid cache file check ends here

if ($ibinc_cache_data['location']) {
    header('Location: ' . $ibinc_cache_data['location']);
    flush();
    die();
}

// It's time to serve the cached page

if (!$ibinc_cache_browsercache) {
    // True if browser caching NOT enabled (default)
    header('Cache-Control: no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
}
else {
    $maxage = $ibinc_cache_timeout - $hc_file_age;
    header('Cache-Control: max-age=' . $maxage);
    header('Expires: ' . gmdate("D, d M Y H:i:s", time() + $maxage) . " GMT");
}

// True if user ask to NOT send Last-Modified
if (!$ibinc_cache_lastmodified) {
    header('Last-Modified: ' . gmdate("D, d M Y H:i:s", $hc_file_time). " GMT");
}

header('Content-Type: ' . $ibinc_cache_data['mime']);
if ($ibinc_cache_data['status'] == 404) header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found");

// Send the cached html
if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false &&
    (($ibinc_cache_gzip && !empty($ibinc_cache_data['gz'])) || ($ibinc_cache_gzip_on_the_fly && function_exists('gzencode')))) {
    header('Content-Encoding: gzip');
    header('Vary: Accept-Encoding');
    if (!empty($ibinc_cache_data['gz'])) {
        echo $ibinc_cache_data['gz'];
    }
    else {
        echo gzencode($ibinc_cache_data['html']);
    }
}
else {
// No compression accepted, check if we have the plain html or
// decompress the compressed one.
    if ($ibinc_cache_data['html']) {
    //header('Content-Length: ' . strlen($ibinc_cache_data['html']));
        echo $ibinc_cache_data['html'];
    }
    else if (function_exists('gzinflate')) {
        $buffer = ibinc_cache_gzdecode($ibinc_cache_data['gz']);
        if ($buffer === false) echo 'Error retrieving the content';
        else echo $buffer;
    }
    else {
        // Cannot decode compressed data, serve fresh page
        return false;
    }
}
flush();
die();


function ibinc_cache_start($delete=true) {
    global $hc_file;

    if ($delete) @unlink($hc_file);
    foreach ($_COOKIE as $n=>$v ) {
        if (substr($n, 0, 14) == 'comment_author') {
            unset($_COOKIE[$n]);
        }
    }
    ob_start('ibinc_cache_callback');
}

// From here Wordpress starts to process the request

// Called whenever the page generation is ended
function ibinc_cache_callback($buffer) {
    global $ibinc_cache_notfound, $ibinc_cache_stop, $ibinc_cache_charset, $ibinc_cache_home, $ibinc_cache_redirects, $ibinc_redirect, $hc_file, $ibinc_cache_name, $ibinc_cache_browsercache, $ibinc_cache_timeout, $ibinc_cache_lastmodified, $ibinc_cache_gzip, $ibinc_cache_gzip_on_the_fly;

    if (!function_exists('is_home')) return $buffer;
    
    if (function_exists('apply_filters')) $buffer = apply_filters('ibinc_cache_buffer', $buffer);

    if ($ibinc_cache_stop) return $buffer;

    if (!$ibinc_cache_notfound && is_404()) {
        return $buffer;
    }

    if (strpos($buffer, '</body>') === false) return $buffer;

    // WP is sending a redirect
    if ($ibinc_redirect) {
        if ($ibinc_cache_redirects) {
            $data['location'] = $ibinc_redirect;
            ibinc_cache_write($data);
        }
        return $buffer;
    }

    if (is_home() && $ibinc_cache_home) {
        return $buffer;
    }

    if (is_feed() && !$ibinc_cache_feed) {
        return $buffer;
    }

    if (is_home()) $data['type'] = 'home';
    else if (is_feed()) $data['type'] = 'feed';
        else if (is_archive()) $data['type'] = 'archive';
            else if (is_single()) $data['type'] = 'single';
                else if (is_page()) $data['type'] = 'page';
    $buffer = trim($buffer);

    // Can be a trackback or other things without a body. We do not cache them, WP needs to get those calls.
    if (strlen($buffer) == 0) return '';

    if (!$ibinc_cache_charset) $ibinc_cache_charset = 'UTF-8';

    if (is_feed()) {
        $data['mime'] = 'text/xml;charset=' . $ibinc_cache_charset;
    }
    else {
        $data['mime'] = 'text/html;charset=' . $ibinc_cache_charset;
    }

    $buffer .= '<!-- ibinc cache: ' . $ibinc_cache_name . ' ' . date('y-m-d h:i:s') .' -->';

    $data['html'] = $buffer;

    if (is_404()) $data['status'] = 404;

    ibinc_cache_write($data);

    if ($ibinc_cache_browsercache) {
        header('Cache-Control: max-age=' . $ibinc_cache_timeout);
        header('Expires: ' . gmdate("D, d M Y H:i:s", time() + $ibinc_cache_timeout) . " GMT");
    }

    // True if user ask to NOT send Last-Modified
    if (!$ibinc_cache_lastmodified) {
        header('Last-Modified: ' . gmdate("D, d M Y H:i:s", @filemtime($hc_file)). " GMT");
    }
    
    if (($ibinc_cache_gzip && !empty($data['gz'])) || ($ibinc_cache_gzip_on_the_fly && !empty($data['html']) && function_exists('gzencode'))) {
        header('Content-Encoding: gzip');
        header('Vary: Accept-Encoding');
        if (empty($data['gz'])) {
            $data['gz'] = gzencode($data['html']);
        }
        return $data['gz'];
    }

    return $buffer;
}

function ibinc_cache_write(&$data) {
    global $hc_file, $ibinc_cache_store_compressed;

    $data['uri'] = $_SERVER['REQUEST_URI'];

    // Look if we need the compressed version
    if ($ibinc_cache_store_compressed && !empty($data['html']) && function_exists('gzencode')) {
        $data['gz'] = gzencode($data['html']);
        if ($data['gz']) unset($data['html']);
    }
    $file = fopen($hc_file, 'w');
    fwrite($file, serialize($data));
    fclose($file);
}

function ibinc_mobile_type() {
    global $ibinc_cache_mobile, $ibinc_cache_mobile_agents, $ibinc_cache_plugin_mobile_pack;

    if ($ibinc_cache_plugin_mobile_pack) {
        @include_once ABSPATH . 'wp-content/plugins/wordpress-mobile-pack/plugins/wpmp_switcher/lite_detection.php';
        if (function_exists('lite_detection')) {
            $is_mobile = lite_detection();
            if (!$is_mobile) return '';
            include_once ABSPATH . 'wp-content/plugins/wordpress-mobile-pack/themes/mobile_pack_base/group_detection.php';
            if (function_exists('group_detection')) {
                return 'mobile' . group_detection();
            }
            else return 'mobile';
        }
    }

    if (!isset($ibinc_cache_mobile) || $ibinc_cache_mobile_agents === false) return '';

    $ibinc_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    foreach ($ibinc_cache_mobile_agents as $ibinc_a) {
        if (strpos($ibinc_agent, $ibinc_a) !== false) {
            if (strpos($ibinc_agent, 'iphone') || strpos($ibinc_agent, 'ipod')) {
                return 'iphone';
            }
            else {
                return 'pda';
            }
        }
    }
    return '';
}

function ibinc_cache_gzdecode ($data) {

    $flags = ord(substr($data, 3, 1));
    $headerlen = 10;
    $extralen = 0;

    $filenamelen = 0;
    if ($flags & 4) {
        $extralen = unpack('v' ,substr($data, 10, 2));

        $extralen = $extralen[1];
        $headerlen += 2 + $extralen;
    }
    if ($flags & 8) // Filename

        $headerlen = strpos($data, chr(0), $headerlen) + 1;
    if ($flags & 16) // Comment

        $headerlen = strpos($data, chr(0), $headerlen) + 1;
    if ($flags & 2) // CRC at end of file

        $headerlen += 2;
    $unpacked = gzinflate(substr($data, $headerlen));
    return $unpacked;
}

function ibinc_cache_exit() {
    global $ibinc_cache_gzip_on_the_fly;

    if ($ibinc_cache_gzip_on_the_fly && extension_loaded('zlib')) ob_start('ob_gzhandler');
    return false;
}
