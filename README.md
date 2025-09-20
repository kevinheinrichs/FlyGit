# FlyGit

FlyGit is a WordPress admin plugin that lets you install and maintain themes or plugins directly from Git repositories without leaving the WordPress dashboard.

**Current version:** 1.0.1

## Table of contents

- [Overview](#overview)
- [Feature highlights](#feature-highlights)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Managing installations](#managing-installations)
- [Automating updates with webhooks](#automating-updates-with-webhooks)
- [Manual updates and rollbacks](#manual-updates-and-rollbacks)
- [Security best practices](#security-best-practices)
- [Troubleshooting](#troubleshooting)
- [Frequently asked questions](#frequently-asked-questions)
- [Contributing and support](#contributing-and-support)

## Overview

FlyGit bridges the gap between Git-centric workflows and WordPress. Point it to any Git repository and the plugin downloads the
archive, extracts it into the correct location (`wp-content/themes` or `wp-content/plugins`), and keeps track of the installati
on so you can update or remove it later. The dashboard inside wp-admin shows every managed theme or plugin alongside relevant m
etadata, secrets, and quick actions.

## Feature highlights

- **Git powered installs** – Deploy themes or plugins from public or private repositories by pasting the repository URL and opt
ionally providing a branch and access token.
- **Unified dashboard** – View every FlyGit managed installation in one place with status indicators, author details, activatio
n toggles, and uninstall buttons.
- **Secure webhook endpoints** – Trigger re-installs remotely using REST endpoints that support static secrets, GitHub signature
s, and GitLab tokens.
- **Per-install settings** – Store access tokens, branches, and webhook secrets per installation to keep private code secure.
- **Safe uninstall workflow** – Remove FlyGit managed items cleanly without touching unrelated plugins or themes.

## Requirements

- WordPress 6.0 or newer (with the REST API enabled).
- PHP 7.4 or newer.
- Outbound HTTP access from the server so WordPress can download repository archives.

## Installation

1. Download or clone this repository into `wp-content/plugins/flygit` inside your WordPress installation.
2. Log in to wp-admin and navigate to **Plugins → Installed Plugins**.
3. Activate **FlyGit**.
4. Open **FlyGit** in the admin sidebar to load the management dashboard.

No build tools are required; the bundled CSS and JavaScript are ready to use.

## Quick start

1. **Prepare your repository.** Ensure the theme or plugin folder sits at the root of the repository and that the `style.css` or
 main plugin file contains valid WordPress headers.
2. **Install a theme or plugin.** In the FlyGit dashboard, pick the **Install Theme** or **Install Plugin** form. Paste the repo
sitory URL and optionally provide a branch/tag and access token (for private repositories, use a token with read-only access).
3. **Confirm the install.** FlyGit downloads the archive, extracts it into the correct directory, and displays a success notice 
with activation buttons.
4. **Activate and test.** Use the provided **Activate** button (or **Customize** for themes) and verify that the installed code 
behaves as expected.

## Managing installations

- **Overview cards** – Each managed item includes the version, author, repository URL, branch, and webhook URL.
- **Activation controls** – Activate, deactivate, or open the Customizer (for themes) directly from the dashboard.
- **Updates** – Reinstall an item at any time by submitting the form again with the same repository details. FlyGit overwrites t
he existing directory safely.
- **Uninstall** – Removing an installation detaches it from FlyGit and deletes the managed copy without affecting other WordPres
s components.

## Automating updates with webhooks

Every installation receives its own REST endpoint at:

```
https://your-site.example/wp-json/flygit/v1/installations/{installation_id}/webhook
```

Trigger the endpoint with a `POST` request whenever code changes are pushed. Supported authentication methods include:

- `X-Flygit-Secret` header or a `secret` field in the JSON payload.
- GitHub style HMAC signatures via `X-Hub-Signature-256` or `X-Hub-Signature`.
- GitLab tokens via `X-Gitlab-Token`.

Use the **Copy** button in the dashboard to grab the exact URL. If you automate deployments, consider combining repository hook
s with WordPress cron to ensure installations stay up to date even if webhook requests are missed.

## Manual updates and rollbacks

- **Update to the latest commit:** Re-submit the installation form with the same repository and branch. FlyGit fetches the fresh
 archive and replaces the existing files.
- **Deploy a specific release:** Provide a tag or release branch in the form to pin the installation to a known state.
- **Rollback:** If an update causes issues, reinstall the previous tag or branch. FlyGit will overwrite the directory with the r
estore version.

## Security best practices

- Prefer read-only access tokens for private repositories and rotate them periodically.
- Store webhook secrets in a password manager and never commit them to version control.
- Restrict wp-admin access to trusted administrators; only they can configure FlyGit.
- Keep WordPress core, themes, and plugins (including FlyGit) updated to benefit from security fixes.

## Troubleshooting

- **Download failed** – Double-check the repository URL and confirm that the server can reach it. Private repositories require a
 valid access token.
- **Webhook returns 403** – Verify that the secret or signature in your request matches the secret stored in FlyGit.
- **Installation missing** – Only themes and plugins installed via FlyGit appear in the dashboard. Reinstall through FlyGit if ne
eded.
- **File permission errors** – Ensure the web server user can write to `wp-content/themes` or `wp-content/plugins`.

## Frequently asked questions

**Can I manage both themes and plugins?** Yes. The dashboard is split into two columns so you can install, update, and remove bo
th types independently.

**Do I need to host a `.zip` file?** Not necessarily. FlyGit can build download URLs for GitHub repositories automatically. Othe
r providers work too as long as they offer downloadable archives.

**Will FlyGit overwrite manual changes?** Reinstalling an item replaces its directory, so avoid editing managed files directly o
n the server. Make changes in Git and redeploy through FlyGit instead.

**Can I trigger updates from CI/CD pipelines?** Absolutely. Use the webhook endpoint in your deployment scripts (for example wi
th `curl`) to request a fresh install after each successful build.

## Contributing and support

Bug reports, feature requests, and pull requests are welcome. Please open an issue before submitting substantial changes so we c
an discuss the approach. If you run into problems using FlyGit, include your WordPress version, PHP version, and any relevant lo
g entries when reaching out for help.
