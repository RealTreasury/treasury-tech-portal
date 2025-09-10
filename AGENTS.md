# AGENTS - Treasury Tech Portal

## Project Overview
A WordPress plugin that provides an interactive platform for discovering and comparing treasury technology solutions. The plugin displays financial technology tools across three categories: Cash Tools, TMS-Lite, and Enterprise TRMS platforms.

**Primary Use Case**: Codex-assisted code modifications and feature development.

## Quick Reference for Code Changes

### Most Common Modification Points
1. **Vendor Data Source**: `TTP_Data::get_all_vendors()` - Adjust vendor properties
2. **UI Components**: `assets/js/treasury-portal.js` - `TreasuryTechPortal` class methods
3. **Styling**: `assets/css/treasury-portal.css` - `.treasury-portal` namespaced styles
4. **Backend Logic**: `includes/class-ttp-data.php` - Data management and caching
5. **Admin Interface**: `includes/class-ttp-admin.php` - WordPress admin functionality

## Architecture & Key Modification Points

### Backend (PHP) - Modify for data/API changes
- **Main Plugin File**: `treasury-tech-portal.php` - WordPress plugin bootstrap
- **Core Classes**:
  - `Treasury_Tech_Portal` - Main plugin controller, shortcode handler
  - `TTP_Data` - **MODIFY HERE**: Data management, caching, tool filtering logic
  - `TTP_Rest` - **MODIFY HERE**: REST API endpoints for frontend data
  - `TTP_Admin` - **MODIFY HERE**: WordPress admin interface for tool management
- **WordPress Integration**: Uses WordPress hooks, shortcodes, and REST API

### Frontend (JavaScript/CSS) - Modify for UI/UX changes
- **Main Class**: `TreasuryTechPortal` - **MODIFY HERE**: All UI interactions and state management
- **Key Methods to Modify**:
  - `filterAndDisplayTools()` - Search and filtering logic
  - `createToolCard()` - Tool card rendering and interactions
  - `showToolModal()` - Modal content and video handling
  - `setupSideMenu()`, `setupShortlistMenu()` - Menu functionality
  - `handleResponsive()` - Mobile/desktop behavior
- **State Management**: All stored in class properties (no external state management)

### Data Structure & Modification Patterns
**Vendor Object Schema** (retrieved via API):
```json
{
  "name": "string (required)",
  "category": "CASH|LITE|TRMS (required)", 
  "desc": "string (required)",
  "features": ["array", "of", "strings"],
  "target": "string",
  "videoUrl": "string (YouTube or direct video)",
  "websiteUrl": "string",
  "logoUrl": "string"
}
```

**Adding New Tool Properties**:
1. Add to JSON schema above
2. Update `createToolCard()` method in JS
3. Update admin form in `templates/admin-page.php`
4. Update save logic in `TTP_Admin::save_tool()`

**Categories**: CASH, LITE, TRMS - defined in `CATEGORY_INFO` object in JavaScript

## File Structure
```
├── treasury-tech-portal.php          # Main plugin file
├── includes/
│   ├── class-treasury-portal.php     # Main plugin class
│   ├── class-ttp-data.php           # Data management
│   ├── class-ttp-rest.php           # REST API
│   ├── class-ttp-admin.php          # Admin interface
│   └── shortcode.php                 # Shortcode template
├── assets/
│   ├── css/treasury-portal.css       # Main stylesheet
│   ├── js/treasury-portal.js         # Main JavaScript
│   └── js/treasury-portal.min.js     # Minified version
├── templates/admin-page.php          # Admin interface
├── .wordpress-com/                   # WordPress.com deployment
└── scripts/                          # Build/test scripts
```

## Common Modification Scenarios

### Adding New Features to Tool Cards
1. **Backend**: Modify `TTP_Admin::save_tool()` to handle new field
2. **Data**: Update vendor properties via API or admin interface
3. **Frontend**: Update `createToolCard()` method to display new feature
4. **Admin**: Add form field to `templates/admin-page.php`

