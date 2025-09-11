# Codex Integration Guide

This repository is optimized for automated code modifications using Codex and similar AI development tools.

## Quick Start for Automated Modifications

### Using Python Tools (Recommended)
```bash
# Add a new tool property
python3 scripts/dev-tools.py add-property "integration_complexity" "medium" "Difficulty of integration"

# Add a new filter option  
python3 scripts/dev-tools.py add-filter "integration_level"

# Validate structure after changes
python3 scripts/validate-structure.py
```

### File Modification Patterns

#### 1. Adding Tool Properties
**Files to modify in order:**
1. Vendor API - Ensure property is returned from vendor data
2. `includes/class-ttp-admin.php` - Add sanitization in `save_tool()`
3. `templates/admin-page.php` - Add form field
4. `assets/js/treasury-portal.js` - Update `createToolCard()` method
5. `assets/css/treasury-portal.css` - Add styling if needed

#### 2. Adding Filters
**Files to modify in order:**
1. `includes/shortcode.php` - Add filter UI
2. `assets/js/treasury-portal.js` - Add filter logic to `setupAdvancedFilters()`
3. `assets/css/treasury-portal.css` - Style new filter

## Testing Automated Changes

```bash
# 1. Validate syntax
./scripts/build.sh

# 2. Test structure
python3 scripts/validate-structure.py

# 3. Test plugin functionality
./scripts/test.sh
```
```
