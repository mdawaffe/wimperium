<?php

/*
 * Require PHP 5.3
 */

class MDAWaffe_Plugin_1 {
	protected static $instances = array();
	protected $_class;

	static function instance() {
		$class = get_called_class();
		
		if ( ! isset( self::$instances[$class] ) ) {
			self::$instances[$class] = new $class;
		}

		return self::$instances[$class];
	}

	protected function __construct() {
		$this->_class = get_called_class();

		$this->init_actions();
		$this->init_filters();
	}


	function init_actions() {}
	function init_filters() {}

/* Hooks */
	/*
	 * Wrapper for add_action and friends
	 * $this->add_action( $filter, [ $method = $filter ], [ $priority = 10 ] );
	 * 	$this->add_action( 'init' )
	 * 	$this->add_action( 'init', 1 )
	 * 	$this->add_action( 'init', 'do_something_on_init' )
	 * 	$this->add_action( 'init', 'do_something_on_init_early', 1 )
	 */
	private function binder( $function, $filter, $method = null, $priority = 10 ) {
		if ( is_int( $method ) ) {
			$priority = $method;
			$method   = null;
		}

		if ( null === $method ) {
			$method = $filter;
		}

		// Autodetect $accepted_args!
		try {
			$reflection = new ReflectionMethod( $this->_class, $method );
		} catch ( ReflectionException $e ) {
			trigger_error( sprintf( 'Undefined method %s::%s', $this->_class, $method ), E_USER_WARNING );
			return false;
		}

		$accepted_args = $reflection->getNumberOfParameters();

		return call_user_func( $function, $filter, array( $this, $method ), $priority, $accepted_args );
	}

	function __call( $name, $arguments ) {
		switch ( $name ) {
		case 'add_filter' :
		case 'remove_filter' :
		case 'add_action' :
		case 'remove_action' :
			array_unshift( $arguments, $name );
			return call_user_func_array( array( $this, 'binder' ), $arguments );
		}

		trigger_error( sprintf( 'Undefined method %s::%s', $this->_class, $name ), E_USER_ERROR );
	}

/* Optons */
	function __get( $option ) {
		$options = get_option( $this->_class );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( ! isset( $options[$option] ) ) {
			trigger_error( sprintf( 'Undefined property via %s::__get() %s', $this->_class, $option ), E_USER_NOTICE );
			return null;
		}

		return $options[$option];
	}

	function __set( $option, $value ) {
		$options = get_option( $this->_class );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$options[$option] = $value;

		return update_option( $this->_class, $options );
	}

	function __isset( $option ) {
		$options = get_option( $this->_class );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return isset( $options[$option] );
	}

	function __unset( $option ) {
		$options = get_option( $this->_class );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		unset( $options[$option] );

		return update_option( $this->_class, $options );
	}
}
