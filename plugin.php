<?php
/*
 * Plugin Name: Hide Try Gutenberg callout
 * Description: Conditionally hides the Try Gutenberg callout
 * Version: 0.2
 * Author: Paul V. Biron/Sparrow Hawk Computing
 * Author URI: https://sparrowhawkcomputing.com/
 * Plugin URL: https://github.com/pbiron/hide-try-gutenberg-callout
 * GitHub Plugin URI: https://github.com/pbiron/hide-try-gutenberg-callout
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

/*
 * @todo decide whether to implement specific behavior for all sites in multiste
 */

defined( 'ABSPATH' ) || die;

/**
 * Conditionally hides the Try Gutenberg callout.
 */
class SHC_Hide_Try_Gutenberg_Callout
{
	/**
	 * Constructor.
	 *
	 * Add necessary hooks.
	 */
	function __construct() {
		if ( is_admin() ) {
			add_action( 'plugins_loaded', array( $this, 'maybe_hide_for_all_users' ) );
			add_filter( 'get_user_metadata', array( $this, 'maybe_hide_for_user' ), 10, 3 );

			add_action( 'admin_init', array( $this, 'add_settings' ) );
			add_action( 'plugin_action_links_hide-try-gutenberg-callout/plugin.php', array( $this, 'add_settings_link' ) );

			// this filter provided by Gutenberg itself
			add_filter( 'gutenberg_can_edit_post_type', array( $this, 'can_use_gutenberg' ) );

			register_activation_hook( __FILE__, array( $this, 'init_option' ) );
		}
	}

	/**
	 * Conditionally hide the Try Gutenberg callout for a given user.
	 *
	 * @param string $default
	 * @param int $user_id The user to maybe hide the callout for.
	 * @param string $meta_key The user meta key.
	 * @return string '0' if the callout should be hidden, `$default` otherwise.
	 */
	function maybe_hide_for_user( $default, $user_id, $meta_key ) {
		if ( 'show_try_gutenberg_panel' !== $meta_key ) {
			return $default;
		}

		$option = get_option( 'hide_try_gutenberg_callout' );

		// hide the callout for all users who can't edit posts
		if ( 'yes' === $option['non_edit_posts'] && ! user_can( $user_id, 'edit_posts' ) ) {
			return '0';
	 	}

	 	// hide the callout for specific users
	 	if ( in_array( $user_id, $option['specific_users'] ) ) {
	 		return '0';
	 	}

	 	return $default;
	}

	/**
	 * Maybe hide the callout for all users.
	 *
	 * @return void
	 */
	function maybe_hide_for_all_users() {
		$option = get_option( 'hide_try_gutenberg_callout' );
		if ( 'yes' === $option['all_users'] ) {
			remove_action( 'try_gutenberg_panel', 'wp_try_gutenberg_panel' );
		}

		return;
	}

