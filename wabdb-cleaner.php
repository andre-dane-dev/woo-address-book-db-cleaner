<?php

/**
 * Plugin Name:       WooCommerce Address Book DB Cleaner
 * Plugin URI:        https://github.com/andre-dane-dev/woo-address-book-db-cleaner
 * Description:       This plugin has the function of cleaning up the database
 *                    following the WooCommerce Address Book plugin bug,
 *                    which created duplicate usermeta if the billing or
 *                    shipping address was disabled in checkout.
 * Version:           1.0.1
 * Author:            andre-dane-dev<andre.dane.dev@gmail.com>
 * Author URI:        https://github.com/andre-dane-dev
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       woo-address-book-db-cleaner
 * Domain Path:       /languages
 *
 * WC tested up to: 6.7.0
 * WC requires at least: 6.7.0
 * Tested up to: 6.0.1
 *
 * Copyright (C) 2022 andre-dane-dev
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once ABSPATH . 'wp-admin/includes/plugin.php';

if ( ! is_plugin_active( 'woocommerce/woocommerce.php') ) exit; // Exit if WooCommerce is not active

/**
 * Class Woo_Address_Book_DB_Cleaner
 *
 * @since 1.0.0
 */
class Woo_Address_Book_DB_Cleaner {

	/**
	 * @since 1.0.0
	 */
	public function __construct() {
		// Load text domain
		add_action( 'plugins_loaded', array( $this, 'wabdbc_load_plugin_textdomain' ) );

		// Add cleaner to Tool Menu
		add_action( 'tool_box', array( $this, 'wabdbc_output_cleaner_ajax_button_script' ) );

		// Enqueue scripts for db cleaner'sajax call
        add_action( 'wp_ajax_run_db_clean', array( $this, 'wabdbc_run_db_cleaner' ) );
	}

