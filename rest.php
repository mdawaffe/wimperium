<?php

class Wimperium_Rest {
	var $method = 'GET';
	var $path   = '/';
	var $query  = array();
	var $body   = null;

	var $content_type;

	var $endpoint = null;
	var $response = null;
	var $status   = 200;

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


	function get_request_headers() {
		if ( function_exists( 'getallheaders' ) ) {
			return getallheaders();
		}
		
		$headers = array();
		
		foreach ( $_SERVER as $key => $value ) {
			if ( 'HTTP_' != substr( $key, 0, 5 ) ) {
				continue;
			}
		
			$key = strtolower( str_replace( '_', ' ', substr( $key, 5 ) ) );
			$key = ucwords( $key );
			$key = str_replcae( ' ', '-', $key );
			$headers[$key] = stripslashes( $value );
		}

		return $headers;
	}

	function generate_cookie_authentication() {
		$cookie = is_ssl() ? $_COOKIE[SECURE_AUTH_COOKIE] : $_COOKIE[AUTH_COOKIE];

		return hash_hmac( 'md5', $cookie, wp_salt( get_called_class() ) );
	}

	// @todo hook to authenticate action
	function cookie_authentication() {
		$headers = $this->get_request_headers();
		if ( ! isset( $headers['Authorization'] ) ) {
			return false;
		}

		$authorization = trim( $headers['Authorization'] );

		if ( ! preg_match( '#^X-WPCOOKIE\s+#i', $authorization, $matches ) ) {
			return false;
		}

		$authorization = substr( $authorization, strlen( $matches[0] ) );

		if ( $this->generate_cookie_authentication() !== $authorization ) {
			return false;
		}

		return true;
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

		if ( is_user_logged_in() && 'Wimperium_Rest_Proxy' !== $endpoint ) {
			if ( ! $this->cookie_authentication() ) {
				wp_set_current_user( 0 );
			}
		}
	
		$this->endpoint = new $endpoint( $this );

		$this->response = $this->endpoint->process( $this->path, $this->query, $this->body );
	}

	function status( $envelope = false ) {
		if ( is_wp_error( $this->response ) ) {
			$data = $this->response->get_error_data();
			$this->status = isset( $data['code'] ) ? $data['code'] : 500;
		} else {
			$this->status = 200;
		}

		if ( ! $envelope ) {
			status_header( $this->status );
		}

		header( 'Content-Type: application/json' );
	}

	function json( $envelope = false ) {
		if ( is_wp_error( $this->response ) ) {
			$json = (object) array(
				'error' => $this->response->get_error_code(),
				'error_message' => $this->response->get_error_message(),
			);
		} else {
			$json = (object) $this->response;
		}

		if ( $envelope ) {
			$json = (object) array(
				'code' => $this->status,
				'body' => $json,
			);
		}

		return json_encode( $json );
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

	protected $rest;

	function __construct( Wimperium_Rest $rest ) {
		$this->rest = $rest;
	}

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

class Wimperium_Rest_Proxy extends Wimperium_Rest_Endpoint {
	static $method = 'GET';
	static $path = '/proxy';

	function process( $path, array $query, $body = null ) {
		$parsed_proxy_url = parse_url( admin_url( 'admin-post.php' ) );

		if ( ! is_user_logged_in() || wp_validate_auth_cookie() !== get_current_user_id() ) {
			setcookie( 'wimperium-rest', ' ', time() - YEAR_IN_SECONDS, $parsed_proxy_url['path'], $parsed_proxy_url['host'], is_ssl() );
			exit;
		}

		$hmac = $this->rest->generate_cookie_authentication();

		header_remove( 'X-Frame-Options' );

		setcookie( 'wimperium-rest', $hmac, time() + WEEK_IN_SECONDS, $parsed_proxy_url['path'], $parsed_proxy_url['host'], is_ssl() );

		require dirname( __FILE__ ) . '/proxy.php';
		exit;
	}
}
Wimperium_Rest_Proxy::register();

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

		if ( ctype_digit( $post_id ) ) {
			$post_id = (int) $post_id;
		} else {
			list( $noop, $post_slug ) = explode( ':', $post_id );

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

		// @todo permissions

		$globals = array_fill_keys( array( 'post', 'pages', 'page' ), '__unset__' );
		foreach ( array_keys( $globals ) as $global ) {
			if ( isset( $GLOBALS[$global] ) ) {
				$globals[$global] = $GLOBALS[$global];
			}
		}

		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$title = get_the_title();

		$content = join( "\n\n", $GLOBALS['pages'] );
		$content = preg_replace( '/<!--more(.*?)?-->/', '', $content );
		$GLOBALS['pages'] = array( $content );
		$GLOBALS['page']  = 1;

		ob_start();
		the_content();
		$content = ob_get_clean();

		foreach ( $globals as $global => $original_value ) {
			if ( '__unset__' === $original_value ) {
				unset( $GLOBALS[$global] );
			} else {
				$GLOBALS[$globals] = $original_value;
			}
		}

		return (object) array(
			'id'      => (int) $post->ID,
			'slug'    => (string) $post->post_name,
			'title'   => (string) $title,
			'content' => (string) $content,
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
		if ( ! current_user_can( 'publish_posts' ) ) {
			return new WP_Error( 'cannot_publish_posts', 'Your account is not authorized to publish posts.', array( 'code' => 403 ) );
		}

		$body = $this->parse_body( $body );

		$post_id = wp_insert_post( (object) array(
			'post_title'   => $body['title'],
			'post_content' => $body['content'],
			'post_status'  => 'publish',
		), true );

		return $this->get_post( $post_id );
	}
}
Wimperium_Rest_Post_New::register();
