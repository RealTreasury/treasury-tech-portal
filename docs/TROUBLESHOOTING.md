# Troubleshooting Guide - Treasury Tech Portal

## Common Frontend Issues

### Tools Not Displaying
**Symptoms**: Empty portal, no tool cards visible, category sections blank

**Debugging Steps**:
```javascript
// Check if tools loaded
console.log(window.treasuryTechPortal?.TREASURY_TOOLS?.length);

// Check current filter state
console.log(window.treasuryTechPortal?.currentFilter);
console.log(window.treasuryTechPortal?.filteredTools?.length);

// Check for JavaScript errors
// Open Browser Dev Tools > Console for error messages
```

**Common Causes & Solutions**:
1. **JavaScript Error**: Check browser console for errors
   - **Solution**: Fix syntax errors, missing semicolons, undefined variables
2. **Data Loading Failed**: Tools array is empty
   - **Solution**: Check REST API endpoint `/wp-json/ttp/v1/tools`
3. **Filter Logic Error**: All tools filtered out
   - **Solution**: Clear filters with `treasuryTechPortal.clearAllFilters()`

### Search Not Working
**Symptoms**: Search input doesn't filter results, no response to typing

**Debugging Steps**:
```javascript
// Check search term
console.log(treasuryTechPortal.searchTerm);

// Check event listeners
// Verify search input has id="searchInput"
document.getElementById('searchInput')?.value;

// Check filtering function
treasuryTechPortal.filterAndDisplayTools();
```

**Solutions**:
1. **Missing Event Listener**: Ensure `setupSearch()` is called in `init()`
2. **Search Input Missing**: Verify `id="searchInput"` in HTML template
3. **Case Sensitivity**: Search uses `toLowerCase()` for case-insensitive matching

### Modal Not Opening
**Symptoms**: Clicking tool cards doesn't open modal, modal appears but content is blank

**Debugging Steps**:
```javascript
// Check modal elements exist
document.getElementById('toolModal');
document.getElementById('modalTitle');

// Test modal manually
treasuryTechPortal.showToolModal(treasuryTechPortal.TREASURY_TOOLS[0]);

// Check for CSS display issues
const modal = document.getElementById('toolModal');
console.log(getComputedStyle(modal).display);
```

**Solutions**:
1. **Missing Modal HTML**: Ensure modal elements exist in `shortcode.php`
2. **CSS z-index Issues**: Modal z-index should be 100001 or higher
3. **Event Conflicts**: Check for event.stopPropagation() blocking clicks

### Mobile Responsiveness Issues
**Symptoms**: Interface doesn't adapt to mobile, touch interactions fail

**Debugging Steps**:
```javascript
// Check mobile detection
console.log(treasuryTechPortal.isMobile());

// Check viewport
console.log(window.innerWidth);

// Check responsive handler
treasuryTechPortal.handleResponsive();
```

**Solutions**:
1. **Viewport Meta Tag**: Ensure `<meta name="viewport" content="width=device-width, initial-scale=1">`
2. **CSS Media Queries**: Use `@media (max-width: 768px)` for mobile styles
3. **Touch Events**: Add `{ passive: false }` for touch event handlers

### Videos Not Playing
**Symptoms**: Video containers empty, YouTube embeds fail to load

**Debugging Steps**:
```javascript
// Check video URLs
console.log(treasuryTechPortal.TREASURY_TOOLS.filter(t => t.videoUrl));

// Check iframe creation
document.querySelectorAll('iframe').forEach(iframe => {
  console.log(iframe.src);
});

// Check video container
document.querySelectorAll('.video-container');
```

**Solutions**:
1. **URL Format Issues**: Ensure YouTube URLs include `enablejsapi=1` parameter
2. **HTTPS Required**: All video URLs must use HTTPS protocol
3. **CORS Issues**: Direct video files must be served from same domain or with CORS headers

## Common Backend Issues

