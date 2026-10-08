<?php
/**
 * TC_Admin_Dashboard — Operational Dashboard for Titip Cirebon.
 *
 * @package TitipCirebonCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the Operational Dashboard page in WordPress Admin.
 *
 * Provides a clean overview of total orders, revenue, status breakdown,
 * delivery areas, top items, and recent orders for weekly operations.
 */
class TC_Admin_Dashboard {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Order status definitions and labels.
	 *
	 * @var array<string, array{label: string, color: string}>
	 */
	private array $order_statuses = array(
		'pesanan_diterima'  => array(
			'label' => 'Pesanan Diterima',
			'color' => '#2563eb', // Blue
		),
		'sedang_diproses'   => array(
			'label' => 'Sedang Diproses',
			'color' => '#d97706', // Amber
		),
		'produk_dibeli'     => array(
			'label' => 'Produk Dibeli',
			'color' => '#d97706', // Amber
		),
		'siap_dikirim'      => array(
			'label' => 'Siap Dikirim',
			'color' => '#059669', // Emerald
		),
		'dalam_pengantaran' => array(
			'label' => 'Dalam Pengantaran',
			'color' => '#059669', // Emerald
		),
		'selesai'           => array(
			'label' => 'Selesai',
			'color' => '#0f172a', // Slate/Black
		),
		'refund'            => array(
			'label' => 'Refund',
			'color' => '#dc2626', // Red
		),
		'replacement'       => array(
			'label' => 'Replacement',
			'color' => '#ea580c', // Orange
		),
	);

	/**
	 * Delivery area labels.
	 *
	 * @var array<string, string>
	 */
	private array $delivery_areas = array(
		'cimalaka'      => 'Cimalaka',
		'sumedang_kota' => 'Sumedang Kota',
		'lainnya'       => 'Lainnya',
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
	 * Constructor — register admin menu hooks.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
	}

	/**
	 * Register top-level page for Dashboard.
	 */
	public function register_admin_menu(): void {
		// Top-level Menu "Dashboard TC" (position 4, just above Products).
		add_menu_page(
			'Dashboard Operasional — Titip Cirebon',
			'Dashboard TC',
			'manage_options',
			'tc-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-chart-bar',
			4
		);
	}

