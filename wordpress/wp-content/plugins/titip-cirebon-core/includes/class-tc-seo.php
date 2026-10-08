<?php
/**
 * TC_SEO — Basic Technical SEO, Open Graph Metadata & Structured Data.
 *
 * @package TitipCirebonCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Open Graph, Twitter Cards, JSON-LD Structured Data, and Analytics placeholders.
 *
 * Injects essential social sharing meta tags for WhatsApp & Facebook previews,
 * schema.org structured data, and tracking placeholders without bloated dependencies.
 */
class TC_SEO {

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
	 * Constructor — register frontend hooks.
	 */
	private function __construct() {
		// Only run on frontend.
		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_head', array( $this, 'render_open_graph_meta' ), 5 );
		add_action( 'wp_head', array( $this, 'render_structured_data' ), 6 );
		add_action( 'wp_head', array( $this, 'render_analytics_placeholder' ), 99 );
	}

	/**
	 * Render Open Graph and Twitter Card meta tags in <head>.
	 */
	public function render_open_graph_meta(): void {
		$site_name = get_bloginfo( 'name' ) ?: 'Titip Cirebon';
		$locale    = 'id_ID';

		$og_data = $this->get_page_meta_data( $site_name );
		?>
<!-- Open Graph & Social Sharing Meta Tags by Titip Cirebon Core -->
<meta property="og:site_name" content="<?php echo esc_attr( $site_name ); ?>">
<meta property="og:locale" content="<?php echo esc_attr( $locale ); ?>">
<meta property="og:type" content="<?php echo esc_attr( $og_data['type'] ); ?>">
<meta property="og:title" content="<?php echo esc_attr( $og_data['title'] ); ?>">
<meta property="og:description" content="<?php echo esc_attr( $og_data['description'] ); ?>">
<meta property="og:url" content="<?php echo esc_url( $og_data['url'] ); ?>">
<?php if ( ! empty( $og_data['image'] ) ) : ?>
<meta property="og:image" content="<?php echo esc_url( $og_data['image'] ); ?>">
<meta property="og:image:alt" content="<?php echo esc_attr( $og_data['title'] ); ?>">
<?php endif; ?>

<!-- Twitter Cards -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo esc_attr( $og_data['title'] ); ?>">
<meta name="twitter:description" content="<?php echo esc_attr( $og_data['description'] ); ?>">
<?php if ( ! empty( $og_data['image'] ) ) : ?>
<meta name="twitter:image" content="<?php echo esc_url( $og_data['image'] ); ?>">
<?php endif; ?>
<!-- End Open Graph Meta Tags -->
		<?php
	}

