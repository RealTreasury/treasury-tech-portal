# Code Examples & Recipes - Treasury Tech Portal

## Common Modification Patterns

### Adding New Tool Properties

#### 1. Add Property to Tool Object
```javascript
// In ../assets/js/treasury-portal.js - Update TREASURY_TOOLS array
{
  "name": "Example Tool",
  "category": "CASH",
  "sub_categories": ["Tool subcategory"],
  "newProperty": "New value here", // ← ADD THIS
  // ... existing properties
}
```

#### 2. Update Admin Form
```php
// In ../templates/admin-page.php - Add form field
<tr>
    <th><label for="tool-new-property">New Property</label></th>
    <td><input name="newProperty" id="tool-new-property" type="text" class="regular-text"></td>
</tr>
```

#### 3. Update Save Logic
```php
// In ../includes/class-ttp-admin.php - TTP_Admin::save_tool()
$tool = [
    'name'        => sanitize_text_field($_POST['name'] ?? ''),
    'category'    => sanitize_text_field($_POST['category'] ?? ''),
    'newProperty' => sanitize_text_field($_POST['newProperty'] ?? ''), // ← ADD THIS
    // ... existing fields
];
```

#### 4. Display in Frontend
```javascript
// In ../assets/js/treasury-portal.js - createToolCard() method
card.innerHTML = `
    <div class="tool-card-content">
        <div class="tool-header">
            <!-- existing content -->
            <div class="new-property">${tool.newProperty || ''}</div> <!-- ← ADD THIS -->
        </div>
    </div>
`;
```

#### 5. Add CSS Styling
```css
/* In ../assets/css/treasury-portal.css */
.treasury-portal .new-property {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 4px;
}
```

### Adding New Filter Options

#### 1. Add Filter State
```javascript
// In ../assets/js/treasury-portal.js - constructor()
this.advancedFilters = {
    features: [],
    hasVideo: false,
    newFilter: false // ← ADD THIS
};
```

#### 2. Add Filter UI
```html
<!-- In ../includes/shortcode.php - side menu filters section -->
<div class="filter-group">
    <div class="checkbox-item">
        <input type="checkbox" id="newFilter">
        <label for="newFilter">New Filter Option</label>
    </div>
</div>
```

#### 3. Add Event Handler
```javascript
// In ../assets/js/treasury-portal.js - setupAdvancedFilters()
const newFilter = document.getElementById('newFilter');
if (newFilter) {
    newFilter.addEventListener('change', (e) => {
        this.advancedFilters.newFilter = e.target.checked;
        this.filterAndDisplayTools();
        this.updateFilterCount();
    });
}
```

#### 4. Update Filter Logic
```javascript
// In ../assets/js/treasury-portal.js - filterAndDisplayTools()
if (this.advancedFilters.newFilter) {
    tools = tools.filter(tool => tool.hasNewFeature === true);
}
```

### Adding New Categories

#### 1. Update Category Data
```javascript
// In ../assets/js/treasury-portal.js - CATEGORY_INFO object
this.CATEGORY_INFO = {
    // ... existing categories
    NEWCAT: {
        name: "New Category Name",
        badge: "Innovative", 
        description: "Description of new category...",
        features: ["Feature 1", "Feature 2"],
        videoUrl: "https://example.com/video.mp4"
    }
};
```

#### 2. Update Category Tags
```javascript
// In ../assets/js/treasury-portal.js - CATEGORY_TAGS object
const newCatTags = ["Tag 1", "Tag 2", "Tag 3"];
this.CATEGORY_TAGS = {
    // ... existing categories
    NEWCAT: newCatTags
};
```

#### 3. Add Category Section HTML
```html
<!-- In ../includes/shortcode.php - add new category section -->
<div class="category-section category-newcat" data-category="NEWCAT">
    <div class="category-header" data-category="NEWCAT">
        <div class="category-info">
            <h2 class="category-title">
                🆕 <span>New Category Name</span>
                <span class="category-badge">Innovative</span>
            </h2>
            <p class="category-description">Description text here...</p>
            <div class="category-tags" id="category-tags-NEWCAT"></div>
        </div>
        <div class="category-count">
            <span class="count-number" id="count-NEWCAT">0</span>
            <span class="count-label">Solutions</span>
        </div>
    </div>
    <div class="tools-grid" id="tools-NEWCAT"></div>
</div>
```

#### 4. Add Filter Tab
```html
<!-- In ../includes/shortcode.php - filter tabs -->
<div class="filter-tabs">
    <!-- existing tabs -->
    <button class="filter-tab" data-category="NEWCAT">New Category</button>
</div>
```

