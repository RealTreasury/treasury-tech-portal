# AGENTS - Assets Directory

## Frontend Assets Overview
This directory contains all frontend assets for the Treasury Tech Portal plugin.

## File Structure
```
assets/
├── css/
│   └── treasury-portal.css      # Main stylesheet (modify this)
└── js/
    ├── treasury-portal.js       # Main JavaScript (modify this)
    └── treasury-portal.min.js   # Minified version (auto-generated)
```

## CSS Modifications (`css/treasury-portal.css`)

### CSS Architecture
- **Namespace**: All styles prefixed with `.treasury-portal`
- **Mobile-first**: Use `@media (max-width: 768px)` for mobile overrides
- **CSS Variables**: Use custom properties for consistent theming
- **BEM-like naming**: `.treasury-portal .component-name__element--modifier`

### Key CSS Sections to Modify
1. **Tool Cards**: `.tool-card` and related classes
2. **Modals**: `.ttp-modal` and `.ttp-modal-content`
3. **Side Menu**: `.side-menu` and `.shortlist-menu`
4. **Mobile Navigation**: `.bottom-nav` and responsive breakpoints
5. **Category Sections**: `.category-section` and `.category-header`

### Common CSS Modification Patterns
```css
/* Adding new tool card features */
.treasury-portal .tool-card .new-feature {
  /* styles here */
}

/* Mobile-specific overrides */
@media (max-width: 768px) {
  .treasury-portal .component {
    /* mobile styles */
  }
}

/* Category-specific styling */
.treasury-portal .category-cash .special-feature {
  /* styles for cash tools category */
}
```

### CSS Organization
- **Reset/Base**: Global resets and base typography
- **Layout**: Grid, flexbox, positioning utilities  
- **Components**: Individual UI components (cards, modals, menus)
- **Responsive**: Mobile-specific overrides
- **Utilities**: Helper classes and animations

## JavaScript Modifications (`js/treasury-portal.js`)

### Main Class: `TreasuryTechPortal`
```javascript
class TreasuryTechPortal {
  constructor() {
    // Tool data and state management
    this.TREASURY_TOOLS = [...];
    this.CATEGORY_INFO = {...};
    this.currentFilter = 'ALL';
    this.filteredTools = [];
    this.shortlist = [];
    // ... initialization
  }
}
```

### Key Methods for UI Modifications

#### Tool Rendering & Interaction
- `createToolCard(tool, category)` - **MODIFY**: Tool card HTML and event handling
- `showToolModal(tool)` - **MODIFY**: Modal content and video integration
- `filterAndDisplayTools()` - **MODIFY**: Search/filter logic and display

#### Menu & Navigation  
- `setupSideMenu()` - **MODIFY**: Filter menu functionality
- `setupShortlistMenu()` - **MODIFY**: Shortlist drag-drop and management
- `setupBottomNav()` - **MODIFY**: Mobile navigation

#### Responsive Behavior
- `handleResponsive()` - **MODIFY**: Mobile/desktop switching logic
- `isMobile()` - **MODIFY**: Breakpoint detection (currently 768px)

#### State Management
- `this.currentFilter` - Current category filter
- `this.filteredTools` - Currently displayed tools
- `this.shortlist` - User's shortlisted tools
- `this.sideMenuOpen` / `this.shortlistMenuOpen` - Menu states

### Adding New UI Features

#### 1. New Tool Card Elements
```javascript
// In createToolCard() method, modify the innerHTML:
card.innerHTML = `
  <div class="tool-card-content">
    <!-- existing content -->
    <div class="new-feature">${tool.newProperty}</div>
  </div>
`;
```

#### 2. New Filter Options
```javascript
// Add to setupAdvancedFilters() method:
const newFilter = document.getElementById('newFilter');
newFilter.addEventListener('change', () => {
  this.advancedFilters.newFilter = e.target.checked;
  this.filterAndDisplayTools();
});
```

#### 3. New Modal Content
```javascript
// In showToolModal() method:
const newSection = document.createElement('div');
newSection.className = 'feature-section';
newSection.innerHTML = `<h4>New Feature</h4><p>${tool.newFeature}</p>`;
modalBody.appendChild(newSection);
```

### Event Handling Patterns
```javascript
// Click handlers
element.addEventListener('click', (e) => {
  e.stopPropagation(); // Prevent event bubbling if needed
  this.methodName();
});

// Mobile touch handlers  
element.addEventListener('touchstart', (e) => {
  // Touch-specific logic
}, { passive: false });

// Responsive event cleanup
window.addEventListener('resize', () => this.handleResponsive());
```

### Video Integration
- **YouTube**: Embedded iframes with enablejsapi=1
- **Direct Video**: HTML5 video elements
- **State Management**: `this.videoTimes` object stores playback positions
- **Responsive**: 16:9 aspect ratio containers

### Mobile-Specific JavaScript
- **Touch Events**: Swipe gestures for menu closing
- **Bottom Navigation**: Mobile-only navigation bar
- **Menu Behavior**: Full-screen overlays on mobile
- **Responsive**: Automatic feature enabling/disabling

### Performance Considerations
- **Debounced Functions**: Search input, height updates
- **Event Cleanup**: Remove listeners when components unmount
- **Memory Management**: Clear video states, remove DOM references
- **Efficient Rendering**: Minimal DOM manipulation in loops

### WordPress.com Constraints
- **No localStorage**: Use in-memory state only
- **No external CDNs**: All code must be self-contained
- **Iframe Environment**: Special height posting for embeds
- **No Build Process**: All code must work as-written

### Debugging Frontend Issues
```javascript
// Access main instance
window.treasuryTechPortal

// Check current state
treasuryTechPortal.filteredTools
treasuryTechPortal.currentFilter
treasuryTechPortal.shortlist

// Debug tool data
treasuryTechPortal.TREASURY_TOOLS

// Check responsive state
treasuryTechPortal.isMobile()
```

## Common Modification Scenarios

### Adding New Tool Properties to UI
1. Update tool card rendering in `createToolCard()`
2. Add to modal display in `showToolModal()`  
3. Include in search/filter logic if needed
4. Add CSS styles for new elements

### Modifying Visual Design
1. Update CSS classes in `treasury-portal.css`
2. Test across mobile and desktop breakpoints
3. Verify modal and menu styling
4. Check video integration styling

### Adding New Interactions
1. Add event listeners in appropriate setup methods
2. Create handler methods following naming convention
3. Update state management if needed
4. Test mobile touch interactions

### Performance Optimization
1. Debounce frequently called functions
2. Minimize DOM queries (cache selectors)
3. Use event delegation for dynamic content
4. Profile memory usage for large datasets
