<?php

if ( get_post_type() != 'post' ) {
	return;
}

//$related_posts = wctampa2015_get_nearby_posts_v2();
$related_posts = wctampa2015_get_nearby_posts_cached();

if ( !$related_posts ) {
	return;
}

?>
<div class="nearby-posts">
	<h3><?php _e( 'Posted Nearby' ); ?></h3>
	<ul>
		<?php foreach( $related_posts as $related_post_id ) { ?>
			<li><a href="<?= get_permalink( $related_post_id ); ?>"><?= get_the_title( $related_post_id ); ?></a></li>
		<?php } ?>
	</ul>
</div>
