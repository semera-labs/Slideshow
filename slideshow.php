<?php
/*
 Plugin Name: Slideshow
 Plugin URI: http://wordpress.org/extend/plugins/slideshow-jquery-image-gallery/
 Description: The slideshow plugin is easily deployable on your website. Add any image that has already been uploaded to add to your slideshow, add text slides, or even add a video. Options and styles are customizable for every single slideshow on your website.
 Version: 2.3.2.1
 Requires at least: 3.5
 Author: StefanBoonstra
 Author URI: http://stefanboonstra.com/
 License: GPLv2
 Text Domain: slideshow-jquery-image-gallery
 Update URI: false

 Compatibility patch by Semera (PHP 8.x, current WordPress/jQuery) based on Slideshow 2.3.2 (development branch) by Stefan Boonstra.
 Original code, author credits and the GPLv2 license are unchanged; the patch is not made or endorsed by the original
 author. All changes are listed in readme.md. "Update URI: false" and the update filters below keep WordPress from
 replacing this patched version with the (unmaintained) upstream release.
*/

/**
 * Class SlideshowPluginMain fires up the application on plugin load and provides some
 * methods for the other classes to use like the auto-includer and the
 * base path/url returning method.
 *
 * @since 1.0.0
 * @author Stefan Boonstra
 */
class SlideshowPluginMain
{
	/** @var string $version */
	static $version = '2.3.2.1';