### Tools Not Saving in Admin
**Symptoms**: Admin form submission doesn't persist data, PHP errors on save

**Debugging Steps**:
```php
// Enable WordPress debug
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check debug log
tail -f /wp-content/debug.log

// Check tool data
$tools = TTP_Data::get_all_tools();
error_log('Tools count: ' . count($tools));
```

**Solutions**:
1. **Nonce Verification Failing**: Ensure nonce field is present in form
   ```php
   wp_nonce_field('ttp_save_tool');
   ```
2. **Permission Issues**: Verify user has `manage_options` capability
3. **Sanitization Errors**: Check all `$_POST` data is properly sanitized

### REST API Not Working
**Symptoms**: Frontend can't load data, 404 errors on API endpoints

**Debugging Steps**:
```bash
# Test API endpoint directly
curl "https://yoursite.com/wp-json/ttp/v1/tools"

# Check WordPress permalink structure
# Go to Settings > Permalinks in admin
```

**Solutions**:
1. **Permalink Issues**: Flush permalinks (Settings > Permalinks > Save)
2. **REST API Disabled**: Check if REST API is disabled by security plugin
3. **Authentication Issues**: Ensure `permission_callback` is `__return_true`

### Caching Issues
**Symptoms**: Changes don't appear, old data persists after updates

**Debugging Steps**:
```php
// Check transient cache
$cached = get_transient('ttp_tools_cache');
var_dump($cached);

// Clear cache manually
delete_transient('ttp_tools_cache');
wp_cache_flush();
```

**Solutions**:
1. **Stale Cache**: Clear transient cache after data changes
2. **Object Cache**: Flush WordPress object cache if using Redis/Memcached
3. **Browser Cache**: Clear browser cache for CSS/JS changes

## WordPress.com Specific Issues

### Deployment Failures
**Symptoms**: GitHub pushes don't trigger deployment, build process fails

**Debugging Steps**:
```bash
# Check required files exist
ls -la docs/WORDPRESS-COM-DEPLOYMENT.md readme.txt treasury-tech-portal.php

# Run local build test
bash ../scripts/build.sh

# Check PHP syntax
find . -name "*.php" -exec php -l {} \;
```

**Solutions**:
1. **Missing Required Files**: Ensure all files in deployment config exist
2. **PHP Syntax Errors**: Fix syntax errors preventing build
3. **File Permissions**: Check file permissions on required files

### localStorage Restrictions
**Symptoms**: JavaScript localStorage errors in iframe environment

**Solution**: Use in-memory state only
```javascript
// DON'T USE
localStorage.setItem('data', value);

// USE INSTEAD  
this.stateProperty = value; // Store in class properties
```

### Plugin Auto-Activation Issues
**Symptoms**: Plugin doesn't activate automatically after deployment

**Debugging Steps**:
```php
// Check auto_activate setting
// In .wordpress-com/config.json
"auto_activate": true
```

**Solutions**:
1. **Config Missing**: Ensure `auto_activate: true` in deployment config
2. **Plugin Errors**: Fix any PHP errors preventing activation
3. **Dependencies**: Check for missing WordPress dependencies

## Performance Issues

### Slow Loading
**Symptoms**: Portal takes long time to initialize, UI feels sluggish

**Debugging Steps**:
```javascript
// Check tool count
console.log(treasuryTechPortal.TREASURY_TOOLS.length);

// Profile JavaScript performance
console.time('filterAndDisplayTools');
treasuryTechPortal.filterAndDisplayTools();
console.timeEnd('filterAndDisplayTools');

// Check DOM manipulation
console.log(document.querySelectorAll('.tool-card').length);
```

**Solutions**:
1. **Too Many DOM Elements**: Implement pagination for large datasets
2. **Inefficient Filtering**: Optimize filter algorithms, use debouncing
3. **Large Images**: Optimize logo images, use lazy loading

### Memory Leaks
**Symptoms**: Browser tab memory usage increases over time, UI becomes unresponsive

