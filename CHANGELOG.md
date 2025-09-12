# Changelog - Treasury Tech Portal

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- AGENTS.md files for AI developer guidance
- Comprehensive documentation (API.md, TROUBLESHOOTING.md, EXAMPLES.md)
- CONTRIBUTING.md with development workflow

### Changed
- Enhanced mobile responsive design
- Improved video integration handling
- Vendor cache refresh now relies solely on `resolve_linked_field()` and
  `TTP_Airbase::resolve_linked_records()` for ID resolution, deprecating
  `rt_airtable_map_ids_to_names()`
- `TTP_Data::get_tools()` no longer applies enabled category limits by default
- REST tools endpoint now explicitly limits results to enabled categories
- Regenerated translation template and updated "Core Capabilities"
  and "Additional Capabilities" strings; translators should update their translations

### Removed
- Slick carousel assets and jQuery dependency for a cleaner front-end

### Fixed
- Modal z-index conflicts with navigation
- Touch gesture handling on mobile devices

## [1.0.2] - 2025-09-11

### Changed
- Regenerated minified assets and updated documentation.
- Bumped version to 1.0.2.

## [1.0.1] - 2025-09-10

### Changed
- Bumped plugin version to 1.0.1
- Updated documentation to reflect new version

## [1.0.0] - 2024-01-XX

### Added
- Initial release of Treasury Tech Portal plugin
- Interactive tool discovery interface with 28+ financial technology tools
- Three-tier categorization system (Cash Tools, TMS-Lite, TRMS)
- Advanced filtering and search functionality
- Shortlist management with drag-and-drop
- Video integration (YouTube embeds and direct video)
- Responsive design with mobile-first approach
- WordPress.com deployment integration
- REST API for tool data access
- Admin interface for tool management
- CSV export functionality for shortlists

### Features
- **Tool Categories**:
  - Cash Tools (10 solutions) - Essential cash visibility platforms
  - TMS-Lite (6 solutions) - Scalable treasury management
  - TRMS (12 solutions) - Advanced enterprise platforms

- **User Interface**:
  - Interactive tool cards with hover effects
  - Modal dialogs for detailed tool information
  - Side menu with advanced filtering options
  - Shortlist menu with drag-and-drop functionality
  - Mobile bottom navigation for small screens
  - Swipe gestures for menu navigation

- **Search & Filtering**:
  - Real-time search across tool names, descriptions, and features
  - Category-based filtering
  - Feature tag filtering
  - Video availability filtering
  - Advanced sorting options (name, category)

- **Technical Features**:
  - WordPress shortcode integration: `[treasury_portal]`
  - REST API endpoint: `/wp-json/ttp/v1/tools`
  - Transient caching for performance
  - WordPress.com deployment automation
  - Mobile-responsive design (768px breakpoint)
  - Cross-browser compatibility

- **Video Integration**:
  - YouTube embed support with API controls
  - Direct video file support (MP4, WebM)
  - Video playback state management
  - Responsive video containers (16:9 aspect ratio)

- **Data Management**:
  - JSON-based tool storage
  - WordPress options API integration
  - Admin interface for tool CRUD operations
  - Data import/export capabilities

### Technical Details
- **PHP Version**: 7.4+
- **WordPress Version**: 5.0+
- **JavaScript**: ES6+ vanilla JavaScript
- **CSS**: Mobile-first responsive design
- **Database**: WordPress options API (no custom tables)
- **Caching**: WordPress transients (1 hour TTL)
- **Deployment**: WordPress.com GitHub integration

### Architecture
- **Backend**: PHP with WordPress integration
- **Frontend**: Vanilla JavaScript with CSS
- **Data Storage**: WordPress options API
- **API**: WordPress REST API
- **Deployment**: WordPress.com automation

### Security
- WordPress nonce verification for admin actions
- Input sanitization using WordPress functions
- Output escaping for XSS prevention
- Capability checks for admin functionality
- No external API dependencies

### Performance
- Transient caching for tool data
- Debounced search input (100ms delay)
- Optimized DOM manipulation
- Lazy loading for video content
- Minimal external dependencies

## Version Format

### Types of Changes
- **Added** for new features
- **Changed** for changes in existing functionality  
- **Deprecated** for soon-to-be removed features
- **Removed** for now removed features
- **Fixed** for any bug fixes
- **Security** for vulnerability fixes

### Version Numbers
- **Major** (X.0.0): Breaking changes, major new features
- **Minor** (1.X.0): New features, backward compatible
- **Patch** (1.0.X): Bug fixes, small improvements

### Example Entry Format
```markdown
## [1.1.0] - 2024-02-XX

### Added
- New tool property: integration difficulty rating
- Enhanced search with autocomplete suggestions
- Bulk tool management in admin interface

### Changed
- Improved mobile navigation with bottom tabs
- Updated video player controls for better UX
- Enhanced shortlist export with additional data fields

### Fixed
- Modal scroll behavior on iOS Safari
- Cache invalidation issues after tool updates
- Touch gesture conflicts with browser navigation

### Security
- Enhanced input validation for admin forms
- Updated nonce verification for AJAX requests
```

## Migration Notes

### From Future Versions
When upgrading, check:
- Tool data structure changes
- New required fields in admin interface
- CSS class name changes
- JavaScript API changes
- WordPress.com deployment requirements

### Backward Compatibility
- Plugin maintains backward compatibility within major versions
- Tool data automatically migrates to new formats
- Legacy shortcode parameters continue to work
- REST API versions are maintained for stability
