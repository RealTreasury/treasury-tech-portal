# AGENTS - Includes Directory (PHP Backend)

## Backend PHP Classes Overview
This directory contains all PHP backend logic for the Treasury Tech Portal plugin.

## File Structure & Modification Points
```
includes/
├── class-treasury-portal.php    # Main plugin controller
├── class-ttp-data.php           # Data management & caching (MODIFY OFTEN)
├── class-ttp-rest.php           # REST API endpoints (MODIFY FOR API CHANGES)
├── class-ttp-admin.php          # WordPress admin interface (MODIFY FOR ADMIN FEATURES)
└── shortcode.php                # Frontend HTML template (MODIFY FOR LAYOUT)
```

## Key Classes & Modification Patterns

### `Treasury_Tech_Portal` (class-treasury-portal.php)
**Purpose**: Main plugin controller and shortcode handler
**Modify When**: Changing plugin initialization, adding new shortcode parameters

```php
class Treasury_Tech_Portal {
    public function shortcode_handler($atts = array(), $content = null) {
        // Add new shortcode attribute handling here
        $atts = shortcode_atts([
            'category' => '',
            'limit' => '',
            // Add new attributes here
        ], $atts);
    }
}
```

### `TTP_Data` (class-ttp-data.php) - **MODIFY MOST OFTEN**
**Purpose**: Tool data management, caching, and filtering
**Modify When**: Adding tool properties, changing data structure, adding filters

#### Common Modifications:
```php
// Adding new tool filtering
public static function get_tools($args = []) {
    $tools = self::get_all_tools();
    
    // Add new filter logic here
    if (!empty($args['new_filter'])) {
        $tools = array_filter($tools, function($tool) use ($args) {
            return isset($tool['new_property']) && $tool['new_property'] === $args['new_filter'];
        });
    }
}

// Modifying cache behavior
const CACHE_TTL = HOUR_IN_SECONDS; // Change cache duration
```

#### Tool Data Validation:
```php
// Add to save_tools() method for new properties
private static function validate_tool($tool) {
    $required = ['name', 'category', 'desc'];
    foreach ($required as $field) {
        if (empty($tool[$field])) {
            return false;
        }
    }
    // Add validation for new fields
    return true;
}
```

### `TTP_Rest` (class-ttp-rest.php) - **MODIFY FOR API CHANGES**
**Purpose**: REST API endpoints for frontend data access
**Modify When**: Adding new API endpoints, changing response format

```php
class TTP_Rest {
    public static function register_routes() {
        // Add new API endpoints here
        register_rest_route('ttp/v1', '/new-endpoint', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'new_endpoint_handler'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    // Add new endpoint handlers
    public static function new_endpoint_handler($request) {
        $data = TTP_Data::get_some_data();
        return rest_ensure_response($data);
    }
}
```

### `TTP_Admin` (class-ttp-admin.php) - **MODIFY FOR ADMIN FEATURES**
**Purpose**: WordPress admin interface for tool management
**Modify When**: Adding admin fields, new admin pages, bulk operations

#### Adding New Tool Fields:
```php
public static function save_tool() {
    // Add sanitization for new fields
    $tool = [
        'name'       => sanitize_text_field($_POST['name'] ?? ''),
        'category'   => sanitize_text_field($_POST['category'] ?? ''),
        'new_field'  => sanitize_text_field($_POST['new_field'] ?? ''), // Add here
        // ... existing fields
    ];
}
```

#### Adding Admin Pages:
```php
public static function register_menu() {
    add_submenu_page(
        'treasury-tools',
        'New Feature',
        'New Feature', 
        'manage_options',
        'treasury-new-feature',
        [__CLASS__, 'render_new_page']
    );
}
```

## WordPress Integration Patterns

### Hook System Usage
```php
// Initialization hooks
add_action('init', ['ClassName', 'method']);
add_action('admin_menu', ['ClassName', 'register_menu']);

// AJAX handlers  
add_action('wp_ajax_action_name', ['ClassName', 'ajax_handler']);
add_action('wp_ajax_nopriv_action_name', ['ClassName', 'ajax_handler']);

// Custom hooks for extensibility
do_action('ttp_before_save_tool', $tool);
$tool = apply_filters('ttp_tool_data', $tool);
```

