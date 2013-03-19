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
	}

	function init_filters() {
		$this->add_filter( 'posts_results' );
		$this->add_filter( 'the_title', 1 );
		$this->add_filter( 'the_content', 1 );
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

		array_unshift( $posts, $template_post );
		return $posts;
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
<form action="" method="post">
<textarea name="content" cols="50" rows="10"></textarea>
<input type="submit" value="<?php esc_attr_e( 'Publish Post' ); ?>" />
</form>
<?php
		$form = ob_get_clean();

		return str_replace( '{{{content}}}', $form, $content );
	}

	function admin_post_wimperium_rest() {
		require dirname( __FILE__ ) . '/rest.php';

		$rest = new Wimperium_Rest();
		$rest->process();
		$rest->status();
		die( $rest->json() );
	}
}

add_action( 'plugins_loaded', array( 'Wimperium_Plugin', 'instance' ) );