	/**
	 * Bootstraps the application by assigning the right functions to
	 * the right action hooks.
	 *
	 * @since 1.0.0
	 */
	static function bootStrap()
	{
		self::autoInclude();

		// Initialize localization on init
		add_action('init', array(__CLASS__, 'localize'));

		// Enqueue hooks
		add_action('wp_enqueue_scripts'   , array(__CLASS__, 'enqueueFrontendScripts'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueueBackendScripts'));

		// Ajax requests
		SlideshowPluginAJAX::init();

		// Register slideshow post type
		SlideshowPluginPostType::init();

		// Add general settings page
		SlideshowPluginGeneralSettings::init();

		// Initialize stylesheet builder
		SlideshowPluginSlideshowStylesheet::init();

		// Deploy slideshow on do_action('slideshow_deploy'); hook.
		add_action('slideshow_deploy', array('SlideshowPlugin', 'deploy'));

		// Initialize shortcode
		SlideshowPluginShortcode::init();

		// Register widget
		add_action('widgets_init', array('SlideshowPluginWidget', 'registerWidget'));

		// Initialize plugin updater
		SlideshowPluginInstaller::init();

		// Semera compatibility patch: do not offer the upstream release as an update, never auto-update this plugin
		add_filter('site_transient_update_plugins', array(__CLASS__, 'hideFromUpdates'));
		add_filter('auto_update_plugin'            , array(__CLASS__, 'disableAutoUpdate'), 10, 2);
	}

	/**
	 * Semera compatibility patch: removes this plugin from the list of available plugin updates.
	 *
	 * @param mixed $transient
	 * @return mixed $transient
	 */
	static function hideFromUpdates($transient)
	{
		$plugin = plugin_basename(__FILE__);

		if (is_object($transient))
		{
			if (isset($transient->response) && is_array($transient->response))
			{
				unset($transient->response[$plugin]);
			}

			if (isset($transient->no_update) && is_array($transient->no_update))
			{
				unset($transient->no_update[$plugin]);
			}
		}

		return $transient;
	}

	/**
	 * Semera compatibility patch: disables automatic updates for this plugin.
	 *
	 * @param bool|null $update
	 * @param object    $item
	 * @return bool|null
	 */
	static function disableAutoUpdate($update, $item)
	{
		if (is_object($item) && isset($item->plugin) && $item->plugin === plugin_basename(__FILE__))
		{
			return false;
		}

		return $update;
	}

	/**
	 * Enqueues frontend scripts and styles.
	 *
	 * Should always be called on the wp_enqueue_scripts hook.
	 *
	 * @since 2.3.0
	 */
	static function enqueueFrontendScripts()
	{
		// Enqueue slideshow script if lazy loading is enabled
		if (SlideshowPluginGeneralSettings::getEnableLazyLoading())
		{
			wp_enqueue_script(
				'slideshow-jquery-image-gallery-script',
				self::getPluginUrl() . '/js/min/all.frontend.min.js',
				array('jquery'),
				self::$version
			);

			self::localizeAdminURL('slideshow-jquery-image-gallery-script');
		}
	}

	/**
	 * Semera compatibility patch: passes the admin URL to a script as a JavaScript variable. wp_localize_script() only
	 * accepts arrays since WordPress 5.7 (passing a string logs a notice), so an inline script is used where available.
	 *
	 * @param string $handle
	 */
	static function localizeAdminURL($handle)
	{
		if (function_exists('wp_add_inline_script'))
		{
			wp_add_inline_script(
				$handle,
				'var slideshow_jquery_image_gallery_script_adminURL = ' . wp_json_encode(admin_url()) . ';',
				'before'
			);

			return;
		}

		wp_localize_script($handle, 'slideshow_jquery_image_gallery_script_adminURL', admin_url());
	}

	/**
	 * Enqueues backend scripts and styles.
	 *
	 * Should always be called on the admin_enqueue_scrips hook.
	 *
	 * @since 2.2.12
	 */
	static function enqueueBackendScripts()
	{
		// Function get_current_screen() should be defined, as this method is expected to fire at 'admin_enqueue_scripts'
		if (!function_exists('get_current_screen'))
		{
			return;
		}

		$currentScreen = get_current_screen();

		// Enqueue 3.5 uploader
		if ($currentScreen->post_type === 'slideshow' &&
			function_exists('wp_enqueue_media'))
		{
			wp_enqueue_media();
		}

		wp_enqueue_script(
			'slideshow-jquery-image-gallery-backend-script',
			self::getPluginUrl() . '/js/min/all.backend.min.js',
			array(
				'jquery',
				'jquery-ui-sortable',
				'wp-color-picker'
			),
			SlideshowPluginMain::$version
		);

		wp_enqueue_style(
			'slideshow-jquery-image-gallery-backend-style',
			self::getPluginUrl() . '/css/all.backend.css',
			array(
				'wp-color-picker'
			),
			SlideshowPluginMain::$version
		);
	}

	/**
	 * Translates the plugin
	 *
	 * @since 1.0.0
	 */
	static function localize()
	{
		load_plugin_textdomain(
			'slideshow-jquery-image-gallery',
			false,
			dirname(plugin_basename(__FILE__)) . '/languages/'
		);
	}

	/**
	 * Returns url to the base directory of this plugin.
	 *
	 * @since 1.0.0
	 * @return string pluginUrl
	 */
	static function getPluginUrl()
	{
		return plugins_url('', __FILE__);
	}

	/**
	 * Returns path to the base directory of this plugin
	 *
	 * @since 1.0.0
	 * @return string pluginPath
	 */
	static function getPluginPath()
	{
		return dirname(__FILE__);
	}

	/**
	 * Outputs the passed view. It's good practice to pass an object like an stdClass to the $data variable, as it can
	 * be easily checked for validity in the view itself using "instanceof".
	 *
	 * @since 2.3.0
	 * @param string   $view
	 * @param stdClass $data (Optional, defaults to stdClass)
	 */
	static function outputView($view, $data = null)
	{
		if (!($data instanceof stdClass))
		{
			$data = new stdClass();
		}

		$file = self::getPluginPath() . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . $view;

		if (file_exists($file))
		{
			include $file;
		}
	}

	/**
	 * Uses self::outputView to render the passed view. Returns the rendered view instead of outputting it.
	 *
	 * @since 2.3.0
	 * @param string   $view
	 * @param stdClass $data (Optional, defaults to null)
	 * @return string
	 */
	static function getView($view, $data = null)
	{
		ob_start();
		self::outputView($view, $data);
		return ob_get_clean();
	}

	/**
	 * This function will load classes automatically on-call.
	 *
	 * @since 1.0.0
	 */
	static function autoInclude()
	{
		if (!function_exists('spl_autoload_register'))
		{
			return;
		}

		function slideshowPluginAutoLoader($name)
		{
			$name = str_replace('\\', DIRECTORY_SEPARATOR, $name);
			$file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . $name . '.php';

			if (is_file($file))
			{
				require_once $file;
			}
		}

		spl_autoload_register('slideshowPluginAutoLoader');
	}
}

/**
 * Activate plugin
 */
SlideShowPluginMain::bootStrap();