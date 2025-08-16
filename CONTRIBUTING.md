# Contributing to Treasury Tech Portal

## Development Workflow for AI Developers

### Before Making Changes
1. **Test current functionality**: Use `[treasury_portal]` shortcode on a test page
2. **Run validation**: Execute `scripts/test.sh` to ensure PHP syntax is valid
3. **Check WordPress.com constraints**: Review deployment limitations below

### Making Code Changes

#### For UI/Frontend Changes (`assets/` directory)
1. **CSS Changes**: Modify `assets/css/treasury-portal.css`
   - Always use `.treasury-portal` namespace
   - Test mobile responsive behavior at 768px breakpoint
   - Use CSS custom properties for consistent theming

2. **JavaScript Changes**: Modify `assets/js/treasury-portal.js`
   - All state managed in `TreasuryTechPortal` class properties
   - Use vanilla JavaScript only (no external dependencies)
   - Test mobile touch interactions and swipe gestures

#### For Backend/Data Changes (`includes/` directory)
1. **Data Structure**: Modify `includes/class-ttp-data.php`
   - Update `get_tools()` method for new filtering
   - Clear cache with `delete_transient('ttp_tools_cache')`

2. **Admin Interface**: Modify `includes/class-ttp-admin.php`
   - Add form fields to `templates/admin-page.php`
   - Update `save_tool()` method with proper sanitization

3. **API Changes**: Modify `includes/class-ttp-rest.php`
   - Add new endpoints for frontend data access
   - Maintain backward compatibility

### Testing Your Changes

#### Frontend Testing Checklist
- [ ] Desktop responsive design (1200px+)
- [ ] Tablet responsive design (768px-1199px)  
- [ ] Mobile responsive design (<768px)
- [ ] Modal functionality and video playback
- [ ] Drag-and-drop shortlist functionality
- [ ] Search and filtering operations
- [ ] Touch gestures on mobile devices

#### Backend Testing Checklist
- [ ] Run `scripts/test.sh` for PHP validation
- [ ] Test admin interface tool creation/editing
- [ ] Verify REST API endpoints return expected data
- [ ] Check caching behavior and invalidation
- [ ] Test with WordPress debug mode enabled

### Common Coding Patterns

#### Adding New Tool Properties
```php
// 1. Backend - Update save method
$tool['new_property'] = sanitize_text_field($_POST['new_property'] ?? '');

// 2. Frontend - Update display  
card.innerHTML += `<div class="new-feature">${tool.newProperty}</div>`;

// 3. Admin - Add form field
<input name="new_property" type="text" value="${tool.new_property || ''}">
```

#### Adding New Filters
```javascript
// 1. Add to advanced filters object
this.advancedFilters.newFilter = false;

// 2. Update filtering logic
if (this.advancedFilters.newFilter) {
  tools = tools.filter(tool => tool.hasNewFeature);
}

// 3. Add UI control and event handler
document.getElementById('newFilter').addEventListener('change', (e) => {
  this.advancedFilters.newFilter = e.target.checked;
  this.filterAndDisplayTools();
});
```

### WordPress.com Deployment Constraints

#### Critical Limitations
- **No localStorage/sessionStorage**: Use in-memory state only
- **No external API calls**: All data must be self-contained
- **No npm build process**: Code must work as-written
- **File size limits**: Keep assets optimized
- **No server-side file writes**: Use WordPress options API only

#### Security Requirements
- **Sanitize all inputs**: Use WordPress sanitization functions
- **Escape all outputs**: Use `esc_html()`, `esc_url()`, etc.
- **Capability checks**: Verify `manage_options` for admin functions
- **Nonce verification**: Use `check_admin_referer()` for admin actions

### Code Quality Standards

#### PHP Standards
- Follow WordPress coding standards
- Use proper DocBlocks for methods
- Handle errors gracefully with `wp_die()` or similar
- Use WordPress hooks and filters appropriately

#### JavaScript Standards  
- Use ES6+ syntax but avoid bleeding-edge features
- Clean up event listeners in component teardown
- Use debouncing for frequently-called functions
- Handle mobile and desktop interactions appropriately

#### CSS Standards
- Mobile-first responsive design
- Use semantic class names with BEM-like structure
- Avoid `!important` declarations
- Use CSS custom properties for theming

### Debugging Common Issues

#### Frontend Issues
```javascript
// Check main instance state
window.treasuryTechPortal.filteredTools
window.treasuryTechPortal.currentFilter

// Debug tool data
console.log(treasuryTechPortal.TREASURY_TOOLS);

// Check responsive state
treasuryTechPortal.isMobile()
```

#### Backend Issues
```php
// Enable WordPress debug mode
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check tool data
$tools = TTP_Data::get_all_tools();
error_log('Tools: ' . print_r($tools, true));

// Clear caches
delete_transient('ttp_tools_cache');
wp_cache_flush();
```

### Performance Considerations

#### Frontend Performance
- Debounce search input (100ms delay implemented)
- Minimize DOM queries by caching selectors
- Use event delegation for dynamic content
- Optimize video loading with lazy loading

#### Backend Performance  
- Use transient caching (1 hour TTL)
- Avoid database queries in loops
- Use WordPress object cache when available
- Paginate large datasets in admin interface

### Git Workflow

#### Commit Message Format
```
type(scope): description

feat(frontend): add new tool property display
fix(backend): resolve caching issue with tool updates  
docs(api): update REST endpoint documentation
style(css): improve mobile responsive design
```

#### Before Committing
1. Run `scripts/test.sh` 
2. Test core functionality with shortcode
3. Verify no console errors in browser
4. Check mobile responsive behavior

### Deployment Process

#### Automatic Deployment
- Changes to `main` branch trigger WordPress.com deployment
- Build process runs `scripts/build.sh` validation
- Failed builds prevent deployment

#### Manual Testing After Deployment
1. Verify shortcode renders correctly
2. Test admin interface functionality  
3. Check mobile responsive behavior
4. Verify video integration works
5. Test drag-and-drop functionality

### Getting Help

#### Common Resources
- WordPress Codex: https://codex.wordpress.org/
- WordPress.com Documentation: https://wordpress.com/support/
- Plugin files have inline comments for guidance

#### Debugging Tools
- Browser Developer Tools for frontend issues
- WordPress Debug Log for backend issues
- Query Monitor plugin for performance analysis
- WordPress.com deployment logs for deployment issues
