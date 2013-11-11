<?php
//define('DB_NAME', '775497_unistrutfallprotection');
//define('DB_USER', '775497_unifall');
//define('DB_PASSWORD', '3Bzwg3jfNeDRBM');
//define('DB_HOST', 'mysql51-021.wc1.ord1.stabletransit.com');
define( 'DB_NAME',     '775497_unistrutfallprotection' );
define( 'DB_USER',     'root' );
define( 'DB_PASSWORD', 'root' );
//define( 'DB_HOST',     'localhost' );
define( 'DB_HOST', ':/Applications/MAMP/tmp/mysql/mysql.sock' );
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
$table_prefix = 'unistrutfallprotection_';
define('AUTH_KEY',         '^N!MtT0{Ezd7dD)<:%cE^Fi0hZ2cbq!{k/j[ tws@d3M>qaVs1FAYO-e3kq<`^@m');
define('SECURE_AUTH_KEY',  'Wezk6A4=?e9#G<Q=:qf2bZ4&=1~L-p{HdGRqx69htRk,.T}l:un]|aDaurYH-^L6');
define('LOGGED_IN_KEY',    '%-F91Q~hI?-XWL I@K/_iB7DB+l+ie0;rY-{JR0@}$]F|W,C=t@Gd7un=rZ<ZCx|');
define('NONCE_KEY',        'o%jo2Auz~J#hK|WX(0dNh.CX.*5y+z;ydN%nTO5E6H`FQeQ?S60^{>QCSHfcWM[8');
define('AUTH_SALT',        '1kirv`u-<k|{rs]W>Dg]g*r,%(GC4a3sDX;etCgjD!+SGG5 -4 }{-O^i-M(CYM?');
define('SECURE_AUTH_SALT', 'ZW)?)(|v6d|h+c_%m]@n##7|(:C9^$UGk}UPkwIrEB4Ev^FD6dUElrGkW57?I+HT');
define('LOGGED_IN_SALT',   'w_kA^vZNm NyQ45@z+E__.Zh`YjXHQ1P/|],XYwcsD*X^-q$!*[-^c8{(Qp9!,2E');
define('NONCE_SALT',       'I>+ZzHnWx+-inCWSl91Am?]29!$JjyaWd7DC([Mb$~s#P,^xjV!.on~8su[XcQs8');
define('WPLANG', 'en_US');
define('WP_HOME', 'http://' . $_SERVER['SERVER_NAME']);
define('WP_SITEURL', 'http://' . $_SERVER['SERVER_NAME']);
define('WP_CONTENT_URL', '/wp-content');
define('UPLOADS',        '/media' );
define('WP_PLUGIN_URL',  '/wp-content/plugins' );
define('DOMAIN_CURRENT_SITE', $_SERVER['SERVER_NAME']);
define('WP_POST_REVISIONS', false );
define('MEDIA_TRASH', false );
define('EMPTY_TRASH_DAYS', '3' );
define('DISALLOW_FILE_EDIT', true );
define('WP_DEBUG', false );
if ( WP_DEBUG ) {
define('WP_DEBUG_LOG', false );
define('WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );
}
define('WP_MEMORY_LIMIT', '256M' );
define('WP_MAX_MEMORY_LIMIT', '256M' );
define('COMPRESS_CSS',        true );
define('COMPRESS_SCRIPTS',    true );
define('CONCATENATE_SCRIPTS', true );
define('ENFORCE_GZIP',        true );
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');
require_once(ABSPATH . 'wp-settings.php');