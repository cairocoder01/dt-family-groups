<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DT_Family_Groups_Menu {

    public $token      = 'dt_family_groups';
    public $page_title = 'Family Groups';

    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        $this->page_title = __( 'Family Groups', 'dt-family-groups' );
    }

    public function register_menu() {
        $this->page_title = __( 'Family Groups', 'dt-family-groups' );
        add_submenu_page(
            'dt_extensions',
            $this->page_title,
            $this->page_title,
            'manage_dt',
            $this->token,
            [ $this, 'content' ]
        );
    }

    public function extensions_menu() {}

    public function content() {
        if ( !current_user_can( 'manage_dt' ) ) {
            wp_die( 'You do not have sufficient permissions to access this page.' );
        }

        $tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
        $link = 'admin.php?page=' . $this->token . '&tab=';
        ?>
        <div class="wrap">
            <h2><?php echo esc_html( $this->page_title ) ?></h2>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_attr( $link ) ?>general"
                   class="nav-tab <?php echo esc_attr( $tab === 'general' ? 'nav-tab-active' : '' ); ?>">
                    <?php esc_html_e( 'General', 'dt-family-groups' ); ?>
                </a>
            </h2>
            <?php
            if ( $tab === 'general' ) {
                ( new DT_Family_Groups_Tab_General() )->content();
            }
            ?>
        </div>
        <?php
    }
}
DT_Family_Groups_Menu::instance();


class DT_Family_Groups_Tab_General {

    public function content() {
        ?>
        <div class="wrap">
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        <?php $this->main_column(); ?>
                    </div>
                    <div id="postbox-container-1" class="postbox-container">
                        <?php $this->right_column(); ?>
                    </div>
                    <div id="postbox-container-2" class="postbox-container"></div>
                </div>
            </div>
        </div>
        <?php
    }

    public function main_column() {
        $token = DT_Family_Groups_Menu::instance()->token;
        $this->process_form( $token );
        ?>
        <table class="widefat striped">
            <thead>
                <tr><th colspan="2"><?php esc_html_e( 'About', 'dt-family-groups' ); ?></th></tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="2">
                        <p>
                            <?php esc_html_e( 'The Family Groups plugin extends Disciple.Tools with family relationship tracking:', 'dt-family-groups' ); ?>
                        </p>
                        <ul style="list-style:disc;margin-left:1.5em;">
                            <li><?php esc_html_e( 'Contacts: Spouse, Parent, Children connections', 'dt-family-groups' ); ?></li>
                            <li><?php esc_html_e( 'Contacts: Marital Status (Single / Married / Divorced / Widowed)', 'dt-family-groups' ); ?></li>
                            <li><?php esc_html_e( 'Contacts: Family Issues tags', 'dt-family-groups' ); ?></li>
                            <li><?php esc_html_e( 'Groups: "Family" type option', 'dt-family-groups' ); ?></li>
                            <li><?php esc_html_e( 'Groups: Family Issues tags', 'dt-family-groups' ); ?></li>
                            <li><?php esc_html_e( 'Groups: Generational family tree visualization (family-type groups only)', 'dt-family-groups' ); ?></li>
                        </ul>
                    </td>
                </tr>
            </tbody>
        </table>
        <br>
        <?php
    }

    public function process_form( $token ) {
        // if ( isset( $_POST['dt_admin_form_nonce'] ) &&
            //  wp_verify_nonce( sanitize_key( wp_unslash( $_POST['dt_admin_form_nonce'] ) ), 'dt_admin_form' ) ) {
            // Reserved for future settings.
        // }
    }

    public function right_column() {
        ?>
        <table class="widefat striped">
            <thead>
                <tr><th><?php esc_html_e( 'Resources', 'dt-family-groups' ); ?></th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <a href="https://github.com/cairocoder01/dt-family-groups" target="_blank" rel="noopener">GitHub Repository</a>
                    </td>
                </tr>
                <tr>
                    <td>
                        <a href="https://disciple.tools" target="_blank" rel="noopener">Disciple.Tools Community</a>
                    </td>
                </tr>
                <tr>
                    <td>
                        <?php esc_html_e( 'Version', 'dt-family-groups' ); ?>: 0.1
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }
}
