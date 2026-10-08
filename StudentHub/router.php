<?php
/**
 * Router for PHP's built-in server:  php -S localhost:8000 router.php
 * Blocks direct access to the SQL scripts and PHP helper files
 * (Apache uses the .htaccess files in those folders),
 * everything else is served as normal.
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if (preg_match('#^/(database|php/(lib|config|db|auth|guard)\.php)(/|$)#i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

return false;
