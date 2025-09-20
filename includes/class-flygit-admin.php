<?php
/**
 * Handles the FlyGit admin dashboard and WordPress integrations.
 *
 * @package FlyGit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FlyGit_Admin {
    /**
     * Installer service instance.
     *
     * @var FlyGit_Installer
     */
    protected $installer;

    /**
     * Snippet manager instance.
     *
     * @var FlyGit_Snippet_Manager
     */
    protected $snippets;

    /**
     * Constructor.
     *
     * @param FlyGit_Installer $installer Installer instance.
     */
    public function __construct( FlyGit_Installer $installer, FlyGit_Snippet_Manager $snippets ) {
        $this->installer = $installer;
        $this->snippets  = $snippets;
    }

    /**
     * Register the FlyGit admin menu.
     */
    public function register_menu() {
        add_menu_page(
            __( 'FlyGit', 'flygit' ),
            __( 'FlyGit', 'flygit' ),
            'manage_options',
            'flygit',
            array( $this, 'render_dashboard' ),
            'dashicons-cloud-upload',
            58
        );
    }

    /**
     * Enqueue styles and scripts for the admin dashboard.
     */
    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_flygit' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'flygit-admin',
            FLYGIT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FLYGIT_VERSION
        );

        wp_enqueue_script(
            'flygit-admin',
            FLYGIT_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            FLYGIT_VERSION,
            true
        );

        wp_localize_script(
            'flygit-admin',
            'flygitAdmin',
            array(
                'copySuccess' => __( 'Copied!', 'flygit' ),
            )
        );
    }

    /**
     * Handle install form submissions.
     */
    public function handle_install_request() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'flygit' ) );
        }

        check_admin_referer( 'flygit_install' );

        $type          = isset( $_POST['install_type'] ) ? sanitize_key( wp_unslash( $_POST['install_type'] ) ) : 'plugin';
        $repository    = isset( $_POST['repository_url'] ) ? esc_url_raw( wp_unslash( $_POST['repository_url'] ) ) : '';
        $branch        = isset( $_POST['branch'] ) ? sanitize_text_field( wp_unslash( $_POST['branch'] ) ) : '';
        $access_token  = isset( $_POST['access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['access_token'] ) ) : '';

        if ( empty( $repository ) ) {
            $this->redirect_with_message( 'error', __( 'Repository URL is required.', 'flygit' ) );
        }

        $result = $this->installer->install_from_repository( $type, $repository, $branch, $access_token );

        if ( is_wp_error( $result ) ) {
            $this->redirect_with_message( 'error', $result->get_error_message() );
        }

        $this->redirect_with_message( 'success', $result );
    }

    /**
     * Persist webhook settings submitted from the dashboard.
     */
    public function handle_webhook_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'flygit' ) );
        }

        check_admin_referer( 'flygit_webhook_settings' );

        $installation_id = isset( $_POST['installation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['installation_id'] ) ) : '';
        if ( empty( $installation_id ) ) {
            $this->redirect_with_message( 'error', __( 'Invalid webhook request. Please try again.', 'flygit' ) );
        }

        $secret = isset( $_POST['webhook_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ) ) : '';

        $installation = $this->installer->get_installation_by_id( $installation_id );
        if ( $installation ) {
            $result = $this->installer->update_installation(
                $installation_id,
                array(
                    'webhook_secret' => $secret,
                )
            );

            if ( is_wp_error( $result ) ) {
                $this->redirect_with_message( 'error', $result->get_error_message() );
            }

            $this->redirect_with_message( 'success', __( 'Webhook settings saved.', 'flygit' ) );
        }

        $snippet_installation = $this->snippets->get_installation_by_id( $installation_id );
        if ( $snippet_installation ) {
            $result = $this->snippets->update_installation(
                $installation_id,
                array(
                    'webhook_secret' => $secret,
                )
            );

            if ( is_wp_error( $result ) ) {
                $this->redirect_with_message( 'error', $result->get_error_message() );
            }

            $this->redirect_with_message( 'success', __( 'Webhook settings saved.', 'flygit' ) );
        }

        $this->redirect_with_message( 'error', __( 'The requested installation could not be found.', 'flygit' ) );
    }

    /**
     * Handle snippet settings updates submitted from the dashboard.
     */
    public function handle_snippet_settings_request() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'flygit' ) );
        }

        check_admin_referer( 'flygit_snippet_settings' );

        $installation_id = isset( $_POST['installation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['installation_id'] ) ) : '';

        if ( empty( $installation_id ) ) {
            $this->redirect_with_message( 'error', __( 'Invalid snippet settings request. Please try again.', 'flygit' ) );
        }

        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

        if ( '' === $name ) {
            $this->redirect_with_message( 'error', __( 'Please provide a name for the snippet repository.', 'flygit' ) );
        }

        $snippet_installation = $this->snippets->get_installation_by_id( $installation_id );

        if ( ! $snippet_installation ) {
            $this->redirect_with_message( 'error', __( 'The requested snippet installation could not be found.', 'flygit' ) );
        }

        $result = $this->snippets->update_installation(
            $installation_id,
            array(
                'name' => $name,
            )
        );

        if ( is_wp_error( $result ) ) {
            $this->redirect_with_message( 'error', $result->get_error_message() );
        }

        $this->redirect_with_message( 'success', __( 'Snippet repository name saved.', 'flygit' ) );
    }

    /**
     * Handle uninstall requests submitted from the dashboard.
     */
    public function handle_uninstall_request() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'flygit' ) );
        }

        check_admin_referer( 'flygit_uninstall' );

        $installation_id = isset( $_POST['installation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['installation_id'] ) ) : '';

        if ( empty( $installation_id ) ) {
            $this->redirect_with_message( 'error', __( 'Invalid uninstall request. Please try again.', 'flygit' ) );
        }

        $installation = $this->installer->get_installation_by_id( $installation_id );
        if ( $installation ) {
            $result = $this->installer->uninstall_installation( $installation_id );

            if ( is_wp_error( $result ) ) {
                $this->redirect_with_message( 'error', $result->get_error_message() );
            }

            $this->redirect_with_message( 'success', $result );
        }

        $snippet_installation = $this->snippets->get_installation_by_id( $installation_id );
        if ( $snippet_installation ) {
            $result = $this->snippets->uninstall_installation( $installation_id );

            if ( is_wp_error( $result ) ) {
                $this->redirect_with_message( 'error', $result->get_error_message() );
            }

            $this->redirect_with_message( 'success', $result );
        }

        $this->redirect_with_message( 'error', __( 'The requested installation could not be found.', 'flygit' ) );
    }

    /**
     * Handle snippet import submissions.
     */
    public function handle_snippet_import_request() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to perform this action.', 'flygit' ) );
        }

        check_admin_referer( 'flygit_import_snippet' );

        $installation_id = isset( $_POST['installation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['installation_id'] ) ) : '';
        $repository      = isset( $_POST['repository_url'] ) ? esc_url_raw( wp_unslash( $_POST['repository_url'] ) ) : '';
        $branch          = isset( $_POST['branch'] ) ? sanitize_text_field( wp_unslash( $_POST['branch'] ) ) : 'main';
        $access_token    = isset( $_POST['access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['access_token'] ) ) : '';
        $name            = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

        if ( ! empty( $installation_id ) ) {
            $result = $this->snippets->import_installation( $installation_id, $repository, $branch, $access_token, $name );
        } else {
            $result = $this->snippets->import_from_repository( $repository, '', $branch, $access_token, $name );
        }

        if ( is_wp_error( $result ) ) {
            $this->redirect_with_message( 'error', $result->get_error_message() );
        }

        $this->redirect_with_message( 'success', $result );
    }

    /**
     * Output the FlyGit dashboard.
     */
    public function render_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $all_themes      = wp_get_themes();
        $all_plugins     = get_plugins();
        $installations   = $this->installer->get_installations();
        $snippet_records = $this->snippets->get_installations();
        $active_plugins  = get_option( 'active_plugins', array() );
        $current_theme   = wp_get_theme();
        $status          = isset( $_GET['flygit_status'] ) ? sanitize_key( wp_unslash( $_GET['flygit_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $message_raw     = isset( $_GET['flygit_message'] ) ? wp_unslash( $_GET['flygit_message'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $message         = $message_raw ? sanitize_text_field( rawurldecode( $message_raw ) ) : '';

        $theme_slugs  = array();
        $plugin_slugs = array();

        foreach ( $installations as $installation ) {
            if ( isset( $installation['type'], $installation['slug'] ) ) {
                if ( 'theme' === $installation['type'] ) {
                    $theme_slugs[] = $installation['slug'];
                } elseif ( 'plugin' === $installation['type'] ) {
                    $plugin_slugs[] = $installation['slug'];
                }
            }
        }

        $themes = array();
        foreach ( $all_themes as $stylesheet => $theme ) {
            if ( in_array( $stylesheet, $theme_slugs, true ) ) {
                $themes[ $stylesheet ] = $theme;
            }
        }

        $plugins = array();
        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            foreach ( $plugin_slugs as $slug ) {
                if ( 0 === strpos( $plugin_file, trailingslashit( $slug ) ) ) {
                    $plugins[ $plugin_file ] = $plugin_data;
                    break;
                }
            }
        }

        $theme_installations_map  = array();
        $plugin_installations_map = array();

        foreach ( $installations as $installation ) {
            if ( empty( $installation['id'] ) || empty( $installation['type'] ) || empty( $installation['slug'] ) ) {
                continue;
            }

            $display = array(
                'id'             => $installation['id'],
                'type'           => $installation['type'],
                'slug'           => $installation['slug'],
                'repository_url' => isset( $installation['repository_url'] ) ? $installation['repository_url'] : '',
                'branch'         => isset( $installation['branch'] ) ? $installation['branch'] : '',
                'webhook_secret' => isset( $installation['webhook_secret'] ) ? $installation['webhook_secret'] : '',
                'webhook_url'    => rest_url( sprintf( 'flygit/v1/installations/%s/webhook', $installation['id'] ) ),
                'name'           => $installation['slug'],
                'description'    => '',
                'version'        => '',
                'is_active'      => false,
            );

            if ( 'theme' === $installation['type'] && isset( $themes[ $installation['slug'] ] ) ) {
                $theme = $themes[ $installation['slug'] ];
                $display['name']        = $theme->get( 'Name' ) ? $theme->get( 'Name' ) : $installation['slug'];
                $display['description'] = $theme->get( 'Description' );
                $display['version']     = $theme->get( 'Version' );
                $display['is_active']   = ( $current_theme->get_stylesheet() === $theme->get_stylesheet() );
                $theme_installations_map[ $installation['slug'] ] = $display;
            } elseif ( 'plugin' === $installation['type'] ) {
                foreach ( $plugins as $plugin_file => $plugin_data ) {
                    if ( 0 === strpos( $plugin_file, trailingslashit( $installation['slug'] ) ) ) {
                        $display['name']        = $plugin_data['Name'];
                        $display['description'] = $plugin_data['Description'];
                        $display['version']     = $plugin_data['Version'];
                        $display['is_active']   = in_array( $plugin_file, $active_plugins, true );
                        $display['plugin_file'] = $plugin_file;
                        break;
                    }
                }

                if ( isset( $display['plugin_file'] ) ) {
                    $plugin_installations_map[ $installation['slug'] ] = $display;
                }
            } else {
                continue;
            }
        }

        $snippet_installations = array();
        foreach ( $snippet_records as $snippet ) {
            if ( empty( $snippet['id'] ) ) {
                continue;
            }

            $files   = isset( $snippet['files'] ) && is_array( $snippet['files'] ) ? $snippet['files'] : array();
            $sources = isset( $snippet['sources'] ) && is_array( $snippet['sources'] ) ? $snippet['sources'] : array();

            $snippet_installations[] = array(
                'id'             => $snippet['id'],
                'type'           => 'snippet',
                'slug'           => isset( $snippet['slug'] ) ? $snippet['slug'] : '',
                'name'           => ! empty( $snippet['name'] ) ? $snippet['name'] : ( isset( $snippet['slug'] ) ? $snippet['slug'] : __( 'Snippet Repository', 'flygit' ) ),
                'repository_url' => isset( $snippet['repository_url'] ) ? $snippet['repository_url'] : '',
                'branch'         => isset( $snippet['branch'] ) ? $snippet['branch'] : 'main',
                'webhook_secret' => isset( $snippet['webhook_secret'] ) ? $snippet['webhook_secret'] : '',
                'files'          => $files,
                'sources'        => $sources,
                'file_count'     => count( $files ),
                'last_import'    => ( isset( $snippet['last_import'] ) && $snippet['last_import'] ) ? wp_date( 'Y-m-d H:i:s', (int) $snippet['last_import'] ) : '',
                'webhook_url'    => rest_url( sprintf( 'flygit/v1/installations/%s/webhook', $snippet['id'] ) ),
            );
        }

        $installed_count = array(
            'themes'   => count( $themes ),
            'plugins'  => count( $plugins ),
            'snippets' => count( $snippet_installations ),
        );

        $code_snippet_error   = '';
        $code_snippets        = $this->snippets->get_snippets();
        if ( is_wp_error( $code_snippets ) ) {
            $code_snippet_error = $code_snippets->get_error_message();
            $code_snippets      = array();
        }

        $snippet_storage_path    = $this->snippets->get_storage_directory_path();
        $snippet_storage_display = $snippet_storage_path;

        if ( defined( 'ABSPATH' ) ) {
            $normalized_base = wp_normalize_path( ABSPATH );
            $normalized_path = wp_normalize_path( $snippet_storage_path );

            if ( 0 === strpos( $normalized_path, $normalized_base ) ) {
                $relative = ltrim( substr( $normalized_path, strlen( $normalized_base ) ), '/' );
                if ( ! empty( $relative ) ) {
                    $snippet_storage_display = $relative;
                }
            }
        }

        include FLYGIT_PLUGIN_DIR . 'includes/views/dashboard.php';
    }

    /**
     * Redirect back to the dashboard with a message.
     *
     * @param string $status  Message status.
     * @param string $message Message text.
     */
    protected function redirect_with_message( $status, $message ) {
        $redirect_url = add_query_arg(
            array(
                'page'            => 'flygit',
                'flygit_status'   => $status,
                'flygit_message'  => rawurlencode( $message ),
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect_url );
        exit;
    }
}