	/**
	 * Load text domain.
	 *
	 * @since  1.0.0
	 */
	public function wabdbc_load_plugin_textdomain() {
		load_plugin_textdomain( 'woo-address-book-db-cleaner', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Output button and script for db cleaner's ajax.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	public function wabdbc_output_cleaner_ajax_button_script() {
		?>
		<div class="card">
			<h2 class="title"><?php _e( 'DB Cleaner for WooCommerce Address Book', 'woo-address-book-db-cleaner' ); ?></h2>
			<p>
			    <button id="wabdbc_db_clean_btn" type="button"><?php esc_html_e( 'Run DB cleaning', 'woo-address-book-db-cleaner' ); ?></button>
			</p>
            <p id="cleaner_delete_response"><?php _e( 'Click on the button to run the db cleaner.', 'woo-address-book-db-cleaner' ) ?></p>
            <p id="cleaner_update_response"></p>
            <p id="cleaner_affected_response"></p>
		</div>

        <script type="text/javascript">
            jQuery( document ).ready(
                function ($) {
                    $( "#wabdbc_db_clean_btn" ).on( "click", function ( response ) {
                        $.ajax( {
                            'type': "POST",
                            'url' : "<?php echo admin_url( 'admin-ajax.php' ); ?>",
                            'data': {
                                'action': 'run_db_clean',
                                'security': "<?php wp_create_nonce( 'wabd_db_clean_ajax_nonce' ); ?>",
                            },
                            'dataType': 'json',
                            success: function ( response ) {
                                if ( response ) {
                                    $('#cleaner_delete_response').html( response['deleted'] );
                                    $('#cleaner_update_response').html( response['updated'] );
                                    $('#cleaner_affected_response').html( response['affected'] );
                                }
                            }
                        } )
                    } )
                }
            );
        </script>
		<?php
	}

    /**
     * Delete additional billing related user meta and update original meta value.
     * NOTE to fix bug caused by WooCommerce Address Book.
     *
     * @since   1.0.0
     *
     * @version 1.0.1 - added affected users log
     * @see     https://github.com/hallme/woo-address-book/issues/128
     * @see     https://stackoverflow.com/a/10634225
     * @return  void
     */
    public function wabdbc_run_db_cleaner() {
        global $wpdb;

		// Declare utility array
        $all_meta_to_update    = array();
        $all_meta_to_delete    = array();
        $all_updated_meta      = array();
        $all_updated_meta_logs = array();
		$affected_users        = array();

		// Declare array for response
		$response              = array();

        // Get all billing usermeta
        $all_meta = $wpdb->get_results(
            "SELECT * FROM {$wpdb->usermeta}
            WHERE meta_key LIKE '%billing%'",
            ARRAY_A
        );

        // Get all usermeta to update and delete
        foreach ( $all_meta as $meta) {
            foreach ( $meta as $key => $value ) {

                if ( $key == 'meta_key' ) {
                    // Check if is an additional billing usermeta (e.g. billilng2_first_name)
                    $pattern       = '/\bbilling\K[0-9]+/i';
                    /**
                     * Get specific counter (e.g. billing2_first_name -> 2) and related offset.
                     * NOTE $counter_match is an array containing the number matched and the offset within the string.
                     *
                     * @since 1.0.0
                     * @var   boolean $is_additional
                     */
                    $is_additional = preg_match( $pattern, $value, $counter_match, PREG_OFFSET_CAPTURE );

                    if ( $is_additional ) {
                        $additional_number = $counter_match[0][0];
                        $offset            = $counter_match[0][1];

                        // Get the original meta_key name (without additional number) & related data
                        $update_meta_key   = substr_replace( $value, '', $offset, strlen( $additional_number ) );
                        $user_id           = $meta['user_id'];
                        $update_meta_value = $meta['meta_value'];

                        // Store usermeta id for later bulk deletion
                        $all_meta_to_delete['umeta_id'][] = $meta['umeta_id'];

						// Store affected users id
						if ( ! in_array( $user_id, $affected_users ) ) {
							$affected_users[] = $user_id;
						}

                        if ( ! $update_meta_value ) continue;

                        // Store data for later bulk update
                        $all_meta_to_update[ $user_id ][ $additional_number ][ $update_meta_key ] = $update_meta_value;
                    }
                }
            }
        }

        // Update original meta with the updated values
        foreach ( $all_meta_to_update as $user_id => $all_counter_meta ) {
            // Get the last additional meta by taking the one with highest counter
            $last_meta  = $all_counter_meta[ max( array_keys( $all_counter_meta ) ) ];

            foreach ( $last_meta as $meta_key => $meta_value ) {

                // Update data. Returns true if old value is different from new one.
                $updated = $wpdb->update(
                    $wpdb->usermeta,
                    array( 'meta_value' => $meta_value ), // data
                    array( 'user_id' => $user_id, 'meta_key' => $meta_key ) // where
                );

                if ( $updated ) {
                    // Store data for output response
                    $all_updated_meta[ $user_id ][] = $meta_key;
                }
            }
        }

        // Delete all stored additional meta
        foreach ( $all_meta_to_delete as $key => $value ) {
            $delete_sql = "
                DELETE FROM {$wpdb->usermeta}
                WHERE " . $key . " IN ("
                . implode( ', ', array_fill( 0, count( $value ), '%s' ) ) . ")
            ";
            // See https://stackoverflow.com/a/10634225
            $affected_query = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $delete_sql ), $value ) );

            $wpdb->query( $affected_query );
        }

		// Log affected users id
		if ( $affected_users ) {
			$affected_sql = "
				DELETE FROM {$wpdb->usermeta}
				WHERE meta_key = 'wc_address_book_billing'
				AND user_id IN ("
				. implode( ', ', array_fill( 0, count( $affected_users ), '%s' ) ) . ")
			";
			// See https://stackoverflow.com/a/10634225
			$affected_query = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $affected_sql ), $affected_users ) );

			$wpdb->query( $affected_query );

			$response['affected'] = 'Affected users: #' . implode( ', #', $affected_users );
		} else {
			$response['affected'] = 'No affected users.';
		}

        // Prepare response
        if ( isset( $all_meta_to_delete['umeta_id'] ) ) {
			$response['deleted'] = 'Usermeta deleted (umeta_id): ' . implode( ', ', $all_meta_to_delete['umeta_id'] );
        } else {
            $response['deleted'] = 'No meta to delete.';
        }

        // Create a string for output report
        foreach ( $all_updated_meta as $user_id => $meta) {
            // Store log for every user
            $all_updated_meta_logs[] = 'Updated meta for user #' . $user_id . ': ' . implode( ', ', $meta );
        }

        // Store log
        if ( ! $all_updated_meta_logs ) {
			$response['updated'] = 'No meta to update.';
        } else {
            $response['updated'] = implode( '<br />', $all_updated_meta_logs );
        }

		wp_send_json( $response );
    }
}

new Woo_Address_Book_DB_Cleaner();
