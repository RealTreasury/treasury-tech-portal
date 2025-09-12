#!/usr/bin/env python3
"""
Validate Treasury Tech Portal code structure
Helps automated tools understand the codebase
"""

import re
from pathlib import Path


def validate_tool_data_source():
    """Ensure tool data is sourced from products instead of local JSON"""

    repo_root = Path(".")
    issues = []

    php_file = repo_root / "includes" / "class-ttp-data.php"
    with open(php_file) as f:
        php_content = f.read()

    if "load_default_tools" in php_content:
        issues.append("Legacy load_default_tools() method still present")

    if "get_all_products" not in php_content:
        issues.append("get_all_tools() does not use product data")

    return issues


def find_modification_points():
    """Identify common modification points for automated tools"""

    points = {
        "vendor_data": "includes/class-ttp-data.php::get_all_products()",
        "js_main_class": "assets/js/treasury-portal.js (TreasuryTechPortal class)",
        "css_styles": "assets/css/treasury-portal.css",
        "admin_form": "templates/admin-page.php",
        "admin_save": "includes/class-ttp-admin.php (save_tool method)",
        "rest_api": "includes/class-ttp-rest.php",
        "shortcode_template": "includes/shortcode.php",
    }

    return points


if __name__ == "__main__":
    print("=== Treasury Tech Portal Structure Validation ===")

    issues = validate_tool_data_source()
    if issues:
        print("Issues found:")
        for issue in issues:
            print(f"  - {issue}")
    else:
        print("✓ All validations passed")

    print("\n=== Common Modification Points ===")
    points = find_modification_points()
    for name, location in points.items():
        print(f"  {name}: {location}")

