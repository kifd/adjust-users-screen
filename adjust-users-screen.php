<?php
/*
Plugin Name: Adjust Users Screen
Version: 0.2
Plugin URI: http://drakard.com/
Description: Alters the Users screen in the admin, re-ordering the columns and turning them on/off so as to avoid duplication or columns you don't need to see.
Author: Keith Drakard
Author URI: http://drakard.com/
*/

class AdjustUsersScreenPlugin {

	public function __construct() {
		load_plugin_textdomain('AdjustUsersScreen', false, plugin_dir_path(__FILE__).'/languages');

		// ensure we have the default columns when calling get_column_headers()
		add_filter('manage_users_columns', function($columns) {
			return array(
				'cb'		=> '<input type="checkbox">',
				'username'	=> __('Username'),
				'name'		=> __('Name'),
				'email'		=> __('E-mail'),
				'role'		=> __('Role'),
				'posts'		=> __('Posts'),
			);
		}, 1); // early priority

		if (is_admin()) {
			$this->options = get_option('AdjustUsersScreenPluginOptions');
			add_action('init', array($this, 'load_admin'));
		}
	}


	public function activation_hook() {
		$columns = get_column_headers('users');
		foreach ($columns as $column => &$value) $value = 1;
		update_option('AdjustUsersScreenPluginOptions', $columns, false);
	}

	public function deactivation_hook() {
		delete_option('AdjustUsersScreenPluginOptions');
	}


	/******************************************************************************************************************************************************************/

	public function reorder_and_unset_users_columns($columns) {
		$changed = array();
		foreach ($this->options as $column => $show) {
			if ($show AND isset($columns[$column])) {
				$changed[$column] = $columns[$column];
			}
		}
		return $changed;
	}


	public function load_admin() {

		// don't bother loading anything if we can't use it
		if (current_user_can('manage_options')) {
			add_action('admin_menu', array($this, 'add_settings_page'));
			add_action('admin_init', array($this, 'settings_init'));

			add_action('admin_init', function() {
				wp_register_script('adjust-users-screen-script', plugins_url('admin.js', __FILE__), array('jquery-ui-sortable'));
				wp_register_style('adjust-users-screen-style', plugins_url('style.css', __FILE__), false, '0.1');
			});
			add_action('admin_footer', function() {
				wp_enqueue_script('adjust-users-screen-script');
				wp_enqueue_style('adjust-users-screen-style');
			});
		}

		// and don't add the filter all the time or we'll need to remove it whenever we use get_column_headers() 
		global $pagenow; if ('users.php' == $pagenow) {
			add_filter('manage_users_columns', array($this, 'reorder_and_unset_users_columns'), 999999);
		}
	}


	public function add_settings_page() {
		add_options_page(__('Adjust Users Screen Options', 'AdjustUsersScreen'), __('Adjust Users Screen', 'AdjustUsersScreen'), 'manage_options', __CLASS__.'/settings.php', array(__CLASS__, 'display_settings_page'));
	}

	public function settings_init() {
		register_setting('AdjustUsersScreenPluginOptions', 'AdjustUsersScreenPluginOptions', array($this, 'validate_settings'));
		add_settings_section('AdjustUsersScreenSettings', __('Columns', 'AdjustUsersScreen'), array($this, 'users_columns_settings_form'), 'AdjustUsersScreenPlugin');
	}


	public function display_settings_page() {
		global $title;
		echo '<div class="wrap"><h2>'.esc_html($title).'</h2>'
			.'<form action="options.php" method="post">';
				settings_fields('AdjustUsersScreenPluginOptions');
				do_settings_sections('AdjustUsersScreenPlugin');
				submit_button();
		echo '</form></div>';
	}


	public function users_columns_settings_form() {
		$columns = get_column_headers('users');

		// in case another plugin has added more columns since this was activated, we need to quickly check
		$keys = array_keys($this->options); foreach ($columns as $key => $text) if (! in_array($key, $keys)) $this->options[$key] = 0;

		$output = '<p>'.__('Click to turn the corresponding column ON (blue) or OFF (grey) in the Users screen, and drag the items to reorder the columns.', 'AdjustUsersScreen').'</p>';

		$output.= '<ol id="sortable">';
		foreach ($this->options as $key => $value) {
			if (! isset($columns[$key])) continue;
			$checked = (1 == $value) ? ' checked="checked"' : '';
			$output.= '<li id="'.$key.'"><input type="checkbox" class="toggle" id="AdjustUsersScreenPluginOptions['.$key.']"'
					. ' name="AdjustUsersScreenPluginOptions['.$key.']" '.$checked.'>'
					. '<label for="AdjustUsersScreenPluginOptions['.$key.']">'.$columns[$key].'</label></li>';
		}

		$output.= '</ol>';

		echo $output;
	}


	public function validate_settings($input) {
		$columns = array_keys(get_column_headers('users'));
		$options = array();

		// add the columns to show in the order received (checkbox, so present = turned on)
		if (isset($input)) {
			foreach ($input as $key => $on) {
				if (in_array($key, $columns)) {
					$options[$key] = 1;
				}
			}
		}

		// then add any that didn't get passed through (ie. turned off)
		$keys = array_keys($options);
		foreach ($columns as $key) {
			if (! in_array($key, $keys)) {
				$options[$key] = 0;
			}
		}

		return $options;
	}

}


$AdjustUsersScreen = new AdjustUsersScreenPlugin();
register_activation_hook(__FILE__, array($AdjustUsersScreen, 'activation_hook'));
register_deactivation_hook(__FILE__, array($AdjustUsersScreen, 'deactivation_hook'));