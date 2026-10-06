<?php
/**
 * TC_Product — Custom Post Type, Taxonomy, and Meta Box for products.
 *
 * @package TitipCirebonCore
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the tc_product CPT, tc_product_cat taxonomy, and product meta box.
 */
class TC_Product {

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
        add_action( 'init', array( $this, 'register_taxonomy' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_tc_product', array( $this, 'save_meta' ) );
    }

    /**
     * Register the tc_product custom post type.
     */
    public function register_post_type(): void {
        $labels = array(
            'name'                  => 'Produk',
            'singular_name'         => 'Produk',
            'menu_name'             => 'Produk',
            'name_admin_bar'        => 'Produk',
            'add_new'               => 'Tambah Baru',
            'add_new_item'          => 'Tambah Produk Baru',
            'new_item'              => 'Produk Baru',
            'edit_item'             => 'Edit Produk',
            'view_item'             => 'Lihat Produk',
            'all_items'             => 'Semua Produk',
            'search_items'          => 'Cari Produk',
            'not_found'             => 'Tidak ada produk ditemukan.',
            'not_found_in_trash'    => 'Tidak ada produk di tong sampah.',
            'featured_image'        => 'Foto Produk',
            'set_featured_image'    => 'Atur Foto Produk',
            'remove_featured_image' => 'Hapus Foto Produk',
            'use_featured_image'    => 'Gunakan sebagai Foto Produk',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'produk-item' ),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-cart',
            'supports'           => array( 'title', 'editor', 'thumbnail' ),
        );

        register_post_type( 'tc_product', $args );
    }

    /**
     * Register the tc_product_cat taxonomy.
     */
    public function register_taxonomy(): void {
        $labels = array(
            'name'              => 'Kategori Produk',
            'singular_name'     => 'Kategori Produk',
            'search_items'      => 'Cari Kategori',
            'all_items'         => 'Semua Kategori',
            'parent_item'       => 'Kategori Induk',
            'parent_item_colon' => 'Kategori Induk:',
            'edit_item'         => 'Edit Kategori',
            'update_item'       => 'Perbarui Kategori',
            'add_new_item'      => 'Tambah Kategori Baru',
            'new_item_name'     => 'Nama Kategori Baru',
            'menu_name'         => 'Kategori Produk',
        );

        $args = array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => array( 'slug' => 'kategori-produk' ),
        );

        register_taxonomy( 'tc_product_cat', 'tc_product', $args );
    }

    /**
     * Add the product detail meta box.
     */
    public function add_meta_boxes(): void {
        add_meta_box(
            'tc_product_meta',
            'Detail Produk',
            array( $this, 'render_meta_box' ),
            'tc_product',
            'normal',
            'high'
        );
    }

    /**
     * Render the product detail meta box.
     *
     * @param \WP_Post $post Current post object.
     */
    public function render_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'tc_product_save_meta', 'tc_product_meta_nonce' );

        $store_origin     = get_post_meta( $post->ID, '_tc_store_origin', true );
        $cost_price       = get_post_meta( $post->ID, '_tc_cost_price', true );
        $storage_duration = get_post_meta( $post->ID, '_tc_storage_duration', true );
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="tc_store_origin">Toko Asal</label>
                </th>
                <td>
                    <input
                        type="text"
                        id="tc_store_origin"
                        name="_tc_store_origin"
                        value="<?php echo esc_attr( $store_origin ); ?>"
                        class="regular-text"
                        placeholder="Contoh: Toko Oleh-oleh Cirebon"
                    >
                    <p class="description">Nama toko atau sumber produk ini.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tc_cost_price">Harga Modal Internal</label>
                </th>
                <td>
                    <input
                        type="number"
                        id="tc_cost_price"
                        name="_tc_cost_price"
                        value="<?php echo esc_attr( $cost_price ); ?>"
                        class="regular-text"
                        min="0"
                        step="1"
                        placeholder="0"
                    >
                    <p class="description">Harga modal dalam Rupiah. Data ini bersifat internal dan tidak ditampilkan di frontend.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="tc_storage_duration">Durasi Penyimpanan</label>
                </th>
                <td>
                    <input
                        type="text"
                        id="tc_storage_duration"
                        name="_tc_storage_duration"
                        value="<?php echo esc_attr( $storage_duration ); ?>"
                        class="regular-text"
                        placeholder="Contoh: 3 hari, 1 minggu"
                    >
                    <p class="description">Berapa lama produk ini dapat disimpan sebelum basi/kadaluarsa.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save product meta data on post save.
     *
     * @param int $post_id Post ID.
     */
    public function save_meta( int $post_id ): void {
        // Verify nonce.
        if ( ! isset( $_POST['tc_product_meta_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['tc_product_meta_nonce'] ) ),
            'tc_product_save_meta'
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

        // Define fields and their sanitization callbacks.
        $fields = array(
            '_tc_store_origin'     => 'sanitize_text_field',
            '_tc_cost_price'       => 'intval',
            '_tc_storage_duration' => 'sanitize_text_field',
        );

        foreach ( $fields as $key => $sanitize_callback ) {
            if ( isset( $_POST[ $key ] ) ) {
                $value = $sanitize_callback( wp_unslash( $_POST[ $key ] ) );
                update_post_meta( $post_id, $key, $value );
            }
        }
    }
}
