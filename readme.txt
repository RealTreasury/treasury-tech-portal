=== Treasury Tech Portal ===
Contributors: 
Tags: treasury, technology, comparison
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive WordPress plugin for discovering and comparing treasury technology solutions.

== Description ==

The Treasury Tech Portal provides an interactive interface for exploring Cash Tools, TMS-Lite solutions, and Enterprise TRMS platforms. Features include advanced filtering, video demonstrations, product summaries, and shortlist functionality.

== Product Summaries ==

Each tool may include a short plain-text summary that appears on the product card and again in the detail modal. Summaries should stay under 280 characters; longer text is truncated on cards.

== Usage ==

Add the portal to any page or post using the shortcode:

[treasury_portal]

=== Airbase Product Field Sync ===

Product entries are built from Airtable product fields. Follow these steps to configure the sync:

1. In the WordPress admin go to **Treasury Tools → Airbase Settings** and set the required values:
   - **API Token** (`ttp_airbase_token`)
   - **Base ID**
   - **Products Table ID**
   These three fields are **mandatory**—the plugin will not query Airbase until they are provided.
   The API Token is masked in the interface; use the **Reveal** button to view or hide it when editing.
2. Save the settings.
3. To fetch products immediately visit **Treasury Tools → Products** and click **Refresh Products**;
   otherwise a scheduled event updates the cache twice daily.
4. Airbase fields map to the following keys in the stored product list:
   - `Product Name` → `name`
   - `Product` → `product`
   - `Product Website` → `website`
   - `Demo Video URL` → `video_url`
   - `Status` → `status`
   - `Hosted Type` → `hosted_type`
   - `Domain` → `domain`
   - `Regions` → `regions`
   - `Sub Categories` → `sub_categories`
   - `Category` → `category`
   - `category_names` (computed) → `category_names`
   - `Core Capabilities` → `core_capabilities`
   - `Additional Capabilities` → `capabilities`
   - `Logo URL` → `logo_url`
   - `HQ Location` → `hq_location`
   - `Founded Year` → `founded_year`
   - `Founders` → `founders`
   - `Product Summary` → `product_summary`
   - `Market Fit Analysis` → `market_fit_analysis`
   - `Full Website URL` → `full_website_url`
   The refresh stores a normalized array accessible via `TTP_Data::get_all_products()`.

See [AIRBASE_API.md](AIRBASE_API.md) for detailed API reference.

== Installation ==

This plugin is automatically deployed from GitHub to your WordPress site through your hosting provider's integration.

== Changelog ==

= 1.0.2 =
* Update documentation and regenerate minified assets.

= 1.0.1 =
* Bump version and update documentation.

= 1.0.0 =
* Initial release.
