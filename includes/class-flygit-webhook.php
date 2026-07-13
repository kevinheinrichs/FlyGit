<?php
/**
 * Optional webhook accelerator (secure by default).
 *
 * @package FlyGit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ONE site-wide endpoint instead of one per installation:
 *
 *   POST /wp-json/flygit/v1/sync
 *
 * Security model (fixes the v1 hole):
 * - Disabled by default; must be enabled in settings.
 * - A secret ALWAYS exists (generated on activation) and is ALWAYS
 *   enforced — there is no unauthenticated path.
 * - The payload can never inject a repository URL. It may only hint
 *   which known repo pushed; everything else comes from stored state.
 * - The request never triggers a deploy synchronously. It schedules a
 *   single cron event a few seconds later (debounced), so GitHub gets
 *   an instant 202 and double-pushes collapse into one check.
 */
class FlyGit_Webhook {

	/**
	 * Registry.
	 *
	 * @var FlyGit_Registry
	 */
	protected $registry;

	/**
	 * Updater.
	 *
	 * @var FlyGit_Updater
	 */
	protected $updater;

	/**
	 * Constructor.
	 *
	 * @param FlyGit_Registry $registry Registry.
	 * @param FlyGit_Updater  $updater  Updater.
	 */
	public function __construct( FlyGit_Registry $registry, FlyGit_Updater $updater ) {
		$this->registry = $registry;
		$this->updater  = $updater;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			'flygit/v1',
			'/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'authenticate' ),
			)
		);
	}

	/**
	 * Authenticate the webhook request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function authenticate( WP_REST_Request $request ) {
		if ( ! FlyGit_Options::get( 'webhook_enabled', false ) ) {
			return new WP_Error( 'flygit_webhook_disabled', __( 'Webhook ist deaktiviert.', 'flygit' ), array( 'status' => 404 ) );
		}

		$secret = (string) FlyGit_Options::get( 'webhook_secret', '' );

		if ( '' === $secret ) {
			// No secret = no access. Never open.
			return new WP_Error( 'flygit_webhook_locked', __( 'Kein Webhook-Secret konfiguriert.', 'flygit' ), array( 'status' => 403 ) );
		}

		// 1) GitHub HMAC signature (preferred).
		$signature = $request->get_header( 'x-hub-signature-256' );
		$raw_body  = $request->get_body();

		if ( ! empty( $signature ) && ! empty( $raw_body ) ) {
			$expected = 'sha256=' . hash_hmac( 'sha256', $raw_body, $secret );
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}

			return new WP_Error( 'flygit_webhook_signature', __( 'Ungültige Signatur.', 'flygit' ), array( 'status' => 403 ) );
		}

		// 2) Static header secret (curl / CI).
		$header_secret = $request->get_header( 'x-flygit-secret' );
		if ( ! empty( $header_secret ) && hash_equals( $secret, $header_secret ) ) {
			return true;
		}

		return new WP_Error( 'flygit_webhook_auth', __( 'Authentifizierung fehlgeschlagen.', 'flygit' ), array( 'status' => 403 ) );
	}

	/**
	 * Handle an authenticated ping: debounce-schedule the real work.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$payload = is_array( $payload ) ? $payload : array();

		// GitHub push payload carries repository.full_name = "owner/repo".
		$full_name = '';
		if ( isset( $payload['repository']['full_name'] ) ) {
			$full_name = strtolower( sanitize_text_field( (string) $payload['repository']['full_name'] ) );
		}

		$scheduled = array();

		if ( '' !== $full_name ) {
			// Target only installations of THIS repo.
			foreach ( $this->registry->all() as $item ) {
				if ( strtolower( $item['owner'] . '/' . $item['repo'] ) === $full_name ) {
					$this->schedule_single( $item['id'] );
					$scheduled[] = $item['slug'];
				}
			}

			// Manifest repo pushed?
			$manifest_repo = strtolower( trim( (string) FlyGit_Options::get( 'manifest_repo', '' ) ) );
			if ( FlyGit_Options::get( 'manifest_enabled', false ) && $manifest_repo === $full_name ) {
				$this->schedule_manifest();
				$scheduled[] = '(manifest)';
			}
		}

		if ( empty( $scheduled ) ) {
			// Unknown or missing repo hint → full check sweep (still async).
			if ( ! wp_next_scheduled( 'flygit_check_updates' ) || $this->seconds_until_next_check() > 30 ) {
				wp_schedule_single_event( time() + 10, 'flygit_check_updates' );
			}
			$scheduled[] = '(alle)';
		}

		FlyGit_Logger::log( 'info', 'Webhook empfangen → geplant: ' . implode( ', ', $scheduled ) );

		return rest_ensure_response(
			array(
				'success'   => true,
				'scheduled' => $scheduled,
			)
		);
	}

	/**
	 * Debounced single-installation check.
	 *
	 * @param string $installation_id Installation id.
	 */
	protected function schedule_single( $installation_id ) {
		$hook = 'flygit_run_single_check';
		$args = array( $installation_id, true );

		if ( ! wp_next_scheduled( $hook, $args ) ) {
			wp_schedule_single_event( time() + 10, $hook, $args );
		}
	}

	/**
	 * Debounced manifest sync.
	 */
	protected function schedule_manifest() {
		if ( ! wp_next_scheduled( 'flygit_run_manifest_sync' ) ) {
			wp_schedule_single_event( time() + 10, 'flygit_run_manifest_sync' );
		}
	}

	/**
	 * Seconds until the next scheduled full check.
	 *
	 * @return int
	 */
	protected function seconds_until_next_check() {
		$next = wp_next_scheduled( 'flygit_check_updates' );
		return $next ? max( 0, $next - time() ) : PHP_INT_MAX;
	}
}
