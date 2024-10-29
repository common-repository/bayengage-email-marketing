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


class BE_WC_Settings_MyPlugin {

    /**
     * Setup settings class
     *
     * @since  1.0
     */
    public function __construct() {

        add_filter( 'woocommerce_get_settings_pages', array($this, 'bem_plugin_add_settings' ), 15 );
    }


    function bem_plugin_add_settings($settings) {

        //echo "<pre>";print_r($settings);die;
        $settings[] = include plugin_dir_path( __FILE__ )  . 'class-bayengage-setting.php' ;
        return $settings;

    }

}

new BE_WC_Settings_MyPlugin();