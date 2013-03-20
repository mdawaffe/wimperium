<?php

/*
 * Plugin Name: Wimperium
 * Description: A Wimpy way of getting live updates on your site via Simperium
 * Author: mdawaffe
 */

if ( ! class_exists( 'MDAWaffe_Plugin_1' ) ) {
	require dirname( __FILE__ ) . '/plugin.php';
}

class Wimperium_Plugin extends MDAWaffe_Plugin_1 {
	const POST_TYPE = 'wimperium_template';

	var $default_prompt = "What's up?";

	function init_actions() {
		$this->add_action( 'init' );
		$this->add_action( 'admin_post_wimperium_rest' );
		$this->add_action( 'admin_post_nopriv_wimperium_rest', 'admin_post_wimperium_rest' );
		$this->add_action( 'wp_enqueue_scripts' );
		$this->add_action( 'wp_print_styles' );
		$this->add_action( 'wp_footer' );
	}

	function init_filters() {
		$this->add_filter( 'posts_results' );
		$this->add_filter( 'the_title', 1 );
		$this->add_filter( 'the_content', 11 );
	}

	function init() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! isset( $this->template_post ) && is_admin() ) {
			$this->register_post_type( false );
			$this->generate_template_post();
		} else {
			$this->register_post_type( true );
		}

		$this->default_prompt = __( "What's up?" );

		$proxy_url = admin_url( 'admin-post.php?action=wimperium_rest&path=/proxy' );
		$parsed_proxy_url = parse_url( $proxy_url );

		wp_register_script( 'wimperium-rest', plugins_url( 'js/proxy-request.js', __FILE__ ), array( 'jquery' ), mt_rand(), true );
		wp_localize_script( 'wimperium-rest', 'WimperiumRest', array(
			'proxyOrigin' => $parsed_proxy_url['scheme'] . '://' . $parsed_proxy_url['host'],
			'proxyURL'    => $proxy_url,
		) );

		// @todo, for same-origin requests, just use AJAX not proxy
		wp_register_script( 'wimperium', plugins_url( 'js/wimperium.js', __FILE__ ), array( 'wimperium-rest' ), mt_rand(), true );

		wp_register_style( 'wimperium-rest', plugins_url( 'css/style.css', __FILE__ ), array(), mt_rand() );
	}

	function register_post_type( $is_public ) {
		register_post_type( self::POST_TYPE, array(
			'public' => $is_public,
			'exclude_from_search' => true,
			'publicly_queryable' => false,
			'show_ui' => false,
			'show_in_nav_menus' => false,
			'rewrite' => false,
			'can_export' => false,
			'capability_type' => 'fnord',
			'supports' => array(
				'title',
				'post-formats',
			),
		) );
	}

	function generate_template_post() {
		$this->template_post = wp_insert_post( add_magic_quotes( array(
			'post_title'     => '{{{title}}}',
			'post_content'   => '{{{content}}}',
			'post_type'      => self::POST_TYPE,
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		) ) );
		$this->register_post_type( true );
	}

	function posts_results( $posts, $query ) {
		if ( ! is_user_logged_in() ) {
			return $posts;
		}

		if ( ! $query->is_main_query() ) {
			return $posts;
		}

		if ( ! $query->is_home() ) {
			return $posts;
		}

		if ( ! $this->template_post ) {
			return $posts;
		}

		$template_post = get_post( $this->template_post );

		if ( ! $template_post ) {
			return $posts;
		}

		array_unshift( $posts, $template_post, $template_post );
		return $posts;
	}

	function wp_enqueue_scripts() {
		wp_enqueue_script( 'wimperium-rest' );
		wp_enqueue_script( 'wimperium' );
	}

	function wp_print_styles() {
		wp_enqueue_style( 'wimperium-rest' );
	}

	function the_title( $title, $post_id ) {
		$post = get_post( $post_id );
		if ( self::POST_TYPE !== $post->post_type ) {
			return $title;
		}

		if ( isset( $this->prompt ) ) {
			$the_title = $this->prompt;
		} else {
			$the_title = $this->default_prompt;
		}

		return str_replace( '{{{title}}}', $the_title, $title );
	}

	function the_content( $content ) {
		$post = get_post();
		if ( self::POST_TYPE !== $post->post_type ) {
			return $content;
		}

		ob_start();
?>
<form class="wimperium-post" action="" method="post">
<p>
	<label>
		<span>Title</span>
		<input type="text" class="title" name="title" placeholder="Title" />
	</label>
</p>

<label>
	<span>Post</span>
	<textarea name="content" class="content" cols="50" rows="10" placeholder="Type your post here&hellip;"></textarea>
</label>

<p class="submit">
	<input type="submit" value="<?php esc_attr_e( 'Publish Post' ); ?>" />
</p>
</form>
<?php
		$form = ob_get_clean();

		return str_replace( '{{{content}}}', $form, $content );
	}

	function wp_footer() {
		
	}

	function admin_post_wimperium_rest() {
		require dirname( __FILE__ ) . '/rest.php';

		$envelope = isset( $_GET['http_envelope'] ) && '1' === $_GET['http_envelope'];

		$rest = new Wimperium_Rest();
		$rest->process();
		$rest->status( $envelope );
		die( $rest->json( $envelope ) );
	}
}

add_action( 'plugins_loaded', array( 'Wimperium_Plugin', 'instance' ) );
