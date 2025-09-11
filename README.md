# Treasury Tech Portal

> Current version: 1.0.2

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
- Bump the `VENDOR_CACHE_VERSION` constant in `includes/class-ttp-data.php` when product-field ID\u2192name mapping or other linked-record logic changes.
- Run `wp ttp refresh-cache` or use the **Refresh Vendors** button on the Vendors admin page to rebuild cached data and product-field ID\u2192name mappings.

## Documentation
Detailed guides and additional documentation can be found in the [docs](docs/) directory:
- [Contributing Guidelines](docs/CONTRIBUTING.md)
- [API Documentation](docs/API.md)
- [Deployment Guide](docs/WORDPRESS-COM-DEPLOYMENT.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)
- [Linked Field Resolution](docs/LINKED_FIELDS.md)

## Development

Minified assets are generated from their unminified sources:

```
npx csso assets/css/treasury-portal.css --output assets/css/treasury-portal.min.css
npx terser assets/js/treasury-portal.js -c -m -o assets/js/treasury-portal.min.js
npx terser assets/js/treasury-portal-admin.js -c -m -o assets/js/treasury-portal-admin.min.js
```

Run these commands after modifying the corresponding CSS or JavaScript files.

## License
Distributed under the GPL-2.0-or-later license. See `readme.txt` for more details.
