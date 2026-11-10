<?php
/**
 * TC_Order — Custom Post Type, Meta Box, and Order Code Generator for internal orders.
 *
 * @package TitipCirebonCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the tc_order CPT, auto-generated order code, and order meta box.
 *
 * Orders are managed entirely by admin (input manual dari chat WhatsApp).
 * CPT ini bersifat privat dan tidak dapat diakses dari frontend.
 */
class TC_Order {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Allowed delivery area values.
	 *
	 * @var array<string, string>
	 */
	private array $delivery_areas = array(
		'cimalaka'      => 'Cimalaka',
		'sumedang_kota' => 'Sumedang Kota',
		'lainnya'       => 'Lainnya',
	);

	/**
	 * Allowed order status values.
	 *
	 * @var array<string, string>
	 */
	private array $order_statuses = array(
		'pesanan_diterima'  => 'Pesanan diterima',
		'sedang_diproses'   => 'Sedang diproses',
		'produk_dibeli'     => 'Produk dibeli',
		'siap_dikirim'      => 'Siap dikirim',
		'dalam_pengantaran' => 'Dalam pengantaran',
		'selesai'           => 'Selesai',
		'refund'            => 'Refund',
		'replacement'       => 'Replacement',
	);

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
	 * Constructor — register WordPress hooks.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'wp_insert_post_data', array( $this, 'filter_post_data' ), 10, 2 );
		add_action( 'wp_insert_post', array( $this, 'generate_order_code' ), 10, 3 );
		add_filter( 'enter_title_here', array( $this, 'change_title_placeholder' ), 10, 2 );
		add_action( 'admin_head', array( $this, 'setup_order_title_ui' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_tc_order', array( $this, 'save_meta' ) );
		add_filter( 'manage_tc_order_posts_columns', array( $this, 'set_custom_columns' ) );
		add_action( 'manage_tc_order_posts_custom_column', array( $this, 'render_custom_column' ), 10, 2 );
	}

	/**
	 * Register the tc_order custom post type.
	 * CPT bersifat privat — tidak publicly queryable, tidak di REST API.
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'               => 'Pesanan',
			'singular_name'      => 'Pesanan',
			'menu_name'          => 'Pesanan',
			'name_admin_bar'     => 'Pesanan',
			'add_new'            => 'Tambah Baru',
			'add_new_item'       => 'Tambah Pesanan Baru',
			'new_item'           => 'Pesanan Baru',
			'edit_item'          => 'Edit Pesanan',
			'view_item'          => 'Lihat Pesanan',
			'all_items'          => 'Semua Pesanan',
			'search_items'       => 'Cari Pesanan',
			'not_found'          => 'Tidak ada pesanan ditemukan.',
			'not_found_in_trash' => 'Tidak ada pesanan di tong sampah.',
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,          // Tidak publik sama sekali.
			'publicly_queryable' => false,          // Tidak bisa diquery dari frontend.
			'show_ui'            => true,           // Tampil di admin UI.
			'show_in_menu'       => true,
			'show_in_rest'       => false,          // Tidak ekspos ke REST API.
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 7,
			'menu_icon'          => 'dashicons-clipboard',
			'supports'           => array( 'title' ), // Hanya title; editor & thumbnail dimatikan.
		);

		register_post_type( 'tc_order', $args );
	}

	/**
	 * Mendapatkan kode pesanan berikutnya untuk tahun berjalan.
	 *
	 * Format: TC-YYYY-XXXX (contoh: TC-2026-0001).
	 * Nomor urut reset setiap tahun dan 100% aman dari duplikasi.
	 *
	 * Strategi dua lapis:
	 *  1. MAX() — termasuk post di 'trash' agar nomor urut yang pernah
	 *     terbit tidak dipakai ulang (penting untuk audit trail).
	 *  2. Collision-check loop — memastikan kode yang dihasilkan benar-benar
	 *     belum ada di database sebelum dikembalikan ke pemanggil.
	 *
	 * @return string
	 */
	public function get_next_order_code(): string {
		global $wpdb;

		$year         = gmdate( 'Y' );
		$like_pattern = $wpdb->esc_like( "TC-{$year}-" ) . '%';

		// Lapis 1: Ambil angka urut tertinggi untuk tahun ini.
		// 'trash' sengaja TIDAK difilter agar nomor yang pernah terbit tidak dipakai ulang.
		$max_number = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(CAST(SUBSTRING(post_title, %d) AS UNSIGNED))
                 FROM {$wpdb->posts}
                 WHERE post_type = 'tc_order'
                   AND post_status NOT IN ('auto-draft', 'inherit')
                   AND post_title LIKE %s",
				9, // Posisi karakter setelah "TC-YYYY-" (8 karakter + 1 offset).
				$like_pattern
			)
		);

		$next_number = ( (int) $max_number ) + 1;

		// Lapis 2: Collision-check loop — jamin kode belum terpakai meski ada race condition.
		do {
			$order_code = sprintf( 'TC-%s-%04d', $year, $next_number );
			$exists     = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					 WHERE post_type = 'tc_order'
					   AND post_title = %s
					 LIMIT 1",
					$order_code
				)
			);
			if ( ! $exists ) {
				break;
			}
			$next_number++;
		} while ( true );

		return $order_code;
	}

	/**
	 * Ganti placeholder pada field title untuk tc_order.
	 *
	 * @param string   $text Placeholder text.
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	public function change_title_placeholder( string $text, \WP_Post $post ): string {
		if ( 'tc_order' === $post->post_type ) {
			return '[Otomatis dibuat saat disimpan]';
		}
		return $text;
	}

	/**
	 * Konfigurasi UI input Judul pada layar Tambah/Edit Pesanan:
	 * 1. Jika pesanan baru (belum disimpan): tampilkan '[Otomatis dibuat saat disimpan]'.
	 * 2. Jika pesanan sudah disimpan: tampilkan kode pesanan yang sebenarnya (misal TC-2026-0001).
	 * 3. Set attribute readonly dan styling disabled look.
	 */
	public function setup_order_title_ui(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'tc_order' !== $screen->post_type ) {
			return;
		}

		global $post;
		$display_title = '[Otomatis dibuat saat disimpan]';

		if ( $post && ! empty( $post->post_title ) && 'Auto Draft' !== $post->post_title && preg_match( '/^TC-\d{4}-\d{4,}$/', $post->post_title ) ) {
			$display_title = $post->post_title;
		}
		?>
		<style>
			#titlediv #title {
				background-color: #f0f0f1 !important;
				cursor: not-allowed !important;
				color: #1d2327 !important;
				font-weight: 700 !important;
				border-color: #8c8f94 !important;
			}
		</style>
		<script>
			jQuery(document).ready(function($) {
				var $title = $('#title');
				$title.prop('readonly', true);
				var currentVal = $title.val();
				if ( ! currentVal || currentVal === 'Auto Draft' || currentVal === '[Otomatis dibuat saat disimpan]' ) {
					$title.val('<?php echo esc_js( $display_title ); ?>');
					$('#title-prompt-text').addClass('screen-reader-text');
				}
			});
		</script>
		<?php
	}

	/**
	 * Filter post data sebelum di-insert ke database.
	 * Men-generate kode pesanan HANYA saat pesanan disimpan secara nyata (bukan auto-draft).
	 *
	 * @param array $data    An array of slashed, sanitized, and processed post data.
	 * @param array $postarr An array of sanitized (and slashed) but otherwise unmodified post data.
	 * @return array
	 */
	public function filter_post_data( array $data, array $postarr ): array {
		if ( 'tc_order' !== $data['post_type'] ) {
			return $data;
		}

		// Lewati auto-draft dan trash agar nomor urut tidak dikonsumsi saat cuma buka halaman.
		if ( 'auto-draft' === $data['post_status'] || 'trash' === $data['post_status'] ) {
			return $data;
		}

		// Jika judul sudah berformat valid TC-YYYY-XXXX (misal saat edit ulang), pertahankan judul tersebut.
		if ( ! empty( $data['post_title'] ) && preg_match( '/^TC-\d{4}-\d{4,}$/', $data['post_title'] ) ) {
			$data['post_name'] = sanitize_title( $data['post_title'] );
			return $data;
		}

		// Jika judul kosong, 'Auto Draft', atau teks placeholder, generate kode baru sekarang (saat simpan nyata).
		$order_code         = $this->get_next_order_code();
		$data['post_title'] = $order_code;
		$data['post_name']  = sanitize_title( $order_code );

		return $data;
	}

	/**
	 * Auto-generate kode pesanan unik sebagai fallback saat hook wp_insert_post terpanggil.
	 * Juga menyimpan kode ke post meta _tc_order_code sebagai audit trail dan
	 * kemudahan query tanpa JOIN ke wp_posts.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  True jika ini adalah update, false jika insert baru.
	 */
	public function generate_order_code( int $post_id, \WP_Post $post, bool $update ): void {
		if ( 'tc_order' !== $post->post_type ) {
			return;
		}

		// Jangan generate kode untuk auto-draft atau trash.
		if ( 'auto-draft' === $post->post_status || 'trash' === $post->post_status ) {
			return;
		}

		// Jika post_title sudah berformat kode pesanan yang benar, hanya sinkronisasi meta.
		if ( ! empty( $post->post_title ) && preg_match( '/^TC-\d{4}-\d{4,}$/', $post->post_title ) ) {
			// Pastikan meta tersinkronisasi meski kode sudah ada di title.
			if ( ! get_post_meta( $post_id, '_tc_order_code', true ) ) {
				update_post_meta( $post_id, '_tc_order_code', $post->post_title );
			}
			return;
		}

		global $wpdb;

		$order_code = $this->get_next_order_code();

		$wpdb->update(
			$wpdb->posts,
			array(
				'post_title' => $order_code,
				'post_name'  => sanitize_title( $order_code ),
			),
			array( 'ID' => $post_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Simpan kode ke post meta untuk audit trail dan kemudahan query.
		update_post_meta( $post_id, '_tc_order_code', $order_code );

		clean_post_cache( $post_id );
	}

	/**
	 * Add the order detail meta box.
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'tc_order_meta',
			'Detail Pesanan',
			array( $this, 'render_meta_box' ),
			'tc_order',
			'normal',
			'high'
		);
	}

	/**
	 * Render the order detail meta box.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'tc_order_save_meta', 'tc_order_meta_nonce' );

		// Ambil nilai yang sudah tersimpan.
		$customer_name     = get_post_meta( $post->ID, '_tc_customer_name', true );
		$customer_wa       = get_post_meta( $post->ID, '_tc_customer_wa', true );
		$delivery_area     = get_post_meta( $post->ID, '_tc_delivery_area', true );
		$delivery_address  = get_post_meta( $post->ID, '_tc_delivery_address', true );
		$order_status      = get_post_meta( $post->ID, '_tc_order_status', true );
		$order_items       = get_post_meta( $post->ID, '_tc_order_items', true );
		$total_price       = get_post_meta( $post->ID, '_tc_total_price', true );
		?>
		<style>
			#tc_order_meta .form-table th { width: 180px; }
			#tc_order_meta .tc-section-heading {
				font-size: 13px;
				font-weight: 600;
				color: #1d2327;
				border-bottom: 1px solid #dcdcde;
				padding-bottom: 6px;
				margin: 16px 0 4px;
			}
			#tc_order_meta .tc-section-heading:first-child { margin-top: 0; }
		</style>

		<p class="tc-section-heading">👤 Informasi Customer</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="tc_customer_name">Nama Customer</label>
				</th>
				<td>
					<input
						type="text"
						id="tc_customer_name"
						name="_tc_customer_name"
						value="<?php echo esc_attr( $customer_name ); ?>"
						class="regular-text"
						placeholder="Contoh: Budi Santoso"
					>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="tc_customer_wa">No. WhatsApp</label>
				</th>
				<td>
					<input
						type="text"
						id="tc_customer_wa"
						name="_tc_customer_wa"
						value="<?php echo esc_attr( $customer_wa ); ?>"
						class="regular-text"
						placeholder="Contoh: 08123456789"
					>
					<p class="description">Nomor WhatsApp customer tanpa tanda baca.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="tc_delivery_area">Area Pengiriman</label>
				</th>
				<td>
					<select id="tc_delivery_area" name="_tc_delivery_area">
						<option value="">— Pilih Area —</option>
						<?php foreach ( $this->delivery_areas as $value => $label ) : ?>
							<option
								value="<?php echo esc_attr( $value ); ?>"
								<?php selected( $delivery_area, $value ); ?>
							>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="tc_delivery_address">Detail Alamat</label>
				</th>
				<td>
					<textarea
						id="tc_delivery_address"
						name="_tc_delivery_address"
						rows="3"
						class="large-text"
						placeholder="Masukkan alamat lengkap pengiriman..."
					><?php echo esc_textarea( $delivery_address ); ?></textarea>
				</td>
			</tr>
		</table>

		<p class="tc-section-heading">📦 Detail Pesanan</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="tc_order_status">Status Pesanan</label>
				</th>
				<td>
					<select id="tc_order_status" name="_tc_order_status">
						<?php foreach ( $this->order_statuses as $value => $label ) : ?>
							<option
								value="<?php echo esc_attr( $value ); ?>"
								<?php selected( $order_status, $value ); ?>
							>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="tc_order_items">Item Pesanan</label>
				</th>
				<td>
					<textarea
						id="tc_order_items"
						name="_tc_order_items"
						rows="5"
						class="large-text"
						placeholder="Contoh:&#10;1x Bandung Cheesecuit&#10;2x Mochi Sukabumi&#10;1x Kue Balok Original"
					><?php echo esc_textarea( $order_items ); ?></textarea>
					<p class="description">Tulis setiap item di baris baru. Contoh: <code>1x Bandung Cheesecuit</code></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="tc_total_price">Total Harga</label>
				</th>
				<td>
					<input
						type="number"
						id="tc_total_price"
						name="_tc_total_price"
						value="<?php echo esc_attr( $total_price ); ?>"
						class="regular-text"
						min="0"
						step="1"
						placeholder="0"
					>
					<p class="description">Total harga pesanan dalam Rupiah (angka saja, tanpa titik/koma).</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save order meta data on post save.
	 * Menerapkan nonce verification, revision guard, autosave guard, dan capability check.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_meta( int $post_id ): void {
		// Lewati revisi — revision tidak menyimpan meta order.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Verifikasi nonce.
		if ( ! isset( $_POST['tc_order_meta_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['tc_order_meta_nonce'] ) ),
			'tc_order_save_meta'
		) ) {
			return;
		}

		// Lewati autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Cek permission.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// --- Simpan field teks sederhana ---
		$text_fields = array(
			'_tc_customer_name'    => 'sanitize_text_field',
			'_tc_customer_wa'      => 'sanitize_text_field',
			'_tc_delivery_address' => 'sanitize_textarea_field',
			'_tc_order_items'      => 'sanitize_textarea_field',
		);

		foreach ( $text_fields as $key => $sanitize_callback ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = $sanitize_callback( wp_unslash( $_POST[ $key ] ) );
				update_post_meta( $post_id, $key, $value );
			}
		}

		// --- Simpan total harga (integer non-negatif) ---
		if ( isset( $_POST['_tc_total_price'] ) ) {
			update_post_meta( $post_id, '_tc_total_price', abs( intval( $_POST['_tc_total_price'] ) ) );
		}

		// --- Simpan area pengiriman (whitelist validation) ---
		if ( isset( $_POST['_tc_delivery_area'] ) ) {
			$area_value = sanitize_text_field( wp_unslash( $_POST['_tc_delivery_area'] ) );
			if ( ! array_key_exists( $area_value, $this->delivery_areas ) ) {
				$area_value = '';
			}
			update_post_meta( $post_id, '_tc_delivery_area', $area_value );
		}

		// --- Simpan status pesanan (whitelist validation) ---
		if ( isset( $_POST['_tc_order_status'] ) ) {
			$status_value = sanitize_text_field( wp_unslash( $_POST['_tc_order_status'] ) );
			if ( ! array_key_exists( $status_value, $this->order_statuses ) ) {
				$status_value = 'pesanan_diterima';
			}
			update_post_meta( $post_id, '_tc_order_status', $status_value );
		}

		// --- Sinkronisasi _tc_order_code dari post_title untuk audit trail ---
		$post = get_post( $post_id );
		if ( $post && preg_match( '/^TC-\d{4}-\d{4,}$/', $post->post_title ) ) {
			update_post_meta( $post_id, '_tc_order_code', $post->post_title );
		}
	}

	/**
	 * Define custom admin columns for the order list table.
	 *
	 * @param  array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function set_custom_columns( array $columns ): array {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			// Sisipkan kolom custom setelah kolom title.
			if ( 'title' === $key ) {
				$new_columns['tc_customer'] = 'Customer';
				$new_columns['tc_wa']       = 'WhatsApp';
				$new_columns['tc_area']     = 'Area';
				$new_columns['tc_status']   = 'Status';
				$new_columns['tc_total']    = 'Total';
			}
		}

		return $new_columns;
	}

	/**
	 * Render content for custom admin columns.
	 *
	 * @param string $column  Column slug.
	 * @param int    $post_id Post ID.
	 */
	public function render_custom_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'tc_customer':
				$name = get_post_meta( $post_id, '_tc_customer_name', true );
				echo $name ? esc_html( $name ) : '<em>—</em>';
				break;

			case 'tc_wa':
				$wa = get_post_meta( $post_id, '_tc_customer_wa', true );
				if ( $wa ) {
					// Format nomor WA sebagai link wa.me agar bisa diklik langsung.
					$wa_clean = preg_replace( '/\D/', '', $wa );
					// Konversi awalan 08 ke 628 untuk format internasional.
					if ( str_starts_with( $wa_clean, '0' ) ) {
						$wa_clean = '62' . substr( $wa_clean, 1 );
					}
					printf(
						'<a href="https://wa.me/%s" target="_blank" rel="noopener noreferrer">%s</a>',
						esc_attr( $wa_clean ),
						esc_html( $wa )
					);
				} else {
					echo '<em>—</em>';
				}
				break;

			case 'tc_area':
				$area = get_post_meta( $post_id, '_tc_delivery_area', true );
				echo isset( $this->delivery_areas[ $area ] )
					? esc_html( $this->delivery_areas[ $area ] )
					: '<em>—</em>';
				break;

			case 'tc_status':
				$status = get_post_meta( $post_id, '_tc_order_status', true );
				if ( isset( $this->order_statuses[ $status ] ) ) {
					$badge_colors = array(
						'pesanan_diterima'  => '#2271b1', // biru
						'sedang_diproses'   => '#996800', // kuning tua
						'produk_dibeli'     => '#996800',
						'siap_dikirim'      => '#00a32a', // hijau
						'dalam_pengantaran' => '#00a32a',
						'selesai'           => '#1d2327', // hitam
						'refund'            => '#d63638', // merah
						'replacement'       => '#d63638',
					);
					$color = $badge_colors[ $status ] ?? '#1d2327';
					printf(
						'<span style="display:inline-block;padding:2px 8px;border-radius:3px;background:%s;color:#fff;font-size:11px;font-weight:600;">%s</span>',
						esc_attr( $color ),
						esc_html( $this->order_statuses[ $status ] )
					);
				} else {
					echo '<em>—</em>';
				}
				break;

			case 'tc_total':
				$price = get_post_meta( $post_id, '_tc_total_price', true );
				if ( $price ) {
					echo 'Rp ' . esc_html( number_format( (int) $price, 0, ',', '.' ) );
				} else {
					echo '<em>—</em>';
				}
				break;
		}
	}
}
