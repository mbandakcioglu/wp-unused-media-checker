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
            'posts_per_page' => -1,
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
    $url      = wp_get_attachment_url( $attachment_id );
    $basename = basename( $url );

    // 1. Iterate through all posts
    $all_posts = get_posts([
        'post_type'      => 'any',
        'post_status'    => 'any',
        'numberposts'    => -1,
        'fields'         => 'ids',
    ]);

    foreach ( $all_posts as $post_id ) {
        $content = get_post_field( 'post_content', $post_id );
        if ( strpos( $content, $url ) !== false || strpos( $content, $basename ) !== false ) {
            return true;
        }

        // 2. Search in post meta (Elementor, ACF, WooCommerce, etc.)
        $meta = get_post_meta( $post_id );
        foreach ( $meta as $key => $values ) {
            foreach ( $values as $val ) {
                if ( is_serialized( $val ) ) {
                    $val = maybe_unserialize( $val );
                }
                if ( is_array( $val ) ) {
                    if ( $this->search_in_array( $val, $attachment_id, $url, $basename ) ) {
                        return true;
                    }
                } else {
                    if (
                        (string) $val === (string) $attachment_id ||
                        strpos( (string) $val, $url ) !== false ||
                        strpos( (string) $val, $basename ) !== false
                    ) {
                        return true;
                    }
                }
            }
        }
    }

    // 3. Check featured images
    $featured_query = new WP_Query([
        'meta_key'   => '_thumbnail_id',
        'meta_value' => $attachment_id,
        'post_type'  => 'any',
        'fields'     => 'ids',
    ]);

    if ( $featured_query->have_posts() ) {
        return true;
    }

    return false;
}

private function search_in_array( $array, $attachment_id, $url, $basename ) {
    foreach ( $array as $value ) {
        if ( is_array( $value ) ) {
            if ( $this->search_in_array( $value, $attachment_id, $url, $basename ) ) {
                return true;
            }
        } else {
            if (
                (string) $value === (string) $attachment_id ||
                strpos( (string) $value, $url ) !== false ||
                strpos( (string) $value, $basename ) !== false
            ) {
                return true;
            }
        }
    }
    return false;
}

private function try_decode_json( $string ) {
    // Try base64 decoding
    if ( base64_encode( base64_decode( $string, true ) ) === $string ) {
        $decoded = base64_decode( $string );
        $json = json_decode( $decoded, true );
        if ( json_last_error() === JSON_ERROR_NONE ) return $json;

        // Try gzip after base64 decode
        $gz = @gzuncompress( $decoded );
        if ( $gz !== false ) {
            $json = json_decode( $gz, true );
            if ( json_last_error() === JSON_ERROR_NONE ) return $json;
        }
    }

    // Try plain JSON decode
    $json = json_decode( $string, true );
    if ( json_last_error() === JSON_ERROR_NONE ) return $json;

    return null;
}
}

// Sınıfı başlat
new Unused_Media_Checker();
