# API Documentation - Treasury Tech Portal

## Data Structures

### Tool Object Schema
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

### Category Information Schema
```javascript
{
  "name": "string",
  "badge": "string", 
  "description": "string",
  "features": ["array", "of", "feature", "strings"],
  "videoUrl": "string",
  "videoPoster": "string"
}
```

### Tool Categories
- **CASH**: Cash Tools - Essential cash visibility and forecasting
- **LITE**: TMS-Lite - Scalable treasury management  
- **TRMS**: Treasury & Risk Management Systems - Advanced enterprise platforms

## REST API Endpoints

### Get Tools
```
GET /wp-json/ttp/v1/tools
```

#### Parameters
- `category` (string): Filter by category (CASH, LITE, TRMS, or ALL)
- `search` (string): Search term for name, description, features
- `has_video` (boolean): Filter tools that have video demonstrations
- `per_page` (integer): Number of tools per page (default: all)
- `page` (integer): Page number for pagination (default: 1)

#### Response Format
```json
[
  {
    "name": "Kyriba",
    "category": "TRMS", 
    "desc": "Market-leading cloud treasury platform...",
    "features": ["AI-driven cash forecasting", "Real-time risk analytics"],
    "target": "Large enterprises and multinational corporations",
    "videoUrl": "https://realtreasury.com/kyriba-06-2025/?embed=1",
    "websiteUrl": "https://www.kyriba.com/...",
    "logoUrl": "https://realtreasury.com/wp-content/uploads/2025/06/Kyriba.png"
  }
]
```

#### Example Requests
```javascript
// Get all tools
fetch('/wp-json/ttp/v1/tools')

// Get CASH category tools only
fetch('/wp-json/ttp/v1/tools?category=CASH')

// Search for AI-related tools
fetch('/wp-json/ttp/v1/tools?search=AI')

// Get tools with videos, paginated
fetch('/wp-json/ttp/v1/tools?has_video=1&per_page=10&page=1')
```


### Get Vendors
```
GET /wp-json/ttp/v1/vendors
```

#### Response Format
Array of vendor objects retrieved from Airbase.

#### Example Request
```
fetch('/wp-json/ttp/v1/vendors')
```

## JavaScript API (Frontend)

### Main Class: TreasuryTechPortal

#### Constructor Data
```javascript
class TreasuryTechPortal {
  constructor() {
    this.TREASURY_TOOLS = [];      // Array of tool objects
    this.CATEGORY_INFO = {};       // Category metadata
    this.CATEGORY_TAGS = {};       // Feature tags by category
    this.currentFilter = 'ALL';    // Current category filter
    this.filteredTools = [];       // Currently displayed tools
    this.shortlist = [];           // User's shortlisted tools
    // ... other state properties
  }
}
```

#### Key Methods
```javascript
// Data & Filtering
.filterAndDisplayTools()           // Apply current filters and display
.get_tools(args)                   // Backend: Server-side filtering
.updateVisibleCounts()             // Update category counters

// UI Rendering  
.createToolCard(tool, category)    // Generate tool card HTML
.showToolModal(tool)               // Display tool details modal
.displayFilteredTools()            // Render filtered results

// User Interactions
.setupSideMenu()                   // Initialize filter menu
.setupShortlistMenu()              // Initialize shortlist functionality  
.handleResponsive()                // Mobile/desktop switching

// State Management
.openSideMenu() / .closeSideMenu()
.openShortlistMenu() / .closeShortlistMenu()
.addToShortlist(tool) / .removeFromShortlist(tool)
```

#### State Properties
```javascript
// Filtering State
this.currentFilter              // 'ALL', 'CASH', 'LITE', 'TRMS'
this.searchTerm                 // Current search string
this.advancedFilters = {
  features: [],                 // Selected feature tags
  hasVideo: false              // Video filter toggle
}

// UI State  
this.currentSort               // 'name' or 'category'
this.currentView               // 'grid' or 'list'
this.groupByCategory           // Boolean for category grouping
this.sideMenuOpen              // Boolean for menu state
this.shortlistMenuOpen         // Boolean for shortlist state

// User Data
this.shortlist = [             // Array of shortlisted tools
  {
    tool: toolObject,          // Reference to tool
    notes: "user notes"        // User's notes for this tool
  }
]
```

## PHP Backend API

### TTP_Data Class Methods

#### Data Retrieval
```php
// Get all tools with caching
TTP_Data::get_all_tools()

// Get filtered tools
TTP_Data::get_tools([
  'category' => 'CASH',
  'search' => 'AI',
  'has_video' => true,
  'per_page' => 10,
  'page' => 1
])

// Save tools and clear cache
TTP_Data::save_tools($tools_array)
```

