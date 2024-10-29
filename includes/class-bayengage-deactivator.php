<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://app.bayengage.com
 * @since      1.0.0
 *
 * @package    BayEngage: Email Marketing
 * @subpackage Email_Campaign_Automation/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    BayEngage: Email Marketing
 * @subpackage Email_Campaign_Automation/includes
 * @author     Targetbay <support@targetbay.com>
 */

class Email_Campaign_Automation_Deactivator{

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {

        if ( ! get_option( 'BEM_CONNECTION_STATUS' ) ) {
            return;
        }

        delete_option( 'BEM_CONNECTION_STATUS' );
	}

}
