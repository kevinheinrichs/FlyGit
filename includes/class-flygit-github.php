<?php
/**
 * GitHub API client with ETag-based conditional requests.
 *
 * @package FlyGit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Talks to the GitHub REST API. Uses conditional requests (ETag),
 * so unchanged branches return 304 without body — this costs no
 * rate limit and nearly no bandwidth. That keeps scheduled checks
 * extremely cheap for the server AND for GitHub.
 */
class FlyGit_GitHub {

	const API_BASE = 'https://api.github.com';

	/**
	 * Parse a GitHub repository reference.
	 *
	 * Accepts "owner/repo", full https URLs and .git URLs.
	 *
	 * @param string $reference Repo reference.
	 * @return array|WP_Error { owner, repo }.
	 */
	public static function parse_repo( $reference ) {
		$reference = trim( (string) $reference );

		if ( '' === $reference ) {
			return new WP_Error( 'flygit_repo_empty', __( 'Repository darf nicht leer sein.', 'flygit' ) );
		}

		// Full URL?
		if ( preg_match( '#^https?://#i', $reference ) ) {
			$parsed = wp_parse_url( $reference );
			if ( empty( $parsed['host'] ) || false === stripos( $parsed['host'], 'github.com' ) ) {
				return new WP_Error( 'flygit_repo_host', __( 'Nur GitHub-Repositories werden unterstützt.', 'flygit' ) );
			}
			$path = isset( $parsed['path'] ) ? trim( $parsed['path'], '/' ) : '';
		} else {
			$path = trim( $reference, '/' );
		}

		$parts = array_values( array_filter( explode( '/', $path ) ) );
		if ( count( $parts ) < 2 ) {
			return new WP_Error( 'flygit_repo_invalid', __( 'Repository muss im Format owner/repo angegeben werden.', 'flygit' ) );
		}

		$owner = sanitize_text_field( $parts[0] );
		$repo  = sanitize_text_field( preg_replace( '/\.git$/i', '', $parts[1] ) );

		if ( ! preg_match( '/^[A-Za-z0-9_.\-]+$/', $owner ) || ! preg_match( '/^[A-Za-z0-9_.\-]+$/', $repo ) ) {
			return new WP_Error( 'flygit_repo_invalid', __( 'Ungültiger Repository-Name.', 'flygit' ) );
		}

		return array(
			'owner' => $owner,
			'repo'  => $repo,
		);
	}

	/**
	 * Build request headers.
	 *
	 * @param string $token GitHub token (optional).
	 * @param string $etag  Cached ETag (optional).
	 * @return array
	 */
	protected static function headers( $token = '', $etag = '' ) {
		$headers = array(
			'Accept'               => 'application/vnd.github+json',
			'User-Agent'           => 'FlyGit/' . FLYGIT_VERSION . '; ' . home_url( '/' ),
			'X-GitHub-Api-Version' => '2022-11-28',
		);

		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		if ( '' !== $etag ) {
			$headers['If-None-Match'] = $etag;
		}

		return $headers;
	}

	/**
	 * Get the latest commit for a branch — with ETag caching.
	 *
	 * @param string $owner  Repo owner.
	 * @param string $repo   Repo name.
	 * @param string $branch Branch name.
	 * @param string $token  GitHub token.
	 * @param string $etag   Previous ETag for conditional request.
	 * @return array|WP_Error {
	 *     @type bool   $modified Whether the branch moved since $etag.
	 *     @type string $sha      Latest commit SHA ('' when not modified).
	 *     @type string $date     ISO commit date ('' when not modified).
	 *     @type string $message  First line of the commit message.
	 *     @type string $etag     New ETag to store.
	 * }
	 */
	public static function latest_commit( $owner, $repo, $branch, $token = '', $etag = '' ) {
		$url = sprintf(
			'%s/repos/%s/%s/commits/%s',
			self::API_BASE,
			rawurlencode( $owner ),
			rawurlencode( $repo ),
			rawurlencode( $branch )
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => self::headers( $token, $etag ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'flygit_github_unreachable', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 304 === $code ) {
			return array(
				'modified' => false,
				'sha'      => '',
				'date'     => '',
				'message'  => '',
				'etag'     => $etag,
			);
		}

		if ( 200 !== $code ) {
			return self::error_from_response( $code, $response );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['sha'] ) ) {
			return new WP_Error( 'flygit_github_malformed', __( 'Unerwartete Antwort von GitHub.', 'flygit' ) );
		}

		$message = isset( $body['commit']['message'] ) ? strtok( (string) $body['commit']['message'], "\n" ) : '';

		return array(
			'modified' => true,
			'sha'      => (string) $body['sha'],
			'date'     => isset( $body['commit']['committer']['date'] ) ? (string) $body['commit']['committer']['date'] : '',
			'message'  => sanitize_text_field( $message ),
			'etag'     => (string) wp_remote_retrieve_header( $response, 'etag' ),
		);
	}

