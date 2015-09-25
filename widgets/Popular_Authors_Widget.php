<?php

/**
 * Class Most_Popular_Widget
 *
 * A widget to show the most popular posts on the site
 */
class Popular_Authors_Widget extends WP_Widget {

	public function __construct() {
		$widget_ops = array('classname' => 'popular_authors', 'description' => __( 'The most liked authors') );
		parent::__construct('popular_authors', __('Popular Authors'), $widget_ops);
	}

	/**
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$counts = $this->get_author_like_counts_v2();

		if ( empty( $counts ) ) {
			return; // no authors to display
		}

		/** This filter is documented in wp-includes/default-widgets.php */
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Popular Posts' ) : $instance['title'], $instance, $this->id_base );

		echo $args['before_widget'];
		if ( $title ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		echo '<ul>';
		foreach ( $counts as $author_id => $like_count ) {
			$author = get_userdata($author_id);
			printf( __( '<li><a href="%1$s">%2$s (%3$s Likes)</a></li>' ), get_author_posts_url( $author_id ), $author->display_name, number_format_i18n( $like_count ) );
		}
		echo '</ul>';

		echo $args['after_widget'];
	}

	private function get_author_like_counts( $letter = 'S', $limit = 5) {
		/** @var \wpdb $wpdb */
		global $wpdb;
		$like = '%' . $wpdb->esc_like( $letter ) . '%%';
		$limit = absint( $limit );
		$sql = "SELECT p.post_author, SUM( m.meta_value ) AS total_likes FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
			LEFT JOIN {$wpdb->postmeta} t ON p.ID = t.post_id
			WHERE m.meta_id IN (
				SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = 'like_count'
			) AND t.meta_id IN (
				SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value > 0
			) AND p.post_author IN (
				SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'first_name' AND meta_value LIKE '$like'
			) AND p.ID IN (
				SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE '$like'
			)
			GROUP BY p.post_author HAVING total_likes > 1 ORDER BY total_likes DESC LIMIT $limit";
		$result = $wpdb->get_results( $sql );
		$counts = [];
		foreach ( $result as $r ) {
			$counts[ $r->post_author ] = $r->total_likes;
		}
		return $counts;
	}

	private function get_author_like_counts_v2( $letter = 'S', $limit = 5) {
		/** @var \wpdb $wpdb */
		global $wpdb;
		$like = '%' . $wpdb->esc_like( $letter ) . '%%';
		$limit = absint( $limit );
		$sql = "SELECT p.post_author, SUM( m.meta_value ) AS total_likes FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
			LEFT JOIN {$wpdb->postmeta} t ON p.ID = t.post_id
			LEFT JOIN {$wpdb->usermeta} u ON u.user_id = p.post_author
			WHERE p.post_title LIKE '$like'
				AND m.meta_key = 'like_count'
				AND u.meta_key = 'first_name' AND u.meta_value LIKE '$like'
				AND t.meta_key = '_thumbnail_id' AND t.meta_value > 0
			GROUP BY p.post_author HAVING total_likes > 1 ORDER BY total_likes DESC LIMIT $limit";
		$result = $wpdb->get_results( $sql );
		$counts = [];
		foreach ( $result as $r ) {
			$counts[ $r->post_author ] = $r->total_likes;
		}
		return $counts;
	}

	/**
	 * @param array $new_instance
	 * @param array $old_instance
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$new_instance = wp_parse_args( (array) $new_instance, array( 'title' => '' ) );
		$instance['title'] = strip_tags($new_instance['title']);

		return $instance;
	}

	/**
	 * @param array $instance
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'count' => 0, 'dropdown' => '') );
		$title = strip_tags($instance['title']);
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></p>
		<?php
	}
}