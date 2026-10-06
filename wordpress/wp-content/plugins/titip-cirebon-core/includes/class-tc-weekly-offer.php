<?php
/**
 * TC_Weekly_Offer — Custom Post Type and Meta Box for weekly offers.
 *
 * @package TitipCirebonCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the tc_weekly_offer CPT, meta box, save logic, and admin columns.
 */
class TC_Weekly_Offer {

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
     * Constructor — register WordPress hooks.
     */
    private function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_tc_weekly_offer', array( $this, 'save_meta' ) );
        add_filter( 'manage_tc_weekly_offer_posts_columns', array( $this, 'set_custom_columns' ) );
        add_action( 'manage_tc_weekly_offer_posts_custom_column', array( $this, 'render_custom_column' ), 10, 2 );
    }

    /**
     * Register the tc_weekly_offer custom post type.
     */
    public function register_post_type(): void {
        $labels = array(
            'name'                  => 'Penawaran Mingguan',
            'singular_name'         => 'Penawaran Mingguan',
            'menu_name'             => 'Penawaran Mingguan',
            'name_admin_bar'        => 'Penawaran Mingguan',
            'add_new'               => 'Tambah Baru',
            'add_new_item'          => 'Tambah Penawaran Baru',
            'new_item'              => 'Penawaran Baru',
            'edit_item'             => 'Edit Penawaran',
            'view_item'             => 'Lihat Penawaran',
            'all_items'             => 'Semua Penawaran',
            'search_items'          => 'Cari Penawaran',
            'not_found'             => 'Tidak ada penawaran ditemukan.',
            'not_found_in_trash'    => 'Tidak ada penawaran di tong sampah.',
            'featured_image'        => 'Foto Penawaran',
            'set_featured_image'    => 'Atur Foto Penawaran',
            'remove_featured_image' => 'Hapus Foto Penawaran',
            'use_featured_image'    => 'Gunakan sebagai Foto Penawaran',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'penawaran' ),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 6,
            'menu_icon'          => 'dashicons-tag',
            'supports'           => array( 'title', 'thumbnail' ),
        );

        register_post_type( 'tc_weekly_offer', $args );
    }

    /**
     * Add the weekly offer meta box.
     */
    public function add_meta_boxes(): void {
        add_meta_box(
            'tc_weekly_offer_meta',
            'Detail Penawaran Mingguan',
            array( $this, 'render_meta_box' ),
            'tc_weekly_offer',
            'normal',
            'high'
        );
    }

    /**
     * Render the weekly offer meta box.
     *
     * @param \WP_Post $post Current post object.
     */
    public function render_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'tc_weekly_offer_save_meta', 'tc_weekly_offer_meta_nonce' );

        $linked_product_id = get_post_meta( $post->ID, '_tc_linked_product_id', true );
        $selling_price     = get_post_meta( $post->ID, '_tc_selling_price', true );
        $badge             = get_post_meta( $post->ID, '_tc_badge', true );
        $start_date        = get_post_meta( $post->ID, '_tc_offer_start_date', true );
        $end_date          = get_post_meta( $post->ID, '_tc_offer_end_date', true );
        $sort_order        = get_post_meta( $post->ID, '_tc_sort_order', true );

        // Query all published products for the dropdown.
        $products = get_posts( array(
            'post_type'      => 'tc_product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $badge_options = array(
            ''            => '— Tanpa Badge —',
            'Baru'        => 'Baru',
            'Populer'     => 'Populer',
            'Best Seller' => 'Best Seller',
            'Promo'       => 'Promo',
        );
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="tc_linked_product_id">Produk Terkait</label>
                </th>
                <td>
                    <select id="tc_linked_product_id" name="_tc_linked_product_id" class="regular-text">
                        <option value="">— Pilih Produk —</option>
                        <?php foreach ( $products as $product ) : ?>
                            <option
                                value="<?php echo esc_attr( $product->ID ); ?>"
                                <?php selected( $linked_product_id, $product->ID ); ?>
                            >
                                <?php echo esc_html( $product->post_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Pilih produk dari daftar Produk yang sudah dipublikasikan.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tc_selling_price">Harga Jual</label>
                </th>
                <td>
                    <input
                        type="number"
                        id="tc_selling_price"
                        name="_tc_selling_price"
                        value="<?php echo esc_attr( $selling_price ); ?>"
                        class="regular-text"
                        min="0"
                        step="1"
                        placeholder="0"
                    >
                    <p class="description">Harga jual untuk penawaran ini dalam Rupiah.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tc_badge">Badge</label>
                </th>
                <td>
                    <select id="tc_badge" name="_tc_badge">
                        <?php foreach ( $badge_options as $value => $label ) : ?>
                            <option
                                value="<?php echo esc_attr( $value ); ?>"
                                <?php selected( $badge, $value ); ?>
                            >
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Badge yang akan ditampilkan pada kartu produk di frontend.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tc_offer_start_date">Tanggal Mulai</label>
                </th>
                <td>
                    <input
                        type="date"
                        id="tc_offer_start_date"
                        name="_tc_offer_start_date"
                        value="<?php echo esc_attr( $start_date ); ?>"
                    >
                    <p class="description">Tanggal penawaran mulai ditampilkan di frontend.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tc_offer_end_date">Tanggal Berakhir</label>
                </th>
                <td>
                    <input
                        type="date"
                        id="tc_offer_end_date"
                        name="_tc_offer_end_date"
                        value="<?php echo esc_attr( $end_date ); ?>"
                    >
                    <p class="description">Tanggal penawaran berakhir dan tidak lagi ditampilkan.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tc_sort_order">Urutan Tampil</label>
                </th>
                <td>
                    <input
                        type="number"
                        id="tc_sort_order"
                        name="_tc_sort_order"
                        value="<?php echo esc_attr( $sort_order ); ?>"
                        class="small-text"
                        min="0"
                        step="1"
                        placeholder="0"
                    >
                    <p class="description">Angka kecil ditampilkan lebih dulu. Default: 0.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save weekly offer meta data on post save.
     *
     * @param int $post_id Post ID.
     */
    public function save_meta( int $post_id ): void {
        // Verify nonce.
        if ( ! isset( $_POST['tc_weekly_offer_meta_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['tc_weekly_offer_meta_nonce'] ) ),
            'tc_weekly_offer_save_meta'
        ) ) {
            return;
        }

        // Skip autosave.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Linked product ID.
        if ( isset( $_POST['_tc_linked_product_id'] ) ) {
            update_post_meta( $post_id, '_tc_linked_product_id', intval( $_POST['_tc_linked_product_id'] ) );
        }

        // Selling price.
        if ( isset( $_POST['_tc_selling_price'] ) ) {
            update_post_meta( $post_id, '_tc_selling_price', intval( $_POST['_tc_selling_price'] ) );
        }

        // Badge.
        if ( isset( $_POST['_tc_badge'] ) ) {
            $allowed_badges = array( '', 'Baru', 'Populer', 'Best Seller', 'Promo' );
            $badge_value    = sanitize_text_field( wp_unslash( $_POST['_tc_badge'] ) );
            if ( ! in_array( $badge_value, $allowed_badges, true ) ) {
                $badge_value = '';
            }
            update_post_meta( $post_id, '_tc_badge', $badge_value );
        }

        // Start date — validate Y-m-d format.
        if ( isset( $_POST['_tc_offer_start_date'] ) ) {
            $start = sanitize_text_field( wp_unslash( $_POST['_tc_offer_start_date'] ) );
            if ( $start && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) ) {
                $start = '';
            }
            update_post_meta( $post_id, '_tc_offer_start_date', $start );
        }

        // End date — validate Y-m-d format.
        if ( isset( $_POST['_tc_offer_end_date'] ) ) {
            $end = sanitize_text_field( wp_unslash( $_POST['_tc_offer_end_date'] ) );
            if ( $end && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ) {
                $end = '';
            }
            update_post_meta( $post_id, '_tc_offer_end_date', $end );
        }

        // Sort order.
        if ( isset( $_POST['_tc_sort_order'] ) ) {
            update_post_meta( $post_id, '_tc_sort_order', intval( $_POST['_tc_sort_order'] ) );
        }
    }

    /**
     * Define custom admin columns for the weekly offer list table.
     *
     * @param  array $columns Existing columns.
     * @return array Modified columns.
     */
    public function set_custom_columns( array $columns ): array {
        // Rebuild columns in desired order.
        $new_columns = array();

        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;

            // Insert custom columns after the title column.
            if ( 'title' === $key ) {
                $new_columns['tc_linked_product'] = 'Produk Terkait';
                $new_columns['tc_selling_price']  = 'Harga Jual';
                $new_columns['tc_badge']          = 'Badge';
                $new_columns['tc_start_date']     = 'Tanggal Mulai';
                $new_columns['tc_end_date']       = 'Tanggal Berakhir';
                $new_columns['tc_sort_order']     = 'Urutan';
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
            case 'tc_linked_product':
                $product_id = get_post_meta( $post_id, '_tc_linked_product_id', true );
                if ( $product_id ) {
                    $product = get_post( intval( $product_id ) );
                    if ( $product ) {
                        echo esc_html( $product->post_title );
                    } else {
                        echo '<em>' . esc_html( 'Produk dihapus' ) . '</em>';
                    }
                } else {
                    echo '—';
                }
                break;

            case 'tc_selling_price':
                $price = get_post_meta( $post_id, '_tc_selling_price', true );
                if ( $price ) {
                    echo 'Rp ' . esc_html( number_format( (int) $price, 0, ',', '.' ) );
                } else {
                    echo '—';
                }
                break;

            case 'tc_badge':
                $badge = get_post_meta( $post_id, '_tc_badge', true );
                echo $badge ? esc_html( $badge ) : '—';
                break;

            case 'tc_start_date':
                $date = get_post_meta( $post_id, '_tc_offer_start_date', true );
                echo $date ? esc_html( $date ) : '—';
                break;

            case 'tc_end_date':
                $date = get_post_meta( $post_id, '_tc_offer_end_date', true );
                echo $date ? esc_html( $date ) : '—';
                break;

            case 'tc_sort_order':
                $order = get_post_meta( $post_id, '_tc_sort_order', true );
                echo esc_html( $order ?: '0' );
                break;
        }
    }
}