**Debugging Steps**:
```javascript
// Check event listeners
// Use browser dev tools > Memory tab

// Check for circular references
console.log(treasuryTechPortal);

// Monitor object creation
// Use Performance tab in dev tools
```

**Solutions**:
1. **Event Listener Cleanup**: Remove event listeners when destroying components
2. **DOM References**: Clear references to removed DOM elements
3. **Timer Cleanup**: Clear intervals and timeouts on component destruction

## Data Issues

### Incorrect Tool Data
**Symptoms**: Wrong information displayed, missing tool properties

**Debugging Steps**:
```php
// Check raw tool data
$tools = TTP_Data::get_all_tools();
foreach ($tools as $tool) {
    if (empty($tool['name'])) {
        error_log('Tool missing name: ' . print_r($tool, true));
    }
}
```

**Solutions**:
1. **Data Migration Needed**: Run data migration for new properties
2. **Validation Missing**: Add validation in `TTP_Admin::save_tool()`
3. **Corrupted Data**: Restore from backup or refresh vendor cache to rebuild product-field ID\u2192name mappings

### Search Results Incorrect
**Symptoms**: Search returns wrong tools, missing expected results

**Debugging Steps**:
```javascript
// Test search manually
const searchTerm = 'AI';
const results = treasuryTechPortal.TREASURY_TOOLS.filter(tool => {
  const searchableText = [
    tool.name,
    tool.desc,
    tool.target,
    ...(tool.tags || []),
    ...(tool.features || [])
  ].join(' ').toLowerCase();
  return searchableText.includes(searchTerm.toLowerCase());
});
console.log(results);
```

**Solutions**:
1. **Search Logic Error**: Fix search algorithm in `filterAndDisplayTools()`
2. **Missing Properties**: Ensure all searchable properties are included
3. **Case Sensitivity**: Use `toLowerCase()` for all string comparisons

## Emergency Recovery

### Complete Reset
If portal is completely broken:

```javascript
// 1. Clear all state
if (window.treasuryTechPortal) {
  treasuryTechPortal.clearAllFilters();
  treasuryTechPortal.closeSideMenu();
  treasuryTechPortal.closeShortlistMenu();
}

// 2. Reload portal
location.reload();
```

```php
// 3. Reset vendor data
delete_option('ttp_vendors');
delete_transient('ttp_vendors_cache');
wp_cache_flush();

// 4. Refresh from API
TTP_Data::refresh_vendor_cache();
```

### Rollback Deployment
If deployment breaks production:

1. **Revert Git Commit**: Push previous working commit to trigger redeployment
2. **Use WordPress.com Rollback**: Use hosting provider's rollback feature
3. **Manual File Restore**: Upload previous working files via FTP/file manager

## Getting Help

### Debug Information to Collect
When reporting issues, include:

```javascript
// Frontend debug info
console.log({
  userAgent: navigator.userAgent,
  viewport: window.innerWidth + 'x' + window.innerHeight,
  toolsCount: treasuryTechPortal?.TREASURY_TOOLS?.length,
  filteredCount: treasuryTechPortal?.filteredTools?.length,
  currentFilter: treasuryTechPortal?.currentFilter,
  jsErrors: /* copy any console errors */
});
```

```php
// Backend debug info
global $wp_version;
error_log('Debug Info: ' . print_r([
    'wp_version' => $wp_version,
    'php_version' => PHP_VERSION,
    'plugin_version' => TTP_VERSION,
    'tools_count' => count(TTP_Data::get_all_tools()),
    'cache_status' => get_transient('ttp_tools_cache') !== false
], true));
```

### Resources
- **WordPress Debug**: Enable `WP_DEBUG` and `WP_DEBUG_LOG`
- **Browser Dev Tools**: Use Console, Network, and Performance tabs
- **WordPress.com Support**: Contact hosting provider for deployment issues
- **Plugin Documentation**: Reference API.md and AGENTS.md files