	/**
	 * Fetch a file's raw contents from a repository.
	 *
	 * @param string $owner  Repo owner.
	 * @param string $repo   Repo name.
	 * @param string $path   File path inside the repo.
	 * @param string $branch Branch.
	 * @param string $token  GitHub token.
	 * @param string $etag   Previous ETag.
	 * @return array|WP_Error { modified, content, etag }
	 */
	public static function file_contents( $owner, $repo, $path, $branch, $token = '', $etag = '' ) {
		$url = sprintf(
			'%s/repos/%s/%s/contents/%s?ref=%s',
			self::API_BASE,
			rawurlencode( $owner ),
			rawurlencode( $repo ),
			str_replace( '%2F', '/', rawurlencode( ltrim( $path, '/' ) ) ),
			rawurlencode( $branch )
		);

		$headers           = self::headers( $token, $etag );
		$headers['Accept'] = 'application/vnd.github.raw+json';

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'flygit_github_unreachable', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 304 === $code ) {
			return array(
				'modified' => false,
				'content'  => '',
				'etag'     => $etag,
			);
		}

		if ( 200 !== $code ) {
			return self::error_from_response( $code, $response );
		}

		return array(
			'modified' => true,
			'content'  => wp_remote_retrieve_body( $response ),
			'etag'     => (string) wp_remote_retrieve_header( $response, 'etag' ),
		);
	}

	/**
	 * Download a repository zipball to a temp file.
	 *
	 * @param string $owner Repo owner.
	 * @param string $repo  Repo name.
	 * @param string $ref   Branch, tag or commit SHA.
	 * @param string $token GitHub token.
	 * @return string|WP_Error Path to the downloaded temp file.
	 */
	public static function download_zipball( $owner, $repo, $ref, $token = '' ) {
		$url = sprintf(
			'%s/repos/%s/%s/zipball/%s',
			self::API_BASE,
			rawurlencode( $owner ),
			rawurlencode( $repo ),
			rawurlencode( $ref )
		);

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// download_url() streams to disk — no request body in memory.
		add_filter( 'http_request_args', $filter = function ( $args, $request_url ) use ( $url, $token ) {
			if ( $request_url === $url ) {
				$args['headers']['User-Agent'] = 'FlyGit/' . FLYGIT_VERSION;
				if ( '' !== $token ) {
					$args['headers']['Authorization'] = 'Bearer ' . $token;
				}
			}
			return $args;
		}, 10, 2 );

		$file = download_url( $url, 120 );

		remove_filter( 'http_request_args', $filter, 10 );

		if ( is_wp_error( $file ) ) {
			return new WP_Error( 'flygit_download_failed', $file->get_error_message() );
		}

		return $file;
	}

	/**
	 * Convert an error response into a readable WP_Error.
	 *
	 * @param int   $code     HTTP status.
	 * @param array $response HTTP response.
	 * @return WP_Error
	 */
	protected static function error_from_response( $code, $response ) {
		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$detail  = is_array( $body ) && ! empty( $body['message'] ) ? sanitize_text_field( $body['message'] ) : '';
		$mapping = array(
			401 => __( 'GitHub-Token ungültig oder abgelaufen.', 'flygit' ),
			403 => __( 'Zugriff verweigert (Rate-Limit oder fehlende Berechtigung).', 'flygit' ),
			404 => __( 'Repository, Branch oder Datei nicht gefunden (bei privaten Repos: Token prüfen).', 'flygit' ),
		);

		$message = isset( $mapping[ $code ] )
			? $mapping[ $code ]
			: sprintf( __( 'GitHub antwortete mit Status %d.', 'flygit' ), $code );

		if ( '' !== $detail ) {
			$message .= ' (' . $detail . ')';
		}

		return new WP_Error( 'flygit_github_http_' . $code, $message );
	}
}
