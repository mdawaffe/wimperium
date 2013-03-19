<?php

class Wimperium_Rest {
	var $method = 'GET';
	var $path   = '/';
	var $query  = array();
	var $body   = null;

	var $content_type;

	var $endpoint = null;
	var $response = null;

	var $endpoints = array();

	function __construct() {
		$this->method = strtoupper( $_SERVER['REQUEST_METHOD'] );
		if ( 'POST' === $this->method && isset( $_GET['method'] ) ) {
			$this->method = strtoupper( $_GET['method'] );
		}

		$this->path = stripslashes( $_GET['path'] );

		$this->query = array_diff_key(
			stripslashes_deep( $_GET ),
			array(
				'query'    => false,
				'path'     => false,
				'method'   => false,
				'action'   => false,
				'_wpnonce' => false,
				'_'        => false
			)
		);

		switch ( $this->method ) {
		case 'POST' :
		case 'PUT' :
			if ( isset( $_SERVER['HTTP_CONTENT_TYPE'] ) && $_SERVER['HTTP_CONTENT_TYPE'] ) {
				$this->content_type = $_SERVER['HTTP_CONTENT_TYPE'];
			} elseif ( isset( $_SERVER['CONTENT_TYPE'] ) && $_SERVER['CONTENT_TYPE'] ) {
				$this->content_type = $_SERVER['CONTENT_TYPE'] ;
			} elseif ( '{' === $this->post_body[0] ) {
				$this->content_type = 'application/json';
			} else {
				$this->content_type = 'application/x-www-form-urlencoded';
			}

			// @todo multipart and $_FILES

			switch ( $this->content_type ) {
			case 'application/json' :
			case 'application/x-javascript' :
			case 'text/javascript' :
			case 'text/x-javascript' :
			case 'text/x-json' :
			case 'text/json' :
				$this->body = json_decode( file_get_contents( 'php://input' ) );
				break;
			case 'multipart/form-data' :
				// @todo
				break;
			default :
				$this->body = stripslashes_deep( $_POST );
			}
		}
	}

	function process() {
		$endpoints = Wimperium_Rest_Endpoints::filter( $this->path );
		if ( ! $endpoints ) {
			$this->response = new WP_Error( 'not_found', 'Not Found', array( 'code' => 404 ) );
			return;
		} elseif ( ! isset( $endpoints[$this->method] ) ) {
			$this->response = new WP_Error( 'not_implemented', 'Not Implemented', array( 'code' => 405 ) );
			return;
		}

		$endpoint = $endpoints[$this->method];

		$this->endpoint = new $endpoint;

		$this->response = $this->endpoint->process( $this->path, $this->query, $this->body );
	}

	function status() {
		if ( is_wp_error( $this->response ) ) {
			$data = $this->response->get_error_data();
			$status = isset( $data['code'] ) ? $data['code'] : 500;
		} else {
			$status = 200;
		}

		status_header( $status );
		return $status;
	}

	function json() {
		if ( is_wp_error( $this->response ) ) {
			$json = (object) array(
				'error' => $this->response->get_error_code(),
				'error_message' => $this->response->get_error_message(),
			);

			return json_encode( $json );
		}

		
		return json_encode( (object) $this->response );
	}
}

class Wimperium_Rest_Endpoints {
	static $endpoints = array();

	static function filter( $path, $method = null ) {
		foreach ( self::$endpoints as $endpoint_path => $endpoints_by_path ) {
			if ( preg_match( "#^$endpoint_path\$#", $path ) ) {
				if ( null === $method ) {
					return $endpoints_by_path;
				} elseif ( in_array( $method, array_keys( $endpoints_by_path ) ) ) {
					return array( $method => $endpoints_by_path[$method] );
				}
			}
		}

		return array();
	}
}

abstract class Wimperium_Rest_Endpoint {
	static $method = 'GET';
	static $path = '/';

	abstract function process( $path, array $query, $body = null );

	static function register() {
		if ( ! isset( Wimperium_Rest_Endpoints::$endpoints[ static::$path ] ) ) {
			Wimperium_Rest_Endpoints::$endpoints[ static::$path ] = array();
		}

		Wimperium_Rest_Endpoints::$endpoints[ static::$path ][ static::$method ] = get_called_class();
	}

	function parse_path( $path ) {
		preg_match( '#^' . static::$path . '$#', $path, $matches );

		return array_diff_key( $matches, array_fill_keys( array_filter( array_keys( $matches ), 'is_numeric' ), true ) );
	}
}

class Wimperium_Rest_Post_Get extends Wimperium_Rest_Endpoint {
	static $method = 'GET';
	static $path = '/posts/(?<post_id>\d+|slug:[^/])';

	function process( $path, array $query, $body = null ) {
		$parsed = $this->parse_path( $path );

		return $this->get_post( $parsed['post_id'] );
	}

	function get_post( $post_id ) {
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ctype_digit( $parsed['post_id'] ) ) {
			$post_id = (int) $parsed['post_id'];
		} else {
			list( $noop, $post_slug ) = explode( ':', $parsed['post_id'] );

			$post_slug = sanitize_title( $post_slug );
			if ( !$post_slug ) {
				return new WP_Error( 'invalid_post', 'Invalid post', array( 'code' => 400 ) );
			}

			$posts = get_posts( array( 'name' => $post_slug ) );
			if ( $posts && isset( $posts[0]->ID ) && $posts[0]->ID ) {
				$post_id = (int) $posts[0]->ID;
			} else {
				$page = get_page_by_path( $post_slug );
				if ( ! $page ) {
					return new WP_Error( 'unknown_post', 'Unknown post', array( 'code' => 404 ) );
				}

				$post_id = (int) $page->ID;
			}
		}

		$post = get_post( $post_id );

		if ( ! $post || is_wp_error( $post ) ) {
			return new WP_Error( 'unknown_post', 'Unknown post', array( 'code' => 404 ) );
		}

		return (object) array(
			'id'      => (int) $post->ID,
			'slug'    => (string) $post->post_name,
			'title'   => (string) $post->post_title,
			'content' => (string) $post->post_content,
		);
	}
}
Wimperium_Rest_Post_Get::register();

class Wimperium_Rest_Post_New extends Wimperium_Rest_Post_Get {
	static $method = 'POST';
	static $path = '/posts/';

	function parse_body( $body ) {
		$body = (array) $body;

		foreach ( array( 'title', 'content' ) as $string_field ) {
			if ( isset( $body[$string_field] ) ) {
				$body[$string_field] = (string) $body[$string_field];
			} else {
				$body[$string_field] = '';
			}
		}

		return $body;
	}

	function process( $path, array $query, $body = null ) {
		$body = $this->parse_body( $body );
var_dump( $body );
exit;
		$post_id = wp_insert_post( (object) array(
			'post_title'   => $body['title'],
			'post_content' => $body['content'],
		), true );

		return $this->get_post( $post_id );
	}
}
Wimperium_Rest_Post_New::register();
