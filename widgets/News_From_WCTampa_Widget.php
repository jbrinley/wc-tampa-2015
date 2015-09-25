<?php

/**
 * Class Most_Popular_Widget
 *
 * A widget to show the most popular posts on the site
 */
class News_From_WCTampa_Widget extends WP_Widget {

	private $feed_url = 'https://tampa.wordcamp.org/2015/feed/';

	public function __construct() {
		$widget_ops = array('classname' => 'wctampa_news', 'description' => __( 'The latest posts from WordCamp Tampa') );
		parent::__construct('wctampa_news', __('News from WordCamp Tampa'), $widget_ops);
	}

	/**
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$latest = $this->get_latest_post();
		if ( ! $latest ) {
			return;
		}

		/** This filter is documented in wp-includes/default-widgets.php */
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Popular Posts' ) : $instance['title'], $instance, $this->id_base );

		echo $args['before_widget'];
		if ( $title ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		printf( '<h3><a href="%s">%s</a></h3>', esc_url( $latest['url'] ), esc_html( $latest['title'] ) );
		echo $latest['excerpt'];

		echo $args['after_widget'];
	}

	private function get_latest_post() {
		$feed = $this->fetch_feed();
		if ( !$feed ) {
			return false;
		}
		$xml = simplexml_load_string( $feed );
		foreach ( $xml->channel->item as $item ) {
			return [
				'title' => (string)$item->title,
				'date' => (string)$item->pubDate,
				'url' => (string)$item->link,
				'excerpt' => (string)$item->description,
			];
		}
		return false;
	}

	private function fetch_feed() {
		$feed_contents = wp_remote_get( $this->feed_url );
		if ( ! $feed_contents || is_wp_error( $feed_contents ) ) {
			return null;
		}
		return $feed_contents[ 'body' ];
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