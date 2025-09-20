<?php
/**
 * Manages code snippets imported from GitHub repositories.
 *
 * @package FlyGit
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FlyGit_Snippet_Manager {
    /**
     * Directory inside wp-content that stores imported snippets.
     */
    const STORAGE_DIR = 'fluent-snippet-storage';

    /**
     * Option name that stores snippet installations.
     */
    const INSTALLATION_OPTION = 'flygit_snippet_installations';

    /**
     * Prefix applied to stored snippet filenames.
     */
    const STORAGE_PREFIX = 'flygit-';

    /**
     * Repository directory containing snippet files.
     */
    const REPOSITORY_DIRECTORY = 'php';

    /**
     * Import PHP files from the default snippet directory of a repository.
     *
     * @param string $repository_url Repository URL.
     * @param string $file_path      Legacy parameter maintained for backwards compatibility.
     * @param string $branch         Branch name.
     * @param string $access_token   Optional GitHub access token.
     *
     * @return string|WP_Error Success message or error on failure.
     */
    public function import_from_repository( $repository_url, $file_path = '', $branch = 'main', $access_token = '' ) {
        unset( $file_path );

        return $this->perform_import( $repository_url, $branch, $access_token );
    }

    /**
     * Re-import snippets for an existing installation.
     *
     * @param string $installation_id Installation identifier.
     * @param string $repository_url  Optional repository URL override.
     * @param string $branch          Optional branch override.
     * @param string $access_token    Optional access token override.
     *
     * @return string|WP_Error
     */
    public function import_installation( $installation_id, $repository_url = '', $branch = '', $access_token = '' ) {
        $installation = $this->get_installation_by_id( $installation_id );

        if ( ! $installation ) {
            return new WP_Error( 'flygit_snippet_installation_not_found', __( 'The requested snippet installation could not be found.', 'flygit' ) );
        }

        $repository_url = ! empty( $repository_url ) ? $repository_url : ( isset( $installation['repository_url'] ) ? $installation['repository_url'] : '' );
        $branch         = ! empty( $branch ) ? $branch : ( isset( $installation['branch'] ) ? $installation['branch'] : 'main' );
        $access_token   = ! empty( $access_token ) ? $access_token : ( isset( $installation['access_token'] ) ? $installation['access_token'] : '' );

        return $this->perform_import( $repository_url, $branch, $access_token, $installation );
    }

    /**
     * Retrieve stored snippets with basic metadata.
     *
     * @return array|WP_Error
     */
    public function get_snippets() {
        $directory = $this->get_storage_directory_path();

        if ( ! is_dir( $directory ) ) {
            return array();
        }

        $files = glob( trailingslashit( $directory ) . '*.php' );
        if ( empty( $files ) ) {
            return array();
        }

        $snippets = array();

        foreach ( $files as $file ) {
            if ( ! is_readable( $file ) ) {
                continue;
            }

            if ( 0 !== strpos( basename( $file ), self::STORAGE_PREFIX ) ) {
                continue;
            }

            $metadata   = $this->extract_snippet_metadata( $file );
            $snippets[] = array(
                'file'     => basename( $file ),
                'path'     => $file,
                'size'     => filesize( $file ),
                'modified' => filemtime( $file ),
                'metadata' => $metadata,
            );
        }

        if ( empty( $snippets ) ) {
            return array();
        }

        usort(
            $snippets,
            function ( $a, $b ) {
                return strcasecmp( $a['file'], $b['file'] );
            }
        );

        return $snippets;
    }

    /**
     * Get the absolute path to the snippet storage directory.
     *
     * @return string
     */
    public function get_storage_directory_path() {
        return trailingslashit( WP_CONTENT_DIR ) . self::STORAGE_DIR;
    }

    /**
     * Retrieve stored snippet installations.
     *
     * @return array
     */
    public function get_installations() {
        $installations = $this->get_installations_raw();

        foreach ( $installations as &$installation ) {
            if ( ! isset( $installation['type'] ) ) {
                $installation['type'] = 'snippet';
            }

            if ( ! isset( $installation['files'] ) || ! is_array( $installation['files'] ) ) {
                $installation['files'] = array();
            }

            if ( ! isset( $installation['sources'] ) || ! is_array( $installation['sources'] ) ) {
                $installation['sources'] = array();
            }
        }

        return $installations;
    }

    /**
     * Retrieve a snippet installation by identifier.
     *
     * @param string $installation_id Installation identifier.
     *
     * @return array|null
     */
    public function get_installation_by_id( $installation_id ) {
        if ( empty( $installation_id ) ) {
            return null;
        }

        $installations = $this->get_installations();

        foreach ( $installations as $installation ) {
            if ( isset( $installation['id'] ) && $installation['id'] === $installation_id ) {
                return $installation;
            }
        }

        return null;
    }

    /**
     * Update data for a stored snippet installation.
     *
     * @param string $installation_id Installation identifier.
     * @param array  $data            Data to merge into the installation.
     *
     * @return true|WP_Error
     */
    public function update_installation( $installation_id, array $data ) {
        $installations = $this->get_installations_raw();
        $updated       = false;

        foreach ( $installations as $index => $installation ) {
            if ( isset( $installation['id'] ) && $installation['id'] === $installation_id ) {
                $installations[ $index ] = array_merge( $installation, $data );
                $updated                 = true;
                break;
            }
        }

        if ( ! $updated ) {
            return new WP_Error( 'flygit_snippet_installation_not_found', __( 'The requested snippet installation could not be found.', 'flygit' ) );
        }

        $this->save_installations( $installations );

        return true;
    }

    /**
     * Remove a snippet installation and delete imported files.
     *
     * @param string $installation_id Installation identifier.
     *
     * @return string|WP_Error
     */
    public function uninstall_installation( $installation_id ) {
        $installation = $this->get_installation_by_id( $installation_id );

        if ( ! $installation ) {
            return new WP_Error( 'flygit_snippet_installation_not_found', __( 'The requested snippet installation could not be found.', 'flygit' ) );
        }

        $files = isset( $installation['files'] ) && is_array( $installation['files'] ) ? $installation['files'] : array();

        foreach ( $files as $file_name ) {
            $this->delete_snippet_file( $file_name );
        }

        $this->remove_installation_record( $installation_id );

        $name = isset( $installation['name'] ) && $installation['name'] ? $installation['name'] : ( isset( $installation['slug'] ) ? $installation['slug'] : __( 'Snippet Repository', 'flygit' ) );

        return sprintf( __( 'Snippet repository "%s" uninstalled successfully.', 'flygit' ), $name );
    }

    /**
     * Ensure the snippet storage directory exists and is writable.
     *
     * @return string|WP_Error
     */
    protected function ensure_storage_directory() {
        $directory = $this->get_storage_directory_path();

        if ( ! is_dir( $directory ) ) {
            if ( ! wp_mkdir_p( $directory ) ) {
                return new WP_Error( 'flygit_snippet_directory_unwritable', __( 'Unable to create the snippet storage directory.', 'flygit' ) );
            }
        }

        if ( ! wp_is_writable( $directory ) ) {
            return new WP_Error( 'flygit_snippet_directory_not_writable', __( 'The snippet storage directory is not writable.', 'flygit' ) );
        }

        return $directory;
    }

    /**
     * Execute a snippet import.
     *
     * @param string     $repository_url        Repository URL.
     * @param string     $branch                Branch name.
     * @param string     $access_token          Access token.
     * @param array|null $existing_installation Existing installation data.
     *
     * @return string|WP_Error
     */
    protected function perform_import( $repository_url, $branch, $access_token, ?array $existing_installation = null ) {
        $repository_url = esc_url_raw( trim( $repository_url ) );
        $branch         = ! empty( $branch ) ? sanitize_text_field( $branch ) : 'main';
        $access_token   = ! empty( $access_token ) ? sanitize_text_field( $access_token ) : '';

        if ( empty( $repository_url ) ) {
            return new WP_Error( 'flygit_snippet_invalid_repository', __( 'Repository URL is required.', 'flygit' ) );
        }

        $repository = $this->parse_github_repository( $repository_url );
        if ( is_wp_error( $repository ) ) {
            return $repository;
        }

        $files = $this->fetch_php_files_from_repository( $repository['owner'], $repository['repo'], $branch, $access_token );
        if ( is_wp_error( $files ) ) {
            return $files;
        }

        if ( empty( $files ) ) {
            return new WP_Error( 'flygit_snippet_no_files', __( 'No PHP files were found in the /php directory of the repository.', 'flygit' ) );
        }

        $storage_dir = $this->ensure_storage_directory();
        if ( is_wp_error( $storage_dir ) ) {
            return $storage_dir;
        }

        $installation_id  = null;
        $slug             = '';
        $name             = '';
        $webhook_secret   = '';
        $existing_files   = array();
        $existing_sources = array();

        if ( $existing_installation ) {
            $installation_id  = isset( $existing_installation['id'] ) ? $existing_installation['id'] : $this->generate_installation_id();
            $slug             = isset( $existing_installation['slug'] ) ? $existing_installation['slug'] : '';
            $name             = isset( $existing_installation['name'] ) ? $existing_installation['name'] : '';
            $webhook_secret   = isset( $existing_installation['webhook_secret'] ) ? $existing_installation['webhook_secret'] : '';
            $existing_files   = isset( $existing_installation['files'] ) && is_array( $existing_installation['files'] ) ? $existing_installation['files'] : array();
            $existing_sources = isset( $existing_installation['sources'] ) && is_array( $existing_installation['sources'] ) ? $existing_installation['sources'] : array();

            if ( empty( $slug ) ) {
                $slug = $this->ensure_unique_slug( $this->generate_installation_slug( $repository['repo'] ), $installation_id );
            }

            if ( empty( $name ) ) {
                $name = $this->generate_installation_name( $repository['repo'] );
            }
        } else {
            $installation_id = $this->generate_installation_id();
            $slug            = $this->generate_installation_slug( $repository['repo'] );
            $name            = $this->generate_installation_name( $repository['repo'] );
        }

        $written_files = array();
        $source_map    = array();

        foreach ( $files as $file ) {
            if ( empty( $file['path'] ) || empty( $file['relative_path'] ) ) {
                continue;
            }

            $content = $this->fetch_github_file( $repository['owner'], $repository['repo'], $file['path'], $branch, $access_token );
            if ( is_wp_error( $content ) ) {
                $this->cleanup_created_files( $written_files );

                return $content;
            }

            $storage_filename = $this->generate_storage_filename( $slug, $file['relative_path'], $written_files, $existing_sources );
            $target_path      = trailingslashit( $storage_dir ) . $storage_filename;
            $header           = $this->resolve_snippet_header( $target_path, $storage_filename, $file['relative_path'] );
            $code             = $this->normalize_snippet_content( $content );
            $final_content    = $this->build_snippet_file_contents( $header, $code );

            if ( false === file_put_contents( $target_path, $final_content ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
                $this->cleanup_created_files( $written_files );

                return new WP_Error( 'flygit_snippet_write_failed', __( 'Unable to write the snippet file to the storage directory.', 'flygit' ) );
            }

            $written_files[]            = $storage_filename;
            $source_map[ $storage_filename ] = $file['relative_path'];

            do_action( 'fluent_snippets/rebuild_index', $storage_filename, true );
        }

        foreach ( $existing_files as $existing_file ) {
            if ( ! in_array( $existing_file, $written_files, true ) ) {
                $this->delete_snippet_file( $existing_file );
            }
        }

        $record = array(
            'id'             => $installation_id,
            'type'           => 'snippet',
            'slug'           => $slug,
            'name'           => $name,
            'repository_url' => $repository_url,
            'branch'         => $branch,
            'access_token'   => $access_token,
            'webhook_secret' => $webhook_secret,
            'files'          => $written_files,
            'sources'        => $source_map,
            'last_import'    => time(),
        );

        $this->record_installation( $record );

        $file_count = count( $written_files );
        $label      = ! empty( $name ) ? $name : $slug;

        $message = sprintf(
            _n( 'Imported %1$d snippet from "%2$s".', 'Imported %1$d snippets from "%2$s".', $file_count, 'flygit' ),
            $file_count,
            $label
        );

        return $message;
    }

    /**
     * Fetch PHP files from the repository snippet directory.
     *
     * @param string $owner        Repository owner.
     * @param string $repo         Repository name.
     * @param string $branch       Branch name.
     * @param string $access_token Optional access token.
     *
     * @return array|WP_Error
     */
    protected function fetch_php_files_from_repository( $owner, $repo, $branch, $access_token = '' ) {
        $directory = trim( self::REPOSITORY_DIRECTORY, '/' );

        $files = $this->crawl_github_directory( $owner, $repo, $directory, $branch, $access_token, $directory );
        if ( is_wp_error( $files ) ) {
            return $files;
        }

        $php_files = array();

        foreach ( $files as $file ) {
            if ( ! isset( $file['path'], $file['relative_path'] ) ) {
                continue;
            }

            if ( ! preg_match( '/\.php$/i', $file['path'] ) ) {
                continue;
            }

            $php_files[] = $file;
        }

        return $php_files;
    }

    /**
     * Crawl a repository directory and return file descriptors.
     *
     * @param string $owner        Repository owner.
     * @param string $repo         Repository name.
     * @param string $path         Path within the repository.
     * @param string $branch       Branch name.
     * @param string $access_token Access token.
     * @param string $base_path    Base path used for relative calculations.
     *
     * @return array|WP_Error
     */
    protected function crawl_github_directory( $owner, $repo, $path, $branch, $access_token, $base_path ) {
        $response = $this->request_github_contents( $owner, $repo, $path, $branch, $access_token );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $files = array();

        if ( isset( $response['type'] ) && 'file' === $response['type'] ) {
            $files[] = array(
                'path'          => $response['path'],
                'relative_path' => $this->normalize_relative_path( $response['path'], $base_path ),
            );

            return $files;
        }

        if ( ! is_array( $response ) ) {
            return new WP_Error( 'flygit_snippet_invalid_response', __( 'Unexpected response received from GitHub.', 'flygit' ) );
        }

        foreach ( $response as $item ) {
            if ( empty( $item['type'] ) || empty( $item['path'] ) ) {
                continue;
            }

            if ( 'file' === $item['type'] ) {
                $files[] = array(
                    'path'          => $item['path'],
                    'relative_path' => $this->normalize_relative_path( $item['path'], $base_path ),
                );
            } elseif ( 'dir' === $item['type'] ) {
                $sub_path  = $item['path'];
                $sub_files = $this->crawl_github_directory( $owner, $repo, $sub_path, $branch, $access_token, $base_path );

                if ( is_wp_error( $sub_files ) ) {
                    return $sub_files;
                }

                if ( ! empty( $sub_files ) ) {
                    $files = array_merge( $files, $sub_files );
                }
            }
        }

        return $files;
    }

    /**
     * Normalize a repository path to be relative to a base path.
     *
     * @param string $path      Repository path.
     * @param string $base_path Base path.
     *
     * @return string
     */
    protected function normalize_relative_path( $path, $base_path ) {
        $path      = trim( $path, '/' );
        $base_path = trim( $base_path, '/' );

        if ( '' === $base_path ) {
            return $path;
        }

        if ( $path === $base_path ) {
            return basename( $path );
        }

        if ( 0 === strpos( $path, $base_path . '/' ) ) {
            return substr( $path, strlen( $base_path ) + 1 );
        }

        return $path;
    }

    /**
     * Request the contents of a repository path from GitHub.
     *
     * @param string $owner        Repository owner.
     * @param string $repo         Repository name.
     * @param string $path         Path within the repository.
     * @param string $branch       Branch name.
     * @param string $access_token Optional access token.
     *
     * @return array|WP_Error
     */
    protected function request_github_contents( $owner, $repo, $path, $branch, $access_token = '' ) {
        $api_url = sprintf(
            'https://api.github.com/repos/%1$s/%2$s/contents/%3$s?ref=%4$s',
            rawurlencode( $owner ),
            rawurlencode( $repo ),
            $this->encode_path_for_github( $path ),
            rawurlencode( $branch )
        );

        $args = array(
            'timeout' => 60,
            'headers' => array(
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'FlyGit-Snippets',
            ),
        );

        if ( ! empty( $access_token ) ) {
            $args['headers']['Authorization'] = 'token ' . trim( $access_token );
        }

        $response = wp_remote_get( $api_url, $args );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'flygit_snippet_http_error', $response->get_error_message() );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( 200 !== $code ) {
            $message = '';

            if ( ! empty( $body ) ) {
                $decoded = json_decode( $body, true );
                if ( is_array( $decoded ) && ! empty( $decoded['message'] ) ) {
                    $message = $decoded['message'];
                }
            }

            if ( empty( $message ) ) {
                if ( 404 === $code ) {
                    $message = sprintf( __( 'Unable to locate "%s" in the repository.', 'flygit' ), sanitize_text_field( $path ) );
                } else {
                    $message = sprintf( __( 'GitHub API responded with status code %d.', 'flygit' ), $code );
                }
            }

            return new WP_Error( 'flygit_snippet_http_error', $message );
        }

        $data = json_decode( $body, true );
        if ( null === $data ) {
            return new WP_Error( 'flygit_snippet_invalid_response', __( 'Unexpected response received from GitHub.', 'flygit' ) );
        }

        return $data;
    }

    /**
     * Parse a GitHub repository URL and return owner and repository name.
     *
     * @param string $repository_url Repository URL.
     *
     * @return array|WP_Error
     */
    protected function parse_github_repository( $repository_url ) {
        $parsed = wp_parse_url( $repository_url );

        if ( empty( $parsed['host'] ) || false === strpos( $parsed['host'], 'github.com' ) ) {
            return new WP_Error( 'flygit_snippet_unsupported_host', __( 'Only GitHub repositories are currently supported for snippets.', 'flygit' ) );
        }

        $path       = isset( $parsed['path'] ) ? trim( $parsed['path'], '/' ) : '';
        $path_parts = array_values( array_filter( explode( '/', $path ) ) );

        if ( count( $path_parts ) < 2 ) {
            return new WP_Error( 'flygit_snippet_invalid_repository', __( 'Unable to determine the repository owner and name.', 'flygit' ) );
        }

        $owner = $path_parts[0];
        $repo  = preg_replace( '/\.git$/', '', $path_parts[1] );

        if ( empty( $owner ) || empty( $repo ) ) {
            return new WP_Error( 'flygit_snippet_invalid_repository', __( 'Unable to determine the repository owner and name.', 'flygit' ) );
        }

        return array(
            'owner' => $owner,
            'repo'  => $repo,
        );
    }
    /**
     * Fetch the contents of a file from GitHub.
     *
     * @param string $owner        Repository owner.
     * @param string $repo         Repository name.
     * @param string $file_path    File path within the repository.
     * @param string $branch       Branch name.
     * @param string $access_token Optional GitHub access token.
     *
     * @return string|WP_Error
     */
    protected function fetch_github_file( $owner, $repo, $file_path, $branch, $access_token = '' ) {
        $api_url = sprintf(
            'https://api.github.com/repos/%1$s/%2$s/contents/%3$s?ref=%4$s',
            rawurlencode( $owner ),
            rawurlencode( $repo ),
            $this->encode_path_for_github( $file_path ),
            rawurlencode( $branch )
        );

        $args = array(
            'timeout' => 60,
            'headers' => array(
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'FlyGit-Snippets',
            ),
        );

        if ( ! empty( $access_token ) ) {
            $args['headers']['Authorization'] = 'token ' . trim( $access_token );
        }

        $response = wp_remote_get( $api_url, $args );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'flygit_snippet_http_error', $response->get_error_message() );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( 200 !== $code ) {
            $message = '';

            if ( ! empty( $body ) ) {
                $decoded = json_decode( $body, true );
                if ( is_array( $decoded ) && ! empty( $decoded['message'] ) ) {
                    $message = $decoded['message'];
                }
            }

            if ( empty( $message ) ) {
                $message = sprintf( __( 'GitHub API responded with status code %d.', 'flygit' ), $code );
            }

            return new WP_Error( 'flygit_snippet_http_error', $message );
        }

        $data = json_decode( $body, true );
        if ( ! is_array( $data ) ) {
            return new WP_Error( 'flygit_snippet_invalid_response', __( 'Unexpected response received from GitHub.', 'flygit' ) );
        }

        if ( empty( $data['encoding'] ) || empty( $data['content'] ) ) {
            return new WP_Error( 'flygit_snippet_missing_content', __( 'GitHub did not return any content for the requested file.', 'flygit' ) );
        }

        if ( 'base64' === $data['encoding'] ) {
            $decoded = base64_decode( $data['content'], true );
            if ( false === $decoded ) {
                return new WP_Error( 'flygit_snippet_decode_error', __( 'Unable to decode the file contents received from GitHub.', 'flygit' ) );
            }

            return $decoded;
        }

        return (string) $data['content'];
    }

    /**
     * Generate the snippet header comment block.
     *
     * @param string $file_name      Snippet file name.
     * @param string $relative_path  Original repository relative path.
     *
     * @return string
     */
    protected function generate_snippet_header( $file_name, $relative_path = '' ) {
        $display_name = pathinfo( $file_name, PATHINFO_FILENAME );
        $timestamp    = current_time( 'mysql' );

        $relative_basename = '' !== $relative_path ? strtolower( basename( $relative_path ) ) : '';

        if ( 'demo.php' === $relative_basename ) {
            $name = 'Demo';
        } elseif ( preg_match( '/(^|-)uptime-kuma$/i', $display_name ) ) {
            $name = 'Uptime Kuma';
        } else {
            $name = 'FlyGit ' . $display_name;
        }

        $header = sprintf(
            "<?php\n// <Internal Doc Start>\n/*\n*\n* @description: \n* @tags: \n* @group: \n* @name: %1\$s\n* @type: PHP\n* @status: draft\n* @created_by: 1\n* @created_at: %2\$s\n* @updated_at: \n* @is_valid: 1\n* @updated_by: 1\n* @priority: 10\n* @run_at: all\n* @load_as_file: \n* @condition: {\"status\":\"no\",\"run_if\":\"assertive\",\"items\":[[]]}\n*/\n?>\n<?php if (!defined(\"ABSPATH\")) { return;} // <Internal Doc End> ?>\n",
            $name,
            $timestamp
        );

        return $header;
    }

    /**
     * Determine the snippet header to use when writing a file.
     *
     * @param string $target_path           Destination file path.
     * @param string $storage_file          Generated storage file name.
     * @param string $source_relative_path  Original repository relative path.
     *
     * @return string
     */
    protected function resolve_snippet_header( $target_path, $storage_file, $source_relative_path = '' ) {
        if ( file_exists( $target_path ) ) {
            $existing_content = file_get_contents( $target_path );

            if ( false !== $existing_content ) {
                $existing_header = $this->extract_internal_doc_header( $existing_content );

                if ( null !== $existing_header ) {
                    return $existing_header;
                }
            }
        }

        return $this->generate_snippet_header( $storage_file, $source_relative_path );
    }

    /**
     * Extract the internal documentation header from existing snippet contents.
     *
     * @param string $content Snippet file contents.
     *
     * @return string|null
     */
    protected function extract_internal_doc_header( $content ) {
        $marker_pos = strpos( $content, '// <Internal Doc End>' );

        if ( false === $marker_pos ) {
            return null;
        }

        $closing_tag_pos = strpos( $content, '?>', $marker_pos );
        if ( false === $closing_tag_pos ) {
            return null;
        }

        $header = substr( $content, 0, $closing_tag_pos + 2 );
        $header = rtrim( $header, "\r\n" ) . "\n";

        return $header;
    }

    /**
     * Combine a snippet header and code into the final file contents.
     *
     * @param string $header Header contents.
     * @param string $code   Normalized snippet code.
     *
     * @return string
     */
    protected function build_snippet_file_contents( $header, $code ) {
        $final_content = $header;

        if ( '' !== $code ) {
            if ( ! preg_match( '/\r?\n$/', $final_content ) ) {
                $final_content .= "\n";
            }

            $final_content .= $code;

            if ( ! preg_match( '/\r?\n$/', $final_content ) ) {
                $final_content .= "\n";
            }
        } elseif ( ! preg_match( '/\r?\n$/', $final_content ) ) {
            $final_content .= "\n";
        }

        return $final_content;
    }

    /**
     * Normalize snippet content by trimming whitespace and removing trailing PHP closing tags.
     *
     * @param string $content Raw snippet content.
     *
     * @return string
     */
    protected function normalize_snippet_content( $content ) {
        if ( '' === $content ) {
            return '';
        }

        $content = preg_replace( '/^\xEF\xBB\xBF/', '', $content );
        $content = ltrim( $content );

        $content = preg_replace( '/\?>\s*$/', '', $content );

        return trim( $content );
    }

    /**
     * Extract metadata from the snippet header.
     *
     * @param string $file_path File path.
     *
     * @return array
     */
    protected function extract_snippet_metadata( $file_path ) {
        $metadata = array(
            'name'        => '',
            'description' => '',
            'created_at'  => '',
            'status'      => '',
        );

        $handle = fopen( $file_path, 'r' );
        if ( ! $handle ) {
            return $metadata;
        }

        $line_number = 0;
        while ( ! feof( $handle ) && $line_number < 80 ) {
            $line = fgets( $handle );
            if ( false === $line ) {
                break;
            }

            $line_number++;
            $trimmed = trim( $line );

            if ( '// <Internal Doc End>' === $trimmed ) {
                break;
            }

            if ( preg_match( '/@name:\s*(.+)$/', $line, $matches ) ) {
                $metadata['name'] = trim( $matches[1] );
            } elseif ( preg_match( '/@description:\s*(.+)$/', $line, $matches ) ) {
                $metadata['description'] = trim( $matches[1] );
            } elseif ( preg_match( '/@created_at:\s*(.+)$/', $line, $matches ) ) {
                $metadata['created_at'] = trim( $matches[1] );
            } elseif ( preg_match( '/@status:\s*(.+)$/', $line, $matches ) ) {
                $metadata['status'] = trim( $matches[1] );
            }
        }

        fclose( $handle );

        return $metadata;
    }

    /**
     * Sanitize a repository file path.
     *
     * @param string $path File path within the repository.
     *
     * @return string
     */
    protected function sanitize_repository_path( $path ) {
        $path = str_replace( '\\', '/', $path );
        $path = trim( $path );
        $path = ltrim( $path, '/' );

        if ( empty( $path ) ) {
            return '';
        }

        if ( false !== strpos( $path, '..' ) ) {
            return '';
        }

        return $path;
    }

    /**
     * Encode a repository path for use with the GitHub API.
     *
     * @param string $path Repository path.
     *
     * @return string
     */
    protected function encode_path_for_github( $path ) {
        $parts = explode( '/', $path );
        $parts = array_map( 'rawurlencode', $parts );

        return implode( '/', $parts );
    }

    /**
     * Persist a snippet installation.
     *
     * @param array $installation Installation data.
     */
    protected function record_installation( array $installation ) {
        $installations = $this->get_installations_raw();
        $found         = false;

        foreach ( $installations as $index => $existing ) {
            if ( isset( $existing['id'] ) && $existing['id'] === $installation['id'] ) {
                $installations[ $index ] = array_merge( $existing, $installation );
                $found                   = true;
                break;
            }
        }

        if ( ! $found ) {
            $installations[] = $installation;
        }

        $this->save_installations( $installations );
    }

    /**
     * Retrieve raw snippet installations.
     *
     * @return array
     */
    protected function get_installations_raw() {
        $installations = get_option( self::INSTALLATION_OPTION, array() );

        if ( ! is_array( $installations ) ) {
            return array();
        }

        return array_values( $installations );
    }

    /**
     * Persist snippet installations.
     *
     * @param array $installations Installations to save.
     */
    protected function save_installations( array $installations ) {
        update_option( self::INSTALLATION_OPTION, array_values( $installations ) );
    }

    /**
     * Remove a snippet installation record.
     *
     * @param string $installation_id Installation identifier.
     */
    protected function remove_installation_record( $installation_id ) {
        $installations = $this->get_installations_raw();
        $updated       = array();

        foreach ( $installations as $installation ) {
            if ( isset( $installation['id'] ) && $installation['id'] === $installation_id ) {
                continue;
            }

            $updated[] = $installation;
        }

        $this->save_installations( $updated );
    }

    /**
     * Delete a snippet file from storage.
     *
     * @param string $file_name Stored snippet filename.
     */
    protected function delete_snippet_file( $file_name ) {
        $file_path = trailingslashit( $this->get_storage_directory_path() ) . $file_name;

        if ( file_exists( $file_path ) && is_file( $file_path ) ) {
            @unlink( $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            do_action( 'fluent_snippets/rebuild_index', $file_name, false );
        }
    }

    /**
     * Cleanup snippet files that were created during the current import.
     *
     * @param array $files Filenames to remove.
     */
    protected function cleanup_created_files( array $files ) {
        if ( empty( $files ) ) {
            return;
        }

        foreach ( $files as $file_name ) {
            $this->delete_snippet_file( $file_name );
        }
    }

    /**
     * Generate a unique installation identifier.
     *
     * @return string
     */
    protected function generate_installation_id() {
        if ( function_exists( 'wp_generate_uuid4' ) ) {
            return wp_generate_uuid4();
        }

        return md5( uniqid( 'flygit', true ) );
    }

    /**
     * Ensure a slug is unique among installations.
     *
     * @param string      $slug       Proposed slug.
     * @param string|null $current_id Optional installation identifier to ignore.
     *
     * @return string
     */
    protected function ensure_unique_slug( $slug, $current_id = null ) {
        $slug = trim( $slug );

        if ( '' === $slug ) {
            $slug = 'snippet';
        }

        $existing_slugs = array();

        foreach ( $this->get_installations() as $installation ) {
            if ( ! isset( $installation['slug'] ) ) {
                continue;
            }

            if ( $current_id && isset( $installation['id'] ) && $installation['id'] === $current_id ) {
                continue;
            }

            $existing_slugs[] = $installation['slug'];
        }

        if ( ! in_array( $slug, $existing_slugs, true ) ) {
            return $slug;
        }

        $base  = $slug;
        $index = 2;

        do {
            $slug = $base . '-' . $index;
            $index++;
        } while ( in_array( $slug, $existing_slugs, true ) );

        return $slug;
    }

    /**
     * Generate an installation slug from a repository name.
     *
     * @param string $repo_name Repository name.
     *
     * @return string
     */
    protected function generate_installation_slug( $repo_name ) {
        $slug = sanitize_title( $repo_name );

        if ( '' === $slug ) {
            $slug = sanitize_title( 'snippet-' . uniqid() );
        }

        return $this->ensure_unique_slug( $slug );
    }

    /**
     * Generate a human readable installation name.
     *
     * @param string $repo_name Repository name.
     *
     * @return string
     */
    protected function generate_installation_name( $repo_name ) {
        $name = trim( $repo_name );

        if ( '' === $name ) {
            $name = __( 'Snippet Repository', 'flygit' );
        }

        return $name;
    }

    /**
     * Generate a storage filename for an imported snippet.
     *
     * @param string $slug             Installation slug.
     * @param string $relative_path    Relative repository path.
     * @param array  $current_files    Files created during this import.
     * @param array  $existing_sources Existing file to source path mapping.
     *
     * @return string
     */
    protected function generate_storage_filename( $slug, $relative_path, array $current_files, array $existing_sources ) {
        foreach ( $existing_sources as $existing_file => $source_path ) {
            if ( $source_path === $relative_path && ! in_array( $existing_file, $current_files, true ) ) {
                return $existing_file;
            }
        }

        $slug_part     = sanitize_title( $slug );
        $relative_base = preg_replace( '/\.php$/i', '', $relative_path );
        $relative_base = str_replace( array( '/', '\\' ), '-', $relative_base );
        $relative_part = sanitize_title( $relative_base );

        $combined = trim( $slug_part . '-' . $relative_part, '-' );
        if ( '' === $combined ) {
            $combined = $slug_part ? $slug_part : 'snippet';
        }

        $base        = self::STORAGE_PREFIX . $combined;
        $storage_dir = trailingslashit( $this->get_storage_directory_path() );
        $counter     = 1;

        do {
            $suffix    = ( 1 === $counter ) ? '' : '-' . $counter;
            $file_name = $base . $suffix . '.php';
            $counter++;
        } while ( in_array( $file_name, $current_files, true ) || file_exists( $storage_dir . $file_name ) );

        return $file_name;
    }
}