#### 5. Add CSS Styling
```css
/* In ../assets/css/treasury-portal.css */
.treasury-portal .category-newcat .category-badge {
    background: rgba(255, 165, 0, 0.1);
    color: #ff8c00;
    border: 1px solid rgba(255, 165, 0, 0.2);
}

```

### Adding Custom Modal Content

#### 1. Detect Custom Content
```javascript
// In ../assets/js/treasury-portal.js - showToolModal()
if (tool.customFeature) {
    const customSection = document.createElement('div');
    customSection.className = 'feature-section custom-feature-section';
    customSection.innerHTML = `
        <h4>🌟 Custom Feature</h4>
        <div class="custom-content">
            <p>${tool.customFeature}</p>
            <button class="custom-action-btn" data-tool="${tool.name}">Custom Action</button>
        </div>
    `;
    modalBody.appendChild(customSection);
    
    // Add event listener for custom button
    customSection.querySelector('.custom-action-btn').addEventListener('click', (e) => {
        const toolName = e.target.dataset.tool;
        this.handleCustomAction(toolName);
    });
}
```

#### 2. Custom Action Handler
```javascript
// In ../assets/js/treasury-portal.js - add new method
handleCustomAction(toolName) {
    const tool = this.TREASURY_TOOLS.find(t => t.name === toolName);
    if (tool) {
        // Perform custom action
        console.log('Custom action for:', tool.name);
        // Example: Add to shortlist automatically
        if (!this.shortlist.some(item => item.tool.name === toolName)) {
            this.shortlist.push({ tool, notes: 'Added via custom action' });
            this.renderShortlist();
        }
    }
}
```

### Adding New Search Features

#### 1. Enhanced Search Logic
```javascript
// In ../assets/js/treasury-portal.js - replace filterAndDisplayTools() search section
if (this.searchTerm) {
    const searchTerms = this.searchTerm.toLowerCase().split(' ');
    tools = this.TREASURY_TOOLS.filter(tool => {
        const searchableText = [
            tool.name,
            ...(tool.subCategories || []),
            tool.target,
            ...(tool.tags || []),
            ...(tool.features || []),
            tool.category,
            tool.customProperty || '' // Include custom properties
        ].join(' ').toLowerCase();
        
        // All search terms must match (AND logic)
        return searchTerms.every(term => searchableText.includes(term));
    });
}
```

#### 2. Search Suggestions
```javascript
// In ../assets/js/treasury-portal.js - add new method
showSearchSuggestions(inputValue) {
    const suggestions = [];
    const lowercaseInput = inputValue.toLowerCase();
    
    // Collect suggestions from tool names
    this.TREASURY_TOOLS.forEach(tool => {
        if (tool.name.toLowerCase().includes(lowercaseInput)) {
            suggestions.push(tool.name);
        }
        // Add feature suggestions
        (tool.features || []).forEach(feature => {
            if (feature.toLowerCase().includes(lowercaseInput)) {
                suggestions.push(feature);
            }
        });
    });
    
    // Display unique suggestions
    const uniqueSuggestions = [...new Set(suggestions)].slice(0, 5);
    this.displaySearchSuggestions(uniqueSuggestions);
}
```

### Adding Export Features

#### 1. Enhanced CSV Export
```javascript
// In ../assets/js/treasury-portal.js - enhance exportShortlist()
exportShortlist() {
    const data = this.shortlist.map(item => ({
        name: item.tool.name,
        category: item.tool.category,
        description: (item.tool.subCategories || []).join(', '),
        website: item.tool.websiteUrl || '',
        features: (item.tool.features || []).join('; '),
        target: item.tool.target || '',
        notes: item.notes || '',
        dateAdded: new Date().toISOString().split('T')[0] // Add current date
    }));
    
    const csv = this.convertToCSV(data);
    this.downloadCSV(csv, `treasury-shortlist-${new Date().toISOString().split('T')[0]}.csv`);
}
```

#### 2. JSON Export Option
```javascript
// In ../assets/js/treasury-portal.js - add new method
exportShortlistJSON() {
    const data = {
        exportDate: new Date().toISOString(),
        tools: this.shortlist.map(item => ({
            tool: item.tool,
            notes: item.notes,
            position: this.shortlist.indexOf(item) + 1
        }))
    };
    
    const jsonString = JSON.stringify(data, null, 2);
    const blob = new Blob([jsonString], { type: 'application/json' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `treasury-shortlist-${new Date().toISOString().split('T')[0]}.json`;
    link.click();
    URL.revokeObjectURL(link.href);
}
```

### Adding Responsive Breakpoints

