<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Alco_Main {
    private static $instance;
    private $option        = 'alco_max_emails';
    private $emails_option = 'alco_submitted_emails';
    private $default       = 100;

    public static function instance() {
        return self::$instance ?: ( self::$instance = new self );
    }

    public static function activate_plugin() {
        $self = new self;
        if ( get_option( $self->option ) === false ) {
            update_option( $self->option, $self->default );
        }
        if ( get_option( $self->emails_option ) === false ) {
            update_option( $self->emails_option, [] );
        }
    }

    private function __construct() {
        add_action( 'rest_api_init',        [ $this, 'register_rest' ] );
        add_action( 'wp_enqueue_scripts',   [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_menu',           [ $this, 'register_admin_page' ] );
        add_action( 'admin_post_alco_save_count', [ $this, 'handle_admin_save' ] );
        add_action( 'admin_post_alco_reset_emails', [ $this, 'handle_reset_emails' ] );
    }

    private function get_count() {
        return (int) get_option( $this->option, $this->default );
    }

    private function get_emails() {
        $emails = get_option( $this->emails_option, [] );
        return is_array( $emails ) ? $emails : [];
    }

    /** ✅ Decrease counter only if email not used before */
    public function decrease_count( $email ) {
        $email  = sanitize_email( $email );
        if ( ! $email ) return;

        $emails  = $this->get_emails();
        $current = $this->get_count();

        if ( $current > 0 && ! in_array( $email, $emails, true ) ) {
            $emails[] = $email;
            update_option( $this->emails_option, $emails );
            update_option( $this->option, $current - 1 );
        }
    }

    /** REST endpoints */
    public function register_rest() {
        // ✅ Get remaining count
        register_rest_route( 'alco/v1', '/count', [
            'methods'             => 'GET',
            'callback'            => fn() => [ 'count' => $this->get_count() ],
            'permission_callback' => '__return_true',
        ] );

        // ✅ Decrease count (with email)
        register_rest_route( 'alco/v1', '/decrease', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rest_decrease_count' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function rest_decrease_count( \WP_REST_Request $request ) {
        $email = sanitize_email( $request->get_param( 'email' ) );
        if ( $email ) {
            $this->decrease_count( $email );
        }
        return [ 'count' => $this->get_count() ];
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'alco-counter',
            ALCO_PLUGIN_URL . 'assets/js/frontend.js',
            [ 'jquery' ],
            time(),
            true
        );

        wp_localize_script( 'alco-counter', 'alcoData', [
            'restUrl'     => esc_url_raw( rest_url( 'alco/v1/count' ) ),
            'decreaseUrl' => esc_url_raw( rest_url( 'alco/v1/decrease' ) ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
        ] );
    }

    public function register_admin_page() {
        add_menu_page(
            'Serve Tech Alco',
            'Alco Count Settings',
            'manage_options',
            'serve-tech-alco',
            [ $this, 'render_admin_page' ],
            'dashicons-email-alt',
            80
        );
    }

    public function render_admin_page() {
        $current = $this->get_count();
        $emails  = $this->get_emails(); ?>
        <div class="wrap">
            <h1>Serve Tech Alco Settings</h1>
            <p><strong>Current Remaining Emails:</strong> <?php echo esc_html( $current ); ?></p>

            <!-- ✅ Form to update count -->
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
                    <button type="submit" name="alco_reset" value="1" class="button">Reset Count to 100</button>
                </p>
            </form>

            <!-- ✅ NEW: Form to reset emails only -->
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px;">
                <?php wp_nonce_field( 'alco_reset_emails', 'alco_reset_emails_nonce' ); ?>
                <input type="hidden" name="action" value="alco_reset_emails">
                <p>
                    <button type="submit" class="button button-secondary"
                            onclick="return confirm('Are you sure you want to clear all submitted emails?');">
                        Reset Emails Only
                    </button>
                </p>
            </form>

            <h2>Emails Already Submitted:</h2>
            <?php if ( $emails ) : ?>
                <ul style="max-height:200px;overflow:auto;background:#fff;padding:10px;border:1px solid #ccc;">
                    <?php foreach ( $emails as $email ) : ?>
                        <li><?php echo esc_html( $email ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><em>No emails stored yet.</em></p>
            <?php endif; ?>
        </div>
    <?php }


    public function handle_admin_save() {
        if (
            ! current_user_can( 'manage_options' ) ||
            ! check_admin_referer( 'alco_save_count', 'alco_nonce' )
        ) {
            wp_die( 'Not allowed' );
        }

        if ( isset( $_POST['alco_reset'] ) ) {
            // 🔹 Reset ONLY the count, DO NOT touch the emails
            update_option( $this->option, $this->default );
        } elseif ( isset( $_POST['alco_new_count'] ) ) {
            $new = max( 0, intval( $_POST['alco_new_count'] ) );
            update_option( $this->option, $new );
        }

        wp_redirect( admin_url( 'admin.php?page=serve-tech-alco&updated=true' ) );
        exit;
    }


    public function handle_reset_emails() {
        if (
            ! current_user_can( 'manage_options' ) ||
            ! check_admin_referer( 'alco_reset_emails', 'alco_reset_emails_nonce' )
        ) {
            wp_die( 'Not allowed' );
        }

        // ✅ Clear only emails, keep count untouched
        update_option( $this->emails_option, [] );

        wp_redirect( admin_url( 'admin.php?page=serve-tech-alco&emails_cleared=true' ) );
        exit;
    }
}
