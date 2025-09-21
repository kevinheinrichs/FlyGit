<?php
/** @var array $themes */
/** @var array $plugins */
/** @var array $active_plugins */
/** @var WP_Theme $current_theme */
/** @var array $theme_installations_map */
/** @var array $plugin_installations_map */
/** @var string $status */
/** @var string $message */
/** @var array $installed_count */
/** @var array $code_snippets */
/** @var string $code_snippet_error */
/** @var string $snippet_storage_path */
/** @var string $snippet_storage_display */

?>
<div class="wrap flygit-dashboard">
    <?php
    $theme_total            = isset( $installed_count['themes'] ) ? (int) $installed_count['themes'] : ( is_array( $themes ) ? count( $themes ) : 0 );
    $plugin_total           = isset( $installed_count['plugins'] ) ? (int) $installed_count['plugins'] : ( is_array( $plugins ) ? count( $plugins ) : 0 );
    $snippet_total          = isset( $installed_count['snippets'] ) ? (int) $installed_count['snippets'] : ( isset( $snippet_installations ) && is_array( $snippet_installations ) ? count( $snippet_installations ) : 0 );
    $active_plugin_count    = is_array( $active_plugins ) ? count( $active_plugins ) : 0;
    $current_theme_name     = ( $current_theme instanceof WP_Theme ) ? $current_theme->get( 'Name' ) : '';
    $stored_snippet_count   = is_array( $code_snippets ) ? count( $code_snippets ) : 0;
    ?>

    <header class="flygit-hero">
        <div class="flygit-hero-body">
            <h1><?php esc_html_e( 'FlyGit Dashboard', 'flygit' ); ?></h1>
            <p>
                <?php esc_html_e( 'Manage Git-powered deployments for your WordPress themes, plugins, and snippets in one place.', 'flygit' ); ?>
            </p>
            <?php if ( ! empty( $current_theme_name ) ) : ?>
                <span class="flygit-hero-meta">
                    <?php printf( esc_html__( 'Current theme: %s', 'flygit' ), esc_html( $current_theme_name ) ); ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="flygit-hero-actions" aria-label="<?php esc_attr_e( 'Quick actions', 'flygit' ); ?>">
            <a class="button button-primary flygit-hero-button" href="#flygit-install-theme">
                <span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span>
                <span><?php esc_html_e( 'Install Theme', 'flygit' ); ?></span>
            </a>
            <a class="button flygit-hero-button" href="#flygit-install-plugin">
                <span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
                <span><?php esc_html_e( 'Install Plugin', 'flygit' ); ?></span>
            </a>
            <a class="button flygit-hero-button" href="#flygit-import-snippets">
                <span class="dashicons dashicons-media-code" aria-hidden="true"></span>
                <span><?php esc_html_e( 'Import Snippets', 'flygit' ); ?></span>
            </a>
        </div>
    </header>

    <?php if ( ! empty( $status ) && ! empty( $message ) ) : ?>
        <?php
        $class = ( 'success' === $status ) ? 'notice-success' : 'notice-error';
        ?>
        <div class="notice <?php echo esc_attr( $class ); ?> is-dismissible">
            <p><?php echo esc_html( $message ); ?></p>
        </div>
    <?php endif; ?>

    <div class="flygit-stats-grid" role="list">
        <div class="flygit-stat-card" role="listitem">
            <span class="flygit-stat-icon dashicons dashicons-admin-appearance" aria-hidden="true"></span>
            <div class="flygit-stat-content">
                <span class="flygit-stat-label"><?php esc_html_e( 'Themes managed', 'flygit' ); ?></span>
                <span class="flygit-stat-value"><?php echo esc_html( number_format_i18n( $theme_total ) ); ?></span>
                <?php if ( ! empty( $current_theme_name ) ) : ?>
                    <span class="flygit-stat-sub"><?php printf( esc_html__( 'Active theme: %s', 'flygit' ), esc_html( $current_theme_name ) ); ?></span>
                <?php else : ?>
                    <span class="flygit-stat-sub"><?php esc_html_e( 'No active theme detected.', 'flygit' ); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="flygit-stat-card" role="listitem">
            <span class="flygit-stat-icon dashicons dashicons-admin-plugins" aria-hidden="true"></span>
            <div class="flygit-stat-content">
                <span class="flygit-stat-label"><?php esc_html_e( 'Plugins managed', 'flygit' ); ?></span>
                <span class="flygit-stat-value"><?php echo esc_html( number_format_i18n( $plugin_total ) ); ?></span>
                <span class="flygit-stat-sub"><?php printf( esc_html__( '%d active plugins', 'flygit' ), (int) $active_plugin_count ); ?></span>
            </div>
        </div>
        <div class="flygit-stat-card" role="listitem">
            <span class="flygit-stat-icon dashicons dashicons-media-code" aria-hidden="true"></span>
            <div class="flygit-stat-content">
                <span class="flygit-stat-label"><?php esc_html_e( 'Snippet repositories', 'flygit' ); ?></span>
                <span class="flygit-stat-value"><?php echo esc_html( number_format_i18n( $snippet_total ) ); ?></span>
                <span class="flygit-stat-sub"><?php printf( esc_html__( '%d stored snippets', 'flygit' ), (int) $stored_snippet_count ); ?></span>
            </div>
        </div>
    </div>

    <div class="flygit-columns">
        <div class="flygit-column">
            <section class="flygit-section">
                <header class="flygit-section-header">
                    <h2>
                        <?php esc_html_e( 'Installed Themes', 'flygit' ); ?>
                        <span class="flygit-badge"><?php echo esc_html( $installed_count['themes'] ); ?></span>
                    </h2>
                </header>
                <div class="flygit-list">
                    <?php if ( ! empty( $themes ) ) : ?>
                        <?php foreach ( $themes as $stylesheet => $theme ) : ?>
                        <?php
                        $theme_slug         = $theme->get_stylesheet();
                        $theme_installation = isset( $theme_installations_map[ $theme_slug ] ) ? $theme_installations_map[ $theme_slug ] : null;
                        $is_active          = ( $current_theme->get_stylesheet() === $theme_slug );
                        $theme_name         = $theme->get( 'Name' );
                        $theme_description  = $theme->get( 'Description' );
                        $activate_url       = wp_nonce_url(
                            admin_url( 'themes.php?action=activate&stylesheet=' . $theme_slug ),
                            'switch-theme_' . $theme_slug
                        );
                        $customize_url = admin_url( 'customize.php?theme=' . $theme_slug );
                        $details_url   = admin_url( 'theme-install.php?tab=theme-information&theme=' . $theme_slug );
                        $uninstall_label = $theme_name ? $theme_name : $theme_slug;
                        $uninstall_message = sprintf( esc_html__( 'Are you sure you want to uninstall the theme "%s"?', 'flygit' ), $uninstall_label );
                        ?>
                        <details class="flygit-item" <?php echo $is_active ? 'open' : ''; ?>>
                            <summary class="flygit-item-summary">
                                <span class="flygit-item-title"><?php echo esc_html( $theme_name ); ?></span>
                                <span class="flygit-item-meta">
                                    <?php if ( $is_active ) : ?>
                                        <span class="flygit-status flygit-status-active"><?php esc_html_e( 'Active', 'flygit' ); ?></span>
                                    <?php else : ?>
                                        <span class="flygit-status flygit-status-inactive"><?php esc_html_e( 'Inactive', 'flygit' ); ?></span>
                                    <?php endif; ?>
                                    <span><?php printf( esc_html__( 'Version %s', 'flygit' ), esc_html( $theme->get( 'Version' ) ) ); ?></span>
                                </span>
                            </summary>
                            <div class="flygit-item-body">
                                <?php if ( $theme_description ) : ?>
                                    <p class="flygit-description"><?php echo esc_html( $theme_description ); ?></p>
                                <?php endif; ?>

                                <ul class="flygit-meta">
                                    <li><strong><?php esc_html_e( 'Author:', 'flygit' ); ?></strong> <?php echo wp_kses_post( $theme->get( 'Author' ) ); ?></li>
                                    <li><strong><?php esc_html_e( 'Template:', 'flygit' ); ?></strong> <?php echo esc_html( $theme->get_template() ); ?></li>
                                    <?php
                                    $theme_tags = $theme->get( 'Tags' );
                                    if ( ! empty( $theme_tags ) ) :
                                        if ( is_array( $theme_tags ) ) {
                                            $theme_tags = implode( ', ', $theme_tags );
                                        }
                                    ?>
                                        <li><strong><?php esc_html_e( 'Tags:', 'flygit' ); ?></strong> <?php echo esc_html( $theme_tags ); ?></li>
                                    <?php endif; ?>
                                </ul>

                                <div class="flygit-actions">
                                    <?php if ( ! $is_active ) : ?>
                                        <a class="button button-primary" href="<?php echo esc_url( $activate_url ); ?>"><?php esc_html_e( 'Activate', 'flygit' ); ?></a>
                                    <?php else : ?>
                                        <span class="button disabled"><?php esc_html_e( 'Active', 'flygit' ); ?></span>
                                    <?php endif; ?>

                                    <a class="button" href="<?php echo esc_url( $customize_url ); ?>"><?php esc_html_e( 'Customize', 'flygit' ); ?></a>
                                    <a class="button" href="<?php echo esc_url( $details_url ); ?>"><?php esc_html_e( 'Details', 'flygit' ); ?></a>
                                    <?php if ( $theme_installation ) : ?>
                                        <?php if ( $theme_installation['is_active'] ) : ?>
                                            <span class="button disabled" title="<?php esc_attr_e( 'Active themes cannot be uninstalled.', 'flygit' ); ?>"><?php esc_html_e( 'Uninstall', 'flygit' ); ?></span>
                                        <?php else : ?>
                                            <form class="flygit-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( $uninstall_message ); ?>');">
                                                <?php wp_nonce_field( 'flygit_uninstall' ); ?>
                                                <input type="hidden" name="action" value="flygit_uninstall" />
                                                <input type="hidden" name="installation_id" value="<?php echo esc_attr( $theme_installation['id'] ); ?>" />
                                                <button type="submit" class="button flygit-button-danger"><?php esc_html_e( 'Uninstall', 'flygit' ); ?></button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <?php if ( $theme_installation ) : ?>
                                    <div class="flygit-installation-settings">
                                        <h4><?php esc_html_e( 'FlyGit Settings', 'flygit' ); ?></h4>

                                        <?php if ( ! empty( $theme_installation['repository_url'] ) || ! empty( $theme_installation['branch'] ) ) : ?>
                                            <ul class="flygit-meta">
                                                <?php if ( ! empty( $theme_installation['repository_url'] ) ) : ?>
                                                    <li><strong><?php esc_html_e( 'Repository:', 'flygit' ); ?></strong> <a href="<?php echo esc_url( $theme_installation['repository_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $theme_installation['repository_url'] ); ?></a></li>
                                                <?php endif; ?>
                                                <?php if ( ! empty( $theme_installation['branch'] ) ) : ?>
                                                    <li><strong><?php esc_html_e( 'Branch:', 'flygit' ); ?></strong> <?php echo esc_html( $theme_installation['branch'] ); ?></li>
                                                <?php endif; ?>
                                            </ul>
                                        <?php endif; ?>

                                        <?php $webhook_element_id = 'flygit-webhook-url-' . sanitize_html_class( $theme_installation['id'] ); ?>
                                        <div class="flygit-installation-webhook">
                                            <span class="flygit-installation-subtitle"><?php esc_html_e( 'Webhook Endpoint', 'flygit' ); ?></span>
                                            <div class="flygit-webhook-endpoint">
                                                <code id="<?php echo esc_attr( $webhook_element_id ); ?>"><?php echo esc_html( $theme_installation['webhook_url'] ); ?></code>
                                                <button type="button" class="button flygit-copy" data-target="<?php echo esc_attr( $webhook_element_id ); ?>"><?php esc_html_e( 'Copy', 'flygit' ); ?></button>
                                            </div>
                                            <p class="description"><?php esc_html_e( 'Send a POST request to this endpoint to pull the latest code for the installation.', 'flygit' ); ?></p>
                                        </div>

                                        <form class="flygit-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                            <?php wp_nonce_field( 'flygit_webhook_settings' ); ?>
                                            <input type="hidden" name="action" value="flygit_save_webhook_settings" />
                                            <input type="hidden" name="installation_id" value="<?php echo esc_attr( $theme_installation['id'] ); ?>" />
                                            <label>
                                                <?php esc_html_e( 'Webhook Secret (optional)', 'flygit' ); ?>
                                                <input type="text" name="webhook_secret" value="<?php echo esc_attr( $theme_installation['webhook_secret'] ); ?>" placeholder="<?php esc_attr_e( 'Secret token to verify requests', 'flygit' ); ?>" />
                                            </label>
                                            <p class="description"><?php esc_html_e( 'When set, authenticate the webhook using the X-Flygit-Secret header, a "secret" payload field, GitHub\'s X-Hub-Signature headers or GitLab\'s X-Gitlab-Token header.', 'flygit' ); ?></p>
                                            <p class="description"><?php esc_html_e( 'Payloads can be sent as application/json (recommended) or application/x-www-form-urlencoded.', 'flygit' ); ?></p>
                                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Webhook Settings', 'flygit' ); ?></button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </details>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="description flygit-empty-state">
                            <span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span>
                            <?php esc_html_e( 'No themes installed with FlyGit yet.', 'flygit' ); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <footer class="flygit-section-footer" id="flygit-install-theme">
                    <h3><?php esc_html_e( 'Install Theme', 'flygit' ); ?></h3>
                    <form class="flygit-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'flygit_install' ); ?>
                        <input type="hidden" name="action" value="flygit_install" />
                        <input type="hidden" name="install_type" value="theme" />

                        <label>
                            <?php esc_html_e( 'Repository URL', 'flygit' ); ?>
                            <input type="url" name="repository_url" placeholder="https://github.com/owner/repo" required />
                        </label>
                        <label>
                            <?php esc_html_e( 'Branch', 'flygit' ); ?>
                            <input type="text" name="branch" placeholder="main" />
                        </label>
                        <label>
                            <?php esc_html_e( 'Access Token (optional)', 'flygit' ); ?>
                            <input type="password" name="access_token" autocomplete="off" />
                        </label>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( 'Install Theme', 'flygit' ); ?>
                        </button>
                    </form>
                </footer>
            </section>
        </div>

        <div class="flygit-column">
            <section class="flygit-section">
                <header class="flygit-section-header">
                    <h2>
                        <?php esc_html_e( 'Installed Plugins', 'flygit' ); ?>
                        <span class="flygit-badge"><?php echo esc_html( $installed_count['plugins'] ); ?></span>
                    </h2>
                </header>
                <div class="flygit-list">
                    <?php if ( ! empty( $plugins ) ) : ?>
                        <?php foreach ( $plugins as $plugin_file => $plugin_data ) : ?>
                        <?php
                        $plugin_slug         = dirname( $plugin_file );
                        $plugin_installation = ( '.' !== $plugin_slug && isset( $plugin_installations_map[ $plugin_slug ] ) ) ? $plugin_installations_map[ $plugin_slug ] : null;
                        $is_active           = in_array( $plugin_file, $active_plugins, true );
                        $activate_url        = wp_nonce_url(
                            admin_url( 'plugins.php?action=activate&plugin=' . $plugin_file ),
                            'activate-plugin_' . $plugin_file
                        );
                        $deactivate_url = wp_nonce_url(
                            admin_url( 'plugins.php?action=deactivate&plugin=' . $plugin_file ),
                            'deactivate-plugin_' . $plugin_file
                        );
                        $plugin_actions = apply_filters( 'plugin_action_links_' . $plugin_file, array(), $plugin_file, $plugin_data, 'flygit' );
                        $plugin_actions = apply_filters( 'plugin_action_links', $plugin_actions, $plugin_file, $plugin_data, 'flygit' );
                        $plugin_uninstall_label   = ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : $plugin_slug;
                        $plugin_uninstall_message = sprintf( esc_html__( 'Are you sure you want to uninstall the plugin "%s"?', 'flygit' ), $plugin_uninstall_label );
                        ?>
                        <details class="flygit-item">
                            <summary class="flygit-item-summary">
                                <span class="flygit-item-title"><?php echo esc_html( $plugin_data['Name'] ); ?></span>
                                <span class="flygit-item-meta">
                                    <?php if ( $is_active ) : ?>
                                        <span class="flygit-status flygit-status-active"><?php esc_html_e( 'Active', 'flygit' ); ?></span>
                                    <?php else : ?>
                                        <span class="flygit-status flygit-status-inactive"><?php esc_html_e( 'Inactive', 'flygit' ); ?></span>
                                    <?php endif; ?>
                                    <span><?php printf( esc_html__( 'Version %s', 'flygit' ), esc_html( $plugin_data['Version'] ) ); ?></span>
                                </span>
                            </summary>
                            <div class="flygit-item-body">
                                <?php if ( ! empty( $plugin_data['Description'] ) ) : ?>
                                    <p class="flygit-description"><?php echo esc_html( $plugin_data['Description'] ); ?></p>
                                <?php endif; ?>

                                <ul class="flygit-meta">
                                    <li><strong><?php esc_html_e( 'Author:', 'flygit' ); ?></strong> <?php echo wp_kses_post( $plugin_data['Author'] ); ?></li>
                                    <?php if ( ! empty( $plugin_data['PluginURI'] ) ) : ?>
                                        <li><strong><?php esc_html_e( 'Website:', 'flygit' ); ?></strong> <a href="<?php echo esc_url( $plugin_data['PluginURI'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $plugin_data['PluginURI'] ); ?></a></li>
                                    <?php endif; ?>
                                    <li><strong><?php esc_html_e( 'Path:', 'flygit' ); ?></strong> <code><?php echo esc_html( $plugin_file ); ?></code></li>
                                </ul>

                                <div class="flygit-actions">
                                    <?php if ( $is_active ) : ?>
                                        <a class="button" href="<?php echo esc_url( $deactivate_url ); ?>"><?php esc_html_e( 'Deactivate', 'flygit' ); ?></a>
                                    <?php else : ?>
                                        <a class="button button-primary" href="<?php echo esc_url( $activate_url ); ?>"><?php esc_html_e( 'Activate', 'flygit' ); ?></a>
                                    <?php endif; ?>

                                    <?php
                                    if ( ! empty( $plugin_actions ) && is_array( $plugin_actions ) ) {
                                        foreach ( $plugin_actions as $action ) {
                                            echo '<span class="flygit-action-link">' . wp_kses_post( $action ) . '</span>';
                                        }
                                    }
                                    ?>
                                    <?php if ( $plugin_installation ) : ?>
                                        <form class="flygit-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( $plugin_uninstall_message ); ?>');">
                                            <?php wp_nonce_field( 'flygit_uninstall' ); ?>
                                            <input type="hidden" name="action" value="flygit_uninstall" />
                                            <input type="hidden" name="installation_id" value="<?php echo esc_attr( $plugin_installation['id'] ); ?>" />
                                            <button type="submit" class="button flygit-button-danger"><?php esc_html_e( 'Uninstall', 'flygit' ); ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <?php if ( $plugin_installation ) : ?>
                                    <div class="flygit-installation-settings">
                                        <h4><?php esc_html_e( 'FlyGit Settings', 'flygit' ); ?></h4>

                                        <?php if ( ! empty( $plugin_installation['repository_url'] ) || ! empty( $plugin_installation['branch'] ) ) : ?>
                                            <ul class="flygit-meta">
                                                <?php if ( ! empty( $plugin_installation['repository_url'] ) ) : ?>
                                                    <li><strong><?php esc_html_e( 'Repository:', 'flygit' ); ?></strong> <a href="<?php echo esc_url( $plugin_installation['repository_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $plugin_installation['repository_url'] ); ?></a></li>
                                                <?php endif; ?>
                                                <?php if ( ! empty( $plugin_installation['branch'] ) ) : ?>
                                                    <li><strong><?php esc_html_e( 'Branch:', 'flygit' ); ?></strong> <?php echo esc_html( $plugin_installation['branch'] ); ?></li>
                                                <?php endif; ?>
                                            </ul>
                                        <?php endif; ?>

                                        <?php $webhook_element_id = 'flygit-webhook-url-' . sanitize_html_class( $plugin_installation['id'] ); ?>
                                        <div class="flygit-installation-webhook">
                                            <span class="flygit-installation-subtitle"><?php esc_html_e( 'Webhook Endpoint', 'flygit' ); ?></span>
                                            <div class="flygit-webhook-endpoint">
                                                <code id="<?php echo esc_attr( $webhook_element_id ); ?>"><?php echo esc_html( $plugin_installation['webhook_url'] ); ?></code>
                                                <button type="button" class="button flygit-copy" data-target="<?php echo esc_attr( $webhook_element_id ); ?>"><?php esc_html_e( 'Copy', 'flygit' ); ?></button>
                                            </div>
                                            <p class="description"><?php esc_html_e( 'Send a POST request to this endpoint to pull the latest code for the installation.', 'flygit' ); ?></p>
                                        </div>

                                        <form class="flygit-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                            <?php wp_nonce_field( 'flygit_webhook_settings' ); ?>
                                            <input type="hidden" name="action" value="flygit_save_webhook_settings" />
                                            <input type="hidden" name="installation_id" value="<?php echo esc_attr( $plugin_installation['id'] ); ?>" />
                                            <label>
                                                <?php esc_html_e( 'Webhook Secret (optional)', 'flygit' ); ?>
                                                <input type="text" name="webhook_secret" value="<?php echo esc_attr( $plugin_installation['webhook_secret'] ); ?>" placeholder="<?php esc_attr_e( 'Secret token to verify requests', 'flygit' ); ?>" />
                                            </label>
                                            <p class="description"><?php esc_html_e( 'When set, authenticate the webhook using the X-Flygit-Secret header, a "secret" payload field, GitHub\'s X-Hub-Signature headers or GitLab\'s X-Gitlab-Token header.', 'flygit' ); ?></p>
                                            <p class="description"><?php esc_html_e( 'Payloads can be sent as application/json (recommended) or application/x-www-form-urlencoded.', 'flygit' ); ?></p>
                                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Webhook Settings', 'flygit' ); ?></button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </details>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="description flygit-empty-state">
                            <span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
                            <?php esc_html_e( 'No plugins installed with FlyGit yet.', 'flygit' ); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <footer class="flygit-section-footer" id="flygit-install-plugin">
                    <h3><?php esc_html_e( 'Install Plugin', 'flygit' ); ?></h3>
                    <form class="flygit-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'flygit_install' ); ?>
                        <input type="hidden" name="action" value="flygit_install" />
                        <input type="hidden" name="install_type" value="plugin" />

                        <label>
                            <?php esc_html_e( 'Repository URL', 'flygit' ); ?>
                            <input type="url" name="repository_url" placeholder="https://github.com/owner/repo" required />
                        </label>
                        <label>
                            <?php esc_html_e( 'Branch', 'flygit' ); ?>
                            <input type="text" name="branch" placeholder="main" />
                        </label>
                        <label>
                            <?php esc_html_e( 'Access Token (optional)', 'flygit' ); ?>
                            <input type="password" name="access_token" autocomplete="off" />
                        </label>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e( 'Install Plugin', 'flygit' ); ?>
                        </button>
                    </form>
                </footer>
            </section>
        </div>
