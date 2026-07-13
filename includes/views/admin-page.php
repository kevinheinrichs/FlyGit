<?php
/**
 * FlyGit admin screen.
 *
 * @package FlyGit
 * @var array $data Screen data (installations, settings, log, ...).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$flygit_tabs = array(
	'dashboard' => __( 'Übersicht', 'flygit' ),
	'add'       => __( 'Neu installieren', 'flygit' ),
	'manifest'  => __( 'Fleet-Manifest', 'flygit' ),
	'settings'  => __( 'Einstellungen', 'flygit' ),
	'log'       => __( 'Aktivität', 'flygit' ),
);

$flygit_active = isset( $flygit_tabs[ $data['active_tab'] ] ) ? $data['active_tab'] : 'dashboard';
$flygit_base   = admin_url( 'admin.php?page=flygit' );

$flygit_pending = 0;
foreach ( $data['installations'] as $flygit_item ) {
	if ( $flygit_item['update_pending'] ) {
		$flygit_pending++;
	}
}
?>
<div class="wrap flygit-wrap">

	<div class="flygit-header">
		<div class="flygit-header-title">
			<span class="flygit-logo dashicons dashicons-cloud-upload"></span>
			<div>
				<h1>FlyGit</h1>
				<p class="flygit-tagline"><?php esc_html_e( 'Git-Deployments für WordPress — automatisch, atomar, flottenweit.', 'flygit' ); ?></p>
			</div>
		</div>
		<div class="flygit-header-meta">
			<?php if ( $data['next_check'] ) : ?>
				<span class="flygit-pill flygit-pill-muted" title="<?php esc_attr_e( 'Nächste automatische Prüfung', 'flygit' ); ?>">
					<span class="dashicons dashicons-clock"></span>
					<?php
					printf(
						/* translators: %s: human time diff */
						esc_html__( 'Nächster Check in %s', 'flygit' ),
						esc_html( human_time_diff( time(), $data['next_check'] ) )
					);
					?>
				</span>
			<?php endif; ?>
			<?php if ( $flygit_pending > 0 ) : ?>
				<span class="flygit-pill flygit-pill-warning">
					<span class="dashicons dashicons-update"></span>
					<?php
					printf(
						/* translators: %d: number of pending updates */
						esc_html( _n( '%d Update verfügbar', '%d Updates verfügbar', $flygit_pending, 'flygit' ) ),
						(int) $flygit_pending
					);
					?>
				</span>
			<?php else : ?>
				<span class="flygit-pill flygit-pill-success">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Alles aktuell', 'flygit' ); ?>
				</span>
			<?php endif; ?>
		</div>
	</div>

	<nav class="flygit-tabs" aria-label="<?php esc_attr_e( 'FlyGit Navigation', 'flygit' ); ?>">
		<?php foreach ( $flygit_tabs as $flygit_tab_key => $flygit_tab_label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $flygit_tab_key, $flygit_base ) ); ?>"
				class="flygit-tab <?php echo $flygit_active === $flygit_tab_key ? 'is-active' : ''; ?>">
				<?php echo esc_html( $flygit_tab_label ); ?>
				<?php if ( 'dashboard' === $flygit_tab_key && $flygit_pending > 0 ) : ?>
					<span class="flygit-badge"><?php echo (int) $flygit_pending; ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'dashboard' === $flygit_active ) : ?>
	<!-- ================================================== ÜBERSICHT -->
	<section class="flygit-section">
		<?php if ( empty( $data['installations'] ) ) : ?>
			<div class="flygit-empty">
				<span class="dashicons dashicons-cloud"></span>
				<h2><?php esc_html_e( 'Noch keine Installationen', 'flygit' ); ?></h2>
				<p><?php esc_html_e( 'Installiere dein erstes Plugin oder Theme direkt aus einem GitHub-Repository.', 'flygit' ); ?></p>
				<a class="button button-primary button-hero" href="<?php echo esc_url( add_query_arg( 'tab', 'add', $flygit_base ) ); ?>">
					<?php esc_html_e( 'Jetzt installieren', 'flygit' ); ?>
				</a>
			</div>
		<?php else : ?>
			<div class="flygit-grid">
				<?php foreach ( $data['installations'] as $flygit_item ) : ?>
					<article class="flygit-card <?php echo $flygit_item['update_pending'] ? 'has-update' : ''; ?> <?php echo ! empty( $flygit_item['last_error'] ) ? 'has-error' : ''; ?>">
						<header class="flygit-card-head">
							<span class="flygit-type-icon dashicons <?php echo 'theme' === $flygit_item['type'] ? 'dashicons-admin-appearance' : 'dashicons-admin-plugins'; ?>"></span>
							<div class="flygit-card-title">
								<h3><?php echo esc_html( $flygit_item['display_name'] ); ?></h3>
								<a class="flygit-repo-link" href="<?php echo esc_url( 'https://github.com/' . $flygit_item['owner'] . '/' . $flygit_item['repo'] ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( $flygit_item['owner'] . '/' . $flygit_item['repo'] ); ?>
									<span class="dashicons dashicons-external"></span>
								</a>
							</div>
							<div class="flygit-card-badges">
								<?php if ( $flygit_item['active'] ) : ?>
									<span class="flygit-pill flygit-pill-success"><?php esc_html_e( 'Aktiv', 'flygit' ); ?></span>
								<?php elseif ( ! $flygit_item['on_disk'] ) : ?>
									<span class="flygit-pill flygit-pill-danger"><?php esc_html_e( 'Fehlt auf Disk', 'flygit' ); ?></span>
								<?php endif; ?>
								<?php if ( 'manifest' === $flygit_item['managed_by'] ) : ?>
									<span class="flygit-pill flygit-pill-info" title="<?php esc_attr_e( 'Wird zentral über das Fleet-Manifest verwaltet', 'flygit' ); ?>"><?php esc_html_e( 'Manifest', 'flygit' ); ?></span>
								<?php endif; ?>
							</div>
						</header>

						<dl class="flygit-card-meta">
							<div>
								<dt><?php esc_html_e( 'Branch', 'flygit' ); ?></dt>
								<dd><code><?php echo esc_html( $flygit_item['branch'] ); ?></code></dd>
							</div>
							<div>
								<dt><?php esc_html_e( 'Version', 'flygit' ); ?></dt>
								<dd>
									<?php echo esc_html( $flygit_item['local_version'] ? $flygit_item['local_version'] : '—' ); ?>
									<?php if ( $flygit_item['installed_sha'] ) : ?>
										<code class="flygit-sha"><?php echo esc_html( substr( $flygit_item['installed_sha'], 0, 7 ) ); ?></code>
									<?php endif; ?>
								</dd>
							</div>
							<div>
								<dt><?php esc_html_e( 'Zuletzt geprüft', 'flygit' ); ?></dt>
								<dd>
									<?php
									echo $flygit_item['last_checked']
										? esc_html( sprintf( __( 'vor %s', 'flygit' ), human_time_diff( $flygit_item['last_checked'], time() ) ) )
										: esc_html__( 'nie', 'flygit' );
									?>
								</dd>
							</div>
							<div>
								<dt><?php esc_html_e( 'Auto-Update', 'flygit' ); ?></dt>
								<dd>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flygit-inline-form">
										<?php wp_nonce_field( 'flygit_toggle_auto_update' ); ?>
										<input type="hidden" name="action" value="flygit_toggle_auto_update">
										<input type="hidden" name="installation_id" value="<?php echo esc_attr( $flygit_item['id'] ); ?>">
										<button type="submit" class="flygit-switch <?php echo ! empty( $flygit_item['auto_update'] ) ? 'is-on' : ''; ?>" role="switch" aria-checked="<?php echo ! empty( $flygit_item['auto_update'] ) ? 'true' : 'false'; ?>" title="<?php esc_attr_e( 'Auto-Update umschalten', 'flygit' ); ?>">
											<span class="flygit-switch-knob"></span>
										</button>
									</form>
								</dd>
							</div>
						</dl>

						<?php if ( $flygit_item['update_pending'] ) : ?>
							<div class="flygit-update-notice">
								<span class="dashicons dashicons-update"></span>
								<div>
									<strong><?php esc_html_e( 'Update verfügbar', 'flygit' ); ?></strong>
									<span class="flygit-commit-msg"><?php echo esc_html( $flygit_item['remote_message'] ); ?></span>
									<code class="flygit-sha"><?php echo esc_html( substr( $flygit_item['remote_sha'], 0, 7 ) ); ?></code>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $flygit_item['last_error'] ) ) : ?>
							<div class="flygit-error-notice">
								<span class="dashicons dashicons-warning"></span>
								<?php echo esc_html( $flygit_item['last_error'] ); ?>
							</div>
						<?php endif; ?>

						<footer class="flygit-card-actions">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flygit-inline-form">
								<?php wp_nonce_field( 'flygit_check_now' ); ?>
								<input type="hidden" name="action" value="flygit_check_now">
								<input type="hidden" name="installation_id" value="<?php echo esc_attr( $flygit_item['id'] ); ?>">
								<button type="submit" class="button button-small">
									<span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Prüfen', 'flygit' ); ?>
								</button>
							</form>

							<?php if ( $flygit_item['update_pending'] || ! $flygit_item['on_disk'] ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flygit-inline-form">
									<?php wp_nonce_field( 'flygit_update_now' ); ?>
									<input type="hidden" name="action" value="flygit_update_now">
									<input type="hidden" name="installation_id" value="<?php echo esc_attr( $flygit_item['id'] ); ?>">
									<button type="submit" class="button button-primary button-small">
										<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Jetzt updaten', 'flygit' ); ?>
									</button>
								</form>
							<?php endif; ?>

							<span class="flygit-spacer"></span>

							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flygit-inline-form">
								<?php wp_nonce_field( 'flygit_detach' ); ?>
								<input type="hidden" name="action" value="flygit_detach">
								<input type="hidden" name="installation_id" value="<?php echo esc_attr( $flygit_item['id'] ); ?>">
								<button type="submit" class="button-link flygit-link-muted" title="<?php esc_attr_e( 'Aus Verwaltung entfernen, Dateien behalten', 'flygit' ); ?>">
									<?php esc_html_e( 'Loslösen', 'flygit' ); ?>
								</button>
							</form>

							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flygit-inline-form flygit-confirm" data-confirm="<?php esc_attr_e( 'Dateien wirklich löschen? Das kann nicht rückgängig gemacht werden.', 'flygit' ); ?>">
								<?php wp_nonce_field( 'flygit_delete' ); ?>
								<input type="hidden" name="action" value="flygit_delete">
								<input type="hidden" name="installation_id" value="<?php echo esc_attr( $flygit_item['id'] ); ?>">
								<button type="submit" class="button-link flygit-link-danger">
									<?php esc_html_e( 'Löschen', 'flygit' ); ?>
								</button>
							</form>
						</footer>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>

	<?php elseif ( 'add' === $flygit_active ) : ?>
	<!-- ================================================== NEU INSTALLIEREN -->
	<section class="flygit-section">
		<div class="flygit-panel flygit-panel-narrow">
			<h2><?php esc_html_e( 'Aus GitHub installieren', 'flygit' ); ?></h2>
			<p class="flygit-muted"><?php esc_html_e( 'Repository angeben, FlyGit erledigt den Rest: Download, Verifizierung, atomare Installation, automatische Updates.', 'flygit' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'flygit_install' ); ?>
				<input type="hidden" name="action" value="flygit_install">

				<div class="flygit-field">
					<label for="flygit-repository"><?php esc_html_e( 'Repository', 'flygit' ); ?> <span class="required">*</span></label>
					<input type="text" id="flygit-repository" name="repository" class="regular-text" placeholder="kevinheinrichs/mein-plugin" required>
					<p class="description"><?php esc_html_e( 'Format: owner/repo oder vollständige GitHub-URL.', 'flygit' ); ?></p>
				</div>

				<div class="flygit-field-row">
					<div class="flygit-field">
						<label for="flygit-type"><?php esc_html_e( 'Typ', 'flygit' ); ?></label>
						<select id="flygit-type" name="type">
							<option value="plugin"><?php esc_html_e( 'Plugin', 'flygit' ); ?></option>
							<option value="theme"><?php esc_html_e( 'Theme', 'flygit' ); ?></option>
						</select>
					</div>
					<div class="flygit-field">
						<label for="flygit-branch"><?php esc_html_e( 'Branch', 'flygit' ); ?></label>
						<input type="text" id="flygit-branch" name="branch" placeholder="main">
					</div>
					<div class="flygit-field">
						<label for="flygit-slug"><?php esc_html_e( 'Ordner-Slug (optional)', 'flygit' ); ?></label>
						<input type="text" id="flygit-slug" name="slug" placeholder="<?php esc_attr_e( 'Standard: Repo-Name', 'flygit' ); ?>">
					</div>
				</div>

				<div class="flygit-field">
					<label for="flygit-token"><?php esc_html_e( 'Access-Token (nur für dieses Repo)', 'flygit' ); ?></label>
					<input type="password" id="flygit-token" name="token" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( $data['has_token'] ? __( 'Leer = globales Token verwenden', 'flygit' ) : __( 'Nur bei privaten Repos nötig', 'flygit' ) ); ?>">
					<p class="description">
						<?php
						if ( $data['has_token'] ) {
							esc_html_e( 'Ein globales Token ist hinterlegt und wird automatisch verwendet.', 'flygit' );
						} else {
							esc_html_e( 'Für private Repositories. Fine-grained Token mit Contents:Read genügt.', 'flygit' );
						}
						?>
					</p>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary button-hero">
						<span class="dashicons dashicons-cloud-upload"></span>
						<?php esc_html_e( 'Installieren', 'flygit' ); ?>
					</button>
				</p>
			</form>
		</div>
	</section>

	<?php elseif ( 'manifest' === $flygit_active ) : ?>
	<!-- ================================================== FLEET-MANIFEST -->
	<section class="flygit-section">
		<div class="flygit-panel">
			<h2><?php esc_html_e( 'Fleet-Manifest', 'flygit' ); ?></h2>
			<p class="flygit-muted">
				<?php esc_html_e( 'Ein zentrales JSON-Repository definiert, welche Plugins & Themes auf allen Shops installiert sein sollen. Jede Site synct sich selbst — ein Push aktualisiert die ganze Flotte.', 'flygit' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'flygit_save_manifest' ); ?>
				<input type="hidden" name="action" value="flygit_save_manifest">

				<div class="flygit-field flygit-field-toggle">
					<label class="flygit-toggle-label">
						<input type="checkbox" name="manifest_enabled" value="1" <?php checked( ! empty( $data['settings']['manifest_enabled'] ) ); ?>>
						<strong><?php esc_html_e( 'Manifest-Modus aktivieren', 'flygit' ); ?></strong>
					</label>
					<p class="description"><?php esc_html_e( 'Diese Site holt sich ihre Soll-Konfiguration aus dem Manifest-Repository.', 'flygit' ); ?></p>
				</div>

				<div class="flygit-field-row">
					<div class="flygit-field flygit-field-grow">
						<label for="flygit-manifest-repo"><?php esc_html_e( 'Manifest-Repository', 'flygit' ); ?></label>
						<input type="text" id="flygit-manifest-repo" name="manifest_repo" class="regular-text" placeholder="kevinheinrichs/fleet-config" value="<?php echo esc_attr( $data['settings']['manifest_repo'] ); ?>">
					</div>
					<div class="flygit-field">
						<label for="flygit-manifest-branch"><?php esc_html_e( 'Branch', 'flygit' ); ?></label>
						<input type="text" id="flygit-manifest-branch" name="manifest_branch" value="<?php echo esc_attr( $data['settings']['manifest_branch'] ); ?>" placeholder="main">
					</div>
					<div class="flygit-field">
						<label for="flygit-manifest-path"><?php esc_html_e( 'Dateipfad', 'flygit' ); ?></label>
						<input type="text" id="flygit-manifest-path" name="manifest_path" value="<?php echo esc_attr( $data['settings']['manifest_path'] ); ?>" placeholder="fleet-manifest.json">
					</div>
				</div>

				<div class="flygit-field flygit-field-toggle">
					<label class="flygit-toggle-label">
						<input type="checkbox" name="manifest_autoapply" value="1" <?php checked( ! empty( $data['settings']['manifest_autoapply'] ) ); ?>>
						<?php esc_html_e( 'Änderungen sofort anwenden (installieren/entfernen ohne Rückfrage)', 'flygit' ); ?>
					</label>
				</div>

				<p class="submit flygit-submit-row">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Speichern', 'flygit' ); ?></button>
				</p>
			</form>

			<?php if ( ! empty( $data['settings']['manifest_enabled'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flygit-inline-form">
					<?php wp_nonce_field( 'flygit_manifest_sync' ); ?>
					<input type="hidden" name="action" value="flygit_manifest_sync">
					<button type="submit" class="button">
						<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Jetzt synchronisieren', 'flygit' ); ?>
					</button>
				</form>
			<?php endif; ?>

			<details class="flygit-details">
				<summary><?php esc_html_e( 'Beispiel-Manifest anzeigen', 'flygit' ); ?></summary>
				<pre class="flygit-code">{
  "version": 1,
  "plugins": [
    { "repo": "kevinheinrichs/fly-geo", "branch": "main" },
    { "repo": "kevinheinrichs/fly-cache", "branch": "main" }
  ],
  "themes": [
    { "repo": "kevinheinrichs/fly-theme", "branch": "main" }
  ],
  "sites": {
    "beauty-bazaar.de": {
      "exclude": [ "kevinheinrichs/fly-geo" ],
      "plugins": [ { "repo": "kevinheinrichs/bb-extra", "branch": "main" } ]
    }
  }
}</pre>
				<p class="description"><?php esc_html_e( 'plugins/themes gelten für alle Sites. Unter sites können einzelne Hosts Einträge ausschließen (exclude) oder eigene ergänzen.', 'flygit' ); ?></p>
			</details>
		</div>
	</section>

	<?php elseif ( 'settings' === $flygit_active ) : ?>
	<!-- ================================================== EINSTELLUNGEN -->
	<section class="flygit-section">
		<div class="flygit-panel flygit-panel-narrow">
			<h2><?php esc_html_e( 'Einstellungen', 'flygit' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'flygit_save_settings' ); ?>
				<input type="hidden" name="action" value="flygit_save_settings">

				<h3 class="flygit-subhead"><?php esc_html_e( 'Automatische Prüfung', 'flygit' ); ?></h3>

				<div class="flygit-field">
					<label for="flygit-interval"><?php esc_html_e( 'Prüf-Intervall', 'flygit' ); ?></label>
					<select id="flygit-interval" name="check_interval">
						<option value="flygit_15min" <?php selected( $data['settings']['check_interval'], 'flygit_15min' ); ?>><?php esc_html_e( 'Alle 15 Minuten', 'flygit' ); ?></option>
						<option value="hourly" <?php selected( $data['settings']['check_interval'], 'hourly' ); ?>><?php esc_html_e( 'Stündlich', 'flygit' ); ?></option>
						<option value="twicedaily" <?php selected( $data['settings']['check_interval'], 'twicedaily' ); ?>><?php esc_html_e( 'Zweimal täglich', 'flygit' ); ?></option>
						<option value="daily" <?php selected( $data['settings']['check_interval'], 'daily' ); ?>><?php esc_html_e( 'Täglich', 'flygit' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Dank ETag-Anfragen kosten Prüfungen ohne Änderungen praktisch nichts — weder Serverlast noch GitHub-Rate-Limit.', 'flygit' ); ?></p>
				</div>

				<div class="flygit-field flygit-field-toggle">
					<label class="flygit-toggle-label">
						<input type="checkbox" name="auto_update" value="1" <?php checked( ! empty( $data['settings']['auto_update'] ) ); ?>>
						<?php esc_html_e( 'Auto-Update als Standard für neue Installationen', 'flygit' ); ?>
					</label>
				</div>

				<h3 class="flygit-subhead"><?php esc_html_e( 'GitHub-Zugang', 'flygit' ); ?></h3>

				<div class="flygit-field">
					<label for="flygit-github-token"><?php esc_html_e( 'Globales Access-Token', 'flygit' ); ?></label>
					<input type="password" id="flygit-github-token" name="github_token" class="regular-text" autocomplete="new-password"
						placeholder="<?php echo esc_attr( $data['has_token'] ? '••••••••' : 'github_pat_…' ); ?>">
					<p class="description">
						<?php esc_html_e( 'Wird verschlüsselt gespeichert (AES-256-GCM). Fine-grained Token mit Contents:Read für alle Fly-Repos genügt. Erhöht zudem das API-Limit von 60 auf 5.000 Anfragen/Stunde.', 'flygit' ); ?>
					</p>
					<?php if ( $data['has_token'] ) : ?>
						<label class="flygit-toggle-label flygit-mt-8">
							<input type="checkbox" name="github_token_clear" value="1">
							<?php esc_html_e( 'Token entfernen', 'flygit' ); ?>
						</label>
					<?php endif; ?>
				</div>

				<h3 class="flygit-subhead"><?php esc_html_e( 'Webhook (optional)', 'flygit' ); ?></h3>

				<div class="flygit-field flygit-field-toggle">
					<label class="flygit-toggle-label">
						<input type="checkbox" name="webhook_enabled" value="1" <?php checked( ! empty( $data['settings']['webhook_enabled'] ) ); ?>>
						<?php esc_html_e( 'Webhook-Endpoint aktivieren (Updates in Sekunden statt beim nächsten Check)', 'flygit' ); ?>
					</label>
				</div>

				<div class="flygit-field">
					<label><?php esc_html_e( 'Endpoint', 'flygit' ); ?></label>
					<div class="flygit-copy-row">
						<code id="flygit-webhook-url"><?php echo esc_html( $data['webhook_url'] ); ?></code>
						<button type="button" class="button button-small flygit-copy" data-copy-target="flygit-webhook-url"><?php esc_html_e( 'Kopieren', 'flygit' ); ?></button>
					</div>
				</div>

				<div class="flygit-field">
					<label><?php esc_html_e( 'Secret', 'flygit' ); ?></label>
					<div class="flygit-copy-row">
						<code id="flygit-webhook-secret" class="flygit-secret" data-secret="<?php echo esc_attr( $data['settings']['webhook_secret'] ); ?>">••••••••••••</code>
						<button type="button" class="button button-small flygit-reveal" data-reveal-target="flygit-webhook-secret"><?php esc_html_e( 'Anzeigen', 'flygit' ); ?></button>
						<button type="button" class="button button-small flygit-copy" data-copy-target="flygit-webhook-secret" data-copy-attr="data-secret"><?php esc_html_e( 'Kopieren', 'flygit' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'In GitHub als Webhook-Secret eintragen (Content type: application/json). FlyGit prüft die HMAC-Signatur jeder Anfrage.', 'flygit' ); ?></p>
				</div>

				<h3 class="flygit-subhead"><?php esc_html_e( 'Verschiedenes', 'flygit' ); ?></h3>

				<div class="flygit-field">
					<label for="flygit-log-entries"><?php esc_html_e( 'Log-Einträge behalten', 'flygit' ); ?></label>
					<input type="number" id="flygit-log-entries" name="keep_log_entries" min="10" max="500" value="<?php echo esc_attr( $data['settings']['keep_log_entries'] ); ?>">
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Einstellungen speichern', 'flygit' ); ?></button>
				</p>
			</form>

			<hr class="flygit-hr">

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flygit-confirm" data-confirm="<?php esc_attr_e( 'Neues Secret generieren? Bestehende GitHub-Webhooks funktionieren dann nicht mehr.', 'flygit' ); ?>">
				<?php wp_nonce_field( 'flygit_regenerate_secret' ); ?>
				<input type="hidden" name="action" value="flygit_regenerate_secret">
				<button type="submit" class="button"><?php esc_html_e( 'Webhook-Secret neu generieren', 'flygit' ); ?></button>
			</form>
		</div>
	</section>

	<?php elseif ( 'log' === $flygit_active ) : ?>
	<!-- ================================================== AKTIVITÄT -->
	<section class="flygit-section">
		<div class="flygit-panel">
			<div class="flygit-panel-head">
				<h2><?php esc_html_e( 'Aktivität', 'flygit' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'flygit_clear_log' ); ?>
					<input type="hidden" name="action" value="flygit_clear_log">
					<button type="submit" class="button button-small"><?php esc_html_e( 'Log leeren', 'flygit' ); ?></button>
				</form>
			</div>

			<?php if ( empty( $data['log'] ) ) : ?>
				<p class="flygit-muted"><?php esc_html_e( 'Noch keine Aktivität aufgezeichnet.', 'flygit' ); ?></p>
			<?php else : ?>
				<ul class="flygit-log">
					<?php foreach ( $data['log'] as $flygit_entry ) : ?>
						<li class="flygit-log-entry flygit-log-<?php echo esc_attr( $flygit_entry['level'] ); ?>">
							<span class="flygit-log-dot"></span>
							<span class="flygit-log-message"><?php echo esc_html( $flygit_entry['message'] ); ?></span>
							<time class="flygit-log-time" datetime="<?php echo esc_attr( gmdate( 'c', $flygit_entry['time'] ) ); ?>">
								<?php echo esc_html( wp_date( 'd.m.Y H:i', $flygit_entry['time'] ) ); ?>
							</time>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</section>
	<?php endif; ?>

</div>
