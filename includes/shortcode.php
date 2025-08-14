<?php
namespace TreasuryTechPortal;

// Exit if accessed directly
if (!defined("ABSPATH")) exit;

use function \get_option;
use function \wp_http_validate_url;
use function \esc_url;

$video_url = defined('TTP_INTRO_VIDEO_URL')
    ? TTP_INTRO_VIDEO_URL
    : get_option('ttp_intro_video_url', '');

if ($video_url && !wp_http_validate_url($video_url)) {
    $video_url = '';
}
?>
<div class="treasury-portal" data-video-src="<?php echo esc_url($video_url); ?>">
    <div class="container">
        <button class="external-menu-toggle" id="externalMenuToggle">Menu</button>
        <button class="external-shortlist-toggle" id="externalShortlistToggle" aria-label="Open shortlist menu" title="Shortlist">Shortlist</button>
        <!-- Loading Screen -->
        <div class="loading" id="loadingScreen" style="display: none; text-align: center; padding: 40px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
            <div class="loading-logo" style="font-size: 3rem; margin-bottom: 1rem;">💼</div>
            <h1 style="color: #281345; font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">Treasury Tech Portal</h1>
            <p style="color: #7e7e7e; font-size: 1rem;">Loading financial tools ecosystem...</p>
        </div>

        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div class="logo-section">
                    <div class="title-section">
                        <h1>Treasury Tech Portal</h1>
                        <p class="subtitle">Discover the complete treasury tech landscape</p>
                    </div>
                </div>

                <div class="intro-video-target"></div>

                <div class="stats-bar">
                    <div class="stat-card">
                        <div class="stat-number" id="totalTools">28</div>
                        <div class="stat-label">Tools</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">3</div>
                        <div class="stat-label">Categories</div>
                    </div>
                </div>

                <div class="filter-tabs">
                    <button class="filter-tab active" data-category="ALL">All</button>
                    <button class="filter-tab" data-category="CASH">Cash Tools</button>
                    <button class="filter-tab" data-category="LITE">TMS-Lite</button>
                    <button class="filter-tab" data-category="TRMS">TRMS</button>
                </div>
        </div>
    </div>
    <div class="side-menu-overlay" id="sideMenuOverlay"></div>
    <div class="side-menu" id="sideMenu">
            <div class="side-menu-header">
                <div></div>
                <h3 class="side-menu-title">Menu</h3>
                <button class="menu-toggle" id="sideMenuToggle">
                    <span class="icon"></span>
                </button>
            </div>
            <div class="side-menu-content">
                <div class="menu-section">
                    <div class="menu-section-header">Search</div>
                    <div class="menu-section-content">
                        <div class="search-container">
                            <span class="search-icon">🔍</span>
                            <input type="text" id="searchInput" class="search-input" placeholder="Search vendors, features, or tags...">
                            <button class="search-clear" id="searchClear" style="display: none;">×</button>
                        </div>
                    </div>
                </div>

                <div class="menu-section">
                    <div class="menu-section-header">Filters</div>
                    <div class="menu-section-content">
                        <div class="filter-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="hasVideoFilter">
                                <label for="hasVideoFilter">Has Video</label>
                            </div>
                        </div>
                        <div class="filter-group">
                            <span class="filter-label">Tags</span>
                            <div class="search-container tag-search">
                                <input type="text" id="tagSearchInput" class="search-input" placeholder="Search tags...">
                                <button class="search-clear" id="tagSearchClear" style="display: none;">×</button>
                            </div>
                            <div class="checkbox-group" id="tagFilters"></div>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label" for="sortFilter">Sort By</label>
                            <select id="sortFilter" class="filter-select">
                                <option value="name">Name</option>
                                <option value="category">Category</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="menu-section">
                    <div class="menu-section-header">View Options</div>
                    <div class="menu-section-content">
                        <div class="view-options">
                            <div class="view-option active" data-view="grid">Grid</div>
                            <div class="view-option" data-view="list">List</div>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label" for="groupByFilter">Group Vendors</label>
                            <select id="groupByFilter" class="filter-select">
                                <option value="category" selected>By Category</option>
                                <option value="none">No Grouping</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="menu-section">
                    <div class="menu-section-header">Quick Actions</div>
                    <div class="menu-section-content">
                        <div class="action-buttons">
                            <button class="action-btn secondary" id="clearAllFilters">Clear Filters</button>
                            <button class="action-btn secondary" id="resetToDefaults">Reset Defaults</button>
                            <button class="action-btn primary" id="applyFilters">Apply &amp; Close</button>
                        </div>
                    </div>
                </div>
            </div>
    </div>

    <div class="shortlist-menu-overlay" id="shortlistMenuOverlay"></div>
    <div class="shortlist-menu" id="shortlistMenu">
        <div class="shortlist-menu-header">
            <div></div>
            <h3 class="shortlist-menu-title">Shortlist</h3>
            <div style="width: 36px;"></div>
        </div>
        <div class="shortlist-menu-content">
            <div class="shortlist-section">
                <div class="menu-section-content">
                    <div id="shortlistContainer" class="shortlist-container empty">
                        <p id="shortlistEmptyMessage" class="shortlist-empty-message">Drag vendor cards here or click to add.</p>
                    </div>

                    <!-- Always visible tool picker -->
                    <div class="tool-picker permanent-picker" id="permanentToolPicker">
                        <button type="button" class="tool-picker-button">Add a Tool</button>
                        <div class="tool-picker-dropdown" id="permanentDropdown" style="display: none;">
                            <input type="text" placeholder="Search tools..." class="tool-picker-search" id="permanentSearch">
                            <ul class="tool-picker-options" id="permanentList">
                                <!-- Options populated by JavaScript -->
                            </ul>
                        </div>
                    </div>
                    <div class="tips-section">
                        <h3>Tips for Building a Tech Vendor Shortlist</h3>
                        <ul>
                            <li>Define must-have features and your budget early on.</li>
                            <li>Check how well each tool integrates with current systems.</li>
                            <li>Ask for references or case studies from similar companies.</li>
                            <li>Consider future scalability and support options.</li>
                        </ul>
                    </div>
                    <button class="action-btn secondary" id="clearShortlist" style="margin-top:12px;">Clear Shortlist</button>
                    <button class="action-btn primary" id="exportShortlistBtn" style="margin-top:12px;" disabled>Export Shortlist</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <!-- No Results Message -->
            <div class="no-results" id="noResults" style="display: none;">
                <h3>No vendors found</h3>
                <p>Try adjusting your search terms or filters</p>
            </div>
            <div class="tools-grid" id="listViewContainer" style="display:none;"></div>

            <!-- Cash Tools Section -->
            <div class="category-section category-cash" data-category="CASH" style="display: block;">
                <div class="category-header" data-category="CASH">
                    <div class="category-info">
                        <h2 class="category-title">
                            💰 <span>Cash Tools</span>
                            <span class="category-badge">Essential</span>
                        </h2>
                        <p class="category-description">
                            Built in the cloud for modern finance, these cash visibility and forecasting platforms combine real-time connectivity, intelligent analytics, and automation to elevate liquidity and cash flow planning.
                        </p>
                        <div class="category-tags" id="category-tags-CASH"></div>
                    </div>
                    <div class="category-count">
                        <span class="count-number" id="count-CASH">10</span>
                        <span class="count-label">Solutions</span>
                    </div>
                </div>
                <div class="tools-grid" id="tools-CASH">
                    <!-- Tools will be populated by JavaScript -->
                </div>
            </div>

            <!-- TMS-Lite Section -->
            <div class="category-section category-lite" data-category="LITE" style="display: block;">
                <div class="category-header" data-category="LITE">
                    <div class="category-info">
                        <h2 class="category-title">
                            ⚡ <span>Treasury Management System Lite (TMS-Lite)</span>
                            <span class="category-badge">Scalable</span>
                        </h2>
                        <p class="category-description">
                            Built for more than visibility, these treasury platforms support treasury payments, detailed cash positioning, and growing complexity—without jumping to a full enterprise solution.
                        </p>
                        <div class="category-tags" id="category-tags-LITE"></div>
                    </div>
                    <div class="category-count">
                        <span class="count-number" id="count-LITE">6</span>
                        <span class="count-label">Solutions</span>
                    </div>
                </div>
                <div class="tools-grid" id="tools-LITE">
                    <!-- Tools will be populated by JavaScript -->
                </div>
            </div>

            <!-- Enterprise Section -->
            <div class="category-section category-enterprise" data-category="TRMS" style="display: block;">
                <div class="category-header" data-category="TRMS">
                    <div class="category-info">
                        <h2 class="category-title">
                            🏢 <span>Treasury & Risk Management Systems (TRMS)</span>
                            <span class="category-badge">Advanced</span>
                        </h2>
                        <p class="category-description">
                            Full-scale treasury management platforms for complex treasury operations. These solutions handle complex derivatives, multi-entity consolidation, advanced risk analytics, and comprehensive regulatory compliance. Built for organizations managing billions in assets with sophisticated financial operations.
                        </p>
                        <div class="category-tags" id="category-tags-TRMS"></div>
                    </div>
                    <div class="category-count">
                        <span class="count-number" id="count-TRMS">11</span>
                        <span class="count-label">Solutions</span>
                    </div>
                </div>
                <div class="tools-grid" id="tools-TRMS">
                    <!-- Tools will be populated by JavaScript -->
                </div>
        </div>
    </div>

    <!-- Tool Details Modal -->
    <div class="ttp-modal" id="toolModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="ttp-modal-content" tabindex="-1">
                <div class="modal-header">
                    <div class="modal-title-group">
                        <h3 class="modal-title" id="modalTitle"></h3>
                        <img id="modalToolLogo" class="modal-tool-logo" alt="" style="display: none;">
                    </div>
                    <div class="modal-header-actions">
                        <a id="modalWebsiteLink" href="#" target="_blank" rel="noopener noreferrer" class="website-link--modal" style="display: none;">Website</a>
                        <button class="modal-close" id="modalClose">×</button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="feature-section">
                        <h4>🎯 Overview</h4>
                        <p id="modalDescription"></p>
                    </div>
                    <div class="feature-section">
                        <h4>🏷️ Tags</h4>
                        <div id="modalTags" class="tool-tags"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Details Modal -->
        <div class="ttp-modal" id="categoryModal" role="dialog" aria-modal="true" aria-labelledby="categoryModalTitle">
            <div class="ttp-modal-content" tabindex="-1">
                <div class="modal-header">
                    <h3 class="modal-title" id="categoryModalTitle"></h3>
                     <div class="modal-header-actions">
                        <button class="modal-close" id="categoryModalClose">×</button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="feature-section">
                        <h4>📋 Description</h4>
                        <p id="categoryModalDescription"></p>
                    </div>
                    <div class="feature-section">
                        <h4>🎯 Key Characteristics</h4>
                        <ul class="feature-list" id="categoryModalFeatures">
                            <!-- Features will be populated by JavaScript -->
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="bottom-nav" id="bottomNav">
        <button id="bottomSearch"><span class="icon">🔍</span> Search</button>
        <button id="bottomShortlist"><span class="icon">📝</span> Shortlist</button>
    </div>

</div>

