<?php
/**
 * TC_Shortcode_Tracking — Public order tracking shortcode [tc_order_tracking].
 *
 * @package TitipCirebonCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the [tc_order_tracking] shortcode.
 *
 * Allows customers to track their order status publicly on /lacak-pesanan/
 * using only their Order Code (e.g., TC-2026-0001) without logging in.
 *
 * SECURITY & PRIVACY NOTICE:
 * To prevent IDOR and PII data leaks, this shortcode NEVER displays
 * sensitive data such as customer full name, WhatsApp number, delivery address,
 * or internal cost price.
 */
class TC_Shortcode_Tracking {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Standard timeline steps in order of progression.
	 *
	 * @var array<string, array{label: string, desc: string}>
	 */
	private array $timeline_steps = array(
		'pesanan_diterima'  => array(
			'label' => 'Pesanan Diterima',
			'desc'  => 'Pesanan telah dicatat dan dikonfirmasi admin.',
		),
		'sedang_diproses'   => array(
			'label' => 'Sedang Diproses',
			'desc'  => 'Pesanan sedang disiapkan untuk jadwal pembelian.',
		),
		'produk_dibeli'     => array(
			'label' => 'Produk Dibeli',
			'desc'  => 'Item pesanan Anda telah dibeli oleh tim jastip.',
		),
		'siap_dikirim'      => array(
			'label' => 'Siap Dikirim',
			'desc'  => 'Pesanan sudah dikemas rapi dan siap diantarkan.',
		),
		'dalam_pengantaran' => array(
			'label' => 'Dalam Pengantaran',
			'desc'  => 'Kurir sedang dalam perjalanan mengantar pesanan.',
		),
		'selesai'           => array(
			'label' => 'Selesai',
			'desc'  => 'Pesanan telah diterima. Terima kasih telah memesan!',
		),
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
	 * Constructor — register the shortcode.
	 */
	private function __construct() {
		add_shortcode( 'tc_order_tracking', array( $this, 'render' ) );
	}

	/**
	 * Render the order tracking form and result.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render( $atts = array() ): string {
		$kode_pesanan = isset( $_GET['kode_pesanan'] )
			? strtoupper( trim( sanitize_text_field( wp_unslash( $_GET['kode_pesanan'] ) ) ) )
			: '';

		$no_wa = isset( $_GET['no_wa'] )
			? sanitize_text_field( wp_unslash( $_GET['no_wa'] ) )
			: '';

		$order_post = null;
		$searched   = ! empty( $kode_pesanan ) && ! empty( $no_wa );
		$is_partial = ( ! empty( $kode_pesanan ) && empty( $no_wa ) ) || ( empty( $kode_pesanan ) && ! empty( $no_wa ) );

		if ( $searched ) {
			global $wpdb;
			$post_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					 WHERE post_type = 'tc_order'
					   AND post_status NOT IN ('auto-draft', 'trash', 'inherit')
					   AND (
						   post_title = %s
						   OR ID IN (
							   SELECT post_id FROM {$wpdb->postmeta}
							   WHERE meta_key = '_tc_order_code' AND meta_value = %s
						   )
					   )
					 LIMIT 1",
					$kode_pesanan,
					$kode_pesanan
				)
			);

			if ( $post_id ) {
				// Verifikasi kepemilikan via No. WhatsApp — cegah IDOR.
				$stored_wa = get_post_meta( (int) $post_id, '_tc_customer_wa', true );
				if ( $this->normalize_phone_number( $no_wa ) === $this->normalize_phone_number( (string) $stored_wa ) ) {
					$order_post = get_post( (int) $post_id );
				}
			}
		}

		ob_start();
		?>
		<div class="tc-order-tracking">
			<?php $this->render_styles(); ?>

			<!-- Form Pencarian -->
			<div class="tc-tracking-search-box">
				<h2 class="tc-tracking-title">Lacak Pesanan Anda</h2>
				<p class="tc-tracking-subtitle">
					Masukkan Kode Pesanan yang Anda terima dari admin dan Nomor WhatsApp terdaftar untuk melihat status pesanan Anda.
				</p>

				<form method="get" action="" class="tc-tracking-form">
					<div class="tc-tracking-input-group">
						<div class="tc-tracking-field">
							<input
								type="text"
								name="kode_pesanan"
								value="<?php echo esc_attr( $kode_pesanan ); ?>"
								placeholder="Kode Pesanan (TC-2026-0001)"
								class="tc-tracking-input"
								required
								autocomplete="off"
							>
						</div>
						<div class="tc-tracking-field">
							<input
								type="text"
								name="no_wa"
								value="<?php echo esc_attr( $no_wa ); ?>"
								placeholder="No. WhatsApp (08123456789)"
								class="tc-tracking-input tc-tracking-input--phone"
								required
								autocomplete="off"
							>
						</div>
						<button type="submit" class="tc-tracking-btn">
							<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
								<circle cx="11" cy="11" r="8"></circle>
								<line x1="21" y1="21" x2="16.65" y2="16.65"></line>
							</svg>
							<span>Lacak Pesanan</span>
						</button>
					</div>
				</form>
			</div>

			<!-- Hasil Pencarian -->
			<?php if ( $searched ) : ?>
				<?php if ( $order_post ) : ?>
					<?php $this->render_order_details( $order_post ); ?>
				<?php else : ?>
					<div class="tc-tracking-alert tc-tracking-alert--error" role="alert">
						<div class="tc-tracking-alert__icon">⚠️</div>
						<div class="tc-tracking-alert__body">
							<strong>Pesanan Tidak Ditemukan</strong>
							<p>Pesanan tidak ditemukan. Pastikan kombinasi Kode Pesanan dan Nomor WhatsApp yang dimasukkan sudah sesuai. Hubungi admin kami via WhatsApp jika membutuhkan bantuan.</p>
						</div>
					</div>
				<?php endif; ?>
			<?php elseif ( $is_partial ) : ?>
				<div class="tc-tracking-alert tc-tracking-alert--warning" role="alert">
					<div class="tc-tracking-alert__icon">ℹ️</div>
					<div class="tc-tracking-alert__body">
						<strong>Verifikasi Diperlukan</strong>
						<p>Mohon lengkapi kedua kolom (Kode Pesanan dan Nomor WhatsApp) untuk melacak pesanan Anda demi keamanan data.</p>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the tracking details of a found order.
	 *
	 * SANGAT PENTING: Hanya menampilkan data publik (Kode, Tanggal, Status, Rincian Item).
	 * Nama, nomor WA, alamat detail, dan harga modal internal tidak ditampilkan.
	 *
	 * @param \WP_Post $post Order post object.
	 */
	private function render_order_details( \WP_Post $post ): void {
		$order_code   = $post->post_title;
		$order_date   = get_the_date( 'd M Y, H:i', $post );
		$order_status = get_post_meta( $post->ID, '_tc_order_status', true ) ?: 'pesanan_diterima';
		$order_items  = get_post_meta( $post->ID, '_tc_order_items', true );

		$is_special_status = in_array( $order_status, array( 'refund', 'replacement' ), true );
		$step_keys         = array_keys( $this->timeline_steps );
		$current_step_idx  = array_search( $order_status, $step_keys, true );
		if ( false === $current_step_idx ) {
			$current_step_idx = 0;
		}

		$status_labels = array(
			'pesanan_diterima'  => 'Pesanan Diterima',
			'sedang_diproses'   => 'Sedang Diproses',
			'produk_dibeli'     => 'Produk Dibeli',
			'siap_dikirim'      => 'Siap Dikirim',
			'dalam_pengantaran' => 'Dalam Pengantaran',
			'selesai'           => 'Selesai',
			'refund'            => 'Refund',
			'replacement'       => 'Replacement',
		);
		$current_label = $status_labels[ $order_status ] ?? 'Pesanan Diterima';
		?>
		<div class="tc-tracking-result-card">
			<!-- Header Kartu -->
			<div class="tc-tracking-card-header">
				<div class="tc-tracking-header-info">
					<span class="tc-tracking-label">Kode Pesanan</span>
					<h3 class="tc-tracking-code"><?php echo esc_html( $order_code ); ?></h3>
					<span class="tc-tracking-date">Dibuat pada: <?php echo esc_html( $order_date ); ?> WIB</span>
				</div>
				<div class="tc-tracking-status-badge tc-status--<?php echo esc_attr( sanitize_title( $order_status ) ); ?>">
					<?php echo esc_html( $current_label ); ?>
				</div>
			</div>

			<!-- Timeline Status -->
			<div class="tc-tracking-timeline-section">
				<h4 class="tc-section-title">Status Pengiriman</h4>

				<?php if ( $is_special_status ) : ?>
					<div class="tc-tracking-alert tc-tracking-alert--special tc-alert--<?php echo esc_attr( $order_status ); ?>">
						<div class="tc-tracking-alert__icon">
							<?php echo 'refund' === $order_status ? '↩️' : '🔄'; ?>
						</div>
						<div class="tc-tracking-alert__body">
							<strong>Status Pesanan: <?php echo esc_html( $current_label ); ?></strong>
							<p>
								<?php if ( 'refund' === $order_status ) : ?>
									Pesanan ini telah dibatalkan atau dana telah dikembalikan. Jika ada pertanyaan lebih lanjut, silakan hubungi admin kami via WhatsApp.
								<?php else : ?>
									Pesanan ini sedang dalam proses penggantian item produk (Replacement). Hubungi admin kami via WhatsApp untuk rincian selengkapnya.
								<?php endif; ?>
							</p>
						</div>
					</div>
				<?php else : ?>
					<!-- Visual Stepper Progress Bar -->
					<div class="tc-stepper">
						<?php
						$index = 0;
						foreach ( $this->timeline_steps as $key => $step ) :
							$is_completed = $index < $current_step_idx;
							$is_current   = $index === $current_step_idx;
							$step_class   = $is_completed ? 'tc-step--completed' : ( $is_current ? 'tc-step--current' : 'tc-step--upcoming' );
							?>
							<div class="tc-step <?php echo esc_attr( $step_class ); ?>">
								<div class="tc-step__indicator">
									<div class="tc-step__circle">
										<?php if ( $is_completed ) : ?>
											<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
												<polyline points="20 6 9 17 4 12"></polyline>
											</svg>
										<?php else : ?>
											<span><?php echo esc_html( $index + 1 ); ?></span>
										<?php endif; ?>
									</div>
									<?php if ( $index < count( $this->timeline_steps ) - 1 ) : ?>
										<div class="tc-step__line"></div>
									<?php endif; ?>
								</div>
								<div class="tc-step__content">
									<strong class="tc-step__title"><?php echo esc_html( $step['label'] ); ?></strong>
									<span class="tc-step__desc"><?php echo esc_html( $step['desc'] ); ?></span>
								</div>
							</div>
							<?php
							$index++;
						endforeach;
						?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Rincian Item Pesanan -->
			<?php if ( ! empty( $order_items ) ) : ?>
				<div class="tc-tracking-items-section">
					<h4 class="tc-section-title">Rincian Item Pesanan</h4>
					<div class="tc-tracking-items-list">
						<?php
						$items_lines = array_filter( array_map( 'trim', explode( "\n", $order_items ) ) );
						if ( ! empty( $items_lines ) ) :
							?>
							<ul>
								<?php foreach ( $items_lines as $item_line ) : ?>
									<li><?php echo esc_html( $item_line ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="tc-tracking-items-raw"><?php echo nl2br( esc_html( $order_items ) ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Bantuan / Hubungi Admin -->
			<div class="tc-tracking-help">
				<p>Ada kendala atau pertanyaan mengenai pesanan ini?
					<a href="https://wa.me/6285155092633?text=<?php echo rawurlencode( 'Halo Titip Cirebon, saya ingin menanyakan status pesanan saya dengan kode ' . $order_code ); ?>" target="_blank" rel="noopener noreferrer" class="tc-tracking-wa-link">
						Hubungi Admin WhatsApp
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Output scoped CSS styles for the order tracking widget.
	 */
	private function render_styles(): void {
		?>
		<style>
			.tc-order-tracking {
				max-width: 720px;
				margin: 24px auto;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
				color: #2c3338;
				line-height: 1.5;
			}
			.tc-tracking-search-box {
				background: #ffffff;
				border: 1px solid #e2e8f0;
				border-radius: 12px;
				padding: 28px 24px;
				box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
				margin-bottom: 24px;
				text-align: center;
			}
			.tc-tracking-title {
				margin: 0 0 8px;
				font-size: 24px;
				font-weight: 700;
				color: #1e293b;
			}
			.tc-tracking-subtitle {
				margin: 0 0 20px;
				font-size: 14px;
				color: #64748b;
			}
			.tc-tracking-form {
				margin: 0;
			}
			.tc-tracking-input-group {
				display: flex;
				gap: 12px;
				max-width: 660px;
				margin: 0 auto;
			}
			.tc-tracking-field {
				flex: 1;
				min-width: 0;
			}
			.tc-tracking-input {
				width: 100%;
				box-sizing: border-box;
				padding: 12px 16px;
				font-size: 15px;
				border: 2px solid #cbd5e1;
				border-radius: 8px;
				outline: none;
				transition: border-color 0.2s, box-shadow 0.2s;
				text-transform: uppercase;
				letter-spacing: 0.5px;
			}
			.tc-tracking-input--phone {
				text-transform: none;
				letter-spacing: normal;
			}
			.tc-tracking-input:focus {
				border-color: #2271b1;
				box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.15);
			}
			.tc-tracking-btn {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				gap: 8px;
				background: #008a3c;
				color: #ffffff;
				border: none;
				padding: 12px 20px;
				font-size: 14px;
				font-weight: 600;
				border-radius: 8px;
				cursor: pointer;
				transition: background-color 0.2s, transform 0.1s;
				white-space: nowrap;
			}
			.tc-tracking-btn:hover {
				background: #007031;
			}
			.tc-tracking-btn:active {
				transform: translateY(1px);
			}
			.tc-tracking-alert {
				display: flex;
				gap: 14px;
				padding: 16px 20px;
				border-radius: 10px;
				margin-bottom: 24px;
				background: #fef2f2;
				border: 1px solid #fecaca;
				color: #991b1b;
			}
			.tc-tracking-alert--error {
				background: #fff5f5;
				border: 1px solid #fed7d7;
				color: #c53030;
			}
			.tc-tracking-alert--warning {
				background: #fffbeb;
				border: 1px solid #fef3c7;
				color: #92400e;
			}
			.tc-tracking-alert__icon {
				font-size: 22px;
				line-height: 1;
			}
			.tc-tracking-alert__body p {
				margin: 4px 0 0;
				font-size: 14px;
				color: #4a5568;
			}
			.tc-tracking-alert--special.tc-alert--refund {
				background: #fff5f5;
				border-color: #feb2b2;
				color: #9b2c2c;
			}
			.tc-tracking-alert--special.tc-alert--replacement {
				background: #fffaf0;
				border-color: #fbd38d;
				color: #9c4221;
			}
			.tc-tracking-result-card {
				background: #ffffff;
				border: 1px solid #e2e8f0;
				border-radius: 12px;
				padding: 24px;
				box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
			}
			.tc-tracking-card-header {
				display: flex;
				justify-content: space-between;
				align-items: flex-start;
				padding-bottom: 20px;
				border-bottom: 1px solid #edf2f7;
				margin-bottom: 24px;
			}
			.tc-tracking-label {
				font-size: 12px;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				color: #64748b;
			}
			.tc-tracking-code {
				margin: 2px 0 4px;
				font-size: 22px;
				font-weight: 800;
				color: #0f172a;
				letter-spacing: 0.5px;
			}
			.tc-tracking-date {
				font-size: 13px;
				color: #64748b;
			}
			.tc-tracking-status-badge {
				display: inline-block;
				padding: 6px 14px;
				border-radius: 20px;
				font-size: 12px;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				color: #ffffff;
				background: #2271b1;
			}
			.tc-status--pesanan_diterima { background: #2563eb; }
			.tc-status--sedang_diproses,
			.tc-status--produk_dibeli { background: #d97706; }
			.tc-status--siap_dikirim,
			.tc-status--dalam_pengantaran { background: #059669; }
			.tc-status--selesai { background: #0f172a; }
			.tc-status--refund { background: #dc2626; }
			.tc-status--replacement { background: #ea580c; }
			.tc-section-title {
				margin: 0 0 16px;
				font-size: 15px;
				font-weight: 700;
				color: #334155;
				text-transform: uppercase;
				letter-spacing: 0.5px;
			}
			.tc-tracking-timeline-section {
				margin-bottom: 28px;
			}
			/* Stepper Styles */
			.tc-stepper {
				display: flex;
				flex-direction: column;
				gap: 0;
				position: relative;
			}
			.tc-step {
				display: flex;
				gap: 16px;
				position: relative;
			}
			.tc-step__indicator {
				display: flex;
				flex-direction: column;
				align-items: center;
				width: 32px;
				flex-shrink: 0;
			}
			.tc-step__circle {
				width: 28px;
				height: 28px;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 12px;
				font-weight: 700;
				background: #f1f5f9;
				color: #94a3b8;
				border: 2px solid #cbd5e1;
				z-index: 2;
				transition: all 0.2s;
			}
			.tc-step__line {
				width: 2px;
				flex-grow: 1;
				min-height: 28px;
				background: #e2e8f0;
				margin: 4px 0;
			}
			.tc-step__content {
				padding-bottom: 20px;
			}
			.tc-step__title {
				display: block;
				font-size: 14px;
				font-weight: 600;
				color: #94a3b8;
			}
			.tc-step__desc {
				display: block;
				font-size: 12px;
				color: #94a3b8;
				margin-top: 2px;
			}
			/* Step States */
			.tc-step--completed .tc-step__circle {
				background: #059669;
				border-color: #059669;
				color: #ffffff;
			}
			.tc-step--completed .tc-step__line {
				background: #059669;
			}
			.tc-step--completed .tc-step__title {
				color: #1e293b;
			}
			.tc-step--completed .tc-step__desc {
				color: #64748b;
			}
			.tc-step--current .tc-step__circle {
				background: #2563eb;
				border-color: #2563eb;
				color: #ffffff;
				box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.2);
			}
			.tc-step--current .tc-step__title {
				color: #2563eb;
				font-weight: 700;
			}
			.tc-step--current .tc-step__desc {
				color: #334155;
			}
			.tc-tracking-items-section {
				background: #f8fafc;
				border: 1px solid #e2e8f0;
				border-radius: 8px;
				padding: 16px 20px;
				margin-bottom: 20px;
			}
			.tc-tracking-items-list ul {
				margin: 0;
				padding-left: 20px;
			}
			.tc-tracking-items-list li {
				margin-bottom: 6px;
				font-size: 14px;
				color: #334155;
			}
			.tc-tracking-items-list li:last-child {
				margin-bottom: 0;
			}
			.tc-tracking-help {
				text-align: center;
				font-size: 13px;
				color: #64748b;
				margin-top: 20px;
				padding-top: 16px;
				border-top: 1px dashed #e2e8f0;
			}
			.tc-tracking-wa-link {
				color: #008a3c;
				font-weight: 600;
				text-decoration: underline;
			}
			.tc-tracking-wa-link:hover {
				color: #007031;
			}
			@media (max-width: 640px) {
				.tc-tracking-input-group {
					flex-direction: column;
				}
				.tc-tracking-btn {
					width: 100%;
				}
				.tc-tracking-card-header {
					flex-direction: column;
					gap: 12px;
				}
			}
		</style>
		<?php
	}

	/**
	 * Normalisasi nomor WhatsApp pelanggan untuk pencocokan yang andal.
	 *
	 * Menghapus semua karakter non-digit, dan menyelaraskan awalan internasional (+62/62)
	 * atau awalan tanpa nol (8...) menjadi format lokal Indonesia standar berawalan '0'.
	 *
	 * @param string $phone Nomor telepon mentah.
	 * @return string Nomor telepon yang telah dinormalisasi.
	 */
	private function normalize_phone_number( string $phone ): string {
		$digits = preg_replace( '/\D/', '', $phone );
		if ( str_starts_with( $digits, '62' ) ) {
			$digits = '0' . substr( $digits, 2 );
		} elseif ( str_starts_with( $digits, '8' ) ) {
			$digits = '0' . $digits;
		}
		return (string) $digits;
	}
}
