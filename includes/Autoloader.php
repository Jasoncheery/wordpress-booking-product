<?php
namespace WP_Bookable_Products;

/**
 * Simple PSR-4 autoloader for the plugin namespace.
 */
class Autoloader {

	private string $namespace;
	private string $base_path;

	public function __construct( string $namespace, string $base_path ) {
		$this->namespace = rtrim( $namespace, '\\' );
		$this->base_path = rtrim( $base_path, '/\\' ) . '/';
		spl_autoload_register( [ $this, 'load_class' ] );
	}

	public function load_class( string $class ): void {
		$prefix_length = strlen( $this->namespace ) + 1;
		if ( 0 !== strncmp( $this->namespace . '\\', $class, $prefix_length - 1 ) ) {
			return;
		}

		$relative_class = substr( $class, $prefix_length );
		$file           = $this->base_path . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
