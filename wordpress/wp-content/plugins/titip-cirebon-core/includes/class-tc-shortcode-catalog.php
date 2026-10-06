<?php
/**
 * TC_Shortcode_Catalog — Dynamic catalog shortcode for active weekly offers.
 *
 * @package TitipCirebonCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the [tc_active_weekly_offers] shortcode.
 *
 * Queries tc_weekly_offer posts whose start_date <= today <= end_date,
 * ordered by sort_order ASC, and renders them as product cards using
 * CSS classes from the Titip Cirebon design system (Sprint 1B/1C).
 */
class TC_Shortcode_Catalog {

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor — register the shortcode.
     */
    private function __construct() {
        add_shortcode( 'tc_active_weekly_offers', array( $this, 'render' ) );
    }

    /**
     * Render the active weekly offers shortcode.
     *
     * @param  array|string $atts Shortcode attributes (unused for now).
     * @return string HTML output.
     */
    public function render( $atts = array() ): string {
        $today = current_time( 'Y-m-d' );

        $args = array(
            'post_type'      => 'tc_weekly_offer',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => '_tc_offer_start_date',
                    'value'   => $today,
                    'compare' => '<=',
                    'type'    => 'DATE',
                ),
                array(
                    'key'     => '_tc_offer_end_date',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            ),
            'meta_key'       => '_tc_sort_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
        );

        $query = new WP_Query( $args );

        if ( ! $query->have_posts() ) {
            return '<p class="tc-weekly-offers__empty">Belum ada penawaran minggu ini. Nantikan update terbaru!</p>';
        }

        ob_start();
        ?>
        <div class="tc-weekly-offers">
            <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                <?php
                $offer_id   = get_the_ID();
                $product_id = get_post_meta( $offer_id, '_tc_linked_product_id', true );
                $product    = $product_id ? get_post( intval( $product_id ) ) : null;

                $selling_price  = get_post_meta( $offer_id, '_tc_selling_price', true );
                $badge          = get_post_meta( $offer_id, '_tc_badge', true );
                $store_origin   = $product ? get_post_meta( $product->ID, '_tc_store_origin', true ) : '';

                // Determine the image: use offer thumbnail, fall back to product thumbnail.
                $image_html = '';
                if ( has_post_thumbnail( $offer_id ) ) {
                    $image_html = get_the_post_thumbnail( $offer_id, 'medium', array(
                        'class'   => 'tc-product-card__img',
                        'loading' => 'lazy',
                    ) );
                } elseif ( $product && has_post_thumbnail( $product->ID ) ) {
                    $image_html = get_the_post_thumbnail( $product->ID, 'medium', array(
                        'class'   => 'tc-product-card__img',
                        'loading' => 'lazy',
                    ) );
                }

                // Product title: use the linked product title if available, otherwise use the offer title.
                $display_title = $product ? $product->post_title : get_the_title();

                // Badge CSS class modifier.
                $badge_class = '';
                if ( $badge ) {
                    $badge_slug  = sanitize_title( $badge );
                    $badge_class = 'tc-badge--' . $badge_slug;
                }
                ?>
                <div class="tc-product-card">
                    <?php if ( $image_html || $badge ) : ?>
                        <div class="tc-product-card__image">
                            <?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_the_post_thumbnail returns safe HTML.
                            echo $image_html;
                            ?>
                            <?php if ( $badge ) : ?>
                                <span class="tc-badge <?php echo esc_attr( $badge_class ); ?>">
                                    <?php echo esc_html( $badge ); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="tc-product-card__body">
                        <h3 class="tc-product-card__title">
                            <?php echo esc_html( $display_title ); ?>
                        </h3>

                        <?php if ( $store_origin ) : ?>
                            <p class="tc-product-card__origin">
                                <?php echo esc_html( $store_origin ); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ( $selling_price ) : ?>
                            <p class="tc-product-card__price">
                                Rp <?php echo esc_html( number_format( (int) $selling_price, 0, ',', '.' ) ); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <?php
        wp_reset_postdata();

        return ob_get_clean();
    }
}
