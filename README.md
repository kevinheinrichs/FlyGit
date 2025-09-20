# FlyGit

FlyGit is a WordPress admin plugin that lets you install and manage themes or plugins directly from Git repositories. It keeps track of each installation, exposes secure webhook endpoints for automated updates, and wraps everything in a tidy dashboard so you can deploy code without leaving wp-admin.

## Table of contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Getting started](#getting-started)
  - [Install a theme or plugin](#install-a-theme-or-plugin)
  - [Supported repositories](#supported-repositories)
  - [Webhook automation](#webhook-automation)
  - [Managing existing installs](#managing-existing-installs)
- [Development notes](#development-notes)
- [Version history](#version-history)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)

## Features

- **Git powered installs** – Deploy any theme or plugin from a public or private repository by pasting its URL and, optionally, the branch and an access token.
- **Unified dashboard** – Review every FlyGit managed theme and plugin in one place, complete with version, author, activation state, and quick action buttons.
- **Webhook endpoints** – Trigger re-installs remotely using automatically generated REST endpoints that support raw secrets, GitHub signatures, and GitLab tokens.
- **Per-install settings** – Store access tokens, branches, and webhook secrets per installation to keep private code secure.
- **Safe uninstall workflow** – Remove a FlyGit managed item from the dashboard without touching unrelated plugins or themes.

## Requirements

- WordPress 6.0 or newer (REST API must be enabled).
- PHP 7.4 or newer.
- Outbound HTTP access so WordPress can download repository archives.

## Installation

1. Download or clone this repository into `wp-content/plugins/flygit`.
2. In wp-admin, navigate to **Plugins → Installed Plugins** and activate **FlyGit**.
3. Visit **FlyGit** in the admin sidebar to open the dashboard.

No build tools are required; the bundled CSS and JavaScript are ready to use.

## Getting started

### Install a theme or plugin

1. Open the FlyGit dashboard.
2. Choose the **Install Theme** or **Install Plugin** form at the bottom of the respective column.
3. Provide the repository URL. You may also supply a branch name and an access token (for private repositories).
4. Submit the form. FlyGit will download the archive, move it into the correct directory, and confirm the installation.

### Supported repositories

FlyGit understands any direct `.zip` URL as well as GitHub repositories. When a GitHub URL is provided, FlyGit automatically builds the proper download link using the branch you supply (defaults to `main`). Other Git providers can be used as long as they expose downloadable archives.

### Webhook automation

Every installation receives a dedicated REST endpoint in the form:

```
https://your-site.example/wp-json/flygit/v1/installations/{installation_id}/webhook
```

Send a `POST` request to this URL to pull the latest code. Authentication options include:

- `X-Flygit-Secret` header or a `secret` field in the payload.
- GitHub style signatures via `X-Hub-Signature-256` or `X-Hub-Signature`.
- GitLab tokens via `X-Gitlab-Token`.

Use the **Copy** button next to the webhook URL in the dashboard to avoid typos.

### Managing existing installs

- Expand any item to view metadata such as author, repository URL, and branch.
- Activate, deactivate, customize (for themes), or uninstall installations with the provided buttons.
- Update the stored webhook secret at any time; the new value is used immediately for future requests.

## Development notes

- Styles live in `assets/css/admin.css` and scripts in `assets/js/admin.js`.
- The WordPress admin integration is handled by `includes/class-flygit-admin.php` and renders the dashboard located in `includes/views/dashboard.php`.
- Repository downloads, extraction, and bookkeeping are implemented in `includes/class-flygit-installer.php`.
- Webhook processing is managed by `includes/class-flygit-webhook-handler.php` and registers REST routes under `flygit/v1`.

When contributing code, follow WordPress coding standards and make sure PHP files remain lint-free.

## Version history

### 1.1.0

- Exposes explicit WordPress (6.0+) and PHP (7.4+) requirements in the plugin metadata.
- Updates this documentation to reflect the 1.1.0 release.

### 1.0.0

- Initial public release of FlyGit with Git-powered theme and plugin installs, webhook automation, and dashboard management tools.

## Troubleshooting

- **Download failed** – Verify the repository URL is correct and that the web server can reach it. Private repositories require a valid access token.
- **Webhook returns 403** – Double-check that the secret or signature in your request matches the secret stored in FlyGit.
- **Installation missing** – Only themes and plugins installed via FlyGit appear in the dashboard. Reinstall through the FlyGit forms if necessary.

## Contributing

Bug reports, feature requests, and pull requests are welcome. Please open an issue before submitting substantial changes so we can discuss the approach.
