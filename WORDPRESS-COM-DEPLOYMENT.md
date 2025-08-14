# WordPress.com Deployment Guide

## Connecting the Repository
1. Ensure the WordPress.com site has a plan that allows GitHub connections.
2. In the site's dashboard, open **Tools → Deployments** and select **Connect GitHub Repository**.
3. Authenticate with GitHub, choose this repository, and pick the branch to deploy.
4. Grant WordPress.com access and confirm the connection.

## Configuration Details
- WordPress.com stores secrets and environment variables in the site's configuration screen. Define variables like `API_URL`, `API_TOKEN`, or other keys required by the plugin.
- Adjust plugin options in `treasury-tech-portal.php` or through the WordPress admin interface.
- Enable `WP_DEBUG` or `SCRIPT_DEBUG` for development and disable them in production.

## Deployment Process
1. Merge or push changes to the selected deployment branch (typically `main`).
2. WordPress.com automatically fetches the latest commit and prepares a deploy.
3. From **Tools → Deployments**, trigger a manual deploy or enable auto‑deploy on commit.
4. After deployment, verify the site loads correctly and the plugin functions as expected.

## Monitoring
- Review the WordPress.com Activity Log for deploy events.
- Check site logs via **Tools → Logs** or monitor the `wp-content/debug.log` file if `WP_DEBUG_LOG` is enabled.
- Use built‑in WordPress.com analytics or third‑party monitoring services for uptime and performance.

## Troubleshooting
- If a deploy fails, inspect the Deployments panel for error messages and consult the logs.
- Roll back to a previous release from the Activity Log or redeploy an earlier commit.
- Enable `WP_DEBUG_LOG` and check `wp-content/debug.log` for PHP errors.

## Local Development
- Clone this repository and run a local WordPress environment using tools like `wp-env`, Docker, or Local WP.
- Install dependencies and activate the plugin locally to test changes before pushing.
- Store local configuration in `.env` files or within `wp-config.php` as needed.

## Version Management
- Update the plugin header's `Version` field in `treasury-tech-portal.php` with each release.
- Tag releases using `git tag -a vX.Y.Z -m "Release message"` and push the tags to GitHub.
- WordPress.com deploys the latest commit from the connected branch; tags and release notes help track deployed versions.