	/**
	 * Render schema.org JSON-LD structured data in <head>.
	 */
	public function render_structured_data(): void {
		$schema = array();

		if ( is_front_page() || is_home() ) {
			// LocalBusiness schema for Homepage.
			$schema = array(
				'@context'           => 'https://schema.org',
				'@type'              => 'LocalBusiness',
				'name'               => 'Titip Cirebon',
				'description'        => 'Layanan Jasa Titip (Jastip) Oleh-oleh dan Kuliner Khas Cirebon terpercaya. Pesan mudah via WhatsApp, dikirim fresh ke kota Anda.',
				'url'                => home_url( '/' ),
				'priceRange'         => '$$',
				'currenciesAccepted' => 'IDR',
				'paymentAccepted'    => 'Bank Transfer, E-Wallet',
				'areaServed'         => array(
					array(
						'@type' => 'City',
						'name'  => 'Sumedang',
					),
					array(
						'@type' => 'City',
						'name'  => 'Cirebon',
					),
				),
			);

			$site_icon = get_site_icon_url( 512 );
			if ( $site_icon ) {
				$schema['image'] = esc_url_raw( $site_icon );
			}
		} elseif ( is_singular( 'tc_product' ) ) {
			// Product schema for single tc_product.
			global $post;
			if ( $post ) {
				$product_title = get_the_title( $post );
				$store_origin  = get_post_meta( $post->ID, '_tc_store_origin', true );
				$description   = $this->get_clean_description( $post );
				$image_url     = get_the_post_thumbnail_url( $post->ID, 'large' );

				$schema = array(
					'@context'    => 'https://schema.org',
					'@type'       => 'Product',
					'name'        => $product_title,
					'description' => $description,
				);

				if ( $image_url ) {
					$schema['image'] = esc_url_raw( $image_url );
				}

				if ( ! empty( $store_origin ) ) {
					$schema['brand'] = array(
						'@type' => 'Brand',
						'name'  => $store_origin,
					);
				} else {
					$schema['brand'] = array(
						'@type' => 'Brand',
						'name'  => 'Titip Cirebon',
					);
				}
			}
		}

		if ( ! empty( $schema ) ) {
			?>
<!-- Structured Data (JSON-LD) by Titip Cirebon Core -->
<script type="application/ld+json">
<?php echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); ?>
</script>
<!-- End Structured Data -->
			<?php
		}
	}

	/**
	 * Render Google Analytics / Meta Pixel placeholder comments in <head>.
	 */
	public function render_analytics_placeholder(): void {
		?>
<!-- ======================================================================= -->
<!-- Google Analytics (GA4) / Meta Pixel Placeholder — Titip Cirebon Core   -->
<!-- Masukkan tag tracking Anda di bawah ini atau gunakan hook: tc_head_analytics -->
<!--
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXXXXX');
</script>
-->
<!-- ======================================================================= -->
		<?php
		/**
		 * Allows developers/administrators to dynamically inject tracking scripts into <head>.
		 */
		do_action( 'tc_head_analytics' );
	}

	/**
	 * Get dynamic metadata based on current screen context.
	 *
	 * @param string $site_name Site name.
	 * @return array{title: string, description: string, url: string, image: string, type: string}
	 */
	private function get_page_meta_data( string $site_name ): array {
		$default_image = apply_filters(
			'tc_seo_default_image',
			get_site_icon_url( 512 ) ?: ''
		);

		if ( is_front_page() || is_home() ) {
			return array(
				'title'       => 'Titip Cirebon — Jastip Oleh-oleh & Kuliner Khas Cirebon',
				'description' => 'Layanan Jasa Titip (Jastip) Oleh-oleh dan Kuliner Khas Cirebon terpercaya. Pesan mudah via WhatsApp, dikirim fresh ke kota Anda.',
				'url'         => home_url( '/' ),
				'image'       => $default_image,
				'type'        => 'website',
			);
		}

		if ( is_singular( 'tc_product' ) ) {
			global $post;
			$product_title = get_the_title( $post );
			$store_origin  = get_post_meta( $post->ID, '_tc_store_origin', true );
			$thumb_url     = get_the_post_thumbnail_url( $post->ID, 'large' );

			$desc = $this->get_clean_description( $post );
			if ( empty( $desc ) ) {
				$desc = $store_origin
					? sprintf( 'Titip beli %s dari %s khas Cirebon terpercaya lewat Titip Cirebon.', $product_title, $store_origin )
					: sprintf( 'Titip beli %s khas Cirebon terpercaya lewat Titip Cirebon.', $product_title );
			}

			return array(
				'title'       => $product_title . ' — ' . $site_name,
				'description' => $desc,
				'url'         => get_permalink( $post ),
				'image'       => $thumb_url ?: $default_image,
				'type'        => 'article',
			);
		}

		if ( is_singular() ) {
			global $post;
			$page_title = get_the_title( $post );
			$thumb_url  = get_the_post_thumbnail_url( $post->ID, 'large' );
			$desc       = $this->get_clean_description( $post );

			if ( empty( $desc ) ) {
				$desc = get_bloginfo( 'description' ) ?: 'Layanan Jastip Oleh-oleh dan Kuliner Khas Cirebon.';
			}

			return array(
				'title'       => $page_title . ' — ' . $site_name,
				'description' => $desc,
				'url'         => get_permalink( $post ),
				'image'       => $thumb_url ?: $default_image,
				'type'        => 'website',
			);
		}

		// Fallback for archives/others.
		return array(
			'title'       => wp_get_document_title(),
			'description' => get_bloginfo( 'description' ) ?: 'Layanan Jastip Oleh-oleh dan Kuliner Khas Cirebon.',
			'url'         => home_url( add_query_arg( array(), '' ) ),
			'image'       => $default_image,
			'type'        => 'website',
		);
	}

	/**
	 * Extract and clean description from post content or excerpt.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private function get_clean_description( \WP_Post $post ): string {
		if ( ! empty( $post->post_excerpt ) ) {
			return wp_strip_all_tags( $post->post_excerpt );
		}

		if ( ! empty( $post->post_content ) ) {
			$clean = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
			$clean = preg_replace( '/\s+/', ' ', $clean );
			return mb_strimwidth( trim( $clean ), 0, 160, '...' );
		}

		return '';
	}
}
