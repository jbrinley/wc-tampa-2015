<?php

if ( get_post_type() != 'post' ) {
	return;
}

$start = microtime( true );
//$related_posts = wctampa2015_get_related_posts();
$related_posts = wctampa2015_get_related_posts_cached();

if ( !$related_posts ) {
	return;
}

?>
<div class="related-posts">
	<h3><?php _e( 'Related Posts' ); ?></h3>
	<ul>
		<?php foreach( $related_posts as $related_post_id ) { ?>
			<li><a href="<?= get_permalink( $related_post_id ); ?>"><?= get_the_title( $related_post_id ); ?></a></li>
		<?php } ?>
	</ul>
</div>

<?php echo microtime( true ) - $start;