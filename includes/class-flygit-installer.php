<?php
/**
 * Handles repository downloads and installations for plugins and themes.
 *
 * @package FlyGit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FlyGit_Installer {
    /**
     * Install a plugin or theme from a repository URL.
     *
     * @param string $type          Installation type (plugin|theme).
     * @param string $repository    Repository URL.
     * @param string $branch        Repository branch.
     * @param string $access_token  Access token for private repositories.
     *
     * @return string|WP_Error Success message or WP_Error on failure.
     */
    public function install_from_repository( $type, $repository, $branch = 'main', $access_token = '' ) {
        $type         = in_array( $type, array( 'plugin', 'theme' ), true ) ? $type : 'plugin';
        $repository   = esc_url_raw( trim( $repository ) );
        $branch       = ! empty( $branch ) ? sanitize_text_field( $branch ) : 'main';
        $access_token = ! empty( $access_token ) ? sanitize_text_field( $access_token ) : '';

        $repo_data = $this->prepare_repository_data( $repository, $branch );
        if ( is_wp_error( $repo_data ) ) {
            return $repo_data;
        }

        $package = $this->download_package( $repo_data['download_url'], $access_token );
        if ( is_wp_error( $package ) ) {
            return $package;
        }

        $destination = ( 'plugin' === $type ) ? WP_PLUGIN_DIR : get_theme_root();

        $result = $this->extract_and_move( $package['file'], $repo_data['slug'], $destination );
        $this->cleanup( $package['file'] );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $this->record_installation(
            $type,
            $repo_data['slug'],
            $repository,
            $branch,
            $access_token
        );

        $message = ( 'plugin' === $type )
            ? sprintf( __( 'Plugin "%s" installed successfully.', 'flygit' ), $repo_data['slug'] )
            : sprintf( __( 'Theme "%s" installed successfully.', 'flygit' ), $repo_data['slug'] );

        return $message;
    }

    /**
     * Prepare repository download URL and slug.
     *
     * @param string $repository Repository URL.
     * @param string $branch     Branch name.
     *
     * @return array|WP_Error
     */
    protected function prepare_repository_data( $repository, $branch ) {
        $repository = trim( $repository );
        $parsed     = wp_parse_url( $repository );

        if ( empty( $parsed['host'] ) ) {
            return new WP_Error( 'flygit_invalid_repository', __( 'Invalid repository URL.', 'flygit' ) );
        }

        $slug         = '';
        $download_url = $repository;

        if ( preg_match( '/\.zip$/', $repository ) ) {
            $slug = basename( $parsed['path'], '.zip' );
        } elseif ( false !== strpos( $parsed['host'], 'github.com' ) ) {
            $path_parts = array_values( array_filter( explode( '/', trim( $parsed['path'], '/' ) ) ) );

            if ( count( $path_parts ) < 2 ) {
                return new WP_Error( 'flygit_invalid_repository', __( 'Unable to determine repository owner and name.', 'flygit' ) );
            }

            $owner = $path_parts[0];
            $repo  = preg_replace( '/\.git$/', '', $path_parts[1] );
            $slug  = sanitize_key( $repo );

            $download_url = sprintf(
                'https://api.github.com/repos/%1$s/%2$s/zipball/%3$s',
                rawurlencode( $owner ),
                rawurlencode( $repo ),
                rawurlencode( $branch )
            );
        } else {
            $slug = sanitize_key( basename( $parsed['path'] ) );
        }

        if ( empty( $slug ) ) {
            return new WP_Error( 'flygit_invalid_slug', __( 'Unable to determine installation slug.', 'flygit' ) );
        }

        return array(
            'slug'         => $slug,
            'download_url' => $download_url,
        );
    }

    /**
     * Download the package archive.
     *
     * @param string $download_url Download URL.
     * @param string $access_token Optional access token.
     *
     * @return array|WP_Error
     */
    protected function download_package( $download_url, $access_token = '' ) {
        $args = array(
            'timeout' => 60,
            'headers' => array(
                'Accept'     => 'application/zip',
                'User-Agent' => 'FlyGit-Installer',
            ),
        );

        if ( ! empty( $access_token ) ) {
            $args['headers']['Authorization'] = 'token ' . trim( $access_token );
        }

        $response = wp_remote_get( $download_url, $args );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'flygit_download_failed', $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== (int) $code ) {
            return new WP_Error( 'flygit_invalid_response', sprintf( __( 'Download failed with status code %d.', 'flygit' ), $code ) );
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return new WP_Error( 'flygit_empty_body', __( 'Repository download returned an empty response.', 'flygit' ) );
        }

        $temp_file = wp_tempnam( 'flygit' );
        if ( ! $temp_file ) {
            return new WP_Error( 'flygit_tempfile_error', __( 'Unable to create a temporary file for download.', 'flygit' ) );
        }

        $written = file_put_contents( $temp_file, $body );
        if ( false === $written ) {
            return new WP_Error( 'flygit_write_error', __( 'Unable to save the downloaded package.', 'flygit' ) );
        }

        return array(
            'file' => $temp_file,
        );
    }

    /**
     * Extract the downloaded archive and move it into the destination directory.
     *
     * @param string $zip_file    Path to the downloaded zip archive.
     * @param string $slug        Slug for the installation directory.
     * @param string $destination Destination root directory.
     *
     * @return true|WP_Error
     */
    protected function extract_and_move( $zip_file, $slug, $destination ) {
        $temp_dir = $this->create_temp_dir();
        if ( is_wp_error( $temp_dir ) ) {
            return $temp_dir;
        }

        $result = $this->extract_archive( $zip_file, $temp_dir );
        if ( is_wp_error( $result ) ) {
            $this->cleanup( $temp_dir, true );
            return $result;
        }

        $source_dir = $this->locate_source_directory( $temp_dir );
        if ( empty( $source_dir ) ) {
            $this->cleanup( $temp_dir, true );
            return new WP_Error( 'flygit_missing_source', __( 'The extracted package did not contain any installable files.', 'flygit' ) );
        }

        if ( ! $this->initialize_filesystem() ) {
            $this->cleanup( $temp_dir, true );
            return new WP_Error( 'flygit_filesystem_error', __( 'WordPress filesystem credentials are required.', 'flygit' ) );
        }

        global $wp_filesystem;

        $target_dir = trailingslashit( $destination ) . $slug;

        if ( $wp_filesystem->is_dir( $target_dir ) ) {
            $wp_filesystem->delete( $target_dir, true );
        }

        $wp_filesystem->mkdir( $target_dir );

        $copy_result = copy_dir( $source_dir, $target_dir );

        $this->cleanup( $temp_dir, true );

        if ( is_wp_error( $copy_result ) ) {
            return new WP_Error( 'flygit_copy_failed', __( 'Unable to copy the package into WordPress.', 'flygit' ) );
        }

        return true;
    }

    /**
     * Extract a zip archive into the given destination.
     *
     * @param string $zip_file   Zip archive path.
     * @param string $temp_dir   Destination directory.
     *
     * @return true|WP_Error
     */
    protected function extract_archive( $zip_file, $temp_dir ) {
        if ( class_exists( 'ZipArchive' ) ) {
            $zip = new ZipArchive();
            $open_result = $zip->open( $zip_file );

            if ( true === $open_result ) {
                $extracted = $zip->extractTo( $temp_dir );
                $zip->close();

                if ( $extracted ) {
                    return true;
                }

                return new WP_Error( 'flygit_unzip_failed', __( 'Unable to extract the downloaded package.', 'flygit' ) );
            }
        }

        $result = unzip_file( $zip_file, $temp_dir );
        if ( is_wp_error( $result ) ) {
            return new WP_Error(
                'flygit_unzip_failed',
                sprintf(
                    __( 'Unable to extract the downloaded package: %s', 'flygit' ),
                    $result->get_error_message()
                )
            );
        }

        return true;
    }

    /**
     * Locate the directory that contains the plugin or theme files inside an extracted archive.
     *
     * @param string $temp_dir Temporary extraction directory.
     *
     * @return string|null
     */
    protected function locate_source_directory( $temp_dir ) {
        $items = glob( trailingslashit( $temp_dir ) . '*' );

        if ( empty( $items ) ) {
            return null;
        }

        foreach ( $items as $item ) {
            if ( is_dir( $item ) ) {
                return $item;
            }
        }

        // Fallback to temp directory itself when files are not wrapped in a folder.
        return $temp_dir;
    }

    /**
     * Create a temporary directory.
     *
     * @return string|WP_Error
     */
    protected function create_temp_dir() {
        $temp_file = wp_tempnam( 'flygit' );

        if ( ! $temp_file ) {
            return new WP_Error( 'flygit_tempdir_error', __( 'Unable to create a working directory.', 'flygit' ) );
        }

        unlink( $temp_file );

        if ( ! wp_mkdir_p( $temp_file ) ) {
            return new WP_Error( 'flygit_tempdir_error', __( 'Unable to prepare a working directory.', 'flygit' ) );
        }

        return $temp_file;
    }

    /**
     * Initialize the WordPress filesystem.
     *
     * @return bool
     */
    protected function initialize_filesystem() {
        global $wp_filesystem;

        if ( $wp_filesystem ) {
            return true;
        }

        $credentials = request_filesystem_credentials( admin_url() );
        if ( false === $credentials ) {
            return false;
        }

        return WP_Filesystem( $credentials );
    }

    /**
     * Cleanup temporary files and directories.
     *
     * @param string $path Path to remove.
     * @param bool   $is_dir Whether the path is a directory.
     */
    protected function cleanup( $path, $is_dir = false ) {
        if ( empty( $path ) ) {
            return;
        }

        if ( $is_dir ) {
            $files = glob( trailingslashit( $path ) . '*', GLOB_MARK );
            if ( $files ) {
                foreach ( $files as $file ) {
                    $this->cleanup( $file, is_dir( $file ) );
                }
            }

            if ( file_exists( $path ) ) {
                @rmdir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            }
            return;
        }

        if ( file_exists( $path ) ) {
            @unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }
    }

    /**
     * Persist installation details for future management.
     *
     * @param string $type         Installation type.
     * @param string $slug         Installation slug.
     * @param string $repository   Repository URL.
     * @param string $branch       Branch name.
     * @param string $access_token Access token used during installation.
     */
    protected function record_installation( $type, $slug, $repository, $branch, $access_token ) {
        $installations = get_option( 'flygit_installations', array() );
        if ( ! is_array( $installations ) ) {
            $installations = array();
        }

        $repository = esc_url_raw( $repository );
        $branch     = sanitize_text_field( $branch );

        $existing_key = null;

        foreach ( $installations as $key => $installation ) {
            if ( isset( $installation['type'], $installation['slug'] ) && $installation['type'] === $type && $installation['slug'] === $slug ) {
                $existing_key = $key;
                break;
            }
        }

        $data = array(
            'id'             => null !== $existing_key ? $installations[ $existing_key ]['id'] : $this->generate_installation_id(),
            'type'           => $type,
            'slug'           => $slug,
            'repository_url' => $repository,
            'branch'         => $branch,
            'access_token'   => $access_token,
            'webhook_secret' => ( null !== $existing_key && isset( $installations[ $existing_key ]['webhook_secret'] ) ) ? $installations[ $existing_key ]['webhook_secret'] : '',
        );

        if ( null !== $existing_key ) {
            $installations[ $existing_key ] = array_merge( $installations[ $existing_key ], $data );
        } else {
            $installations[] = $data;
        }

        update_option( 'flygit_installations', array_values( $installations ) );
    }

    /**
     * Get all recorded installations.
     *
     * @return array
     */
    public function get_installations() {
        $installations = get_option( 'flygit_installations', array() );

        if ( ! is_array( $installations ) ) {
            return array();
        }

        return array_values( $installations );
    }

    /**
     * Remove an installation from the filesystem and stored registry.
     *
     * @param string $installation_id Installation identifier.
     *
     * @return string|WP_Error Success message on success, WP_Error on failure.
     */
    public function uninstall_installation( $installation_id ) {
        $installation = $this->get_installation_by_id( $installation_id );

        if ( ! $installation || empty( $installation['type'] ) || empty( $installation['slug'] ) ) {
            return new WP_Error( 'flygit_installation_not_found', __( 'The requested installation could not be found.', 'flygit' ) );
        }

        $type = $installation['type'];
        $slug = $installation['slug'];

        if ( 'theme' === $type ) {
            $current_theme = wp_get_theme();

            if ( $current_theme && $current_theme->get_stylesheet() === $slug ) {
                return new WP_Error( 'flygit_uninstall_active_theme', __( 'The active theme cannot be uninstalled.', 'flygit' ) );
            }

            if ( ! $this->initialize_filesystem() ) {
                return new WP_Error( 'flygit_filesystem_error', __( 'WordPress filesystem credentials are required.', 'flygit' ) );
            }

            global $wp_filesystem;

            $theme_path = trailingslashit( get_theme_root() ) . $slug;

            if ( $wp_filesystem->is_dir( $theme_path ) ) {
                $deleted = $wp_filesystem->delete( $theme_path, true );

                if ( ! $deleted ) {
                    return new WP_Error( 'flygit_uninstall_failed', __( 'Unable to remove the theme directory.', 'flygit' ) );
                }
            } elseif ( $wp_filesystem->exists( $theme_path ) ) {
                $deleted = $wp_filesystem->delete( $theme_path );

                if ( ! $deleted ) {
                    return new WP_Error( 'flygit_uninstall_failed', __( 'Unable to remove the theme files.', 'flygit' ) );
                }
            }

            wp_clean_themes_cache();
        } elseif ( 'plugin' === $type ) {
            $plugins     = get_plugins();
            $plugin_file = '';

            foreach ( $plugins as $file => $plugin_data ) {
                if ( 0 === strpos( $file, trailingslashit( $slug ) ) ) {
                    $plugin_file = $file;
                    break;
                }
            }

            if ( ! empty( $plugin_file ) && is_plugin_active( $plugin_file ) ) {
                deactivate_plugins( array( $plugin_file ) );
            }

            if ( ! $this->initialize_filesystem() ) {
                return new WP_Error( 'flygit_filesystem_error', __( 'WordPress filesystem credentials are required.', 'flygit' ) );
            }

            global $wp_filesystem;

            $plugin_path = trailingslashit( WP_PLUGIN_DIR ) . $slug;

            if ( $wp_filesystem->is_dir( $plugin_path ) ) {
                $deleted = $wp_filesystem->delete( $plugin_path, true );

                if ( ! $deleted ) {
                    return new WP_Error( 'flygit_uninstall_failed', __( 'Unable to remove the plugin directory.', 'flygit' ) );
                }
            } elseif ( $wp_filesystem->exists( $plugin_path . '.php' ) ) {
                $deleted = $wp_filesystem->delete( $plugin_path . '.php' );

                if ( ! $deleted ) {
                    return new WP_Error( 'flygit_uninstall_failed', __( 'Unable to remove the plugin file.', 'flygit' ) );
                }
            }

            wp_clean_plugins_cache();
        } else {
            return new WP_Error( 'flygit_invalid_type', __( 'Unknown installation type.', 'flygit' ) );
        }

        $this->remove_installation_record( $installation_id );

        $name = isset( $installation['name'] ) && $installation['name'] ? $installation['name'] : $slug;

        return ( 'plugin' === $type )
            ? sprintf( __( 'Plugin "%s" uninstalled successfully.', 'flygit' ), $name )
            : sprintf( __( 'Theme "%s" uninstalled successfully.', 'flygit' ), $name );
    }

    /**
     * Retrieve an installation by its identifier.
     *
     * @param string $installation_id Installation identifier.
     *
     * @return array|null
     */
    public function get_installation_by_id( $installation_id ) {
        $installations = $this->get_installations();

        foreach ( $installations as $installation ) {
            if ( isset( $installation['id'] ) && $installation['id'] === $installation_id ) {
                return $installation;
            }
        }

        return null;
    }

    /**
     * Update a stored installation with new data.
     *
     * @param string $installation_id Installation identifier.
     * @param array  $data            Data to merge into the installation.
     *
     * @return true|WP_Error
     */
    public function update_installation( $installation_id, array $data ) {
        $installations = $this->get_installations();
        $updated       = false;

        foreach ( $installations as $index => $installation ) {
            if ( isset( $installation['id'] ) && $installation['id'] === $installation_id ) {
                $installations[ $index ] = array_merge( $installation, $data );
                $updated                 = true;
                break;
            }
        }

        if ( ! $updated ) {
            return new WP_Error( 'flygit_installation_not_found', __( 'The requested installation could not be found.', 'flygit' ) );
        }

        update_option( 'flygit_installations', array_values( $installations ) );

        return true;
    }

    /**
     * Delete an installation record from the stored registry.
     *
     * @param string $installation_id Installation identifier.
     *
     * @return bool Whether the installation was removed.
     */
    protected function remove_installation_record( $installation_id ) {
        $installations = get_option( 'flygit_installations', array() );

        if ( ! is_array( $installations ) ) {
            update_option( 'flygit_installations', array() );
            return false;
        }

        $updated = array();
        $found   = false;

        foreach ( $installations as $installation ) {
            if ( isset( $installation['id'] ) && $installation['id'] === $installation_id ) {
                $found = true;
                continue;
            }

            $updated[] = $installation;
        }

        update_option( 'flygit_installations', array_values( $updated ) );

        return $found;
    }

    /**
     * Generate a unique identifier for a stored installation.
     *
     * @return string
     */
    protected function generate_installation_id() {
        if ( function_exists( 'wp_generate_uuid4' ) ) {
            return wp_generate_uuid4();
        }

        return md5( uniqid( 'flygit', true ) );
    }
}
