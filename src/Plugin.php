<?php
/**
 * Main plugin class: wires all services together.
 *
 * @package Glixform
 */

namespace Glixform;

use Glixform\Admin\Admin;
use Glixform\Database\EntryRepository;
use Glixform\Fields\FieldRegistry;
use Glixform\Forms\FormPostType;
use Glixform\Forms\FormRepository;
use Glixform\Forms\Templates;
use Glixform\Frontend\Assets;
use Glixform\Frontend\Block;
use Glixform\Frontend\Preview;
use Glixform\Frontend\Renderer;
use Glixform\Frontend\Shortcode;
use Glixform\Mail\EmailLog;
use Glixform\Mail\SmtpMailer;
use Glixform\Notifications\Mailer;
use Glixform\Notifications\SmartTags;
use Glixform\Process\Submission;
use Glixform\Process\SubmissionController;
use Glixform\Rest\RestController;
use Glixform\Support\Privacy;

defined( 'ABSPATH' ) || exit;

/**
 * Service container and bootstrap.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Field type registry.
	 *
	 * @var FieldRegistry
	 */
	public $fields;

	/**
	 * Form storage.
	 *
	 * @var FormRepository
	 */
	public $forms;

	/**
	 * Form templates.
	 *
	 * @var Templates
	 */
	public $templates;

	/**
	 * Entry storage.
	 *
	 * @var EntryRepository
	 */
	public $entries;

	/**
	 * Smart tag parser.
	 *
	 * @var SmartTags
	 */
	public $smart_tags;

	/**
	 * Notification mailer.
	 *
	 * @var Mailer
	 */
	public $mailer;

	/**
	 * Front-end form renderer.
	 *
	 * @var Renderer
	 */
	public $renderer;

	/**
	 * Submission processor.
	 *
	 * @var Submission
	 */
	public $submission;

	/**
	 * Get (and on first call, boot) the plugin.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Create services and register hooks.
	 */
	private function boot() {
		load_plugin_textdomain( 'glixform', false, dirname( plugin_basename( GLIXFORM_FILE ) ) . '/languages' );

		Install::maybe_upgrade();

		$this->fields     = new FieldRegistry();
		$this->forms      = new FormRepository( $this->fields );
		$this->entries    = new EntryRepository();
		$this->smart_tags = new SmartTags();
		$this->mailer     = new Mailer( $this->smart_tags );
		$this->renderer   = new Renderer( $this->fields, $this->smart_tags );
		$this->templates  = new Templates( $this->forms );
		$this->submission = new Submission( $this->forms, $this->fields, $this->entries, $this->mailer, $this->smart_tags );

		( new FormPostType() )->register_hooks();
		( new Assets() )->register_hooks();
		( new Shortcode( $this->forms, $this->renderer ) )->register_hooks();
		( new Block( $this->forms ) )->register_hooks();
		( new Preview( $this->forms, $this->renderer ) )->register_hooks();
		( new SubmissionController( $this->submission, $this->renderer ) )->register_hooks();
		( new RestController( $this ) )->register_hooks();
		( new Privacy( $this->entries ) )->register_hooks();
		( new SmtpMailer() )->register_hooks();
		( new EmailLog() )->register_hooks();

		if ( is_admin() ) {
			( new Admin( $this ) )->register_hooks();
		}

		/**
		 * Fires once Glixform has loaded. Add-ons should hook here.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'glixform_loaded', $this );
	}

	/**
	 * Read a plugin setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Value when the setting is missing.
	 * @return mixed
	 */
	public static function setting( $key, $fallback = null ) {
		$settings = wp_parse_args( (array) get_option( 'glixform_settings', array() ), self::default_settings() );
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}

	/**
	 * Default plugin settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'store_ip'               => true,
			'min_submit_seconds'     => 2,
			'delete_on_uninstall'    => false,
			'captcha_provider'       => '',
			'captcha_site_key'       => '',
			'captcha_secret_key'     => '',
			'recaptcha_v3_threshold' => 0.5,
			'retention_days'         => 0,
		);
	}

	/**
	 * Capability required to manage forms and entries.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filters the capability needed to manage Glixform.
		 *
		 * @param string $capability Capability name.
		 */
		return (string) apply_filters( 'glixform_capability', 'manage_options' );
	}
}
