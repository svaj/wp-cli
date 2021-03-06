<?php

if ( PHP_SAPI !== 'cli' ) {
	die( 'Only cli access' );
}

define( 'WP_CLI_VERSION', '0.4.0' );

// Define the wp-cli location
define( 'WP_CLI_ROOT', __DIR__ . '/' );

// Set a constant that can be used to check if we are running wp-cli or not
define( 'WP_CLI', true );

// Include the wp-cli classes
include WP_CLI_ROOT . 'class-wp-cli.php';
include WP_CLI_ROOT . 'class-wp-cli-command.php';

// Include the command line tools
include WP_CLI_ROOT . '../php-cli-tools/lib/cli/cli.php';

// Register the cli tools autoload
\cli\register_autoload();

// Get the cli arguments
list( $arguments, $assoc_args ) = WP_CLI::parse_args( array_slice( $GLOBALS['argv'], 1 ) );

// Handle --version parameter
if ( isset( $assoc_args['version'] ) ) {
	WP_CLI::line( 'wp-cli ' . WP_CLI_VERSION );
	exit;
}

// Define the WordPress location
if ( is_readable( $_SERVER['PWD'] . '/../wp-load.php' ) ) {
	define('WP_ROOT', $_SERVER['PWD'] . '/../');
}
else {
	define('WP_ROOT', $_SERVER['PWD'] . '/');
}

if ( !is_readable( WP_ROOT . 'wp-load.php' ) ) {
	if ( array( 'core', 'download' ) == $arguments ) {
		WP_CLI::line('Downloading WordPress...');
		exec("curl http://wordpress.org/latest.zip > /tmp/wordpress.zip");
		exec("unzip /tmp/wordpress.zip");
		exec("mv wordpress/* ./");
		exec("rm -r wordpress");
		WP_CLI::success('WordPress downloaded.');
		exit;
	} else {
		WP_CLI::error('This does not seem to be a WordPress install. Try running `wp core download`.');
		exit;
	}
}

if ( array( 'core', 'config' ) == $arguments ) {
	$_POST['dbname'] = $assoc_args['name'];
	$_POST['uname'] = $assoc_args['user'];
	$_POST['pwd'] = $assoc_args['pass'];
	$_POST['dbhost'] = isset( $assoc_args['host'] ) ? $assoc_args['host'] : 'localhost';
	$_POST['prefix'] = isset( $assoc_args['prefix'] ) ? $assoc_args['prefix'] : '';

	$_GET['step'] = 2;
	require WP_ROOT . '/wp-admin/setup-config.php';
	exit;
}

// Handle --blog parameter
if ( isset( $assoc_args['blog'] ) ) {
	$blog = $assoc_args['blog'];
	unset( $assoc_args['blog'] );
	if ( true === $blog ) {
		WP_CLI::line( 'usage: wp --blog=example.com' );
	}
} elseif ( is_readable( WP_ROOT . 'wp-cli-blog' ) ) {
	$blog = trim( file_get_contents( WP_ROOT . 'wp-cli-blog' ) );
}

if ( isset( $blog ) ) {
	WP_CLI::set_url( $blog );
}

// Set installer flag before loading any WP files
if ( count( $arguments ) >= 2 && $arguments[0] == 'core' && $arguments[1] == 'install' ) {
    define( 'WP_INSTALLING', true );
}

// Load WordPress libs
require_once(WP_ROOT . 'wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/admin.php');

// Load all internal commands
foreach ( glob(WP_CLI_ROOT.'/commands/internals/*.php') as $filename ) {
	include $filename;
}

// Load all plugin commands
foreach ( glob(WP_CLI_ROOT.'/commands/community/*.php') as $filename ) {
	include $filename;
}

// Handle --completions parameter
if ( isset( $assoc_args['completions'] ) ) {
	foreach ( WP_CLI::$commands as $name => $command ) {
		WP_CLI::line( $name .  ' ' . implode( ' ', WP_CLI_Command::get_subcommands($command) ) );
	}
	exit;
}

// Get the top-level command
if ( empty( $arguments ) )
	$command = 'help';
else
	$command = array_shift( $arguments );

// Translate aliases
$aliases = array(
	'sql' => 'db'
);

if ( isset( $aliases[ $command ] ) )
	$command = $aliases[ $command ];

if ( !isset( WP_CLI::$commands[$command] ) ) {
	WP_CLI::error( "'$command' is not a registered wp command. See 'wp help'." );
	exit;
}

new WP_CLI::$commands[$command]( $arguments, $assoc_args );

