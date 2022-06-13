<?php
/**
 * Configuración básica de WordPress.
 *
 * Este archivo contiene las siguientes configuraciones: ajustes de MySQL, prefijo de tablas,
 * claves secretas, idioma de WordPress y ABSPATH. Para obtener más información,
 * visita la página del Codex{@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} . Los ajustes de MySQL te los proporcionará tu proveedor de alojamiento web.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// ** Ajustes de MySQL. Solicita estos datos a tu proveedor de alojamiento web. ** //
/** El nombre de tu base de datos de WordPress */
define( 'DB_NAME', 'electrodomestico' );

/** Tu nombre de usuario de MySQL */
define( 'DB_USER', 'root' );

/** Tu contraseña de MySQL */
define( 'DB_PASSWORD', '' );

/** Host de MySQL (es muy probable que no necesites cambiarlo) */
define( 'DB_HOST', 'localhost' );

/** Codificación de caracteres para la base de datos. */
define( 'DB_CHARSET', 'utf8mb4' );

/** Cotejamiento de la base de datos. No lo modifiques si tienes dudas. */
define('DB_COLLATE', '');

/**#@+
 * Claves únicas de autentificación.
 *
 * Define cada clave secreta con una frase aleatoria distinta.
 * Puedes generarlas usando el {@link https://api.wordpress.org/secret-key/1.1/salt/ servicio de claves secretas de WordPress}
 * Puedes cambiar las claves en cualquier momento para invalidar todas las cookies existentes. Esto forzará a todos los usuarios a volver a hacer login.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY', 'C(Uj#qkFez#V_&4?*s$q8l{x{feIUK~z>^>#Q),=DgzRB8H}U=)YmX^[50coa xo' );
define( 'SECURE_AUTH_KEY', '+Vv6CUe*8PEE3d),i2pbQX;{Ec2Cs>{d[cj9I3FP<3)ui}xCjgf%t8TM{7V0<?@>' );
define( 'LOGGED_IN_KEY', 'IQ}V1o8&AjD/MJ`.3l#4uu9FECli>.S !pK=Hi=m^XQ),Bo`$< ct+SBS@J,[<>x' );
define( 'NONCE_KEY', 'h1!/k:]+&$e&;cqpg=?: <kC%H%7K1JgRlst/}ILH6fU3a_!GV/8RT$68QnX!fj,' );
define( 'AUTH_SALT', 'Ps`Y-%ITu8m9qL2dXT~Nk2)IZM8]dYX)7{~jY!UE~8k |QL{q6cZ:N~ROg1zNIeA' );
define( 'SECURE_AUTH_SALT', 'WZ {&3-<0TsF-]{AoZF$V!GrPxU}+,8?JH=3UJYLQKw(pNVwnVp#88g%[]6yru,I' );
define( 'LOGGED_IN_SALT', 'I%L-A)9 L$+$yI*(1)#?.^|nim5tOh1GsF.}V2oQrq7xOc^Cj9X:H%EL=iby-cmD' );
define( 'NONCE_SALT', '&|xff=%5v`7PU`rH cdJkWnr;h1w3vIJv0JDNf4.so*}f YYF=WIvxfX3Oyozg0T' );

/**#@-*/

/**
 * Prefijo de la base de datos de WordPress.
 *
 * Cambia el prefijo si deseas instalar multiples blogs en una sola base de datos.
 * Emplea solo números, letras y guión bajo.
 */
$table_prefix = 'wp_';


/**
 * Para desarrolladores: modo debug de WordPress.
 *
 * Cambia esto a true para activar la muestra de avisos durante el desarrollo.
 * Se recomienda encarecidamente a los desarrolladores de temas y plugins que usen WP_DEBUG
 * en sus entornos de desarrollo.
 */
define('WP_DEBUG', false);

/* ¡Eso es todo, deja de editar! Feliz blogging */

/** WordPress absolute path to the Wordpress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');

