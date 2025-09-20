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
    <h1><?php esc_html_e( 'FlyGit Dashboard', 'flygit' ); ?></h1>

    <?php if ( ! empty( $status ) && ! empty( $message ) ) : ?>
        <?php
        $class = ( 'success' === $status ) ? 'notice-success' : 'notice-error';
        ?>
        <div class="notice <?php echo esc_attr( $class ); ?> is-dismissible">
            <p><?php echo esc_html( $message ); ?></p>
        </div>
    <?php endif; ?>

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
                            <summary>
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
                        <p class="description"><?php esc_html_e( 'No themes installed with FlyGit yet.', 'flygit' ); ?></p>
                    <?php endif; ?>
                </div>
                <footer class="flygit-section-footer">
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
                            <summary>
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
                        <p class="description"><?php esc_html_e( 'No plugins installed with FlyGit yet.', 'flygit' ); ?></p>
                    <?php endif; ?>
                </div>
                <footer class="flygit-section-footer">
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
        <h2><?php esc_html_e( 'Code Snippets', 'flygit' ); ?></h2>
    </header>
    <div class="flygit-snippets-body">
        <p class="description">
            <?php esc_html_e( 'Import reusable PHP snippets directly from GitHub repositories. Imported snippets are stored in:', 'flygit' ); ?>
            <code><?php echo esc_html( $snippet_storage_display ); ?></code>
            <?php if ( $snippet_storage_display !== $snippet_storage_path ) : ?>
                <span class="flygit-snippets-path-alt">(<?php echo esc_html( $snippet_storage_path ); ?>)</span>
            <?php endif; ?>
        </p>

        <form class="flygit-form flygit-snippet-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'flygit_import_snippet' ); ?>
            <input type="hidden" name="action" value="flygit_import_snippet" />

            <label>
                <?php esc_html_e( 'Repository URL', 'flygit' ); ?>
                <input type="url" name="repository_url" placeholder="https://github.com/owner/repository" required />
            </label>

            <label>
                <?php esc_html_e( 'Repository File Path', 'flygit' ); ?>
                <input type="text" name="file_path" placeholder="path/to/snippet.php" required />
            </label>

            <label>
                <?php esc_html_e( 'Branch', 'flygit' ); ?>
                <input type="text" name="branch" placeholder="main" />
            </label>

            <label>
                <?php esc_html_e( 'Access Token (optional)', 'flygit' ); ?>
                <input type="password" name="access_token" autocomplete="off" />
            </label>

            <p class="description">
                <?php esc_html_e( 'Provide a GitHub personal access token when importing from private repositories.', 'flygit' ); ?>
            </p>

            <button type="submit" class="button button-primary">
                <?php esc_html_e( 'Import Snippet', 'flygit' ); ?>
            </button>
        </form>

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
                <p class="description"><?php esc_html_e( 'No snippets imported yet.', 'flygit' ); ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>

</div>