</div>

<section class="flygit-section flygit-snippets-section">
    <header class="flygit-section-header">
        <h2>
            <?php esc_html_e( 'Installed Snippet Repositories', 'flygit' ); ?>
            <span class="flygit-badge"><?php echo esc_html( isset( $installed_count['snippets'] ) ? $installed_count['snippets'] : 0 ); ?></span>
        </h2>
    </header>
    <div class="flygit-snippets-body">
        <p class="description">
            <?php esc_html_e( 'Import reusable PHP snippets directly from Git repositories. Imported snippets are stored in:', 'flygit' ); ?>
            <code><?php echo esc_html( $snippet_storage_display ); ?></code>
            <?php if ( $snippet_storage_display !== $snippet_storage_path ) : ?>
                <span class="flygit-snippets-path-alt">(<?php echo esc_html( $snippet_storage_path ); ?>)</span>
            <?php endif; ?>
        </p>

        <div class="flygit-list">
            <?php if ( ! empty( $snippet_installations ) ) : ?>
                <?php foreach ( $snippet_installations as $snippet_installation ) : ?>
                    <?php
                    $snippet_files   = isset( $snippet_installation['files'] ) && is_array( $snippet_installation['files'] ) ? $snippet_installation['files'] : array();
                    $snippet_sources = isset( $snippet_installation['sources'] ) && is_array( $snippet_installation['sources'] ) ? $snippet_installation['sources'] : array();
                    $snippet_count   = isset( $snippet_installation['file_count'] ) ? (int) $snippet_installation['file_count'] : count( $snippet_files );
                    $snippet_last    = isset( $snippet_installation['last_import'] ) ? $snippet_installation['last_import'] : '';
                    $snippet_uninstall_label = ! empty( $snippet_installation['name'] ) ? $snippet_installation['name'] : $snippet_installation['id'];
                    $snippet_uninstall_message = sprintf( esc_html__( 'Are you sure you want to uninstall the snippet repository "%s"?', 'flygit' ), $snippet_uninstall_label );
                    $snippet_webhook_element_id = 'flygit-webhook-url-' . sanitize_html_class( $snippet_installation['id'] );
                    $snippet_display_name      = ! empty( $snippet_installation['name'] ) ? $snippet_installation['name'] : ( isset( $snippet_installation['slug'] ) ? $snippet_installation['slug'] : __( 'Snippet Repository', 'flygit' ) );
                    ?>
                    <details class="flygit-item">
                        <summary class="flygit-item-summary">
                            <span class="flygit-item-title"><?php echo esc_html( $snippet_display_name ); ?></span>
                            <span class="flygit-item-meta">
                                <span><?php printf( esc_html__( '%d files', 'flygit' ), $snippet_count ); ?></span>
                                <?php if ( ! empty( $snippet_last ) ) : ?>
                                    <span><?php printf( esc_html__( 'Last import: %s', 'flygit' ), esc_html( $snippet_last ) ); ?></span>
                                <?php endif; ?>
                            </span>
                        </summary>
                        <div class="flygit-item-body">
                            <ul class="flygit-meta">
                                <?php if ( ! empty( $snippet_installation['repository_url'] ) ) : ?>
                                    <li><strong><?php esc_html_e( 'Repository:', 'flygit' ); ?></strong> <a href="<?php echo esc_url( $snippet_installation['repository_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $snippet_installation['repository_url'] ); ?></a></li>
                                <?php endif; ?>
                                <?php if ( ! empty( $snippet_installation['branch'] ) ) : ?>
                                    <li><strong><?php esc_html_e( 'Branch:', 'flygit' ); ?></strong> <?php echo esc_html( $snippet_installation['branch'] ); ?></li>
                                <?php endif; ?>
                                <?php if ( ! empty( $snippet_files ) ) : ?>
                                    <li>
                                        <strong><?php esc_html_e( 'Imported Files:', 'flygit' ); ?></strong>
                                        <ul class="flygit-sublist">
                                            <?php foreach ( $snippet_files as $file_name ) : ?>
                                                <?php $source = isset( $snippet_sources[ $file_name ] ) ? $snippet_sources[ $file_name ] : ''; ?>
                                                <li>
                                                    <code><?php echo esc_html( $file_name ); ?></code>
                                                    <?php if ( ! empty( $source ) ) : ?>
                                                        <span class="description">(<?php echo esc_html( $source ); ?>)</span>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </li>
                                <?php endif; ?>
                            </ul>

                            <div class="flygit-actions">
                                <form class="flygit-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                    <?php wp_nonce_field( 'flygit_import_snippet' ); ?>
                                    <input type="hidden" name="action" value="flygit_import_snippet" />
                                    <input type="hidden" name="installation_id" value="<?php echo esc_attr( $snippet_installation['id'] ); ?>" />
                                    <button type="submit" class="button"><?php esc_html_e( 'Pull Latest', 'flygit' ); ?></button>
                                </form>
                                <form class="flygit-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( $snippet_uninstall_message ); ?>');">
                                    <?php wp_nonce_field( 'flygit_uninstall' ); ?>
                                    <input type="hidden" name="action" value="flygit_uninstall" />
                                    <input type="hidden" name="installation_id" value="<?php echo esc_attr( $snippet_installation['id'] ); ?>" />
                                    <button type="submit" class="button flygit-button-danger"><?php esc_html_e( 'Uninstall', 'flygit' ); ?></button>
                                </form>
                            </div>

                            <div class="flygit-installation-settings">
                                <h4><?php esc_html_e( 'FlyGit Settings', 'flygit' ); ?></h4>

                                <form class="flygit-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                    <?php wp_nonce_field( 'flygit_snippet_settings' ); ?>
                                    <input type="hidden" name="action" value="flygit_update_snippet_settings" />
                                    <input type="hidden" name="installation_id" value="<?php echo esc_attr( $snippet_installation['id'] ); ?>" />
                                    <label>
                                        <?php esc_html_e( 'Display Name', 'flygit' ); ?>
                                        <input type="text" name="name" value="<?php echo esc_attr( $snippet_display_name ); ?>" required />
                                    </label>
                                    <button type="submit" class="button"><?php esc_html_e( 'Save Name', 'flygit' ); ?></button>
                                </form>

                                <div class="flygit-installation-webhook">
                                    <span class="flygit-installation-subtitle"><?php esc_html_e( 'Webhook Endpoint', 'flygit' ); ?></span>
                                    <div class="flygit-webhook-endpoint">
                                        <code id="<?php echo esc_attr( $snippet_webhook_element_id ); ?>"><?php echo esc_html( $snippet_installation['webhook_url'] ); ?></code>
                                        <button type="button" class="button flygit-copy" data-target="<?php echo esc_attr( $snippet_webhook_element_id ); ?>"><?php esc_html_e( 'Copy', 'flygit' ); ?></button>
                                    </div>
                                    <p class="description"><?php esc_html_e( 'Send a POST request to this endpoint to pull the latest snippets.', 'flygit' ); ?></p>
                                </div>

                                <form class="flygit-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                    <?php wp_nonce_field( 'flygit_webhook_settings' ); ?>
                                    <input type="hidden" name="action" value="flygit_save_webhook_settings" />
                                    <input type="hidden" name="installation_id" value="<?php echo esc_attr( $snippet_installation['id'] ); ?>" />
                                    <label>
                                        <?php esc_html_e( 'Webhook Secret (optional)', 'flygit' ); ?>
                                        <input type="text" name="webhook_secret" value="<?php echo esc_attr( $snippet_installation['webhook_secret'] ); ?>" placeholder="<?php esc_attr_e( 'Secret token to verify requests', 'flygit' ); ?>" />
                                    </label>
                                    <p class="description"><?php esc_html_e( 'When set, authenticate the webhook using the same headers supported for themes and plugins.', 'flygit' ); ?></p>
                                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Webhook Settings', 'flygit' ); ?></button>
                                </form>
                            </div>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php else : ?>
                <p class="description flygit-empty-state">
                    <span class="dashicons dashicons-media-code" aria-hidden="true"></span>
                    <?php esc_html_e( 'No snippet repositories imported yet.', 'flygit' ); ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if ( ! empty( $code_snippet_error ) ) : ?>
            <div class="notice notice-error inline">
                <p><?php echo esc_html( $code_snippet_error ); ?></p>
            </div>
        <?php endif; ?>

        <div class="flygit-snippets-list">
            <h3><?php esc_html_e( 'Stored Snippets', 'flygit' ); ?></h3>

            <?php if ( ! empty( $code_snippets ) ) : ?>
                <ul class="flygit-snippet-list">
                    <?php foreach ( $code_snippets as $snippet ) : ?>
                        <?php
                        $metadata = isset( $snippet['metadata'] ) && is_array( $snippet['metadata'] ) ? $snippet['metadata'] : array();
                        $snippet_title = ! empty( $metadata['name'] ) ? $metadata['name'] : $snippet['file'];
                        $snippet_description = isset( $metadata['description'] ) ? $metadata['description'] : '';
                        $snippet_created = isset( $metadata['created_at'] ) ? $metadata['created_at'] : '';
                        $snippet_status = isset( $metadata['status'] ) ? $metadata['status'] : '';
                        $snippet_updated = ( isset( $snippet['modified'] ) && $snippet['modified'] ) ? wp_date( 'Y-m-d H:i:s', (int) $snippet['modified'] ) : '';
                        $snippet_size = ( isset( $snippet['size'] ) && is_numeric( $snippet['size'] ) ) ? size_format( (float) $snippet['size'] ) : '';
                        ?>
                        <li class="flygit-snippet-item">
                            <span class="flygit-snippet-title"><?php echo esc_html( $snippet_title ); ?></span>
                            <span class="flygit-snippet-meta">
                                <span><strong><?php esc_html_e( 'File:', 'flygit' ); ?></strong> <?php echo esc_html( $snippet['file'] ); ?></span>
                                <?php if ( ! empty( $snippet_created ) ) : ?>
                                    <span><strong><?php esc_html_e( 'Created:', 'flygit' ); ?></strong> <?php echo esc_html( $snippet_created ); ?></span>
                                <?php endif; ?>
                                <?php if ( ! empty( $snippet_updated ) ) : ?>
                                    <span><strong><?php esc_html_e( 'Updated:', 'flygit' ); ?></strong> <?php echo esc_html( $snippet_updated ); ?></span>
                                <?php endif; ?>
                                <?php if ( ! empty( $snippet_size ) ) : ?>
                                    <span><strong><?php esc_html_e( 'Size:', 'flygit' ); ?></strong> <?php echo esc_html( $snippet_size ); ?></span>
                                <?php endif; ?>
                                <?php if ( ! empty( $snippet_status ) ) : ?>
                                    <span><strong><?php esc_html_e( 'Status:', 'flygit' ); ?></strong> <?php echo esc_html( $snippet_status ); ?></span>
                                <?php endif; ?>
                            </span>
                            <?php if ( ! empty( $snippet_description ) ) : ?>
                                <p class="flygit-snippet-description"><?php echo esc_html( $snippet_description ); ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="description flygit-empty-state">
                    <span class="dashicons dashicons-media-code" aria-hidden="true"></span>
                    <?php esc_html_e( 'No snippets imported yet.', 'flygit' ); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <footer class="flygit-section-footer" id="flygit-import-snippets">
        <h3><?php esc_html_e( 'Import Snippet Repository', 'flygit' ); ?></h3>
        <form class="flygit-form flygit-snippet-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'flygit_import_snippet' ); ?>
            <input type="hidden" name="action" value="flygit_import_snippet" />

            <label>
                <?php esc_html_e( 'Display Name', 'flygit' ); ?>
                <input type="text" name="name" placeholder="<?php esc_attr_e( 'Code Snippets', 'flygit' ); ?>" />
            </label>

            <label>
                <?php esc_html_e( 'Repository URL', 'flygit' ); ?>
                <input type="url" name="repository_url" placeholder="https://github.com/owner/repository" required />
            </label>

            <label>
                <?php esc_html_e( 'Branch', 'flygit' ); ?>
                <input type="text" name="branch" placeholder="main" />
            </label>

            <label>
                <?php esc_html_e( 'Access Token (optional)', 'flygit' ); ?>
                <input type="password" name="access_token" autocomplete="off" />
            </label>

            <p class="description"><?php esc_html_e( 'All PHP files located in the /php directory will be imported automatically.', 'flygit' ); ?></p>
            <p class="description"><?php esc_html_e( 'Provide a GitHub personal access token when importing from private repositories.', 'flygit' ); ?></p>

            <button type="submit" class="button button-primary">
                <?php esc_html_e( 'Import Snippets', 'flygit' ); ?>
            </button>
        </form>
    </footer>
</section>

</div>
