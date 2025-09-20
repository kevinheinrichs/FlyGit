<?php
/**
 * Handles incoming webhook requests for automatic installations.
 *
 * @package FlyGit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FlyGit_Webhook_Handler {
    /**
     * Installer service instance.
     *
     * @var FlyGit_Installer
     */
    protected $installer;

    /**
     * Constructor.
     *
     * @param FlyGit_Installer $installer Installer instance.
     */
    public function __construct( FlyGit_Installer $installer ) {
        $this->installer = $installer;
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route(
            'flygit/v1',
            '/installations/(?P<installation_id>[a-z0-9\-]+)/webhook',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_installation_webhook' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Process webhook requests.
     *
     * @param WP_REST_Request $request REST request instance.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handle_installation_webhook( WP_REST_Request $request ) {
        $installation_id = $request->get_param( 'installation_id' );

        if ( empty( $installation_id ) ) {
            return new WP_Error( 'flygit_missing_installation', __( 'Installation identifier is required.', 'flygit' ), array( 'status' => 400 ) );
        }

        $installation = $this->installer->get_installation_by_id( $installation_id );

        if ( empty( $installation ) ) {
            return new WP_Error( 'flygit_installation_not_found', __( 'The requested installation does not exist.', 'flygit' ), array( 'status' => 404 ) );
        }

        $secret = isset( $installation['webhook_secret'] ) ? $installation['webhook_secret'] : '';
        if ( ! empty( $secret ) && ! $this->is_request_authenticated( $request, $secret ) ) {
            return new WP_Error( 'flygit_invalid_secret', __( 'Invalid webhook secret.', 'flygit' ), array( 'status' => 403 ) );
        }

        if ( empty( $installation['type'] ) || empty( $installation['repository_url'] ) ) {
            return new WP_Error( 'flygit_missing_repository', __( 'The installation is missing repository details.', 'flygit' ), array( 'status' => 400 ) );
        }

        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $params = array();
        }

        $repository_url = isset( $params['repository_url'] ) ? $params['repository_url'] : $request->get_param( 'repository_url' );
        $branch         = isset( $params['branch'] ) ? $params['branch'] : $request->get_param( 'branch' );
        $access_token   = isset( $params['access_token'] ) ? $params['access_token'] : $request->get_param( 'access_token' );

        $repository_url = ! empty( $repository_url ) ? esc_url_raw( $repository_url ) : $installation['repository_url'];
        $branch         = ! empty( $branch ) ? sanitize_text_field( $branch ) : ( isset( $installation['branch'] ) ? $installation['branch'] : 'main' );
        $access_token   = ! empty( $access_token ) ? sanitize_text_field( $access_token ) : ( isset( $installation['access_token'] ) ? $installation['access_token'] : '' );

        $result = $this->installer->install_from_repository( $installation['type'], $repository_url, $branch, $access_token );

        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'flygit_webhook_failed', $result->get_error_message(), array( 'status' => 500 ) );
        }

        return rest_ensure_response(
            array(
                'success' => true,
                'message' => $result,
            )
        );
}

    /**
     * Validate the webhook secret against multiple supported mechanisms.
     *
     * Supports direct secret transmission via the X-Flygit-Secret header or
     * `secret` field, GitHub style HMAC signatures and the X-Gitlab-Token header.
     *
     * @param WP_REST_Request $request Request instance.
     * @param string          $secret  Stored secret value.
     *
     * @return bool
     */
    protected function is_request_authenticated( WP_REST_Request $request, $secret ) {
        $provided_secret = $request->get_header( 'x-flygit-secret' );

        if ( empty( $provided_secret ) ) {
            $provided_secret = $request->get_param( 'secret' );
        }

        if ( ! empty( $provided_secret ) && hash_equals( $secret, $provided_secret ) ) {
            return true;
        }

        $raw_body = $request->get_body();

        $github_sha256 = $request->get_header( 'x-hub-signature-256' );
        if ( ! empty( $github_sha256 ) && ! empty( $raw_body ) ) {
            $expected_signature = 'sha256=' . hash_hmac( 'sha256', $raw_body, $secret );

            if ( hash_equals( $expected_signature, $github_sha256 ) ) {
                return true;
            }
        }

        $github_sha1 = $request->get_header( 'x-hub-signature' );
        if ( ! empty( $github_sha1 ) && ! empty( $raw_body ) ) {
            $expected_signature = 'sha1=' . hash_hmac( 'sha1', $raw_body, $secret );

            if ( hash_equals( $expected_signature, $github_sha1 ) ) {
                return true;
            }
        }

        $gitlab_token = $request->get_header( 'x-gitlab-token' );
        if ( ! empty( $gitlab_token ) && hash_equals( $secret, $gitlab_token ) ) {
            return true;
        }

        return false;
    }
}
