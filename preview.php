<?php

class Wimperium_Preview extends MDAWaffe_Plugin_1 {
	function init_filters() {
		$this->add_filter( 'body_class', 'preview_class_filter' );
		$this->add_filter( 'post_class', 'preview_class_filter' );
		$this->add_filter( 'comment_class', 'preview_class_filter' );

		foreach ( array( 'the_time', 'get_comment_date', 'get_post_time', 'get_the_date' ) as $filter ) {
			$this->add_filter( $filter, 'preview_time_filter' );
		}

		$this->add_filter( 'the_category' );
		$this->add_filter( 'the_tags' );
		$this->add_filter( 'the_terms' );

		$this->add_filter( 'wp_link_pages' );

		$this->add_filter( 'get_comment_ID' );
		$this->add_filter( 'get_comment_link' );
		$this->add_filter( 'comment_reply_link' );

		// the_date, title element?, next/prev post link?
		foreach ( array(
			'the_title', 'the_content', 'the_excerpt', 'the_permalink', 'get_edit_post_link',
			'the_author', 'the_author_posts_link', 'author_link',
			'comment_author', 'get_comment_author_url', 'get_comment_excerpt', 'get_comments_link', 'comments_number', 'comment_text',
			'get_comment_time', 'get_comment_type', 'post_comments_link', 'get_edit_comment_link',
		) as $filter ) {
			$this->add_filter( $filter, 'preview_filter' );
		}

		foreach ( array( 'user_email', 'user_url', 'first_name', 'last_name', 'description', 'user_nicename', 'display_name', 'ID' ) as $filter ) {
			$this->add_filter( "get_the_author_$filter", 'preview_filter' );
		}
	}

	function preview_filter() {
		return sprintf( '{{{%s}}}', current_filter() );
	}

	function preview_class_filter( $classes, $class ) {
		$return = sprintf( '{{{%s %s}}}', current_filter(), json_encode( compact( 'class' ) ) );
		return array( esc_attr( $return ) );
	}

	function the_category( $the_list, $separator, $parents ) {
		return sprintf( '{{{%s %s}}}', __FUNCTION__, json_encode( compact( 'separator', 'parents' ) ) );
	}

	function the_tags( $the_list, $before, $separator, $after ) {
		return sprintf( '{{{%s %s}}}', __FUNCTION__, json_encode( compact( 'separator', 'before', 'after' ) ) );
	}

	function the_terms( $the_list, $taxonomy, $before, $sep, $after ) {
		return sprintf( '{{{%s %s}}}', __FUNCTION__, json_encode( compact( 'taxonomy', 'separator', 'before', 'after' ) ) );
	}

	function wp_link_pages( $output, $args ) {
		return sprintf( '{{{%s %s}}}', __FUNCTION__, json_encode( compact( 'args' ) ) );
	}

	function preview_time_filter( $time, $format ) {
		$filter = current_filter();

		if ( ! $format ) {
			$format = get_option( false === strpos( $filter, 'date' ) ? 'date_format' : 'time_format' );
		}

		return sprintf( '{{{%s %s}}}', $filter, json_encode( compact( 'format' ) ) );
	}

	function get_comment_link( $link, $comment, $args ) {
		return sprintf( '{{{%s %s}}}', __FUNCTION__, json_encode( compact( 'args' ) ) );
	}

	function comment_reply_link( $link, $args ) {
		return sprintf( '{{{%s %s}}}', __FUNCTION__, json_encode( compact( 'args' ) ) );
	}
}
