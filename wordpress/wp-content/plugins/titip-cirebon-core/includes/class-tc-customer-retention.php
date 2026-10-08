<?php
/**
 * TC_Customer_Retention — Customer Retention & Repeat Order Management.
 *
 * @package TitipCirebonCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the Customer Retention sub-menu under Dashboard TC.
 *
 * Aggregates completed orders (status: 'selesai') grouped by customer WhatsApp
 * number, identifies repeat customers, and provides a 1-click WhatsApp follow-up
 * action with a pre-filled promotional message.
 */
class TC_Customer_Retention {

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
	 * Constructor — register admin hooks.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_submenu' ) );
	}

	/**
	 * Register submenu under 'tc-dashboard'.
	 */
	public function register_admin_submenu(): void {
		add_submenu_page(
			'tc-dashboard',
			'Retensi Pelanggan — Titip Cirebon',
			'Retensi Pelanggan',
			'manage_options',
			'tc-customer-retention',
			array( $this, 'render_retention_page' )
		);
	}

	/**
	 * Render the Customer Retention admin page.
	 */
	public function render_retention_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki hak akses untuk membuka halaman ini.', 'titip-cirebon-core' ) );
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'all';
		if ( ! in_array( $current_tab, array( 'all', 'repeat', 'single' ), true ) ) {
			$current_tab = 'all';
		}

		$search_query = isset( $_GET['s'] ) ? trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : '';

		$data = $this->get_customer_retention_data( $current_tab, $search_query );
		?>
		<div class="wrap tc-retention-wrap">
			<h1 class="wp-heading-inline">Retensi Pelanggan & Follow-up WhatsApp</h1>
			<p class="description" style="margin-top: 4px; font-size: 14px; color: #64748b;">
				Identifikasi pelanggan yang sering membeli (repeat order) dari riwayat pesanan yang telah selesai dan lakukan follow-up PO mingguan langsung via WhatsApp.
			</p>
			<hr class="wp-header-end">

			<?php $this->render_styles(); ?>

			<!-- 3 Kartu Ringkasan Metrik -->
			<div class="tc-retention-metric-grid">
				<div class="tc-retention-card tc-card-blue">
					<span class="tc-retention-card__label">Total Pelanggan Unik</span>
					<div class="tc-retention-card__val"><?php echo esc_html( number_format_i18n( $data['total_unique_customers'] ) ); ?></div>
					<span class="tc-retention-card__sub">Berdasarkan nomor WhatsApp</span>
				</div>

				<div class="tc-retention-card tc-card-emerald">
					<span class="tc-retention-card__label">Repeat Customers (&gt;1 Order)</span>
					<div class="tc-retention-card__val"><?php echo esc_html( number_format_i18n( $data['repeat_customers_count'] ) ); ?></div>
					<span class="tc-retention-card__sub"><?php echo esc_html( $data['repeat_rate'] ); ?>% dari total pelanggan</span>
				</div>

				<div class="tc-retention-card tc-card-slate">
					<span class="tc-retention-card__label">Pelanggan 1x Order</span>
					<div class="tc-retention-card__val"><?php echo esc_html( number_format_i18n( $data['single_order_count'] ) ); ?></div>
					<span class="tc-retention-card__sub">Potensi re-engagement untuk PO baru</span>
				</div>
			</div>

			<!-- Filter Tabs & Search Bar -->
			<div class="tc-retention-filter-bar">
				<nav class="nav-tab-wrapper wp-clearfix tc-retention-tabs">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-customer-retention&tab=all' ) ); ?>" class="nav-tab <?php echo 'all' === $current_tab ? 'nav-tab-active' : ''; ?>">
						Semua Pelanggan (<?php echo esc_html( $data['total_unique_customers'] ); ?>)
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-customer-retention&tab=repeat' ) ); ?>" class="nav-tab <?php echo 'repeat' === $current_tab ? 'nav-tab-active' : ''; ?>">
						⭐ Repeat Customer (<?php echo esc_html( $data['repeat_customers_count'] ); ?>)
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-customer-retention&tab=single' ) ); ?>" class="nav-tab <?php echo 'single' === $current_tab ? 'nav-tab-active' : ''; ?>">
						Pelanggan 1x Order (<?php echo esc_html( $data['single_order_count'] ); ?>)
					</a>
				</nav>

				<form method="get" action="" class="tc-retention-search-form">
					<input type="hidden" name="page" value="tc-customer-retention">
					<input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>">
					<input
						type="search"
						name="s"
						value="<?php echo esc_attr( $search_query ); ?>"
						placeholder="Cari nama atau nomor WA..."
						class="regular-text"
						style="height: 32px; padding: 0 10px; font-size: 13px;"
					>
					<button type="submit" class="button">Cari</button>
					<?php if ( ! empty( $search_query ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-customer-retention&tab=' . $current_tab ) ); ?>" class="button button-link" style="margin-left: 4px;">Reset</a>
					<?php endif; ?>
				</form>
			</div>

			<!-- Tabel Pelanggan Native WP -->
			<div class="tc-retention-table-container">
				<?php if ( ! empty( $data['customers'] ) ) : ?>
					<table class="wp-list-table widefat fixed striped table-view-list">
						<thead>
							<tr>
								<th style="width: 220px;">Nama Pelanggan</th>
								<th style="width: 160px;">Nomor WhatsApp</th>
								<th style="width: 150px; text-align: center;">Frekuensi Order</th>
								<th style="width: 150px; text-align: right;">Total Belanja</th>
								<th>Produk Paling Sering Dipesan</th>
								<th style="width: 170px;">Terakhir Order</th>
								<th style="width: 180px; text-align: center;">Aksi Follow-up</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $data['customers'] as $customer ) : ?>
								<?php
								$is_repeat    = $customer['total_orders'] > 1;
								$wa_clean     = $customer['wa_clean'];
								$name_display = ! empty( $customer['name'] ) ? $customer['name'] : 'Pelanggan Tanpa Nama';

								// Template pesan WhatsApp ramah untuk penawaran PO mingguan baru.
								$wa_message = sprintf(
									'Halo %s, ini dari Titip Cirebon. Minggu ini kita open PO lagi lho! Ada menu baru yang mungkin cocok. Mau titip sesuatu minggu ini?',
									$name_display
								);
								$wa_link = 'https://wa.me/' . $wa_clean . '?text=' . rawurlencode( $wa_message );
								?>
								<tr>
									<td>
										<strong><?php echo esc_html( $name_display ); ?></strong>
										<?php if ( $is_repeat ) : ?>
											<div style="margin-top: 3px;">
												<span class="tc-badge-repeat">⭐ Repeat Customer</span>
											</div>
										<?php endif; ?>
									</td>
									<td>
										<a href="<?php echo esc_url( 'https://wa.me/' . $wa_clean ); ?>" target="_blank" rel="noopener noreferrer" style="font-family: monospace; font-size: 13px;">
											<?php echo esc_html( $customer['wa_raw'] ); ?>
										</a>
									</td>
									<td style="text-align: center;">
										<span class="tc-order-count-pill <?php echo $is_repeat ? 'tc-pill--repeat' : 'tc-pill--single'; ?>">
											<?php echo esc_html( $customer['total_orders'] ); ?>x Order
										</span>
									</td>
									<td style="text-align: right; font-weight: 600;">
										Rp <?php echo esc_html( number_format( (int) $customer['total_spent'], 0, ',', '.' ) ); ?>
									</td>
									<td>
										<?php if ( ! empty( $customer['top_item'] ) && '—' !== $customer['top_item'] ) : ?>
											<span title="Produk favorit yang paling sering dipesan">
												🛍️ <?php echo esc_html( $customer['top_item'] ); ?>
											</span>
										<?php else : ?>
											<span style="color: #94a3b8;">—</span>
										<?php endif; ?>
									</td>
									<td style="color: #64748b; font-size: 13px;">
										<?php echo esc_html( $customer['last_order_date'] ); ?>
									</td>
									<td style="text-align: center;">
										<a
											href="<?php echo esc_url( $wa_link ); ?>"
											target="_blank"
											rel="noopener noreferrer"
											class="tc-btn-wa-followup"
											title="Kirim pesan WhatsApp follow-up open PO mingguan"
										>
											<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" style="margin-right: 5px;">
												<path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
											</svg>
											Follow-up via WA
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<div class="tc-retention-empty-state">
						<div style="font-size: 36px; margin-bottom: 8px;">📭</div>
						<h3 style="margin: 0 0 4px; color: #1e293b;">Belum Ada Data Pelanggan</h3>
						<p style="margin: 0; color: #64748b;">
							<?php if ( ! empty( $search_query ) ) : ?>
								Tidak ditemukan pelanggan yang cocok dengan pencarian "<strong><?php echo esc_html( $search_query ); ?></strong>".
							<?php else : ?>
								Data retensi dihitung dari pesanan yang berstatus <strong>Selesai</strong>. Ketika pesanan selesai dicatat, pelanggan akan otomatis muncul di sini.
							<?php endif; ?>
						</p>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Aggregate customer retention metrics and list based on completed orders.
	 *
	 * @param string $tab Filter tab ('all', 'repeat', 'single').
	 * @param string $search Search query for customer name or WA.
	 * @return array<string, mixed>
	 */
	private function get_customer_retention_data( string $tab = 'all', string $search = '' ): array {
		$args = array(
			'post_type'      => 'tc_order',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$orders = get_posts( $args );

		$customers_map = array();

		foreach ( $orders as $order ) {
			// Hanya hitung pesanan yang berstatus 'selesai'.
			$order_status = get_post_meta( $order->ID, '_tc_order_status', true );
			if ( 'selesai' !== $order_status ) {
				continue;
			}

			$customer_name = get_post_meta( $order->ID, '_tc_customer_name', true );
			$customer_wa   = get_post_meta( $order->ID, '_tc_customer_wa', true );
			$total_price   = (int) get_post_meta( $order->ID, '_tc_total_price', true );
			$order_items   = get_post_meta( $order->ID, '_tc_order_items', true );
			$order_ts      = (int) get_post_time( 'U', true, $order );
			$order_date    = get_the_date( 'd M Y, H:i', $order );

			if ( empty( $customer_wa ) ) {
				continue;
			}

			// Normalisasi nomor WhatsApp ke format 628xxxxxxxx.
			$clean_wa = preg_replace( '/\D/', '', $customer_wa );
			if ( str_starts_with( $clean_wa, '0' ) ) {
				$clean_wa = '62' . substr( $clean_wa, 1 );
			}

			if ( empty( $clean_wa ) ) {
				continue;
			}

			if ( ! isset( $customers_map[ $clean_wa ] ) ) {
				$customers_map[ $clean_wa ] = array(
					'name'            => $customer_name,
					'wa_raw'          => $customer_wa,
					'wa_clean'        => $clean_wa,
					'total_orders'    => 0,
					'total_spent'     => 0,
					'last_order_ts'   => $order_ts,
					'last_order_date' => $order_date,
					'items_tally'     => array(),
				);
			}

			$customers_map[ $clean_wa ]['total_orders']++;
			$customers_map[ $clean_wa ]['total_spent'] += $total_price;

			// Perbarui nama dan tanggal ke order terbaru.
			if ( $order_ts >= $customers_map[ $clean_wa ]['last_order_ts'] ) {
				$customers_map[ $clean_wa ]['last_order_ts']   = $order_ts;
				$customers_map[ $clean_wa ]['last_order_date'] = $order_date;
				if ( ! empty( $customer_name ) ) {
					$customers_map[ $clean_wa ]['name'] = $customer_name;
				}
			}

			// Parse item pesanan.
			if ( ! empty( $order_items ) ) {
				$lines = array_filter( array_map( 'trim', explode( "\n", $order_items ) ) );
				foreach ( $lines as $line ) {
					$clean_item = preg_replace( '/^\d+\s*[xX]\s*/', '', $line );
					$clean_item = trim( $clean_item );
					if ( ! empty( $clean_item ) ) {
						$customers_map[ $clean_wa ]['items_tally'][ $clean_item ] = ( $customers_map[ $clean_wa ]['items_tally'][ $clean_item ] ?? 0 ) + 1;
					}
				}
			}
		}

		// Hitung produk favorit untuk setiap pelanggan.
		foreach ( $customers_map as $wa => &$cust ) {
			if ( ! empty( $cust['items_tally'] ) ) {
				arsort( $cust['items_tally'] );
				$top_keys         = array_keys( array_slice( $cust['items_tally'], 0, 1, true ) );
				$cust['top_item'] = $top_keys[0] ?? '—';
			} else {
				$cust['top_item'] = '—';
			}
		}
		unset( $cust );

		// Urutkan pelanggan: frekuensi order terbanyak dahulu, lalu transaksi terbaru.
		uasort(
			$customers_map,
			function ( $a, $b ) {
				if ( $a['total_orders'] === $b['total_orders'] ) {
					return $b['last_order_ts'] <=> $a['last_order_ts'];
				}
				return $b['total_orders'] <=> $a['total_orders'];
			}
		);

		$total_unique     = count( $customers_map );
		$repeat_customers = 0;
		$single_orders    = 0;

		foreach ( $customers_map as $cust ) {
			if ( $cust['total_orders'] > 1 ) {
				$repeat_customers++;
			} else {
				$single_orders++;
			}
		}

		$repeat_rate = $total_unique > 0 ? round( ( $repeat_customers / $total_unique ) * 100, 1 ) : 0;

		// Filter berdasarkan Tab dan Search.
		$filtered_customers = array();
		foreach ( $customers_map as $wa => $cust ) {
			// Tab filtering.
			if ( 'repeat' === $tab && $cust['total_orders'] <= 1 ) {
				continue;
			}
			if ( 'single' === $tab && $cust['total_orders'] > 1 ) {
				continue;
			}

			// Search filtering.
			if ( ! empty( $search ) ) {
				$match_name = stripos( $cust['name'], $search ) !== false;
				$match_wa   = stripos( $cust['wa_raw'], $search ) !== false || stripos( $cust['wa_clean'], $search ) !== false;
				if ( ! $match_name && ! $match_wa ) {
					continue;
				}
			}

			$filtered_customers[] = $cust;
		}

		return array(
			'total_unique_customers' => $total_unique,
			'repeat_customers_count' => $repeat_customers,
			'single_order_count'    => $single_orders,
			'repeat_rate'            => $repeat_rate,
			'customers'              => $filtered_customers,
		);
	}

	/**
	 * Scoped styles for the customer retention page.
	 */
	private function render_styles(): void {
		?>
		<style>
			.tc-retention-wrap {
				max-width: 1280px;
			}
			.tc-retention-metric-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
				gap: 16px;
				margin: 18px 0 24px;
			}
			.tc-retention-card {
				background: #ffffff;
				border: 1px solid #c3c4c7;
				border-left-width: 5px;
				border-radius: 4px;
				padding: 18px 20px;
				box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
			}
			.tc-card-blue { border-left-color: #2563eb; }
			.tc-card-emerald { border-left-color: #059669; }
			.tc-card-slate { border-left-color: #64748b; }
			.tc-retention-card__label {
				display: block;
				font-size: 13px;
				font-weight: 600;
				color: #64748b;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				margin-bottom: 6px;
			}
			.tc-retention-card__val {
				font-size: 26px;
				font-weight: 800;
				color: #1e293b;
				line-height: 1.2;
				margin-bottom: 6px;
			}
			.tc-retention-card__sub {
				display: block;
				font-size: 12px;
				color: #64748b;
			}
			.tc-retention-filter-bar {
				display: flex;
				justify-content: space-between;
				align-items: center;
				flex-wrap: wrap;
				gap: 12px;
				margin-bottom: 16px;
			}
			.tc-retention-tabs .nav-tab {
				font-size: 13px;
				padding: 6px 14px;
			}
			.tc-retention-search-form {
				display: flex;
				align-items: center;
				gap: 6px;
			}
			.tc-badge-repeat {
				display: inline-block;
				background: #fef3c7;
				color: #92400e;
				border: 1px solid #fde68a;
				font-size: 11px;
				font-weight: 700;
				padding: 2px 6px;
				border-radius: 4px;
			}
			.tc-order-count-pill {
				display: inline-block;
				padding: 3px 10px;
				border-radius: 12px;
				font-weight: 700;
				font-size: 12px;
			}
			.tc-pill--repeat {
				background: #dcfce7;
				color: #166534;
			}
			.tc-pill--single {
				background: #f1f5f9;
				color: #475569;
			}
			.tc-btn-wa-followup {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				background: #008a3c;
				color: #ffffff !important;
				text-decoration: none;
				padding: 6px 12px;
				font-size: 12px;
				font-weight: 600;
				border-radius: 4px;
				transition: background 0.15s;
			}
			.tc-btn-wa-followup:hover {
				background: #007031;
			}
			.tc-retention-empty-state {
				background: #ffffff;
				border: 1px dashed #cbd5e1;
				border-radius: 8px;
				padding: 40px 20px;
				text-align: center;
			}
			@media (max-width: 782px) {
				.tc-retention-filter-bar {
					flex-direction: column;
					align-items: stretch;
				}
				.tc-retention-search-form {
					width: 100%;
				}
				.tc-retention-search-form .regular-text {
					flex: 1;
				}
			}
		</style>
		<?php
	}
}
