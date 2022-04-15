<?php
/**
 * Block class.
 *
 * @package SiteCounts
 */

namespace XWP\SiteCounts;

use WP_Block;
use WP_Query;

/**
 * The Site Counts dynamic block.
 *
 * Registers and renders the dynamic block.
 */
class Block {

	/**
	 * The Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Instantiates the class.
	 *
	 * @param Plugin $plugin The plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Adds the action to register the block.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Registers the block.
	 */
	public function register_block() {
		register_block_type_from_metadata(
			$this->plugin->dir(),
			[
				'render_callback' => [ $this, 'render_callback' ],
			]
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array    $attributes The attributes for the block.
	 * @param string   $content    The block content, if any.
	 * @param WP_Block $block      The instance of this block.
	 * @return string The markup of the block.
	 */
	public function render_callback( $attributes, $content, $block ) {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		$class_name = sanitize_html_class( $attributes['className'] ?? '' );
		$current_id = get_the_ID();
		ob_start();

		?>
		<div class="<?php echo esc_attr( $class_name ); ?>">
			<h2><?php _e( 'Post Counts', 'site-counts' ) ?></h2>
			<ul>
				<?php
					foreach ( $post_types as $post_type_slug => $post_type_object ) :
						$post_type_object = get_post_type_object( $post_type_slug  );
						$post_count       = wp_count_posts( $post_type_slug )->publish;
						?>
		
						<li><?php printf( __( 'There are %d %s.', 'site-counts' ), $post_count, $post_type_object->labels->name ); ?></li>
						<?php
					endforeach;
				?>
			</ul>
			<p><?php printf( __( 'The current post ID is %d', 'site-counts' ), $current_id ); ?></p>

			<?php

				// Check if cache data exists in site_counts group.
				$query = wp_cache_get( 'sc_baz_foo_filtered_posts', 'site_counts' );

				if ( false === $query ) {
					$query = new WP_Query(  array(
						'post_type'              => ['post', 'page'],
						'post_status'            => 'any',
						'posts_per_page'         => 6, // Total posts plus count of excluded posts.
						'no_found_rows'          => true,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
						'date_query'             => array(
							array(
								'hour'    => 9,
								'compare' => '>=',
							),
							array(
								'hour'    => 17,
								'compare' => '<=',
							),
						),
						'tax_query'      => array(
							'relation' => 'AND',
							array(
								'taxonomy'         => 'category',
								'field'            => 'slug',
								'terms'            => 'baz',
								'include_children' => false,
							),
							array(
								'taxonomy' => 'post_tag',
								'field'    => 'slug',
								'terms'    => 'foo',
							),
						),
					));

					// Since we are dealing with large set of data here, We can store the entire query result in the cache group.
					wp_cache_set( 'sc_baz_foo_filtered_posts', $query, 'site_counts' );
				}

				if ( $query->have_posts() ) :
					$post_count = 0;
					?>
					<h2><?php printf( __( '%d posts with the tag of foo and the category of baz', 'site-counts' ), 5 ); ?></h2>
					<ul>
						<?php
							while ( $query->have_posts() ) :
								$query->the_post();
								if ( $current_id !== get_the_ID() && $post_count < 5 ) {
									$post_count++;
									?>
									<li><?php echo esc_html( get_the_title() ); ?></li>
									<?php
								}
							endwhile;
						wp_reset_postdata();
						?>
					</ul>
					<?php
				endif;
			?>
		</div>
		<?php
		return ob_get_clean();
	}
}
