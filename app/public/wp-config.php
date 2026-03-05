<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'Dy3x}|$(YxM5MA1T/os}m3gD~sEYOz$o/MW>-B`S!.Vq(,,0_zif!y1,gZO_D9}7' );
define( 'SECURE_AUTH_KEY',   'xlhv{ 7397.xN%+]_-GX67NYVT kw!@+v[._:|4{gyT^/fGZcuq.~XZbq:.zq25D' );
define( 'LOGGED_IN_KEY',     'GRkP2tQ8)weVvh-d(WVD$!P`,o,jN;5y{I(oQ!-VS7]8h;0jEVQZB6 j8JOxuY]v' );
define( 'NONCE_KEY',         'm+P-4Z.{P%7 UD}jg;/bEV<M>?pzgrIhxbBxh0f2IyhP&>eWH]XI,F(LC1.P>R:s' );
define( 'AUTH_SALT',         'sTX,&ttb1;k&/SOx>S-b}a5o,Kj5T!OSht?sn/<OSh~GIG=-r>F$3B.5-*3hF+p3' );
define( 'SECURE_AUTH_SALT',  'VzuTX`k#3X<J%i! YcKWrg]/21S&6I--(n[%/>4mkL0q4HZob+d78NBoJmPvMs27' );
define( 'LOGGED_IN_SALT',    'B=n6{jfYDr{Vn~$>p<*rAj@so&b>LUOqj_fs[E=+,2JShLCqcW^!mdP8eoFfAFmJ' );
define( 'NONCE_SALT',        '%nmALry{iw&`HTVn.2x/OW`nCI7_hM 0a.  4JJ*mZ K)F&q%yOW;A>9oEd+2#N>' );
define( 'WP_CACHE_KEY_SALT', 'cr3XSxh]xFH(`tWi|Z#s?W&/.3O4xw|>2qPSXp,/|fjrW?NL~yUtDnq^:h_oA(9P' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */

define( 'OPENAI_API_KEY', 'sk-xxxxxxxxxxxxxxxx' );



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