### Security Patterns (CRITICAL)
```php
// Capability checking
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

// Nonce verification
check_admin_referer('action_name');
wp_nonce_field('action_name');

// Input sanitization
$value = sanitize_text_field($_POST['field'] ?? '');
$url = esc_url_raw($_POST['url'] ?? '');
$textarea = sanitize_textarea_field($_POST['text'] ?? '');

// Output escaping
echo esc_html($user_input);
echo esc_url($url);
echo wp_kses_post($rich_content);
```

### Caching Patterns
```php
// Transient caching
$data = get_transient('cache_key');
if ($data === false) {
    $data = expensive_operation();
    set_transient('cache_key', $data, HOUR_IN_SECONDS);
}

// Cache invalidation
delete_transient('cache_key');
wp_cache_flush(); // Nuclear option
```

## Data Management

### Tool Data Structure
```php
// Expected tool array structure
$tool = [
    'name'       => 'Tool Name',           // string, required
    'category'   => 'CASH|LITE|TRMS',     // string, required  
    'desc'       => 'Description',        // string, required
    'features'   => ['Feature 1', '...'], // array of strings
    'target'     => 'Target audience',    // string
    'videoUrl'   => 'https://...',        // string, URL
    'websiteUrl' => 'https://...',        // string, URL  
    'logoUrl'    => 'https://...',        // string, URL
];
```

### Adding New Properties:
1. **Backend**: Update `TTP_Admin::save_tool()` sanitization
2. **Storage**: No DB changes needed (JSON storage)
3. **API**: Automatically included in REST responses
4. **Admin**: Add form fields to `templates/admin-page.php`
5. **Frontend**: Update JavaScript to use new property

### Database Operations
```php
// Options API (current storage method)
update_option('ttp_tools', $tools);
$tools = get_option('ttp_tools', []);

// If migrating to custom tables:
global $wpdb;
$table = $wpdb->prefix . 'ttp_tools';
$wpdb->insert($table, $data);
```

## Error Handling & Debugging

### Error Logging
```php
// WordPress error logging
if (WP_DEBUG) {
    error_log('TTP Debug: ' . print_r($data, true));
}

// Custom logging
do_action('qm/debug', $data); // Query Monitor integration
```

### Validation Patterns
```php
// Input validation
if (empty($required_field)) {
    wp_die('Required field missing');
}

// Data type validation
if (!is_array($features)) {
    $features = [];
}

// URL validation
if (!wp_http_validate_url($url)) {
    $url = '';
}
```

### WordPress.com Constraints
- **No file system writes** outside WordPress directories
- **No external API calls** during page load  
- **Memory limits** - optimize for large datasets
- **No custom database tables** - use options API
- **Security scanning** - all inputs must be sanitized

## Common Backend Modifications

### Adding Product Import/Export
```php
// In TTP_Admin class
public static function export_products() {
    $products = TTP_Data::get_all_products();
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="products.json"');
    echo wp_json_encode($products);
    exit;
}
```

### Adding Bulk Operations
```php
public static function bulk_update_tools() {
    check_admin_referer('bulk_update');
    $tools = TTP_Data::get_all_tools();
    
    foreach ($tools as &$tool) {
        // Apply bulk changes
        $tool['updated'] = current_time('mysql');
    }
    
    TTP_Data::save_tools($tools);
}
```

### Adding Data Migration
```php
public static function migrate_data() {
    $tools = TTP_Data::get_all_tools();
    
    foreach ($tools as &$tool) {
        // Add new default properties
        if (!isset($tool['new_property'])) {
            $tool['new_property'] = 'default_value';
        }
    }
    
    TTP_Data::save_tools($tools);
}
```

### Performance Optimization
```php
// Lazy loading
public static function get_tools_lazy($args = []) {
    // Only load tools when actually needed
    static $tools = null;
    if ($tools === null) {
        $tools = self::get_all_tools();
    }
    return self::filter_tools($tools, $args);
}

// Pagination for large datasets
public static function get_tools_paginated($page = 1, $per_page = 20) {
    $tools = self::get_all_tools();
    $offset = ($page - 1) * $per_page;
    return array_slice($tools, $offset, $per_page);
}
```
