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
     * Import a snippet from a GitHub repository.
     *
     * @param string $repository_url Repository URL.
     * @param string $file_path      File path within the repository.
     * @param string $branch         Branch name.
     * @param string $access_token   Optional GitHub access token.
     *
     * @return string|WP_Error Success message or error on failure.
     */
    public function import_from_repository( $repository_url, $file_path, $branch = 'main', $access_token = '' ) {
        $repository_url = esc_url_raw( trim( $repository_url ) );
        $branch         = ! empty( $branch ) ? sanitize_text_field( $branch ) : 'main';
        $access_token   = ! empty( $access_token ) ? sanitize_text_field( $access_token ) : '';
        $file_path      = $this->sanitize_repository_path( $file_path );

        if ( empty( $repository_url ) ) {
            return new WP_Error( 'flygit_snippet_invalid_repository', __( 'Repository URL is required.', 'flygit' ) );
        }

        if ( empty( $file_path ) ) {
            return new WP_Error( 'flygit_snippet_invalid_path', __( 'File path within the repository is required.', 'flygit' ) );
        }

        $repository = $this->parse_github_repository( $repository_url );
        if ( is_wp_error( $repository ) ) {
            return $repository;
        }

        $content = $this->fetch_github_file( $repository['owner'], $repository['repo'], $file_path, $branch, $access_token );
        if ( is_wp_error( $content ) ) {
            return $content;
        }

        $file_name = sanitize_file_name( basename( $file_path ) );
        if ( empty( $file_name ) ) {
            return new WP_Error( 'flygit_snippet_invalid_filename', __( 'Unable to determine a valid filename for the snippet.', 'flygit' ) );
        }

        $storage_dir = $this->ensure_storage_directory();
        if ( is_wp_error( $storage_dir ) ) {
            return $storage_dir;
        }

        $header = $this->generate_snippet_header( $file_name );
        $code   = $this->normalize_snippet_content( $content );

        $final_content = $header;
        if ( '' !== $code ) {
            $final_content .= "\n" . $code;
        }
        $final_content .= "\n";

        $target_path = trailingslashit( $storage_dir ) . $file_name;

        if ( false === file_put_contents( $target_path, $final_content ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
            return new WP_Error( 'flygit_snippet_write_failed', __( 'Unable to write the snippet file to the storage directory.', 'flygit' ) );
        }

        $message = sprintf( __( 'Snippet "%s" imported successfully.', 'flygit' ), $file_name );

        return $message;
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

        $pattern  = trailingslashit( $directory ) . '*.php';
        $files    = glob( $pattern );
        $snippets = array();

        if ( empty( $files ) ) {
            return array();
        }

        foreach ( $files as $file ) {
            if ( ! is_readable( $file ) ) {
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
     * @param string $file_name Snippet file name.
     *
     * @return string
     */
    protected function generate_snippet_header( $file_name ) {
        $display_name = pathinfo( $file_name, PATHINFO_FILENAME );
        $timestamp    = current_time( 'mysql' );

        $header = sprintf(
            "<?php\n// <Internal Doc Start>\n/*\n*\n* @description: \n* @tags: \n* @group: \n* @name: FlyGit %1\$s\n* @type: PHP\n* @status: draft\n* @created_by: 1\n* @created_at: %2\$s\n* @updated_at: \n* @is_valid: 1\n* @updated_by: 1\n* @priority: 10\n* @run_at: all\n* @load_as_file: \n* @condition: {\"status\":\"no\",\"run_if\":\"assertive\",\"items\":[[]]}\n*/\n?>\n<?php if (!defined(\"ABSPATH\")) { return;} // <Internal Doc End> ?>",
            $display_name,
            $timestamp
        );

        return $header;
    }

    /**
     * Normalize snippet content by removing PHP opening/closing tags and trimming whitespace.
     *
     * @param string $content Raw snippet content.
     *
     * @return string
     */
    protected function normalize_snippet_content( $content ) {
        if ( '' === $content ) {
            return '';
        }

        $content = preg_replace( '/^\xEF\xBB\xBF/', '', $content ); // Remove BOM.
        $content = ltrim( $content );

        if ( 0 === strpos( $content, '<?php' ) ) {
            $content = substr( $content, 5 );
            $content = ltrim( $content, " \t\n\r\0\x0B" );
        } elseif ( 0 === strpos( $content, '<?' ) ) {
            $content = substr( $content, 2 );
            $content = ltrim( $content, " \t\n\r\0\x0B" );
        }

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
}
