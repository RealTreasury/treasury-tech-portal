=== Treasury Tech Portal ===
Contributors: 
Tags: treasury, technology, comparison
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive WordPress plugin for discovering and comparing treasury technology solutions.

== Description ==

The Treasury Tech Portal provides an interactive interface for exploring Cash Tools, TMS-Lite solutions, and Enterprise TRMS platforms. Features include advanced filtering, video demonstrations, and shortlist functionality.

== Usage ==

Add the portal to any page or post using the shortcode:

[treasury_portal]

=== Airbase Vendor Sync ===

1. In the WordPress admin go to **Treasury Tools → Airbase Settings** and set the required values:
   - **API Token** (`ttp_airbase_token`)
   - **Base ID**
   - **Products Table ID**
   These three fields are **mandatory**—the plugin will not query Airbase until they are provided.
2. Save the settings.
3. To fetch vendors immediately visit **Treasury Tools → Vendors** and click **Refresh Vendors**;
   otherwise a scheduled event updates the cache twice daily.
4. Airbase fields map to the following keys in the stored vendor list:
   - `Product Name` → `name`
   - `Linked Vendor` → `vendor`
   - `Product Website` → `website`
   - `Status` → `status`
   - `Hosted Type` → `hosted_type`
   - `Domain` → `domain`
   - `Regions` → `regions`
   - `Sub Categories` → `sub_categories`
   - `Parent Category` → `parent_category`
   - `category_names` (computed) → `category_names`
   - `Capabilities` → `capabilities`
   - `Logo URL` → `logo_url`
   - `HQ Location` → `hq_location`
   - `Founded Year` → `founded_year`
   - `Founders` → `founders`
   The refresh stores a normalized array accessible via `TTP_Data::get_all_vendors()`.

See [AIRBASE_API.md](AIRBASE_API.md) for detailed API reference.

== Installation ==

This plugin is automatically deployed from GitHub to your WordPress site through your hosting provider's integration.

== Changelog ==

= 1.0.1 =
* Bump version and update documentation.

= 1.0.0 =
* Initial release.