#### 1. Custom Breakpoint Detection
```javascript
// In ../assets/js/treasury-portal.js - enhance responsive detection
getViewportSize() {
    const width = window.innerWidth;
    if (width <= 480) return 'mobile-small';
    if (width <= 768) return 'mobile';
    if (width <= 1024) return 'tablet';
    if (width <= 1200) return 'desktop';
    return 'desktop-large';
}

handleResponsiveEnhanced() {
    const viewportSize = this.getViewportSize();
    
    // Apply different behaviors based on viewport
    switch (viewportSize) {
        case 'mobile-small':
            this.applyMobileSmallStyles();
            break;
        case 'mobile':
            this.applyMobileStyles();
            break;
        case 'tablet':
            this.applyTabletStyles();
            break;
        default:
            this.applyDesktopStyles();
    }
}
```

#### 2. Responsive CSS Patterns
```css
/* In ../assets/css/treasury-portal.css */

/* Mobile Small (≤480px) */
@media (max-width: 480px) {
    .treasury-portal .tool-card {
        padding: 12px;
        font-size: 0.9rem;
    }
    
    .treasury-portal .category-header {
        flex-direction: column;
        gap: 1rem;
    }
}

/* Tablet (481px - 1024px) */
@media (min-width: 481px) and (max-width: 1024px) {
    .treasury-portal .tools-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
}

/* Desktop Large (>1200px) */
@media (min-width: 1200px) {
    .treasury-portal .tools-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .treasury-portal .main-content {
        max-width: 1600px;
    }
}
```

### Adding Analytics/Tracking

#### 1. User Interaction Tracking
```javascript
// In ../assets/js/treasury-portal.js - add tracking methods
trackEvent(category, action, label = '', value = 0) {
    // Google Analytics 4 example
    if (typeof gtag !== 'undefined') {
        gtag('event', action, {
            event_category: category,
            event_label: label,
            value: value
        });
    }
    
    // Console logging for development
    console.log('Track:', { category, action, label, value });
}

// Track tool interactions
showToolModal(tool) {
    this.trackEvent('Tool', 'view_details', tool.name);
    // ... existing modal code
}

// Track shortlist actions
addToShortlist(tool) {
    this.trackEvent('Shortlist', 'add_tool', tool.name);
    // ... existing shortlist code
}

// Track search usage
filterAndDisplayTools() {
    if (this.searchTerm) {
        this.trackEvent('Search', 'search_tools', this.searchTerm);
    }
    // ... existing filter code
}
```

#### 2. Performance Monitoring
```javascript
// In ../assets/js/treasury-portal.js - add performance tracking
monitorPerformance() {
    // Track page load time
    window.addEventListener('load', () => {
        const loadTime = performance.now();
        this.trackEvent('Performance', 'page_load_time', '', Math.round(loadTime));
    });
    
    // Track filter performance
    const originalFilter = this.filterAndDisplayTools;
    this.filterAndDisplayTools = function() {
        const startTime = performance.now();
        originalFilter.call(this);
        const duration = performance.now() - startTime;
        
        if (duration > 100) { // Log slow filters
            console.warn('Slow filter operation:', duration + 'ms');
            this.trackEvent('Performance', 'slow_filter', '', Math.round(duration));
        }
    };
}
```

### Adding Custom Themes

#### 1. Theme System
```javascript
// In ../assets/js/treasury-portal.js - add theme management
applyTheme(themeName = 'default') {
    const themes = {
        default: {
            '--primary-color': '#7216f4',
            '--secondary-color': '#8f47f6',
            '--background-color': '#f8f9fa',
            '--text-color': '#281345'
        },
        dark: {
            '--primary-color': '#9333ea',
            '--secondary-color': '#a855f7',
            '--background-color': '#1f2937',
            '--text-color': '#f9fafb'
        },
        corporate: {
            '--primary-color': '#1e40af',
            '--secondary-color': '#3b82f6',
            '--background-color': '#f8fafc',
            '--text-color': '#1e293b'
        }
    };
    
    const theme = themes[themeName] || themes.default;
    const portalElement = document.querySelector('.treasury-portal');
    
    if (portalElement) {
        Object.entries(theme).forEach(([property, value]) => {
            portalElement.style.setProperty(property, value);
        });
    }
}
```

#### 2. CSS Variables Setup
```css
/* In ../assets/css/treasury-portal.css - add CSS variables */
.treasury-portal {
    --primary-color: #7216f4;
    --secondary-color: #8f47f6;
    --background-color: #f8f9fa;
    --text-color: #281345;
    --border-color: #e5e7eb;
    --card-background: #ffffff;
}

/* Use variables throughout CSS */
.treasury-portal .tool-card {
    background: var(--card-background);
    border: 1px solid var(--border-color);
    color: var(--text-color);
}

.treasury-portal .filter-tab.active {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
}
```

These examples provide concrete patterns that AI developers can follow and modify for specific use cases. Each example includes the complete code changes needed across the different files in the project.
