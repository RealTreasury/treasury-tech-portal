<?php
/**
 * Display glassmorphism-styled related posts at end of post content
 */
function display_custom_related_posts() {
    // Only show on single posts
    if ( ! is_single() ) {
        return;
    }

    global $post;

    $categories = get_the_category( $post->ID );
    if ( empty( $categories ) ) {
        return;
    }

    $category_ids = wp_list_pluck( $categories, 'term_id' );

    $related = new WP_Query(
        array(
            'post_type'      => 'post',
            'posts_per_page' => 3,
            'post__not_in'   => array( $post->ID ),
            'category__in'   => $category_ids,
            'orderby'        => 'rand',
            'meta_query'     => array(
                array(
                    'key'     => '_thumbnail_id',
                    'compare' => 'EXISTS',
                ),
            ),
        )
    );

    if ( $related->have_posts() ) {
        echo '<div class="related-posts-wrapper" style="background: linear-gradient(135deg, #f8f8f8 0%, #ffffff 50%, #f0f0f0 100%); padding: 4rem 0; margin-top: 3rem;">';
        echo '<div class="ast-container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">';
        echo '<section class="custom-related-posts">';
        echo '<h2 class="related-posts-title">Related Posts</h2>';
        echo '<div class="related-posts-grid">';

        while ( $related->have_posts() ) {
            $related->the_post();

            echo '<article class="related-post-item">';
            echo '<h3 class="related-post-title">';
            echo '<a href="' . esc_url( get_permalink() ) . '">' . get_the_title() . '</a>';
            echo '</h3>';

            // Get excerpt or create one
            $excerpt = get_the_excerpt();
            if ( empty( $excerpt ) ) {
                $excerpt = wp_trim_words( get_the_content(), 20, '...' );
            }

            echo '<p class="related-post-excerpt">' . esc_html( $excerpt ) . '</p>';
            echo '<a href="' . esc_url( get_permalink() ) . '" class="related-post-link">Read More →</a>';
            echo '</article>';
        }

        echo '</div>';
        echo '</section>';
        echo '</div>';
        echo '</div>';
    }

    wp_reset_postdata();
}

// Hook to display at end of post content
add_action( 'astra_entry_after', 'display_custom_related_posts', 25 );
?>
