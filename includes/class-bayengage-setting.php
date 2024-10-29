<?php
/**
 * Plugin Name: Sample WooComm Settings Plugin
 * Plugin URI: http://www.skyverge.com/
 * Description: A sample of a new working settings tab
 * Author: SkyVerge
 * Author URI: http://www.skyverge.com/
 * Version: 1.0
 * Text Domain: my-textdomain
 * Domain Path: /i18n/languages/
 *
 * @package   My-Plugin/Admin
 * @author    SkyVerge
 * @category  Admin
 * @copyright Copyright (c) 2016, SkyVerge, Inc
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


class BE_WC_Settings_Page extends WC_Settings_Page {


    /**
     * Meta key for the User ID of the successful connection.
     *
     * @since 2019-03-21
     * @var   string
     */
    const BEM_CONNECTION_STATUS = 'bem_woo_api_connection_status';

    /**
     * Meta key for the User ID of the successful connection.
     *
     * @since 2019-03-21
     * @var   string
     */
    const BEM_CONNECTION_USER_ID = 'bem_woo_api_user_id';

    /**
     * Setup settings class
     *
     * @since  1.0
     */
    public function __construct() {

        $this->id    = 'bayengage';
        $this->label = __( 'BayEngage', 'BayEngage- Email Marketing' );

        add_filter( 'woocommerce_settings_tabs_array',        array( $this, 'add_settings_page' ), 99 );
        add_action( 'woocommerce_settings_' . $this->id,      array( $this, 'output' ), 1 );

        add_action( "woocommerce_sections_{$this->id}", [ $this, 'maybe_update_connection_status' ], 1 );

        add_action( "woocommerce_sections_{$this->id}", [ $this, 'add_cc_connection_button' ] );

        add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
        add_action( 'woocommerce_sections_' . $this->id,      array( $this, 'output_sections' ) );
    }

    public function add_settings_page( $settings_tabs ) {
        $settings_tabs[$this->id] = __( 'Bayengage', 'bayengage' );
        return $settings_tabs;
    }

    /**
     * Get settings array
     *
     * @since 1.0.0
     * @param string $current_section Optional. Defaults to empty string.
     * @return array Array of settings
     */
    public function get_settings( $current_section = '' ) {

        /**
         * Filter Plugin Section 1 Settings
         *
         * @since 1.0.0
         * @param array $settings Array of the plugin settings
         */
        $settings = $this->get_setting_options();
        $connected = get_option('BEM_CONNECTION_STATUS');

        if( $connected != 1) {

            return $this->get_default_settings();
        }
        /**
         * Filter MyPlugin Settings
         *
         * @since 1.0.0
         * @param array $settings Array of the plugin settings
         */
        //return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );

        return $this->get_filtered_settings( $settings );

    }

    private function get_filtered_settings( array $settings ) {

        /* This filter is documented in WooCommerce */
        return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $GLOBALS['current_section'] ?? '' );
    }


    /**
     * Output the settings
     *
     * @since 1.0
     */
    public function output() {

        global $current_section;

        $settings = $this->get_settings( $current_section );
        WC_Admin_Settings::output_fields( $settings );
    }


    /**
     * Listen for GET request that establishes connection.
     *
     * @author Jeremy Ward <jeremy.ward@webdevstudios.com>
     * @since  2019-03-21
     * @return void
     */
    public function maybe_update_connection_status() {
        $success = filter_input( INPUT_GET, 'success', FILTER_SANITIZE_NUMBER_INT );
        $installed = filter_input( INPUT_GET, 'plugin_installed', FILTER_SANITIZE_NUMBER_INT );
        $user_id = filter_input( INPUT_GET, 'user_id' );

        $logger = wc_get_logger();
        //$logger->debug('success status===', $success );
        //$logger->debug('user_id===', $user_id );

        if ( is_null( $user_id ) ) {
            //$logger->debug('failure===', $success );
            return;
        }

        if(is_null($success)) {
            $success = $installed;
        }

        $this->set_connection( $success, $user_id );
    }

    public function set_connection($status, $user_id) {
        $connected = get_option('BEM_CONNECTION_STATUS');
        $logger = wc_get_logger();


        if(!$connected) {
            update_option( 'BEM_CONNECTION_STATUS', $status );
            update_option( 'BEM_CONNECTION_USER_ID', $user_id );
        }
    }

    public function add_cc_connection_button() {
        $connected = get_option('BEM_CONNECTION_STATUS');

        $logger = wc_get_logger();

        $logger->debug( $connected );

        $value     = ($connected) ? 'disconnect' : 'connect';
        $message   = $connected
            ? esc_html__( 'Disconnect from BayEngage', 'cc-woo' )
            : esc_html__( 'Connect with BayEngage', 'cc-woo' );

        if (BAYENGAGE_ENVIRONMENT === 'LIVE'){
            $url = BAYENGAGE_LIVE_ONBOARD_URL;
        }else{
            $url = BAYENGAGE_DEV_ONBOARD_URL;
        }

        //wp_nonce_field( $this->nonce_action, $this->nonce_name );
        if($value === 'connect') {
            ?>
            <div style="padding: 1rem 0;">
                <a class="button button-primary" type="submit" name="cc_woo_action" value="<?php echo esc_attr( $value ); ?>" href= <?= $url ?> >
                    <?php echo esc_html( $message ); ?>
                </a>
                <span style="line-height:28px; margin-left:25px;">

                </span>
            </div>
            <?php
        }

    }

    public function get_default_settings() {
        return [
            [
                'title' => esc_html__( 'Your store is not connected to BayEngage. Please click above button', 'bayengage' ),
                'type'  => 'title',
                'id'    => 'bem_woo_connection_established_heading',
            ],
            [
                'type' => 'bem_cta_button',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'bem_woo_store_information_settings',
            ],
        ];
    }

    public function get_setting_options() {
        $readonly_from_general_settings = esc_html__( 'This field is read from your General settings.', 'bayengage' );

        return [
            [
                'title' => esc_html__( 'Congratulations! Your store is connected to BayEngage.', 'bem-woo' ),
                'type'  => 'title',
                'id'    => 'bem_woo_connection_established_heading',
            ],
            [
                'title'             => esc_html__( 'Your Store Reference Number', 'bayengage' ),
                'desc'              => '',
                'value'             => get_option('BEM_CONNECTION_USER_ID'),
                'type'              => 'text',
                'custom_attributes' => [
                    'readonly' => 'readonly',
                    'size'     => 10,
                ],
            ],
            [
                'type' => 'sectionend',
                'id'   => 'bem_woo_store_information_settings',
            ],
        ];
    }

    private function get_connection_established_options() {
        return [
            [
                'title' => esc_html__( 'Congratulations! Your store is connected to BayEngage.', 'bayengage' ),
                'type'  => 'title',
                'id'    => 'bem_woo_connection_established_heading',
            ],
            [
                'type' => 'bem_cta_button',
            ],
            [
                'type' => 'sectionend',
                'id'   => 'bem_woo_store_information_settings',
            ],
        ];
    }

}

return new BE_WC_Settings_Page();