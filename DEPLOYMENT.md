# Deployment Guide

This document outlines how to deploy the Treasury Tech Portal plugin using GitHub Actions and the included scripts.

## 1. Repository Setup for WordPress Deployment
- Ensure all plugin files are committed to the `main` branch.
- Configure the GitHub Actions workflow located at `.github/workflows/deploy.yml`.
- When a tag matching `v*` is pushed, a release and plugin ZIP will be created automatically.

## 2. Configuring GitHub Secrets
No additional secrets are required for public repositories. For private repositories, add these secrets:
- `GITHUB_TOKEN`: Provided automatically by GitHub Actions.
- `TTP_GITHUB_TOKEN`: Personal access token used by the updater for private repositories.

## 3. Creating Releases
1. Bump the version in `treasury-tech-portal.php`.
2. Commit the changes and push to `main`.
3. Create a new tag: `git tag v1.0.1 && git push origin v1.0.1`.
4. The workflow will package the plugin and publish a release with the generated ZIP.

## 4. Installing the Plugin from GitHub
- Download the ZIP file from the latest release and install it through the WordPress admin.
- To install automatically, use the `TTP_Installer::install_from_github` helper from `deployment/install.php`.

## 5. Troubleshooting
- **Missing readme.txt**: Ensure the file exists at the plugin root; the workflow checks for it.
- **PHP errors**: Run `php -l` on the main plugin files to validate syntax.
- **Workflow failures**: Inspect the GitHub Actions logs for details and confirm required secrets are set.

