# Treasury Tech Portal

A WordPress plugin that provides an interactive interface for discovering and comparing treasury technology solutions across Cash Tools, TMS-Lite platforms, and Enterprise TRMS suites.

## Features
- Vendor listings grouped by category
- Advanced filtering and search
- Video demonstrations
- Shortlist and comparison tools

## Quick Start
1. Install and activate the plugin in WordPress.
2. Add the portal to any page or post using the shortcode:
   ```
   [treasury_portal]
   ```

### Refreshing the Vendor Cache
- Bump the `VENDOR_CACHE_VERSION` constant in `includes/class-ttp-data.php` when linked-record logic changes.
- Run `wp ttp refresh-cache` or use the **Refresh Vendors** button on the Vendors admin page to rebuild cached data.

## Documentation
Detailed guides and additional documentation can be found in the [docs](docs/) directory:
- [Contributing Guidelines](docs/CONTRIBUTING.md)
- [API Documentation](docs/API.md)
- [Deployment Guide](docs/WORDPRESS-COM-DEPLOYMENT.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)

## License
Distributed under the GPL-2.0-or-later license. See `readme.txt` for more details.
