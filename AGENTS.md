# AGENTS - Treasury Tech Portal

## Project Overview
A WordPress plugin that provides an interactive platform for discovering and comparing treasury technology solutions. The plugin displays financial technology tools across three categories: Cash Tools, TMS-Lite, and Enterprise TRMS platforms.

## Architecture

### Backend (PHP)
- **Main Plugin File**: `treasury-tech-portal.php` - WordPress plugin bootstrap
- **Core Classes**:
  - `Treasury_Tech_Portal` - Main plugin controller
  - `TTP_Data` - Data management with caching
  - `TTP_Rest` - REST API endpoints
  - `TTP_Admin` - WordPress admin interface
- **WordPress Integration**: Uses WordPress hooks, shortcodes, and REST API

### Frontend (JavaScript/CSS)
- **Main Class**: `TreasuryTechPortal` - Handles all UI interactions
- **Key Features**:
  - Interactive tool cards with drag-and-drop
  - Modal dialogs for detailed tool information
  - Advanced filtering and search
  - Responsive design with mobile support
  - Video integration (YouTube embeds and direct video)
  - Shortlist functionality with export

### Data Structure
Tools are stored as JSON objects with properties:
- `name`, `category`, `desc`, `features`, `target`
- `videoUrl`, `websiteUrl`, `logoUrl`
- Categories: CASH, LITE, TRMS

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
├── data/tools.json                   # Default tool data
├── templates/admin-page.php          # Admin interface
├── .wordpress-com/                   # WordPress.com deployment
└── scripts/                          # Build/test scripts
```

## Coding Standards

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
