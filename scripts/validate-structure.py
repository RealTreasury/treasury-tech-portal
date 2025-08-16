#!/usr/bin/env python3
"""
Validate Treasury Tech Portal code structure
Helps automated tools understand the codebase
"""

import json
import re
from pathlib import Path

def validate_tool_data_consistency():
    """Ensure tool data is consistent across all files"""
    
    repo_root = Path(".")
    issues = []
    
    # Load tools data
    with open(repo_root / "data" / "tools.json") as f:
        tools = json.load(f)
    
    # Check JavaScript data matches
    js_file = repo_root / "assets" / "js" / "treasury-portal.js"
    with open(js_file) as f:
        js_content = f.read()
    
    # Extract TREASURY_TOOLS from JavaScript
    js_tools_match = re.search(r'this\.TREASURY_TOOLS = (\[.*?\]);', js_content, re.DOTALL)
    if js_tools_match:
        try:
            # Simple validation - count tools
            js_tools_str = js_tools_match.group(1)
            js_tool_count = js_tools_str.count('"name":')
            json_tool_count = len(tools)
            
            if js_tool_count != json_tool_count:
                issues.append(f"Tool count mismatch: JSON has {json_tool_count}, JS has {js_tool_count}")
        except:
            issues.append("Could not parse JavaScript tools data")
    
    # Validate tool properties
    required_props = ['name', 'category', 'desc']
    for i, tool in enumerate(tools):
        for prop in required_props:
            if not tool.get(prop):
                issues.append(f"Tool {i} missing required property: {prop}")
    
    return issues

def find_modification_points():
    """Identify common modification points for automated tools"""
    
    points = {
        "tool_data": "data/tools.json",
        "js_main_class": "assets/js/treasury-portal.js (TreasuryTechPortal class)",
        "css_styles": "assets/css/treasury-portal.css",
        "admin_form": "templates/admin-page.php",
        "admin_save": "includes/class-ttp-admin.php (save_tool method)",
        "rest_api": "includes/class-ttp-rest.php",
        "shortcode_template": "includes/shortcode.php"
    }
    
    return points

if __name__ == "__main__":
    print("=== Treasury Tech Portal Structure Validation ===")
    
    issues = validate_tool_data_consistency()
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