### Modifying Filtering/Search Logic
- **Location**: `TreasuryTechPortal.filterAndDisplayTools()` method
- **Note**: Filters work on `this.filteredTools` array
- **Advanced Filters**: Modify `this.advancedFilters` object and related methods

### Adding New Categories
1. Update `CATEGORY_INFO` object in JavaScript
2. Add to `CATEGORY_TAGS` mapping 
3. Update category sections in `includes/shortcode.php`
4. Add CSS styles with `.category-{name}` pattern

### Video Integration Changes
- **Location**: `showToolModal()` method
- **Supports**: YouTube embeds and direct video files
- **State**: Video times stored in `this.videoTimes` object

### Mobile Responsiveness Modifications
- **Location**: `handleResponsive()` and `isMobile()` methods
- **Breakpoint**: 768px (defined in `isMobile()`)
- **Mobile-specific**: Bottom navigation, swipe gestures, touch handling

## Debugging & Testing

### Frontend Debugging
- Main class available as `window.treasuryTechPortal`
- State stored in class properties (check `treasuryTechPortal.filteredTools`)
- Console logs available in development mode

### Backend Debugging  
- WordPress debug mode: `define('WP_DEBUG', true)`
- Cache clearing: `delete_transient('ttp_tools_cache')`
- Tool data: `TTP_Data::get_all_tools()`

### Testing Changes
1. Run `scripts/test.sh` for PHP validation
2. Test shortcode: `[treasury_portal]`
3. Check mobile responsive behavior
4. Verify WordPress.com deployment constraints

### PHP
- Follow WordPress coding standards
- Use WordPress hooks and APIs
- Sanitize all user input with WordPress functions
- Use proper nonce verification for admin actions
- Exit early with `if (!defined('ABSPATH')) exit;`

### JavaScript
- ES6+ syntax acceptable
- No external dependencies beyond what's available in WordPress
- Use vanilla JavaScript (no jQuery dependency)
- Mobile-first responsive design
- Comprehensive error handling

### CSS
- Use class-based selectors with `.treasury-portal` namespace
- Mobile-first responsive design
- CSS custom properties for theming
- Avoid `!important` declarations

## Development Workflow

### Local Development
1. Clone repository
2. Set up WordPress development environment
3. Activate plugin
4. Use `[treasury_portal]` shortcode to display

### Testing
- Run `scripts/test.sh` for PHP syntax validation
- Test shortcode rendering
- Verify WordPress.com deployment requirements

### Deployment
- Automatic deployment to WordPress.com via GitHub integration
- Build process validates PHP syntax and required files
- Uses WordPress.com's deployment pipeline

## Key Conventions

### Naming
- Plugin prefix: `TTP_` for classes, `ttp_` for functions/options
- CSS classes: `.treasury-portal` namespace
- JavaScript: `TreasuryTechPortal` main class

### Security
- Nonce verification for admin actions
- Sanitization of all user inputs
- Proper capability checks (`manage_options`)
- No direct file access allowed

### Performance
- Transient caching for tool data
- Minified assets in production
- Lazy loading for videos
- Debounced iframe height updates

## WordPress.com Specific
- Plugin auto-activates on deployment
- Uses WordPress.com deployment hooks
- Compatible with WordPress.com hosting restrictions
- No external API dependencies

## Mobile Considerations
- Touch-friendly interface
- Swipe gestures for menu navigation
- Bottom navigation for mobile
- Responsive modals and overlays
- Optimized for small screens

## Video Integration
- Supports both YouTube embeds and direct video files
- Auto-plays videos in modals
- Remembers video playback position
- Responsive video containers

## Data Management
- JSON-based tool storage
- WordPress options API
- Transient caching (1 hour TTL)
- REST API for data access

## Browser Support
- Modern browsers (ES6+ support)
- Mobile browsers (iOS Safari, Chrome Mobile)
- Touch and mouse interaction support
- Fallbacks for video playback issues