#### Cache Management
```php
// Constants
TTP_Data::OPTION_KEY = 'ttp_tools'      // WordPress option key
TTP_Data::CACHE_KEY = 'ttp_tools_cache'  // Transient cache key  
TTP_Data::CACHE_TTL = HOUR_IN_SECONDS    // Cache duration

// Clear cache manually
delete_transient('ttp_tools_cache')
```

### TTP_Admin Class Methods

#### Admin Interface
```php
// Register admin menu
TTP_Admin::register_menu()

// Handle tool save
TTP_Admin::save_tool()         // Processes form submission

// Handle tool deletion  
TTP_Admin::delete_tool()       // Removes tool by index

// Render admin page
TTP_Admin::render_page()       // Displays admin interface
```

#### Security Patterns
```php
// Required for admin actions
check_admin_referer('ttp_save_tool');
current_user_can('manage_options');

// Input sanitization
sanitize_text_field($input);
sanitize_textarea_field($input);
esc_url_raw($url);
```

## Event System

### Frontend Events (JavaScript)

#### Tool Card Events
```javascript
// Tool card click -> show modal
card.addEventListener('click', () => this.showToolModal(tool));

// Drag start -> open shortlist menu
card.addEventListener('dragstart', () => this.openShortlistMenu());

// Touch interactions for mobile
card.addEventListener('touchstart', () => {...});
```

#### Filter Events
```javascript
// Search input
searchInput.addEventListener('input', () => this.filterAndDisplayTools());

// Category tabs
tab.addEventListener('click', () => {
  this.currentFilter = tab.dataset.category;
  this.filterAndDisplayTools();
});

// Advanced filters
checkbox.addEventListener('change', () => {
  this.updateFeatureFilters();
  this.filterAndDisplayTools();
});
```

#### Menu Events
```javascript
// Side menu toggle
menuToggle.addEventListener('click', () => this.toggleSideMenu());

// Shortlist operations
addButton.addEventListener('click', () => this.addToShortlist(tool));
removeButton.addEventListener('click', () => this.removeFromShortlist(tool));

// Export shortlist
exportBtn.addEventListener('click', () => this.exportShortlist());
```

### Backend Events (WordPress Hooks)

#### Plugin Hooks
```php
// Plugin initialization
add_action('init', ['TTP_Rest', 'init']);
add_action('init', ['TTP_Admin', 'init']);

// Admin interface
add_action('admin_menu', ['TTP_Admin', 'register_menu']);

// Admin actions
add_action('admin_post_ttp_save_tool', ['TTP_Admin', 'save_tool']);
add_action('admin_post_ttp_delete_tool', ['TTP_Admin', 'delete_tool']);
```

#### REST API Hooks
```php
// Register REST routes
add_action('rest_api_init', ['TTP_Rest', 'register_routes']);
```

## Data Flow

### Frontend Data Flow
```
User Interaction → 
JavaScript Event Handler → 
Update State Properties → 
Call filterAndDisplayTools() → 
Update DOM Display → 
Post Height to Parent (if iframe)
```

### Backend Data Flow  
```
WordPress Load → 
Plugin Init → 
Shortcode Render → 
Enqueue Assets → 
REST API Available → 
Frontend JavaScript Loads → 
AJAX/REST Calls for Data
```

### Shortlist Data Flow
```
Drag Tool Card → 
JavaScript Capture → 
Update this.shortlist Array → 
Re-render Shortlist Menu → 
Local Storage (NOT used - in memory only) → 
Export to CSV (user initiated)
```

## Integration Points

### WordPress Integration
- **Options API**: Tool data storage (`ttp_tools` option)
- **Transients**: Caching layer (`ttp_tools_cache` transient)
- **REST API**: Frontend data access (`/wp-json/ttp/v1/tools`)
- **Shortcodes**: Page embedding (`[treasury_portal]`)
- **Admin Interface**: Tool management (admin menu)

### External Integrations
- **YouTube API**: Video embedding with enablejsapi=1
- **Direct Video**: HTML5 video elements
- **Iframe Communication**: Height posting for embedded contexts
- **CSV Export**: Client-side data export functionality

### Mobile Integration
- **Touch Events**: Swipe gestures for menu navigation
- **Responsive Design**: 768px breakpoint for mobile/desktop
- **Bottom Navigation**: Mobile-specific navigation bar
- **Viewport**: Optimized for mobile browsing