	/**
	 * Can a user use Gutenberg?
	 *
	 * If the callout is hidden for a user by our settings then that user will not be
	 * allowed to use Gutenberg.
	 *
	 * @return bool
	 */
	function can_use_gutenberg( $default ) {
		$option = get_option( 'hide_try_gutenberg_callout' );
		if ( 'no' === $option['disable_gutenberg'] ) {
			return $default;
		}

		if ( 'yes' === $option['all_users'] ) {
			return false;
		}

		if ( null === $this->maybe_hide_for_user( null, get_current_user_id(), 'show_try_gutenberg_panel' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Add a link to the settings on the Plugins screen.
	 *
	 * @param array $actions Plugin row action links.
	 * @return array
	 */
	function add_settings_link( $actions ) {
		if ( current_user_can( 'manage_options' ) ) {
			$settings_link = sprintf(
				'<a href="%s">%s</a>',
				admin_url( 'options-writing.php#hide-try-gutenberg-callout-options' ),
				__( 'Settings', 'hide-try-gutenberg-callout' )
			);
			array_unshift( $actions, $settings_link );
		}

		return $actions;
	}

	/**
	 * Add our settings.
	 *
	 * @return void
	 */
	function add_settings() {
		register_setting(
			'writing',
			'hide_try_gutenberg_callout',
			array(
				'sanitize_callback' => array( $this, 'sanitize_option' ),
			)
		);

		add_option_whitelist( array(
			'writing' => array( 'hide_try_gutenberg_callout' ),
		) );

		add_settings_section(
			'hide_try_gutenberg_callout',
			__( 'Hide "Try Gutenberg" Callout', 'hide-gutenberg-callout' ),
			array( $this, 'settings_section' ),
			'writing'
		);

		add_settings_field(
			'disable_gutenberg',
			__( 'Disable Gutenberg for users for whom the callout is hidden', 'hide-try-gutenberg-callout' ),
			array( $this, 'disable_gutenberg_setting' ),
			'writing',
			'hide_try_gutenberg_callout'
		);

		add_settings_field(
			'all_users',
			__( 'Hide for all users', 'hide-try-gutenberg-callout' ),
			array( $this, 'all_users_setting' ),
			'writing',
			'hide_try_gutenberg_callout'
		);

		add_settings_field(
			'non_edit_posts',
			__( 'Hide for all users without \'edit_posts\' capability', 'hide-try-gutenberg-callout' ),
			array( $this, 'non_edit_posts_setting' ),
			'writing',
			'hide_try_gutenberg_callout'
		);

		add_settings_field(
			'specific_users',
			__( 'Hide for specific users with \'edit_posts\' capability', 'hide-try-gutenberg-callout' ),
			array( $this, 'specific_users_setting' ),
			'writing',
			'hide_try_gutenberg_callout'
		);

		return;
	}

	/**
	 * Output our settings seciton (include Javascript).
	 *
	 * @return void
	 */
	function settings_section() {
 ?>
 	<a name='hide-try-gutenberg-callout-options'></a>
	<p>
		<?php _e( 'These settings allow you to control when the "Try Gutenberg" callout is hidden.', 'hide-try-gutenberg-callout ') ?>
	</p>
	<script>
		// control whether the other settings are disabled depending on whether "Hide always" is "Yes" or "No"
		jQuery( 'document' ).ready( function( $ ) {
			var yes_always = $( '#hide_try_gutenberg_callout_always-yes' );
//			var rows = yes_always.parents( 'table' ).find( 'tr' ).not( ':first' ).not( ':last' );
			var rows = yes_always.parents( 'table' ).find( 'tr:gt( 1 )' );

			if ( yes_always.is( ':checked' ) ) {
				rows.find( 'input' ).attr( 'disabled', 'disabled' );
			}
			else {
				rows.find( 'input' ).removeAttr( 'disabled' );
			}

			$( 'input[name="hide_try_gutenberg_callout[all_users]"]' ).on( 'change', function() {
				if ( 'yes' === $( this ).val() ) {
					rows.find( 'input' ).attr( 'disabled', 'disabled' );
				}
				else {
					rows.find( 'input' ).removeAttr( 'disabled' );
				}
			} );
		} );
	</script>
<?php

		return;
	}

	/**
	 * Render our "Hide for all users" setting.
	 *
	 * Outputs "Yes"/"No" radio buttons.
	 *
	 * @return void
	 */
	function all_users_setting() {
		$option = get_option( 'hide_try_gutenberg_callout' );
 ?>
	<p id="hide_try_gutenberg_callout_always-options" style="margin: 0;">
		<input type="radio" name="hide_try_gutenberg_callout[all_users]" id="hide_try_gutenberg_callout_always-yes" value="yes"<?php checked( $option['all_users'], 'yes', true ) ?> />
		<label for="hide_try_gutenberg_callout_all_users-yes">
			<?php _e( 'Yes', 'hide-try-gutenberg-callout' ); ?>
			<span class='description'>
				<?php
					echo str_repeat( '&nbsp;', 5 );
					_e( 'The callout will be hidden for all users.', 'hide-try-gutenberg-callout' );
				 ?>
			</span>
		</label>
		<br>

		<input type="radio" name="hide_try_gutenberg_callout[all_users]" id="hide_try_gutenberg_callout_always-no" value="no"<?php checked( $option['all_users'], 'no', true ) ?> />
		<label for="hide_try_gutenberg_callout_all_users-yes">
			<?php _e( 'No', 'hide-try-gutenberg-callout' ); ?>
			<span class='description'>
				<?php
					echo str_repeat( '&nbsp;', 5 );
					_e( 'The other settings in this section will control when the callout is hidden.', 'hide-try-gutenberg-callout' );
				 ?>
			</span>
		</label>
	</p>
<?php

		return;
	}

	/**
	 * Render our "...for specific users..." setting.
	 *
	 * Outputs a checkbox for each user with `edit_posts` capability.
	 *
	 * @return void
	 */
	function specific_users_setting() {
		$option = get_option( 'hide_try_gutenberg_callout' );
	?>

	<p id="hide_try_gutenberg_callout_specific_users-options" style="margin: 0;">
<?php
		foreach ( get_users() as $user ) {
			if ( ! user_can( $user->ID, 'edit_posts' ) ) {
				//
				continue;
			}
 ?>
		<input type="checkbox" name="hide_try_gutenberg_callout[specific_users][]" id="hide_try_gutenberg_callout[specific_users][<?php echo $user->ID ?>]" value="<?php echo $user->ID ?>"<?php checked( in_array( $user->ID, $option['specific_users'] ), true ) ?> />
		<label for="hide_try_gutenberg_callout[specific_users][<?php echo $user->ID ?>]">
			<?php echo $user->display_name ?>
		</label>
		<br>
<?php
		}
 ?>
		<span class='description'>
		<?php
			_e( 'The callout will be hidden for those users that are checked.', 'hide-try-gutenberg-callout' );
		 ?>
		</span>
 	</p>
 <?php

		return;
	}

	/**
	 * Render our "Hide for all users without edit_posts" setting.
	 *
	 * Outputs "Yes"/"No" radio buttons.
	 *
	 * @return void
	 */
	function non_edit_posts_setting() {
		$option = get_option( 'hide_try_gutenberg_callout' );
 ?>

	<p id="hide_try_gutenberg_callout_for_non_edit_posts-options" style="margin: 0;">
		<input type="radio" name="hide_try_gutenberg_callout[non_edit_posts]" id="hide_try_gutenberg_callout_non_edit_posts-yes" value="yes"<?php checked( $option['non_edit_posts'], 'yes', true ) ?> />
		<label for="hide_try_gutenberg_callout_non_edit_posts-yes">
			<?php _e( 'Yes', 'hide-try-gutenberg-callout' ); ?>
			<span class='description'>
			<?php
					echo str_repeat( '&nbsp;', 5 );
					_e( 'The callout will be hidden for all users who do not have "edit_posts" capability.', 'hide-try-gutenberg-callout' );
			 ?>
			</span>
		</label>
		<br>

		<input type="radio" name="hide_try_gutenberg_callout[non_edit_posts]" id="hide_try_gutenberg_callout_non_edit_posts-no" value="no"<?php checked( $option['non_edit_posts'], 'no', true ) ?> />
		<label for="hide_try_gutenberg_callout_non_edit_posts-yes">
			<?php _e( 'No', 'hide-try-gutenberg-callout' ); ?>
			<span class='description'>
			<?php
					echo str_repeat( '&nbsp;', 5 );
					_e( 'The other settings in this section will control when the callout is hidden.', 'hide-try-gutenberg-callout' );
			 ?>
			</span>
		</label>
	</p>
<?php

		return;
	}

	/**
	 * Render our "Disable Gutenberg" setting.
	 *
	 * Outputs "Yes"/"No" radio buttons.
	 *
	 * @return void
	 */
	function disable_gutenberg_setting() {
		$option = get_option( 'hide_try_gutenberg_callout' );
 ?>
	<p id="hide_try_gutenberg_callout_disable_gutenberg-options" style="margin: 0;">
		<input type="radio" name="hide_try_gutenberg_callout[disable_gutenberg]" id="hide_try_gutenberg_callout_disable_gutenberg-yes" value="yes"<?php checked( $option['disable_gutenberg'], 'yes', true ) ?> />
		<label for="hide_try_gutenberg_callout_disable_gutenberg-yes">
			<?php _e( 'Yes', 'hide-try-gutenberg-callout' ); ?>
			<span class='description'>
				<?php
					echo str_repeat( '&nbsp;', 5 );
					_e( 'Gutenberg will be disabled if the callout is hidden by the other settings in this section.', 'hide-try-gutenberg-callout' );
				 ?>
			</span>
		</label>
		<br>

		<input type="radio" name="hide_try_gutenberg_callout[disable_gutenberg]" id="hide_try_gutenberg_callout_disable_gutenberg-no" value="no"<?php checked( $option['disable_gutenberg'], 'no', true ) ?> />
		<label for="hide_try_gutenberg_callout_disable_gutenberg-no">
			<?php _e( 'No', 'hide-try-gutenberg-callout' ); ?>
			<span class='description'>
				<?php
					echo str_repeat( '&nbsp;', 5 );
					_e( 'Gutenberg will be available if the callout is not hidden by the other settings in this section.', 'hide-try-gutenberg-callout' );
				 ?>
			</span>
		</label>
		<br>
		<span class='description'>
			<?php _e( 'Note that if the other settings in this section allow the callout to shown to a user and that user has manually dismissed the callout then this setting will have no effect.', 'hide-try-gutenberg-callout' ) ?>
		</span>
	</p>
<?php

		return;
	}

	/**
	 * Sanitize our option.
	 *
	 * @param array $value Our option value.
	 * @return array Sanitized option value
	 */
	function sanitize_option( $value ) {
		if ( ! is_array( $value ) ) {
			$value = array();
		}

		if ( ! in_array( $value['all_users'], array( 'yes', 'no' ) ) ) {
			$value['non_edit_posts'] = 'no';
		}

		if ( ! in_array( $value['non_edit_posts'], array( 'yes', 'no' ) ) ) {
			$value['non_edit_posts'] = 'no';
		}

		$value['specific_users'] = array_filter( array_map( 'intval', (array) $value['specific_users'] ) );

		return $value;
	}

	/**
	 * Initialize our option on plugin activation.
	 *
	 * @return void
	 */
	function init_option() {
		if ( get_option( 'hide_try_gutenberg_callout' ) ) {
			return;
		}

		add_option( 'hide_try_gutenberg_callout', array(
			'all_users' => 'no',
			'non_edit_posts' => 'no',
			'specific_users' => array(),
			'disable_gutenberg' => 'yes',
		) );

		return;
	}
}

new SHC_Hide_Try_Gutenberg_Callout();