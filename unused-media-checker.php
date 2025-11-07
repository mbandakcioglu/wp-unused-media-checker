<?php
/**
 * Plugin Name: Unused Media Checker
 * Description: Medya kütüphanesindeki kullanılmayan dosyaları bulur ve yönetir.
 * Version: 1.0.0
 * Author: Murat Bandakçıoğlu
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Direkt erişimi engelle

class Unused_Media_Checker {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // AJAX isteklerini sınıf içinde yakala
        add_action( 'wp_ajax_umc_scan', [ $this, 'ajax_scan' ] );
        add_action( 'wp_ajax_umc_delete', [ $this, 'ajax_delete' ] );
    }

    // Admin menüde sayfa ekle
    public function add_admin_menu() {
        add_menu_page(
            'Unused Media',
            'Unused Media',
            'manage_options',
            'unused-media-checker',
            [ $this, 'render_admin_page' ],
            'dashicons-trash',
            80
        );
    }

    // Basit admin arayüzü
    public function render_admin_page() {
        ?>
<div class="wrap">
    <h1>📁 Kullanılmayan Medya Dosyaları</h1>
    <p>Bu araç medya kütüphanesindeki kullanılmayan dosyaları tespit eder.</p>
    <button id="umc-scan-btn" class="button button-primary">Taramayı Başlat</button>
    <div id="umc-results" style="margin-top:20px;"></div>
</div>
<?php
    }

    // JS ve CSS
    public function enqueue_assets( $hook ) {
        if ( $hook !== 'toplevel_page_unused-media-checker' ) return;

        wp_enqueue_style( 'umc-style', plugin_dir_url( __FILE__ ) . 'assets/style.css' );
        wp_enqueue_script( 'umc-script', plugin_dir_url( __FILE__ ) . 'assets/script.js', ['jquery'], false, true );

        wp_localize_script( 'umc-script', 'umcAjax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'umc_scan_nonce' ),
        ] );
    }

    /**
     * AJAX callback to scan for attachments and generate HTML table output.
     *
     * This method queries a limited number of attachment posts and returns
     * a simple HTML table similar to the initial prototype.
     */
    public function ajax_scan() {
        check_ajax_referer( 'umc_scan_nonce', 'nonce' );

        // Perform WP_Query to retrieve attachments
        $query = new WP_Query([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 10,
        ]);

        $attachments = $query->posts;

        // Build output
        ob_start();
        echo '<table><tr><th>Önizleme</th><th>Dosya</th><th>Durum</th><th>İşlem</th></tr>';
		foreach ( $attachments as $att ) {
			if ( ! $this->is_media_used( $att->ID ) ) {
				$url = wp_get_attachment_url( $att->ID );
				$status = '<span style="color:red;">Kullanılmıyor</span>';

				echo '<tr>';
				echo '<td><img src="' . esc_url( $url ) . '" style="max-width:80px;"></td>';
				echo '<td>' . esc_html( $att->post_title ) . '</td>';
				echo '<td>' . $status . '</td>';
				echo '<td><button class="button delete-unused" data-id="' . esc_attr( $att->ID ) . '">Sil</button></td>';
				echo '</tr>';
			}
		}
        echo '</table>';
        $output = ob_get_clean();

        echo $output;
        wp_die();
    }

	public function ajax_delete() {
	    check_ajax_referer( 'umc_scan_nonce', 'nonce' );
	    if ( ! current_user_can( 'delete_posts' ) ) wp_send_json_error('Yetkiniz yok.');
	    $id = intval( $_POST['id'] ?? 0 );
	    if ( ! $id ) wp_send_json_error('Geçersiz ID.');
	    $deleted = wp_delete_attachment( $id, true );
	    if ( $deleted ) wp_send_json_success('Dosya silindi.');
	    else wp_send_json_error('Silme başarısız.');
	}

	private function is_media_used( $attachment_id ) {
		global $wpdb;

		$url      = wp_get_attachment_url( $attachment_id );
		$basename = basename( $url );

		// İçeriklerde tam URL veya dosya adı geçiyor mu?
		$in_posts = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			WHERE post_content LIKE %s OR post_content LIKE %s",
			'%' . $wpdb->esc_like( $url ) . '%',
			'%' . $wpdb->esc_like( $basename ) . '%'
		) );
		if ( $in_posts > 0 ) {
			return true;
		}

		// Postmeta'da URL, ID veya virgüllü ID listelerinde arama
		$in_meta = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			WHERE meta_value LIKE %s
			OR meta_value LIKE %s
			OR meta_value REGEXP %s",
			'%' . $wpdb->esc_like( $url ) . '%',
			'%' . $wpdb->esc_like( $basename ) . '%',
			'(^|,|:|\\s)' . $attachment_id . '($|,|;|\\s|\\})'
		) );
		if ( $in_meta > 0 ) {
			return true;
		}

		// Elementor özel alanı (JSON içi ID)
		$elementor_check = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			WHERE meta_key = '_elementor_data'
			AND (meta_value LIKE %s OR meta_value LIKE %s)",
			'%\"id\":' . $attachment_id . '%',
			'%' . $wpdb->esc_like( $url ) . '%'
		) );
		if ( $elementor_check > 0 ) {
			return true;
		}

		// ACF / thumbnail kontrolü
		$acf_check = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			WHERE meta_value = %d OR (meta_key = '_thumbnail_id' AND meta_value = %d)",
			$attachment_id, $attachment_id
		) );
		if ( $acf_check > 0 ) {
			return true;
		}

		return false;
	}
}

// Sınıfı başlat
new Unused_Media_Checker();