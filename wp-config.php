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
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'serbaserbu' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         'pdG[JDSb<4F@ANSvaJXqZhRPOh4NznoujSCm~F$qeu!KU]E8LOBSC3Jun}&?vAcW' );
define( 'SECURE_AUTH_KEY',  '{`b!xep6bob&qR=Qi+ct^mAwBc=HlfgNxQPgqQ:gf6<fl9J(w~%kOIUg[7AtjC?R' );
define( 'LOGGED_IN_KEY',    'WC6pYWe.Vnf/mpT`#s|=uY~<*%hat*@5kt/1KU6, +@NR>W&[Qcu5}j=jI2aZUcb' );
define( 'NONCE_KEY',        '.>|#Y5kmN:lj~t>Ma6$`0MI2r%v9Zd|Do$)up$[9YO7x^*B4/)KyGR%T!5-MZoFU' );
define( 'AUTH_SALT',        '|]4DYc@@xbTJ]HC4i`f>cKKbUw~,,8dcKVBysT-ypod)G):;)[Afp22#vvEq2y+s' );
define( 'SECURE_AUTH_SALT', 'xilPe0]Y}xvs_^D8_c/f<<G.dEn]7L8dh5OId_(@[K8VEd!#%+YJk0 wx5h[ia=@' );
define( 'LOGGED_IN_SALT',   'Enhq_7u0@uz/pVCmx1.+}]]HL^r(G|L8l6Zjb(#^B_Bsg9Br$uPG9P`y&|hMV[ ]' );
define( 'NONCE_SALT',       'rlepSv2MM.D/cFAHKxN0.T]<S:]#:57%4X?/gl@Y9|-CP2sqe^;vfLw~JH^J@f4*' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

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
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
