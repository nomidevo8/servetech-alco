<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Alco_Main {
    private static $instance;
    private $option  = 'alco_max_emails';
    private $emails_option = 'alco_submitted_emails';
    private $default = 100;

    public static function instance() {
        return self::$instance ?: ( self::$instance = new self );
    }

    /** Called from register_activation_hook in main file */
    public static function activate_plugin() {
        $self = new self;
        if ( get_option( $self->option ) === false ) {
            update_option( $self->option, $self->default );
        }
    }

    private function __construct() {
        // (Optional) Still keep the original hook for safety
        add_action(
            'forminator_custom_form_after_handle_submit',
            [ $this, 'decrease_count' ],
            10,
            3
        );

        // REST endpoints
        add_action( 'rest_api_init', [ $this, 'register_rest' ] );

        // Frontend JS
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        // Admin UI
        add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
        add_action( 'admin_post_alco_save_count', [ $this, 'handle_admin_save' ] );
    }

    /** Helpers */
    private function get_count() {
        return (int) get_option( $this->option, $this->default );
    }

    /** Reduce counter by 1 each submission */
    public function decrease_count( $entry = null, $form_id = null, $form_settings = null ) {
        $current = $this->get_count();
        if ( $current > 0 ) {
            update_option( $this->option, $current - 1 );
        }
    }

    /** REST endpoints */
    public function register_rest() {
        // ✅ Get live count
        register_rest_route( 'alco/v1', '/count', [
            'methods'             => 'GET',
            'callback'            => fn() => [ 'count' => $this->get_count() ],
            'permission_callback' => '__return_true',
        ] );

        // ✅ Decrease counter manually
        register_rest_route( 'alco/v1', '/decrease', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rest_decrease_count' ],
            'permission_callback' => '__return_true', // For public use, or add nonce check
        ] );
    }

    /** REST callback to decrease count */
    public function rest_decrease_count( \WP_REST_Request $request ) {
        $this->decrease_count();
        return [ 'count' => $this->get_count() ];
    }

    /** JS to update counter on frontend */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'alco-counter',
            ALCO_PLUGIN_URL . 'assets/js/frontend.js',
            [ 'jquery' ],
            ALCO_VERSION,
            true
        );
        wp_localize_script( 'alco-counter', 'alcoData', [
            'restUrl'     => esc_url_raw( rest_url( 'alco/v1/count' ) ),
            'decreaseUrl' => esc_url_raw( rest_url( 'alco/v1/decrease' ) ),
            'nonce'       => wp_create_nonce( 'wp_rest' ), // Optional security
        ] );
    }

    /** Admin page menu */
    public function register_admin_page() {
        add_menu_page(
            'Serve Tech Alco',
            'Alco Settings',
            'manage_options',
            'serve-tech-alco',
            [ $this, 'render_admin_page' ],
            'dashicons-email-alt',
            80
        );
    }

    /** Admin page HTML */
    public function render_admin_page() {
        $current = $this->get_count(); ?>
        <div class="wrap">
            <h1>Serve Tech Alco Settings</h1>
            <p><strong>Current Remaining Emails:</strong> <?php echo esc_html( $current ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'alco_save_count', 'alco_nonce' ); ?>
                <input type="hidden" name="action" value="alco_save_count">
                <p>
                    <label for="alco_new_count"><strong>Set New Count:</strong></label><br>
                    <input type="number" name="alco_new_count" id="alco_new_count"
                           value="<?php echo esc_attr( $current ); ?>" min="0" max="100">
                </p>
                <p>
                    <button type="submit" class="button button-primary">Save</button>
                    <button type="submit" name="alco_reset" value="1" class="button">Reset to 100</button>
                </p>
            </form>
        </div>
    <?php }

    /** Save from admin page */
    public function handle_admin_save() {
        if (
            ! current_user_can( 'manage_options' ) ||
            ! check_admin_referer( 'alco_save_count', 'alco_nonce' )
        ) {
            wp_die( 'Not allowed' );
        }

        if ( isset( $_POST['alco_reset'] ) ) {
            update_option( $this->option, $this->default );
        } elseif ( isset( $_POST['alco_new_count'] ) ) {
            $new = max( 0, intval( $_POST['alco_new_count'] ) );
            update_option( $this->option, $new );
        }
        wp_redirect( admin_url( 'admin.php?page=serve-tech-alco&updated=true' ) );
        exit;
    }
}
