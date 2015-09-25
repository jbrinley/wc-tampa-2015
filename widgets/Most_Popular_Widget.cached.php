<?php

/**
 * Class Most_Popular_Widget (cached version)
 *
 * A widget to show the most popular posts on the site
 */
class Most_Popular_Widget extends WP_Widget {

	public function __construct() {
		$widget_ops = array('classname' => 'most_popular', 'description' => __( 'The most liked posts') );
		parent::__construct('most_popular', __('Popular Posts'), $widget_ops);
	}

	/**
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$content = tlc_transient( 'popular-posts-widget' )
			->updates_with( [ $this, 'get_widget_content' ] )
			->expires_in( 12 * HOUR_IN_SECONDS )
			->get();

		if ( empty( $content ) ) {
			return;
		}

		/** This filter is documented in wp-includes/default-widgets.php */
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Popular Posts' ) : $instance['title'], $instance, $this->id_base );

		echo $args['before_widget'];
		if ( $title ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		echo $content;

		echo $args['after_widget'];
	}

	public function get_widget_content() {
		ob_start();
		$categories = $this->get_categories();
		foreach ( $categories as $term ) {
			$query = $this->get_popular_posts_query( $term, 1 );

			if ( $query->have_posts() ) {
				?><h3><a href="<?= get_term_link( $term ) ?>"><?= sprintf( __( 'Category: %s' ),  $term->name ); ?></a></h3><?php
			}

			while ( $query->have_posts() ) {
				$query->the_post();
				?>
				<h4><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
				<div class="featured-image"><a href="<?php the_permalink(); ?>"><?php the_post_thumbnail(); ?></a></div>
				<div class="excerpt"><?php the_excerpt(); ?></div>
				<?php
			}
		}
		wp_reset_postdata();
		return ob_get_clean();
	}

	private function get_categories() {
		return get_terms( 'category', [ 'parent' => 0 ] );
	}

	/**
	 * @param object $category A WordPress term object
	 * @param int $count The number of posts to retrieve
	 *
	 * @return WP_Query
	 */
	private function get_popular_posts_query( $category = null, $count = 5 ) {
		$query_args = [
			'post_type' => 'post',
			'posts_per_page' => $count,
			'meta_query' => [
				'likes' => [
					'key' => 'like_count',
					'compare' => 'EXISTS',
					'type' => 'NUMERIC',
				],
				'thumbnail' => [
					'key' => '_thumbnail_id',
					'compare' => '>',
					'value' => '0',
					'type' => 'NUMERIC',
				],
			],
			'orderby' => 'likes',
			'order' => 'DESC',
		];
		if ( $category ) {
			$query_args['tax_query'] = [
				[
					'taxonomy' => 'category',
					'field' => 'term_id',
					'terms' => $category->term_id,
					'include_children' => true,
				],
			];
		}
		$query = new WP_Query( $query_args );
		return $query;
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