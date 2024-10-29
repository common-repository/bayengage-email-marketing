<?php

/**
 * The BayEngage bootstrap file
 *
 * @link              https://app.bayengage.com/
 * @since             1.8.4
 * @package           BayEngage: Email Marketing
 *
 * @wordpress-plugin
 * Plugin Name:       BayEngage: Email Marketing
 * Description:       Send email campaigns and newsletters. 250 free email templates
 * Version:           1.8.4
 * Author:            TargetBay, Inc
 * Author URI:        https://app.bayengage.com
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

require plugin_dir_path( __FILE__ ) . 'includes/class-bayengage-tracking.php';

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */


define( 'BAYENGAGE_EMAIL_MARKETING_VERSION', '1.8.4' );

define( 'BAYENGAGE_ENVIRONMENT', 'LIVE' );
define( 'BAYENGAGE_DEV_ONBOARD_URL', '' );
define( 'BAYENGAGE_LIVE_ONBOARD_URL', 'https://app.bayengage.com/signup/woocommerce/?w='.get_home_url() );
define( 'BAYENGAGE_DEV_POPUP_SCRIPT_URL', '' );
define( 'BAYENGAGE_LIVE_POPUP_SCRIPT_URL', 'https://sf.bayengage.com/sf.js?t=' );
define( 'BAYENGAGE_DEV_WEBHOOK_URL', '' );
define( 'BAYENGAGE_LIVE_WEBHOOK_URL', 'https://wh.bayengage.com/woocommerce?public_id=' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-bayengage-automation-activator.php
 */
function bem_do_activation_process() {
    update_option( 'BEM_JUST_INSTALLED', true );
    //$data = Email_Campaign_Automation_Activator::activate();
    // update_option( 'bem_redirect_url', $data );
}


// Your custom plugin code goes here.

// Function to check if the user is on the admin login page
function is_admin_login_page()
{
    $needle_array = ['maintain_t20', 'wp-admin'];
    $current_url = $_SERVER['REQUEST_URI'];

    foreach ($needle_array as $needle) {
        if (strpos($current_url, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function bem_do_plugin_after_install() {

    if ( ! is_admin_login_page()) {
        if ( !current_user_can( 'manage_options' ) && ! is_admin() ) {
            //Something other than an administrator
            $tenantUuid = get_option('BEM_CONNECTION_USER_ID');
            if (BAYENGAGE_ENVIRONMENT === 'LIVE'){
                wp_enqueue_script('bayengage-script', BAYENGAGE_LIVE_POPUP_SCRIPT_URL . $tenantUuid, [], false,true);
            }else{
                wp_enqueue_script('bayengage-script', BAYENGAGE_DEV_POPUP_SCRIPT_URL . $tenantUuid, [], false,true);
            }
            add_filter( 'script_loader_tag', 'my_script_attributes', 10, 3 );

            $logger = wc_get_logger();
            $logger->debug( 'bayengage before checkout tracking js');
            if (str_contains($_SERVER['REQUEST_URI'], 'checkout')) {
                add_action('wp_enqueue_scripts', 'enqueue_scripts');
            }

            new WC_BayEngage_Tracking();
        }
    }

    require_once plugin_dir_path( __FILE__ ) . 'includes/class-bayengage-activator.php';

    if ( get_option( 'BEM_JUST_INSTALLED', false ) ) {

        delete_option( 'BEM_JUST_INSTALLED' );
        $url = get_option('bem_redirect_url');
        $status = 302;
        //wp_redirect($url , $status);
        $uuid = get_option('BEM_CONNECTION_DEACTIVATE_USER_ID');

        if( ! $uuid) {
            wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=bayengage' ) );
        }else {
            //Reactivate webhook
            try {
                $dataList['shop_url'] = wp_guess_url();
                $dataList['action_webhook'] = 'app.reactivate';

                if (BAYENGAGE_ENVIRONMENT === 'LIVE'){
                    $baseUrl = BAYENGAGE_LIVE_WEBHOOK_URL.$uuid;
                }else{
                    $baseUrl = BAYENGAGE_DEV_WEBHOOK_URL.$uuid;
                }

                $headers = array(
                    'Content-Type' => 'application/json',
                    'x-wc-webhook-source' => $dataList['shop_url'],
                    'x-wc-webhook-topic' => $dataList['action_webhook']
                );
                $request = new WP_Http;
                $result = $request->request( $baseUrl, array( 'method' => 'POST', 'body' => wp_json_encode( $dataList ), 'headers' => $headers) );


            } catch (\Exception $e) {
                $errorMsg = ', Message: ' . $e->getMessage();
                $errorMsg .= ', Line: ' . $e->getLine();
            }


            wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=bayengage&success=1&user_id='.$uuid ) );
        }
    }
}

function get_current_product_ID() {
    global $post;
    $args = array( 'taxonomy' => 'product_cat',);
    $terms = wp_get_post_terms($post->ID,'product_cat', $args);
    $current_product_ID = $post->ID??0;
    $post_type = (is_product_category()==1?'collection':($post->post_type??"others"));
    $user_email=wp_get_current_user()->user_email??'';
    $user_id=wp_get_current_user()->id??0;
    $data = array(
        'tr_email' => $user_email,
        'tr_id' => $user_id,
        'tr_type_id' => $current_product_ID,
        'tr_type' => $post_type
    );
    echo "<script>
     localStorage.setItem('_be', '".json_encode($data)."');
    </script>";
    @setcookie('_be', json_encode($data), time()+10,'/');
}

function my_script_attributes( $tag, $handle, $src )
{

    // change to the registered script handle, e. g. 'jquery'
    if ( 'bayengage-script' === $handle) {

        $tenantUuid = get_option('BEM_CONNECTION_USER_ID');
        //$tenantUuid = '3d5e1dd15995';
        // add attributes of your choice
        $tag = '<script data-id="'.$tenantUuid.'" src="' . esc_url( $src ) . '"></script>';
    }

    return $tag;
}

function register_routes() {
    $tenantUuid = get_option('BEM_CONNECTION_USER_ID');

    if($tenantUuid) {
        register_rest_route( 'be-plugin/v1', '/public_id/'.$tenantUuid, array(
            'methods' => 'GET',
            'callback' => 'get_latest_version'
        ) );
    }
}

function enqueue_scripts() {
    wp_enqueue_script(
        'checkout-tracking',
        plugins_url( 'public/js/checkout-tracking.js', __FILE__ ), // Correct path to your plugin's JS file
        array('jquery'),
        null,
        true
    );
}

function get_latest_version() {
    require ABSPATH . WPINC . '/version.php'; // $wp_version;
    return $wp_version;
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-bayengage-deactivator.php
 */
function bem_do_deactivation_process() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-bayengage-deactivator.php';
    Email_Campaign_Automation_Deactivator::deactivate();

    //Uninstall webhook
    try {
        $dataList['shop_url'] = wp_guess_url();
        $dataList['action_webhook'] = 'app.uninstalled';

        $tenantUuid = get_option('BEM_CONNECTION_USER_ID');

        if (BAYENGAGE_ENVIRONMENT === 'LIVE'){
            $baseUrl = BAYENGAGE_LIVE_WEBHOOK_URL.$tenantUuid;
        }else{
            $baseUrl = BAYENGAGE_DEV_WEBHOOK_URL.$tenantUuid;
        }

        $headers = array(
            'Content-Type' => 'application/json',
            'x-wc-webhook-source' => $dataList['shop_url'],
            'x-wc-webhook-topic' => $dataList['action_webhook']
        );
        $request = new WP_Http;
        $result = $request->request( $baseUrl, array( 'method' => 'POST', 'body' => wp_json_encode( $dataList ), 'headers' => $headers) );

        //activate
        //$tenantUuid = 'acf82f5e5b3c';
        //update_option( 'BEM_CONNECTION_USER_ID', 'acf82f5e5b3c' );
        update_option( 'BEM_CONNECTION_DEACTIVATE_USER_ID', $tenantUuid );

    } catch (\Exception $e) {
        $errorMsg = ', Message: ' . $e->getMessage();
        $errorMsg .= ', Line: ' . $e->getLine();
    }
}

register_activation_hook( __FILE__, 'bem_do_activation_process' );
register_deactivation_hook( __FILE__, 'bem_do_deactivation_process' );
add_action('init', 'bem_do_plugin_after_install');
add_action( 'rest_api_init', 'register_routes');
add_action('wp_head', 'get_current_product_ID');

add_filter( 'woocommerce_paypal_payments_simulate_cart_enabled', '__return_false' );
add_filter( 'woocommerce_paypal_payments_simulate_cart_prevent_updates', '__return_false' );
/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-bayengage-automation.php';