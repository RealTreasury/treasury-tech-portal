#!/usr/bin/env python3
"""
Development tools for Treasury Tech Portal
Provides utilities for automated code modifications
"""

import re
from pathlib import Path

class PortalCodeModifier:
    def __init__(self, repo_root="."):
        self.repo_root = Path(repo_root)
        
    def add_tool_property(self, property_name, default_value="", description=""):
        """Add a new property to tool data structure"""
        
        # 1. Update tool schema in AGENTS.md
        agents_file = self.repo_root / "AGENTS.md"
        schema_pattern = r'(\{\s*"name":\s*"string \(required\)"[^}]+)'
        schema_replacement = f'\\1,\n  "{property_name}": "{description or "string"}"'
        self._replace_in_file(agents_file, schema_pattern, schema_replacement)
        
        # 2. Update admin form template
        admin_template = self.repo_root / "templates" / "admin-page.php"
        form_pattern = r'(<tr>\s*<th><label for="tool-logo">Logo URL</label></th>[^<]+</tr>)'
        form_addition = f'''\\1
            <tr>
                <th><label for="tool-{property_name.lower().replace("_", "-")}">{property_name.replace("_", " ").title()}</label></th>
                <td><input name="{property_name}" id="tool-{property_name.lower().replace("_", "-")}" type="text" class="regular-text"></td>
            </tr>'''
        self._replace_in_file(admin_template, form_pattern, form_addition)
        
        # 3. Update admin save logic
        admin_class = self.repo_root / "includes" / "class-ttp-admin.php"
        save_pattern = r"('logoUrl'\s*=>\s*esc_url_raw\(\$_POST\['logoUrl'\]\s*\?\?\s*''\))"
        save_addition = f"\\1,\n            '{property_name}' => sanitize_text_field($_POST['{property_name}'] ?? '{default_value}')"
        self._replace_in_file(admin_class, save_pattern, save_addition)
        
        # 4. Update JavaScript tool card creation
        js_file = self.repo_root / "assets" / "js" / "treasury-portal.js"
        card_pattern = r"(<div class=\"tool-description\">\\$\\{\\(tool\\.subCategories \\|\\| \\[]\\)\\.join\\(', '\\)\\}</div>)"
        card_addition = f'\\1\n                        ${{tool.{property_name} ? `<div class="{property_name.lower().replace("_", "-")}">${{tool.{property_name}}}</div>` : ""}}'
        self._replace_in_file(js_file, card_pattern, card_addition)
        
    
    def add_css_rule(self, selector, properties):
        """Add CSS rule to main stylesheet"""
        css_file = self.repo_root / "assets" / "css" / "treasury-portal.css"
        css_rule = f"\n\n        .treasury-portal {selector} {{\n"
        for prop, value in properties.items():
            css_rule += f"            {prop}: {value};\n"
        css_rule += "        }"
        
        with open(css_file, 'a') as f:
            f.write(css_rule)
    
    def add_filter_option(self, filter_name, filter_type="checkbox"):
        """Add new filter option to side menu"""
        shortcode_file = self.repo_root / "includes" / "shortcode.php"
        
        if filter_type == "checkbox":
            filter_html = f'''
                        <div class="filter-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="{filter_name}Filter">
                                <label for="{filter_name}Filter">{filter_name.replace("_", " ").title()}</label>
                            </div>
                        </div>'''
            
            # Find the filters section and add new filter
            pattern = r'(<div class="checkbox-item">\s*<input type="checkbox" id="hasVideoFilter">[^<]+</div>\s*</div>)'
            replacement = f'\\1{filter_html}'
            self._replace_in_file(shortcode_file, pattern, replacement)
    
    def _replace_in_file(self, file_path, pattern, replacement):
        """Helper method for regex replacement in files"""
        with open(file_path, 'r') as f:
            content = f.read()
        
        new_content = re.sub(pattern, replacement, content, flags=re.MULTILINE | re.DOTALL)
        
        if new_content != content:
            with open(file_path, 'w') as f:
                f.write(new_content)
            print(f"Updated {file_path}")
        else:
            print(f"No changes made to {file_path}")

# CLI interface
if __name__ == "__main__":
    import sys
    
    modifier = PortalCodeModifier()
    
    if len(sys.argv) < 2:
        print("Usage: python dev-tools.py <command> [args...]")
        print("Commands:")
        print("  add-property <name> [default_value] [description]")
        print("  add-filter <name> [type]")
        sys.exit(1)
    
    command = sys.argv[1]
    
    if command == "add-property":
        name = sys.argv[2]
        default = sys.argv[3] if len(sys.argv) > 3 else ""
        desc = sys.argv[4] if len(sys.argv) > 4 else ""
        modifier.add_tool_property(name, default, desc)
        
    elif command == "add-filter":
        name = sys.argv[2]
        filter_type = sys.argv[3] if len(sys.argv) > 3 else "checkbox"
        modifier.add_filter_option(name, filter_type)
        
    else:
        print(f"Unknown command: {command}")
