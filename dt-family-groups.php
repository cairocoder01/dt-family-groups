<?php
/**
 * Plugin Name: Disciple.Tools - Family Groups
 * Plugin URI: https://github.com/cairocoder01/dt-family-groups
 * Description: Track family relationships, marital status, and family dynamics for contacts and groups in Disciple.Tools. Adds spouse/parent/child connections, marital status, family issue tags, and a visual generational family tree for family-type groups.
 * Text Domain: dt-family-groups
 * Domain Path: /languages
 * Version: 0.1
 * Author URI: https://github.com/cairocoder01
 * GitHub Plugin URI: https://github.com/cairocoder01/dt-family-groups
 * Requires at least: 4.7.0
 * Tested up to: 6.5
 *
 * @package Disciple_Tools
 * @link    https://github.com/cairocoder01
 * @license GPL-2.0 or later
 *          https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function dt_family_groups() {
    $required_dt_theme_version = '1.19';
    $wp_theme = wp_get_theme();
    $version  = $wp_theme->version;

    $is_theme_dt = class_exists( 'Disciple_Tools' );
    if ( $is_theme_dt && version_compare( $version, $required_dt_theme_version, '<' ) ) {
        add_action( 'admin_notices', 'dt_family_groups_hook_admin_notice' );
        add_action( 'wp_ajax_dismissed_notice_handler', 'dt_hook_ajax_notice_handler' );
        return false;
    }
    if ( !$is_theme_dt ) {
        return false;
    }

    if ( !defined( 'DT_FUNCTIONS_READY' ) ) {
        require_once get_template_directory() . '/dt-core/global-functions.php';
    }

    return DT_Family_Groups::instance();
}
add_action( 'after_setup_theme', 'dt_family_groups', 20 );

add_filter( 'dt_plugins', function ( $plugins ) {
    $plugin_data = get_file_data( __FILE__, [ 'Version' => 'Version', 'Plugin Name' => 'Plugin Name' ], false );
    $plugins['dt-family-groups'] = [
        'plugin_url' => trailingslashit( plugin_dir_url( __FILE__ ) ),
        'version'    => $plugin_data['Version'] ?? null,
        'name'       => $plugin_data['Plugin Name'] ?? null,
    ];
    return $plugins;
} );

class DT_Family_Groups {

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        $is_rest = dt_is_rest();

        if ( $is_rest && strpos( dt_get_url_path(), 'dt-family-groups' ) !== false ) {
            require_once( 'rest-api/family-rest-api.php' );
        }

        require_once( 'tile/custom-tile.php' );
        require_once( 'tile/family-group-tile.php' );

        if ( is_admin() ) {
            require_once( 'admin/admin-menu-and-tabs.php' );
            add_filter( 'plugin_row_meta', [ $this, 'plugin_description_links' ], 10, 4 );
        }

        $this->i18n();
    }

    public function plugin_description_links( $links_array, $plugin_file_name, $plugin_data, $status ) {
        if ( strpos( $plugin_file_name, basename( __FILE__ ) ) ) {
            $links_array[] = '<a href="https://disciple.tools">Disciple.Tools Community</a>';
        }
        return $links_array;
    }

    public static function activation() {}

    public static function deactivation() {
        delete_option( 'dismissed-dt-family-groups' );
    }

    public function i18n() {
        $domain = 'dt-family-groups';
        load_plugin_textdomain( $domain, false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) . 'languages' );
    }

    public function __toString() {
        return 'dt-family-groups';
    }

    public function __clone() {
        _doing_it_wrong( __FUNCTION__, 'Whoah, partner!', '0.1' );
    }

    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, 'Whoah, partner!', '0.1' );
    }

    public function __call( $method = '', $args = array() ) {
        _doing_it_wrong( 'DT_Family_Groups::' . esc_html( $method ), 'Method does not exist.', '0.1' );
        unset( $method, $args );
        return null;
    }
}

register_activation_hook( __FILE__, [ 'DT_Family_Groups', 'activation' ] );
register_deactivation_hook( __FILE__, [ 'DT_Family_Groups', 'deactivation' ] );

if ( ! function_exists( 'dt_family_groups_hook_admin_notice' ) ) {
    function dt_family_groups_hook_admin_notice() {
        $required_dt_theme_version = '1.19';
        $wp_theme      = wp_get_theme();
        $current_version = $wp_theme->version;
        $message = "'Disciple.Tools - Family Groups' plugin requires 'Disciple.Tools' theme to work. Please activate 'Disciple.Tools' theme or make sure it is the latest version.";
        if ( $wp_theme->get_template() === 'disciple-tools-theme' ) {
            $message .= ' ' . sprintf( esc_html( 'Current Disciple.Tools version: %1$s, required version: %2$s' ), esc_html( $current_version ), esc_html( $required_dt_theme_version ) );
        }
        if ( ! get_option( 'dismissed-dt-family-groups', false ) ) { ?>
            <div class="notice notice-error notice-dt-family-groups is-dismissible" data-notice="dt-family-groups">
                <p><?php echo esc_html( $message ); ?></p>
            </div>
            <script>
                jQuery(function($) {
                    $( document ).on( 'click', '.notice-dt-family-groups .notice-dismiss', function () {
                        $.ajax( ajaxurl, {
                            type: 'POST',
                            data: {
                                action: 'dismissed_notice_handler',
                                type: 'dt-family-groups',
                                security: '<?php echo esc_html( wp_create_nonce( 'wp_rest_dismiss' ) ) ?>'
                            }
                        });
                    });
                });
            </script>
        <?php }
    }
}

if ( !function_exists( 'dt_hook_ajax_notice_handler' ) ) {
    function dt_hook_ajax_notice_handler() {
        check_ajax_referer( 'wp_rest_dismiss', 'security' );
        if ( isset( $_POST['type'] ) ) {
            $type = sanitize_text_field( wp_unslash( $_POST['type'] ) );
            update_option( 'dismissed-' . $type, true );
        }
    }
}
