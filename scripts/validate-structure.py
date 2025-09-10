#!/usr/bin/env python3
"""
Validate Treasury Tech Portal code structure
Helps automated tools understand the codebase
"""

import re
from pathlib import Path


def validate_vendor_source():
    """Ensure vendor data retrieval is properly configured"""

    repo_root = Path('.')
    issues = []

    php_file = repo_root / 'includes' / 'class-ttp-data.php'
    with open(php_file) as f:
        php_content = f.read()

    if 'get_all_vendors' not in php_content:
        issues.append('get_all_vendors function missing in class-ttp-data.php')

    if 'data/tools.json' in php_content:
        issues.append('Deprecated data/tools.json reference found in class-ttp-data.php')

    return issues


def find_modification_points():
    """Identify common modification points for automated tools"""

    points = {
        'vendor_data': 'includes/class-ttp-data.php::get_all_vendors',
        'js_main_class': 'assets/js/treasury-portal.js (TreasuryTechPortal class)',
        'css_styles': 'assets/css/treasury-portal.css',
        'admin_form': 'templates/admin-page.php',
        'admin_save': 'includes/class-ttp-admin.php (save_tool method)',
        'rest_api': 'includes/class-ttp-rest.php',
        'shortcode_template': 'includes/shortcode.php',
    }

    return points


if __name__ == '__main__':
    print('=== Treasury Tech Portal Structure Validation ===')

    issues = validate_vendor_source()
    if issues:
        print('Issues found:')
        for issue in issues:
            print(f'  - {issue}')
    else:
        print('\u2713 All validations passed')

    print('\n=== Common Modification Points ===')
    points = find_modification_points()
    for name, location in points.items():
        print(f'  {name}: {location}')