	/**
	 * Render the operational dashboard page.
	 */
	public function render_dashboard_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Anda tidak memiliki hak akses untuk membuka halaman ini.', 'titip-cirebon-core' ) );
		}

		$period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'this_week';
		if ( ! in_array( $period, array( 'this_week', 'this_month', 'all' ), true ) ) {
			$period = 'this_week';
		}

		$data = $this->aggregate_order_data( $period );
		?>
		<div class="wrap tc-dashboard-wrap">
			<h1 class="wp-heading-inline">Dashboard Operasional Titip Cirebon</h1>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=tc_order' ) ); ?>" class="page-title-action">
				+ Tambah Pesanan Baru
			</a>
			<hr class="wp-header-end">

			<?php $this->render_styles(); ?>

			<!-- Filter Periode (Tabs) -->
			<nav class="nav-tab-wrapper wp-clearfix tc-dashboard-tabs" style="margin-top: 15px; margin-bottom: 20px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-dashboard&period=this_week' ) ); ?>" class="nav-tab <?php echo 'this_week' === $period ? 'nav-tab-active' : ''; ?>">
					📅 Minggu Ini
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-dashboard&period=this_month' ) ); ?>" class="nav-tab <?php echo 'this_month' === $period ? 'nav-tab-active' : ''; ?>">
					🗓️ Bulan Ini
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tc-dashboard&period=all' ) ); ?>" class="nav-tab <?php echo 'all' === $period ? 'nav-tab-active' : ''; ?>">
					📊 Semua Waktu
				</a>
			</nav>

			<!-- 4 Kartu Metrik Utama -->
			<div class="tc-metric-cards-grid">
				<div class="tc-metric-card tc-card-blue">
					<span class="tc-metric-card__title">Total Pesanan</span>
					<div class="tc-metric-card__value"><?php echo esc_html( number_format_i18n( $data['total_orders'] ) ); ?></div>
					<span class="tc-metric-card__sub"><?php echo esc_html( $data['period_label'] ); ?></span>
				</div>

				<div class="tc-metric-card tc-card-emerald">
					<span class="tc-metric-card__title">Total Penjualan</span>
					<div class="tc-metric-card__value">Rp <?php echo esc_html( number_format( (int) $data['total_revenue'], 0, ',', '.' ) ); ?></div>
					<span class="tc-metric-card__sub">Akumulasi nilai pesanan</span>
				</div>

				<div class="tc-metric-card tc-card-amber">
					<span class="tc-metric-card__title">Rata-rata per Pesanan</span>
					<div class="tc-metric-card__value">Rp <?php echo esc_html( number_format( (int) $data['avg_order_value'], 0, ',', '.' ) ); ?></div>
					<span class="tc-metric-card__sub">AOV (Average Order Value)</span>
				</div>

				<div class="tc-metric-card tc-card-indigo">
					<span class="tc-metric-card__title">Pesanan Aktif</span>
					<div class="tc-metric-card__value"><?php echo esc_html( number_format_i18n( $data['active_orders'] ) ); ?></div>
					<span class="tc-metric-card__sub">Sedang berjalan (belum selesai/refund)</span>
				</div>
			</div>

			<!-- Grid 2 Kolom: Status Breakdown & Area / Top Items -->
			<div class="tc-dashboard-columns">
				<!-- Kolom Kiri: Breakdown Status Pesanan -->
				<div class="tc-dashboard-col">
					<div class="postbox">
						<div class="postbox-header">
							<h2 class="hndle">📦 Breakdown Status Pesanan</h2>
						</div>
						<div class="inside" style="padding: 16px;">
							<table class="widefat striped" style="border: none;">
								<thead>
									<tr>
										<th>Status</th>
										<th style="text-align: right;">Jumlah</th>
										<th style="text-align: right;">Persentase</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $this->order_statuses as $status_key => $status_info ) : ?>
										<?php
										$count = $data['status_counts'][ $status_key ] ?? 0;
										$pct   = $data['total_orders'] > 0 ? round( ( $count / $data['total_orders'] ) * 100, 1 ) : 0;
										?>
										<tr>
											<td>
												<span class="tc-status-dot" style="background-color: <?php echo esc_attr( $status_info['color'] ); ?>;"></span>
												<strong><?php echo esc_html( $status_info['label'] ); ?></strong>
											</td>
											<td style="text-align: right;">
												<span class="tc-badge-count"><?php echo esc_html( $count ); ?></span>
											</td>
											<td style="text-align: right; color: #64748b;">
												<?php echo esc_html( $pct ); ?>%
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<!-- Kolom Kanan: Area Pengiriman & Ringkasan Item -->
				<div class="tc-dashboard-col">
					<!-- Sebaran Area Pengiriman -->
					<div class="postbox">
						<div class="postbox-header">
							<h2 class="hndle">📍 Sebaran Area Pengiriman</h2>
						</div>
						<div class="inside" style="padding: 16px;">
							<table class="widefat striped" style="border: none;">
								<thead>
									<tr>
										<th>Area</th>
										<th style="text-align: right;">Jumlah Pesanan</th>
										<th style="text-align: right;">Porsi</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $this->delivery_areas as $area_key => $area_label ) : ?>
										<?php
										$count = $data['area_counts'][ $area_key ] ?? 0;
										$pct   = $data['total_orders'] > 0 ? round( ( $count / $data['total_orders'] ) * 100, 1 ) : 0;
										?>
										<tr>
											<td><strong><?php echo esc_html( $area_label ); ?></strong></td>
											<td style="text-align: right;"><?php echo esc_html( $count ); ?></td>
											<td style="text-align: right; color: #64748b;"><?php echo esc_html( $pct ); ?>%</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>

					<!-- Ringkasan Item Terbanyak -->
					<div class="postbox">
						<div class="postbox-header">
							<h2 class="hndle">🛍️ Ringkasan Item Terbanyak Dicatat</h2>
						</div>
						<div class="inside" style="padding: 16px;">
							<?php if ( ! empty( $data['top_items'] ) ) : ?>
								<ol style="margin: 0; padding-left: 20px;">
									<?php foreach ( $data['top_items'] as $item_text => $item_count ) : ?>
										<li style="margin-bottom: 8px;">
											<strong><?php echo esc_html( $item_text ); ?></strong>
											<span style="color: #64748b; font-size: 12px;">(dicatat <?php echo esc_html( $item_count ); ?>x)</span>
										</li>
									<?php endforeach; ?>
								</ol>
							<?php else : ?>
								<p style="color: #64748b; margin: 0;">Belum ada item pesanan yang dicatat pada periode ini.</p>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<!-- Tabel 10 Pesanan Terbaru -->
			<div class="postbox" style="margin-top: 24px;">
				<div class="postbox-header" style="display: flex; justify-content: space-between; align-items: center; padding-right: 14px;">
					<h2 class="hndle">⏱️ 10 Pesanan Terbaru</h2>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=tc_order' ) ); ?>" style="font-size: 13px; text-decoration: none;">
						Lihat Semua Pesanan &rarr;
					</a>
				</div>
				<div class="inside" style="padding: 0; margin: 0;">
					<?php if ( ! empty( $data['recent_orders'] ) ) : ?>
						<table class="wp-list-table widefat fixed striped table-view-list">
							<thead>
								<tr>
									<th style="width: 140px;">Kode Pesanan</th>
									<th style="width: 160px;">Tanggal</th>
									<th>Nama Pelanggan</th>
									<th>Area</th>
									<th style="width: 160px;">Status</th>
									<th style="width: 140px; text-align: right;">Total</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $data['recent_orders'] as $order ) : ?>
									<?php
									$order_id      = $order->ID;
									$order_code    = $order->post_title;
									$customer_name = get_post_meta( $order_id, '_tc_customer_name', true ) ?: '—';
									$area_raw      = get_post_meta( $order_id, '_tc_delivery_area', true );
									$area_label    = $this->delivery_areas[ $area_raw ] ?? ( $area_raw ?: '—' );
									$status_raw    = get_post_meta( $order_id, '_tc_order_status', true ) ?: 'pesanan_diterima';
									$status_info   = $this->order_statuses[ $status_raw ] ?? array(
										'label' => $status_raw,
										'color' => '#64748b',
									);
									$total_price   = get_post_meta( $order_id, '_tc_total_price', true );
									$edit_url      = get_edit_post_link( $order_id );
									?>
									<tr>
										<td>
											<a href="<?php echo esc_url( $edit_url ); ?>" style="font-weight: 700;">
												<?php echo esc_html( $order_code ); ?>
											</a>
										</td>
										<td style="color: #64748b;">
											<?php echo esc_html( get_the_date( 'd M Y, H:i', $order ) ); ?>
										</td>
										<td>
											<?php echo esc_html( $customer_name ); ?>
										</td>
										<td>
											<?php echo esc_html( $area_label ); ?>
										</td>
										<td>
											<span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; color: #ffffff; background-color: <?php echo esc_attr( $status_info['color'] ); ?>;">
												<?php echo esc_html( $status_info['label'] ); ?>
											</span>
										</td>
										<td style="text-align: right; font-weight: 600;">
											<?php echo $total_price ? 'Rp ' . esc_html( number_format( (int) $total_price, 0, ',', '.' ) ) : '—'; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p style="padding: 20px; color: #64748b; margin: 0;">Tidak ada pesanan pada periode ini.</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Aggregate metrics and data based on period.
	 *
	 * @param string $period Filter period ('this_week', 'this_month', 'all').
	 * @return array<string, mixed>
	 */
	private function aggregate_order_data( string $period ): array {
		$now = current_time( 'timestamp' );

		$args = array(
			'post_type'      => 'tc_order',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$period_label = 'Semua Waktu';

		if ( 'this_week' === $period ) {
			$start_of_week = gmdate( 'Y-m-d 00:00:00', strtotime( 'monday this week', $now ) );
			$end_of_week   = gmdate( 'Y-m-d 23:59:59', strtotime( 'sunday this week', $now ) );

			$args['date_query'] = array(
				array(
					'after'     => $start_of_week,
					'before'    => $end_of_week,
					'inclusive' => true,
				),
			);
			$period_label       = 'Minggu Ini (' . gmdate( 'd M', strtotime( $start_of_week ) ) . ' - ' . gmdate( 'd M Y', strtotime( $end_of_week ) ) . ')';
		} elseif ( 'this_month' === $period ) {
			$start_of_month = gmdate( 'Y-m-01 00:00:00', $now );
			$end_of_month   = gmdate( 'Y-m-t 23:59:59', $now );

			$args['date_query'] = array(
				array(
					'after'     => $start_of_month,
					'before'    => $end_of_month,
					'inclusive' => true,
				),
			);
			$period_label       = 'Bulan Ini (' . gmdate( 'F Y', $now ) . ')';
		}

		$orders = get_posts( $args );

		$total_orders  = count( $orders );
		$total_revenue = 0;
		$active_orders = 0;

		$status_counts = array_fill_keys( array_keys( $this->order_statuses ), 0 );
		$area_counts   = array_fill_keys( array_keys( $this->delivery_areas ), 0 );
		$items_tally   = array();

		foreach ( $orders as $order ) {
			$price  = (int) get_post_meta( $order->ID, '_tc_total_price', true );
			$status = get_post_meta( $order->ID, '_tc_order_status', true ) ?: 'pesanan_diterima';
			$area   = get_post_meta( $order->ID, '_tc_delivery_area', true );
			$items  = get_post_meta( $order->ID, '_tc_order_items', true );

			$total_revenue += $price;

			if ( isset( $status_counts[ $status ] ) ) {
				$status_counts[ $status ]++;
			} else {
				$status_counts['pesanan_diterima']++;
			}

			// Active order count (anything not finished or refunded).
			if ( ! in_array( $status, array( 'selesai', 'refund' ), true ) ) {
				$active_orders++;
			}

			// Area breakdown.
			if ( isset( $area_counts[ $area ] ) ) {
				$area_counts[ $area ]++;
			} else {
				$area_counts['lainnya']++;
			}

			// Parse item lines for top items.
			if ( ! empty( $items ) ) {
				$lines = array_filter( array_map( 'trim', explode( "\n", $items ) ) );
				foreach ( $lines as $line ) {
					// Clean prefix numbers like "1x " or "2x " for cleaner aggregation.
					$clean_item = preg_replace( '/^\d+\s*[xX]\s*/', '', $line );
					$clean_item = trim( $clean_item );
					if ( ! empty( $clean_item ) ) {
						$items_tally[ $clean_item ] = ( $items_tally[ $clean_item ] ?? 0 ) + 1;
					}
				}
			}
		}

		// Sort items by count descending.
		arsort( $items_tally );
		$top_items = array_slice( $items_tally, 0, 5, true );

		$avg_order_value = $total_orders > 0 ? (int) round( $total_revenue / $total_orders ) : 0;
		$recent_orders   = array_slice( $orders, 0, 10 );

		return array(
			'total_orders'    => $total_orders,
			'total_revenue'   => $total_revenue,
			'avg_order_value' => $avg_order_value,
			'active_orders'   => $active_orders,
			'status_counts'   => $status_counts,
			'area_counts'     => $area_counts,
			'top_items'       => $top_items,
			'recent_orders'   => $recent_orders,
			'period_label'    => $period_label,
		);
	}

	/**
	 * Scoped styles for the operational dashboard.
	 */
	private function render_styles(): void {
		?>
		<style>
			.tc-dashboard-wrap {
				max-width: 1280px;
			}
			.tc-dashboard-tabs .nav-tab {
				font-size: 14px;
				padding: 8px 16px;
			}
			.tc-metric-cards-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
				gap: 16px;
				margin-bottom: 24px;
			}
			.tc-metric-card {
				background: #ffffff;
				border: 1px solid #c3c4c7;
				border-left-width: 5px;
				border-radius: 4px;
				padding: 18px 20px;
				box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
			}
			.tc-card-blue { border-left-color: #2271b1; }
			.tc-card-emerald { border-left-color: #008a3c; }
			.tc-card-amber { border-left-color: #d97706; }
			.tc-card-indigo { border-left-color: #6366f1; }
			.tc-metric-card__title {
				display: block;
				font-size: 13px;
				font-weight: 600;
				color: #64748b;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				margin-bottom: 6px;
			}
			.tc-metric-card__value {
				font-size: 26px;
				font-weight: 800;
				color: #1e293b;
				line-height: 1.2;
				margin-bottom: 6px;
			}
			.tc-metric-card__sub {
				display: block;
				font-size: 12px;
				color: #64748b;
			}
			.tc-dashboard-columns {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 20px;
			}
			.tc-status-dot {
				display: inline-block;
				width: 8px;
				height: 8px;
				border-radius: 50%;
				margin-right: 6px;
			}
			.tc-badge-count {
				display: inline-block;
				padding: 2px 8px;
				border-radius: 12px;
				background: #f1f5f9;
				font-weight: 700;
				font-size: 12px;
				color: #1e293b;
			}
			@media (max-width: 850px) {
				.tc-dashboard-columns {
					grid-template-columns: 1fr;
				}
			}
		</style>
		<?php
	}
}

