        const EMBED_ORIGIN = 'https://realtreasury.com';
        let treasuryTechPortal;
        // Root element for the portal; abort setup if not found.
        const portalRoot = document.querySelector('.treasury-portal');

        if (!portalRoot) {
            console.warn('TreasuryTechPortal: .treasury-portal element not found. Aborting initialization.');
        }

        function postHeight() {
            if (window.parent !== window) {
                const h = Math.max(
                    document.documentElement.scrollHeight,
                    document.documentElement.offsetHeight,
                    document.body.scrollHeight,
                    document.body.offsetHeight,
                    window.innerHeight
                );
                window.parent.postMessage({ 
                    type: "treasury-height",
                    height: h + 100
                }, "*");
                try {
                    window.parent.postMessage({ 
                        type: "treasury-height",
                        height: h + 100
                    }, EMBED_ORIGIN);
                } catch(e) {
                    /* Ignore if origin mismatch */
                }
            }
        }

function debounce(fn, delay) {
    let timer;
    return function() {
        clearTimeout(timer);
        timer = setTimeout(fn, delay);
    };
}

const debouncedPostHeight = debounce(postHeight, 100);

function containsRecordIds(value) {
    if (Array.isArray(value)) {
        return value.some(containsRecordIds);
    }
    if (value && typeof value === 'object') {
        return Object.values(value).some(containsRecordIds);
    }
    return typeof value === 'string' && /^rec[0-9a-z]/i.test(value);
}

// Post height more frequently and reliably
window.addEventListener('load', () => {
    setTimeout(postHeight, 100);
    setTimeout(postHeight, 500);
    setTimeout(postHeight, 1000);
});
window.addEventListener('resize', debouncedPostHeight);

if (portalRoot) {
    new ResizeObserver(() => {
        debouncedPostHeight();
    }).observe(portalRoot);

    new MutationObserver(() => {
        debouncedPostHeight();
    }).observe(portalRoot, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['style', 'class']
    });
}

document.addEventListener('DOMContentLoaded', async () => {
    if (!portalRoot) {
        // Skip initialization when portal root is absent.
        return;
    }
    const containerEl = document.querySelector('.treasury-portal .container');
    if (!window.TTP_DATA) {
        if (portalRoot) {
            const banner = document.createElement('div');
            banner.className = 'error-banner';
            banner.textContent = 'Configuration data is unavailable.';
            banner.style.cssText = 'padding:1rem;margin:1rem 0;background:#f8d7da;color:#721c24;border:1px solid #f5c2c7;border-radius:4px;';
            portalRoot.prepend(banner);
        }
        if (containerEl) containerEl.classList.add('loaded');
        console.warn('TreasuryTechPortal: window.TTP_DATA is missing');
        return;
    }

    treasuryTechPortal = new TreasuryTechPortal();

    // Ensure iframe height is set after content loads
    setTimeout(() => {
        if (typeof postHeight === 'function') {
            postHeight();
        }
    }, 1500);
    
    // Handle window resize to properly enable/disable mobile features
    window.addEventListener('resize', () => {
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile) {
            // Close any open menus on mobile
            if (treasuryTechPortal.sideMenuOpen) treasuryTechPortal.closeSideMenu();
            if (treasuryTechPortal.shortlistMenuOpen) treasuryTechPortal.closeShortlistMenu();
        } else {
            // Re-enable sidebar functionality on desktop
            treasuryTechPortal.renderShortlist();
        }
        
        // Update height after resize
        setTimeout(() => {
            if (typeof postHeight === 'function') {
                postHeight();
            }
        }, 200);
    });
    const navDropdowns = document.querySelectorAll(".rt-nav-item");
    const treasuryPortal = window.treasuryTechPortal;

    navDropdowns.forEach(item => {
        item.addEventListener("click", function() {
            if (treasuryPortal) {
                if (treasuryPortal.sideMenuOpen) {
                    treasuryPortal.closeSideMenu();
                }
                if (treasuryPortal.shortlistMenuOpen) {
                    treasuryPortal.closeShortlistMenu();
                }
            }
        });
    });

    document.addEventListener("click", function(e) {
        if (e.target.closest(".external-menu-toggle") ||
            e.target.closest(".external-shortlist-toggle")) {
            document.querySelectorAll(".rt-nav-item.active").forEach(item => {
                item.classList.remove("active");
            });
        }
    });

    const params = new URLSearchParams(window.location.search);
    const toolName = params.get('tool');
    if (toolName && document.cookie.includes('portal_access_token=')) {
        try {
            await treasuryTechPortal.toolsLoaded;
            const toolObj = treasuryTechPortal.TREASURY_TOOLS.find(t =>
                t.name.toLowerCase() === decodeURIComponent(toolName).toLowerCase());
            if (toolObj) {
                setTimeout(() => treasuryTechPortal.showToolModal(toolObj), 500);
            }
        } catch (err) {
            console.error('Failed to load tools for deep link:', err);
        }
    }

});
        class TreasuryTechPortal {
            constructor() {
                this.TREASURY_TOOLS = [];
                this.toolsLoaded = this.fetchTools();

                // Category information with videos
                this.CATEGORY_INFO = {
                    CASH: {
                        name: "Cash Tools",
                        badge: "Essential",
                        description: "Cash Tools are the essential first step for treasury teams moving away from manual spreadsheets. These platforms provide a single, unified view of bank balances and transactions through direct bank connections (via API or files). They excel at providing clear, real-time cash visibility and basic forecasting, allowing businesses to understand their current cash position and anticipate future needs without the complexity of a full TRMS.",
                        features: [
                            "Bank Connectivity (API, SFTP)",
                            "Basic Forecasting Tools",
                            "Cash Visibility",
                            "Transaction Search, Sort, Tag, Group"
                        ],
                        videoUrl: "https://realtreasury.com/wp-content/uploads/2025/08/Cash-Tools-Intro.mp4",
                        videoPoster: "https://realtreasury.com/wp-content/uploads/2025/08/Cash-Tools-Intro.png"
                    },
                    LITE: {
                        name: "Treasury Management System Lite (TMS-Lite)",
                        badge: "Scalable",
                        description: "TMS-Lite solutions bridge the gap between basic Cash Tools and enterprise TRMS platforms. They build upon cash visibility by adding crucial treasury functions like multi-currency cash positioning, initiating treasury payments (wires, transfers), and managing basic financial instruments like foreign exchange contracts. These systems are ideal for growing companies whose needs have outpaced simple cash tools but do not yet require the full complexity of an enterprise system.",
                        features: [
                            "Bank Connectivity (API, SFTP)",
                            "Basic Forecasting Tools",
                            "Cash Visibility",
                            "Transaction Search, Sort, Tag, Group",
                            "Cash Positioning",
                            "Market Data",
                            "Treasury Payments (API, SFTP)"
                        ],
                        videoUrl: "https://realtreasury.com/wp-content/uploads/2025/08/TMS-Lite-Intro.mp4",
                        videoPoster: "https://realtreasury.com/wp-content/uploads/2025/08/TMS-Lite-Intro.png"
                    },
                    TRMS: {
                        name: "Treasury & Risk Management Systems (TRMS)",
                        badge: "Advanced",
                        description: "Treasury & Risk Management Systems (TRMS) are comprehensive platforms for large, complex organizations. They offer a broad suite of tightly integrated modules far beyond cash visibility, covering debt and investment management, advanced financial risk analysis (FX, interest rates, commodities), hedge accounting, global payments, and in-house banking. These systems are designed to centralize and automate sophisticated treasury workflows.",
                        features: [
                            "Bank Connectivity (API, SFTP)",
                            "Basic Forecasting Tools",
                            "Cash Visibility",
                            "Transaction Search, Sort, Tag, Group",
                            "Cash Positioning",
                            "Market Data",
                            "Treasury Payments (API, SFTP)",
                            "Derivatives (Interest, FX)",
                            "Intercompany Loans",
                            "Instrument Valuations",
                            "AI Forecasting",
                            "AI Insights",
                            "AP Payments",
                            "Bank Account Management",
                            "Basic FX (Spots, FWD)",
                            "Cash Accounting",
                            "Debt Management",
                            "Deal Accounting",
                            "In-House Banking",
                            "Investments",
                            "SWIFT Connectivity"
                        ],
                        videoUrl: "https://realtreasury.com/wp-content/uploads/2025/08/TRMS-Intro.mp4",
                        videoPoster: "https://realtreasury.com/wp-content/uploads/2025/08/TRMS-Intro.png"
                    }
                };

                const cashTags = ["Bank Connectivity", "Basic Forecasting Tools", "Cash Visibility", "Transaction Search, Sort, Tag, Group"];
                const liteTags = [...cashTags, "Cash Positioning", "Market Data", "Treasury Payments"];
                const trmsTags = [...liteTags, "AI Forecasting", "AI Insights", "AP Payments", "Bank Account Management", "Basic FX (Spots, FWD)", "Cash Accounting", "Debt Management", "Deal Accounting", "In-House Banking", "Investments", "SWIFT Connectivity", "Derivatives (Interest, FX)", "Intercompany Loans", "Instrument Valuations"];

                this.CATEGORY_TAGS = {
                    CASH: cashTags,
                    LITE: liteTags,
                    TRMS: trmsTags
                };
                this.availableCategories = Array.isArray(window.TTP_DATA && TTP_DATA.available_categories) && TTP_DATA.available_categories.length ? TTP_DATA.available_categories : [];
                this.enabledCategories = Array.isArray(window.TTP_DATA && TTP_DATA.enabled_categories) && TTP_DATA.enabled_categories.length ? TTP_DATA.enabled_categories : this.availableCategories;
                this.availableDomains = Array.isArray(window.TTP_DATA && TTP_DATA.available_domains) && TTP_DATA.available_domains.length ? TTP_DATA.available_domains : [];
                this.enabledDomains = Array.isArray(window.TTP_DATA && TTP_DATA.enabled_domains) && TTP_DATA.enabled_domains.length ? TTP_DATA.enabled_domains : this.availableDomains;
                this.categoryLabels = (window.TTP_DATA && TTP_DATA.category_labels) || {};
                this.currentFilter = 'ALL';
                this.searchTerm = '';
                this.filteredTools = [];
                this.allTags = [];
                this.allRegions = [];
                this.allCategories = [];
                this.allSubcategories = [];
                this.subcategoriesByCategory = {};
                this.advancedFilters = { features: [], hasVideo: false, regions: [], categories: [], subcategories: [] };
                this.currentSort = 'name';
                this.currentView = 'grid';
                this.groupByCategory = true;
                this.sideMenuOpen = false;
                this.shortlist = [];
                this.shortlistMenuOpen = false;
                this.touchDragTool = null;
                this.previousFocusedElement = null;
                this.videoTimes = {};
                this.currentToolId = null;
                this.permanentToolPickerInitialized = false;
                this.spaceHandler = null;
                this.handleOutsideSideMenuClick = (e) => {
                    const sideMenu = document.getElementById('sideMenu');
                    const toggle = document.getElementById('sideMenuToggle');
                    const externalToggle = document.getElementById('externalMenuToggle');
                    if (sideMenu && !sideMenu.contains(e.target) &&
                        !toggle?.contains(e.target) &&
                        !externalToggle?.contains(e.target)) {
                        e.stopPropagation();
                        this.closeSideMenu();
                    }
                };

                this.handleOutsideShortlistMenuClick = (e) => {
                    const menu = document.getElementById('shortlistMenu');
                    const toggle = document.getElementById('shortlistMenuToggle');
                    const externalToggle = document.getElementById('externalShortlistToggle');
                    if (menu && !menu.contains(e.target) &&
                        !toggle?.contains(e.target) &&
                        !externalToggle?.contains(e.target)) {
                        this.closeShortlistMenu();
                    }
                };

                this.init();

                // Swipe gesture tracking
                this.swipeStart = null;
                this.swipeThreshold = 100; // Minimum distance for a swipe
                this.swipeVelocityThreshold = 0.3; // Minimum velocity

                window.addEventListener('message', (e) => {
                    if (e.origin.includes('youtube.com') && typeof e.data === 'string') {
                        try {
                            const data = JSON.parse(e.data);
                            if (data.event === 'infoDelivery' && typeof data.info === 'number' && data.id) {
                                const key = data.id.replace(/^yt-/, '');
                                this.videoTimes[key] = data.info;
                            }
                        } catch (_) {}
                    }
                });
           }

            isMobile() {
                return window.matchMedia('(max-width: 768px)').matches;
            }

            handleResponsive() {
                const mobile = this.isMobile();

                document.querySelectorAll('.tool-card').forEach(card => {
                    card.draggable = !mobile;
                });

                if (mobile) {
                    this.closeSideMenu();
                    this.closeShortlistMenu();

                    const externalMenuToggle = document.getElementById('externalMenuToggle');
                    const externalShortlistToggle = document.getElementById('externalShortlistToggle');
                    if (externalMenuToggle) externalMenuToggle.style.display = 'none';
                    if (externalShortlistToggle) externalShortlistToggle.style.display = 'none';

                    const bottomNav = document.getElementById('bottomNav');
                    if (bottomNav) bottomNav.style.display = 'flex';
                } else {
                    const externalMenuToggle = document.getElementById('externalMenuToggle');
                    const externalShortlistToggle = document.getElementById('externalShortlistToggle');
                    if (externalMenuToggle) externalMenuToggle.style.display = 'flex';
                    if (externalShortlistToggle) externalShortlistToggle.style.display = 'flex';

                    const bottomNav = document.getElementById('bottomNav');
                    if (bottomNav) bottomNav.style.display = 'none';
                }

                this.applyViewStyles();
            }

            normalizeCategory(category) {
                const upper = (category || '').toString().toUpperCase();
                for (const slug in this.categoryLabels) {
                    if (!Object.prototype.hasOwnProperty.call(this.categoryLabels, slug)) continue;
                    const label = (this.categoryLabels[slug] || '').toString().toUpperCase();
                    if (upper.includes(slug.toUpperCase()) || (label && upper.includes(label.toUpperCase()))) {
                        return slug;
                    }
                }
                return upper;
            }

            async fetchTools() {
                const loading = document.getElementById('loadingScreen');
                const container = document.querySelector('.treasury-portal .container');
                const bottomNav = document.getElementById('bottomNav');
                let loadingTimer;

                if (loading) {
                    loading.classList.remove('fade-out');
                    loading.style.display = 'none';
                    loadingTimer = setTimeout(() => {
                        loading.style.display = 'block';
                    }, 200);
                }

                try {
                    const url = new URL(TTP_DATA.rest_url);
                    const { regions = [], categories = [], subcategories = [] } = this.advancedFilters || {};
                    regions.forEach(r => url.searchParams.append('region', r));
                    categories.forEach(c => url.searchParams.append('category', c));
                    subcategories.forEach(s => url.searchParams.append('sub_category', s));
                    const response = await fetch(url.toString());
                    const data = await response.json();
                    let vendors = Array.isArray(data.vendors) ? data.vendors : (Array.isArray(data) ? data : []);
                    if (Array.isArray(data.enabled_domains)) {
                        this.enabledDomains = data.enabled_domains;
                    }
                    const enabledDomains = Array.isArray(this.enabledDomains) ? this.enabledDomains : [];
                    vendors = vendors.filter(vendor => {
                        const vdomains = Array.isArray(vendor.domain) ? vendor.domain : [];
                        return vdomains.length === 0 || vdomains.some(d => enabledDomains.includes(d));
                    });
                    const allRegions = new Set();
                    const allCategories = new Set();
                    const allSubcategories = new Set();
                    const subcategoriesByCategory = {};
                    const addValue = (set, val) => {
                        const trimmed = typeof val === 'string' ? val.trim() : '';
                        if (trimmed && !/^\d+$/.test(trimmed)) {
                            set.add(trimmed);
                        }
                    };
                    this.TREASURY_TOOLS = vendors.map(vendor => {
                        if (!Array.isArray(vendor.regions) || vendor.regions.length === 0) {
                            console.warn('Vendor missing regions:', vendor);
                        }
                        if (!vendor.category && (!Array.isArray(vendor.categories) || vendor.categories.length === 0)) {
                            console.warn('Vendor missing category:', vendor);
                        }

                        let rawCategory = '';
                        if (Array.isArray(vendor.category) && vendor.category.length > 0) {
                            rawCategory = vendor.category[0];
                        } else if (vendor.category) {
                            rawCategory = vendor.category;
                        } else if (Array.isArray(vendor.categories) && vendor.categories.length > 0) {
                            rawCategory = vendor.categories[0];
                        } else if (Array.isArray(vendor.category_names) && vendor.category_names.length > 0) {
                            rawCategory = vendor.category_names[0];
                        }
                        const category = this.normalizeCategory(rawCategory);
                        const subCategories = Array.isArray(vendor.sub_categories) ? vendor.sub_categories : [];
                        const regions = Array.isArray(vendor.regions) ? vendor.regions.map(r => r.trim()) : [];
                        addValue(allCategories, rawCategory);
                        if (!subcategoriesByCategory[category]) {
                            subcategoriesByCategory[category] = new Set();
                        }
                        subCategories.forEach(sc => {
                            addValue(allSubcategories, sc);
                            addValue(subcategoriesByCategory[category], sc);
                        });
                        regions.forEach(r => addValue(allRegions, r));
                        return {
                            name: vendor.name || '',
                            desc: vendor.status || '',
                            category,
                            categoryName: rawCategory,
                            subCategories,
                            regions,
                            videoUrl: vendor.video_url || '',
                            websiteUrl: vendor.full_website_url || vendor.website || '',
                            logoUrl: vendor.logo_url || '',
                            capabilities: vendor.capabilities || [],
                            target: ''
                        };
                    });
                    this.allRegions = Array.from(allRegions).sort((a, b) => a.localeCompare(b));
                    this.allCategories = Array.from(allCategories).sort((a, b) => a.localeCompare(b));
                    this.allSubcategories = Array.from(allSubcategories).sort((a, b) => a.localeCompare(b));
                    this.subcategoriesByCategory = {};
                    Object.keys(subcategoriesByCategory).forEach(cat => {
                        this.subcategoriesByCategory[cat] = Array.from(subcategoriesByCategory[cat]).sort((a, b) => a.localeCompare(b));
                    });
                    const categoriesFromData = Array.from(new Set(this.TREASURY_TOOLS.map(t => t.category)));
                    this.enabledCategories = Array.from(new Set([...this.enabledCategories, ...categoriesFromData])).sort((a, b) => a.localeCompare(b));
                    this.updateCounts();
                    this.populateCategoryTags();
                    this.populateRegionFilters();
                    this.populateCategoryFilters();
                    this.populateSubcategoryFilters();
                    this.renderHeaderFilters();
                    this.renderSubcategoryTabs(this.currentFilter === 'ALL' ? null : this.currentFilter);
                    this.filterAndDisplayTools();
                    this.applyViewStyles();
                } catch (err) {
                    console.error('Failed to load tools:', err);
                } finally {
                    if (container) container.classList.add('loaded');
                    if (bottomNav) bottomNav.style.display = 'flex';
                    if (loading) {
                        clearTimeout(loadingTimer);
                        if (loading.style.display !== 'none') {
                            loading.classList.add('fade-out');
                            loading.addEventListener('transitionend', () => {
                                loading.style.display = 'none';
                            }, { once: true });
                        }
                    }
                }
            }

            init() {
                this.setupInteractions();
                this.setupSearch();
                this.setupModals();
                this.setupSideMenu();
                this.setupShortlistMenu();
                this.setupBottomNav();

                this.handleResponsive();
                window.addEventListener('resize', () => this.handleResponsive());
            }

            // Swipe detection utility methods
            handleTouchStart(e, element) {
                if (!this.isMobile()) return;

                this.swipeStart = {
                    x: e.touches[0].clientX,
                    y: e.touches[0].clientY,
                    time: Date.now(),
                    element: element
                };
            }

            handleTouchMove(e, element) {
                if (!this.swipeStart || !this.isMobile()) return;

                // Prevent default scrolling during potential swipe
                const deltaX = Math.abs(e.touches[0].clientX - this.swipeStart.x);
                const deltaY = Math.abs(e.touches[0].clientY - this.swipeStart.y);

                // If moving more horizontally than vertically, prevent vertical scroll
                if (deltaX > deltaY && deltaX > 10) {
                    e.preventDefault();
                }
            }

            handleTouchEnd(e, element, closeCallback) {
                if (!this.swipeStart || !this.isMobile()) return;

                const endX = e.changedTouches[0].clientX;
                const endY = e.changedTouches[0].clientY;
                const deltaX = endX - this.swipeStart.x;
                const deltaY = endY - this.swipeStart.y;
                const deltaTime = Date.now() - this.swipeStart.time;
                const distanceX = Math.abs(deltaX);
                const distanceY = Math.abs(deltaY);
                const velocity = Math.max(distanceX, distanceY) / deltaTime;

                // Check if this qualifies as a swipe
                if (velocity > this.swipeVelocityThreshold && deltaTime < 1000) {
                    let shouldClose = false;

                    if (element === 'sideMenu' && deltaX < -this.swipeThreshold) {
                        // Swipe left to close left side menu
                        shouldClose = true;
                    } else if (element === 'shortlistMenu' && deltaX > this.swipeThreshold) {
                        // Swipe right to close right shortlist menu
                        shouldClose = true;
                    } else if ((element === 'toolModal' || element === 'categoryModal') && deltaY > this.swipeThreshold) {
                        // Swipe down to close modals
                        shouldClose = true;
                    }

                    if (shouldClose && closeCallback) {
                        closeCallback();
                    }
                }

                this.swipeStart = null;
            }

            setupSwipeToClose(elementId, elementType, closeCallback) {
                const element = document.getElementById(elementId);
                if (!element) return;

                const touchStartHandler = (e) => this.handleTouchStart(e, elementType);
                const touchMoveHandler = (e) => this.handleTouchMove(e, elementType);
                const touchEndHandler = (e) => this.handleTouchEnd(e, elementType, closeCallback);

                element.addEventListener('touchstart', touchStartHandler, { passive: false });
                element.addEventListener('touchmove', touchMoveHandler, { passive: false });
                element.addEventListener('touchend', touchEndHandler, { passive: false });

                // Store handlers for cleanup if needed
                element._swipeHandlers = {
                    touchstart: touchStartHandler,
                    touchmove: touchMoveHandler,
                    touchend: touchEndHandler
                };
            }

            setupInteractions() {
                // Subcategory tabs are rendered dynamically via renderSubcategoryTabs()

                document.querySelectorAll('.category-header').forEach(header => {
                    header.addEventListener('click', (e) => {
                        if (e.target.closest('.show-more-category-tags-btn') ||
                            e.target.closest('.show-less-category-tags-btn')) {
                            return;
                        }

                        const category = header.dataset.category;
                        if (category && this.CATEGORY_INFO[category]) {
                            this.showCategoryModal(this.CATEGORY_INFO[category], category);
                        }
                    });
                });

                const mainContent = document.getElementById('mainContent');
                if (mainContent) {
                    mainContent.addEventListener('click', (e) => {
                        if (e.target.classList.contains('show-more-capabilities-btn')) {
                            e.stopPropagation();
                            const toolName = e.target.dataset.toolName;
                            const tool = this.TREASURY_TOOLS.find(t => t.name === toolName);
                            if (tool) {
                                const capContainer = e.target.parentElement;
                                const sortedCaps = [...tool.capabilities].sort((a, b) => a.localeCompare(b));
                                capContainer.innerHTML = sortedCaps.map(cap => `<span class="tool-capability">${cap}</span>`).join('');
                                capContainer.innerHTML += `<button class="show-less-capabilities-btn" data-tool-name="${tool.name}">Show less</button>`;
                            }
                        } else if (e.target.classList.contains('show-less-capabilities-btn')) {
                            e.stopPropagation();
                            const toolName = e.target.dataset.toolName;
                            const tool = this.TREASURY_TOOLS.find(t => t.name === toolName);
                            if (tool) {
                                const capContainer = e.target.parentElement;
                                const sortedCaps = [...tool.capabilities].sort((a, b) => a.localeCompare(b));
                                const displayCaps = sortedCaps.slice(0, 3);
                                const hasMore = sortedCaps.length > 3;
                                capContainer.innerHTML = displayCaps.map(cap => `<span class="tool-capability">${cap}</span>`).join('');
                                if (hasMore) {
                                    capContainer.innerHTML += `<button class="show-more-capabilities-btn" data-tool-name="${tool.name}">... more</button>`;
                                }
                            }
                        } else if (e.target.classList.contains('show-more-category-tags-btn')) {
                            e.stopPropagation();
                            const category = e.target.dataset.category;
                            const tagsContainer = e.target.parentElement;
                            const tags = this.CATEGORY_TAGS[category] || [];
                            const sorted = [...tags].sort((a, b) => a.localeCompare(b));
                            tagsContainer.innerHTML = sorted.map(tag => `<span class="category-tag">${tag}</span>`).join('');
                            tagsContainer.innerHTML += `<button class="show-less-category-tags-btn" data-category="${category}">Show less</button>`;
                        } else if (e.target.classList.contains('show-less-category-tags-btn')) {
                            e.stopPropagation();
                            const category = e.target.dataset.category;
                            const tagsContainer = e.target.parentElement;
                            const tags = this.CATEGORY_TAGS[category] || [];
                            const sorted = [...tags].sort((a, b) => a.localeCompare(b));
                            const displayTags = sorted.slice(0, 3);
                            const hasMore = sorted.length > 3;
                            tagsContainer.innerHTML = displayTags.map(tag => `<span class="category-tag">${tag}</span>`).join('');
                            if (hasMore) {
                                tagsContainer.innerHTML += `<button class="show-more-category-tags-btn" data-category="${category}">... more</button>`;
                            }
                        }
                    });
                }
            }

            setupSearch() {
                const searchInput = document.getElementById('searchInput');
                const searchClear = document.getElementById('searchClear');

                if (searchInput) {
                    searchInput.addEventListener('input', (e) => {
                        this.searchTerm = e.target.value.toLowerCase();
                        if (searchClear) searchClear.style.display = this.searchTerm ? 'block' : 'none';
                        this.filterAndDisplayTools();
                    });

                    searchInput.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            this.closeSideMenu();
                        }
                    });
                }

                if (searchClear) {
                    searchClear.addEventListener('click', () => {
                        if (searchInput) {
                            searchInput.value = '';
                            searchInput.focus(); // Keep focus on the input after clearing
                        }
                        this.searchTerm = '';
                        searchClear.style.display = 'none';
                        this.filterAndDisplayTools();
                    });
                }
            }

            setupTagSearch() {
                const searchInput = document.getElementById('tagSearchInput');
                const searchClear = document.getElementById('tagSearchClear');

                if (searchInput) {
                    searchInput.addEventListener('input', () => {
                        const term = searchInput.value.toLowerCase();
                        if (searchClear) searchClear.style.display = term ? 'block' : 'none';
                        this.filterTagCheckboxes(term);
                    });
                }

                if (searchClear) {
                    searchClear.addEventListener('click', () => {
                        if (searchInput) {
                            searchInput.value = '';
                            searchInput.focus();
                        }
                        searchClear.style.display = 'none';
                        this.filterTagCheckboxes('');
                    });
                }
            }

            filterTagCheckboxes(term) {
                const container = document.getElementById('tagFilters');
                if (!container) return;
                const items = container.querySelectorAll('.checkbox-item');
                items.forEach(item => {
                    const label = item.textContent.toLowerCase();
                    item.style.display = label.includes(term) ? 'flex' : 'none';
                });

                const showMore = document.getElementById('showMoreTagFilters');
                const showLess = document.getElementById('showLessTagFilters');
                const extra = document.getElementById('extraTagFilters');
                if (showMore && showLess && extra) {
                    if (term) {
                        extra.style.display = 'block';
                        showMore.style.display = 'none';
                        showLess.style.display = 'none';
                    } else {
                        extra.style.display = 'none';
                        showLess.style.display = 'none';
                        showMore.style.display = 'inline-block';
                    }
                }
            }

            setupModals() {
                const toolModal = document.getElementById('toolModal');
                const toolModalClose = document.getElementById('modalClose');

                if (toolModalClose) {
                    toolModalClose.addEventListener('click', () => this.closeModal('toolModal'));
                }
                if (toolModal) {
                    toolModal.addEventListener('click', (e) => {
                        if (e.target.closest('.ttp-modal-content') === null) this.closeModal('toolModal');
                    });
                }

                const categoryModal = document.getElementById('categoryModal');
                const categoryModalClose = document.getElementById('categoryModalClose');

                if (categoryModalClose) {
                    categoryModalClose.addEventListener('click', () => this.closeModal('categoryModal'));
                }
                if (categoryModal) {
                    categoryModal.addEventListener('click', (e) => {
                        if (e.target.closest('.ttp-modal-content') === null) this.closeModal('categoryModal');
                    });
                }

                const container = document.querySelector('.treasury-portal');
                const target = container?.querySelector('.intro-video-target') || container;
                const src = target?.getAttribute('data-video-src') || '';
                const poster = target?.getAttribute('data-poster') || '';

                const createVideo = (source, posterUrl) => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'video-wrapper';
                    const vid = document.createElement('video');
                    vid.src = source;
                    vid.autoplay = false;
                    vid.muted = false;
                    vid.playsInline = true;
                    vid.preload = 'metadata';
                    vid.setAttribute('playsinline', '');
                    if (posterUrl) vid.poster = posterUrl;

                    const btn = document.createElement('button');
                    btn.className = 'video-play-button';
                    btn.setAttribute('aria-label', 'Play video');
                    btn.innerHTML = '\u25B6';

                    const togglePlay = () => {
                        if (vid.paused) {
                            vid.play();
                        } else {
                            vid.pause();
                        }
                    };

                    vid.addEventListener('click', togglePlay);
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        togglePlay();
                    });
                    vid.addEventListener('play', () => wrapper.classList.add('playing'));
                    vid.addEventListener('pause', () => wrapper.classList.remove('playing'));
                    vid.addEventListener('ended', () => wrapper.classList.remove('playing'));

                    wrapper.appendChild(vid);
                    wrapper.appendChild(btn);
                    return wrapper;
                };

                const showFallback = () => {
                    target.innerHTML = '<div class="intro-video-fallback">Intro video unavailable</div>';
                };

                if (src) {
                    const wrapper = createVideo(src, poster);
                    wrapper.querySelector('video').onerror = showFallback;
                    target.innerHTML = '';
                    target.appendChild(wrapper);
                } else {
                    showFallback();
                }

                document.querySelectorAll('.category-video-target').forEach((el) => {
                    const categorySrc = el.getAttribute('data-video-src');
                    const catPoster = el.getAttribute('data-poster');
                    if (categorySrc) {
                        const wrapper = createVideo(categorySrc, catPoster);
                        el.innerHTML = '';
                        el.appendChild(wrapper);
                    }
                });

                // Setup swipe-to-close for tool modal
                this.setupSwipeToClose('toolModal', 'toolModal', () => this.closeModal('toolModal'));

                // Setup swipe-to-close for category modal
                this.setupSwipeToClose('categoryModal', 'categoryModal', () => this.closeModal('categoryModal'));

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.closeModal('toolModal');
                        this.closeModal('categoryModal');
                    }
                });
            }
            
            openModal(modal) {
                 if (modal) {
                    this.previousFocusedElement = document.activeElement;
                    modal.classList.add('show');
                    portalRoot.classList.add('modal-open');

                    const focusable = modal.querySelector('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])');
                    if (focusable) {
                        focusable.focus();
                    } else {
                        const content = modal.querySelector('.ttp-modal-content');
                        if (content) content.focus();
                    }
                }
            }

            showToolModal(tool) {
                const modal = document.getElementById('toolModal');
                const modalTitle = document.getElementById('modalTitle');
                const modalDescription = document.getElementById('modalDescription');
                const modalWebsiteLink = document.getElementById('modalWebsiteLink');
                const modalBody = modal?.querySelector('.modal-body');
                const modalLogo = document.getElementById('modalToolLogo');
                const modalCapabilities = document.getElementById('modalCapabilities');

                if (!modal || !modalBody) return;

                if (this.spaceHandler) {
                    this.spaceHandler.el.removeEventListener('keydown', this.spaceHandler.fn);
                    this.spaceHandler = null;
                }

                this.currentToolId = tool.name.toLowerCase().replace(/[^a-z0-9]+/g, '-');

                // 1. Update the static content first
                if (modalTitle) modalTitle.textContent = tool.name;
                if (modalDescription) modalDescription.textContent = tool.desc;

                if (modalWebsiteLink) {
                    if (tool.websiteUrl) {
                        modalWebsiteLink.href = tool.websiteUrl;
                        modalWebsiteLink.style.display = 'inline-flex';
                    } else {
                        modalWebsiteLink.style.display = 'none';
                    }
                }

                if (modalLogo) {
                    if (tool.logoUrl) {
                        // Always wrap in a link if websiteUrl exists
                        if (tool.websiteUrl) {
                            modalLogo.outerHTML = `<a href="${tool.websiteUrl}" target="_blank" rel="noopener noreferrer" class="modal-logo-link">
                                <img id="modalToolLogo" class="modal-tool-logo" src="${tool.logoUrl}" alt="${tool.name} logo">
                            </a>`;
                        } else {
                            // No website URL - show logo without link
                            modalLogo.src = tool.logoUrl;
                            modalLogo.alt = `${tool.name} logo`;
                            modalLogo.style.display = 'block';
                            // Ensure it's not wrapped in a link
                            if (modalLogo.parentElement.classList.contains('modal-logo-link')) {
                                modalLogo.parentElement.replaceWith(modalLogo);
                            }
                        }
                    } else {
                        modalLogo.style.display = 'none';
                    }

                    // After the logo link creation, add event handling
                    if (tool.websiteUrl && tool.logoUrl) {
                        setTimeout(() => {
                            const logoLink = document.querySelector('.modal-logo-link');
                            if (logoLink) {
                                logoLink.addEventListener('click', (e) => {
                                    e.stopPropagation(); // Prevent modal from closing
                                });
                            }
                        }, 0);
                    }
                }

                if (modalCapabilities) {
                    const caps = tool.capabilities || [];
                    const sortedCaps = [...caps].sort((a, b) => a.localeCompare(b));
                    const displayCaps = sortedCaps.slice(0, 5);
                    const hasMoreCaps = sortedCaps.length > 5;

                    modalCapabilities.innerHTML = displayCaps.map(cap => `<span class="tool-capability">${cap}</span>`).join('');
                    if (hasMoreCaps) {
                        modalCapabilities.innerHTML += `<button class="show-more-modal-capabilities-btn" data-tool-name="${tool.name}">... more (${sortedCaps.length - 5})</button>`;
                    }

                    modalCapabilities.addEventListener('click', (e) => {
                        if (e.target.classList.contains('show-more-modal-capabilities-btn')) {
                            e.stopPropagation();
                            const toolName = e.target.dataset.toolName;
                            const currentTool = this.TREASURY_TOOLS.find(t => t.name === toolName);
                            if (currentTool) {
                                const sortedCaps = [...currentTool.capabilities || []].sort((a, b) => a.localeCompare(b));
                                modalCapabilities.innerHTML = sortedCaps.map(cap => `<span class="tool-capability">${cap}</span>`).join('');
                                modalCapabilities.innerHTML += `<button class="show-less-modal-capabilities-btn" data-tool-name="${toolName}">Show less</button>`;
                            }
                        } else if (e.target.classList.contains('show-less-modal-capabilities-btn')) {
                            e.stopPropagation();
                            const toolName = e.target.dataset.toolName;
                            const currentTool = this.TREASURY_TOOLS.find(t => t.name === toolName);
                            if (currentTool) {
                                const sortedCaps = [...currentTool.capabilities || []].sort((a, b) => a.localeCompare(b));
                                const displayCaps = sortedCaps.slice(0, 5);
                                const hasMoreCaps = sortedCaps.length > 5;
                                modalCapabilities.innerHTML = displayCaps.map(cap => `<span class="tool-capability">${cap}</span>`).join('');
                                if (hasMoreCaps) {
                                    modalCapabilities.innerHTML += `<button class="show-more-modal-capabilities-btn" data-tool-name="${toolName}">... more (${sortedCaps.length - 5})</button>`;
                                }
                            }
                        }
                    });
                }

                // 2. Remove any video section from a previous click
                const existingVideoSection = modalBody.querySelector('.video-demo-section');
                if (existingVideoSection) {
                    existingVideoSection.remove();
                }

                // 3. Add a new video section if the current tool has one
                if (tool.videoUrl) {
                    const videoSection = document.createElement('div');
                    videoSection.className = 'feature-section video-demo-section';

                    const resume = this.videoTimes[this.currentToolId] || 0;

                    if (tool.videoUrl.includes('youtu.be/') || tool.videoUrl.includes('youtube.com/watch')) {
                        let embedUrl = tool.videoUrl;
                        if (tool.videoUrl.includes('youtu.be/')) {
                            const videoId = tool.videoUrl.split('youtu.be/')[1].split('?')[0];
                            embedUrl = `https://www.youtube.com/embed/${videoId}`;
                        } else {
                            const videoId = new URL(tool.videoUrl).searchParams.get('v');
                            embedUrl = `https://www.youtube.com/embed/${videoId}`;
                        }
                        embedUrl += (embedUrl.includes('?') ? '&' : '?') + 'enablejsapi=1&playsinline=1';
                        if (resume) embedUrl += `&start=${Math.floor(resume)}&autoplay=1`;

                        videoSection.innerHTML = `
                            <h4>🎥 Product Differentiator</h4>
                            <div class="video-container">
                                <iframe id="yt-${this.currentToolId}" src="${embedUrl}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy" playsinline></iframe>
                            </div>
                        `;
                    } else {
                        videoSection.innerHTML = `
                            <h4>🎥 Product Differentiator</h4>
                            <div class="video-container">
                                <video src="${tool.videoUrl}" controls playsinline></video>
                            </div>
                        `;
                        if (resume) {
                            videoSection.querySelector('video').currentTime = resume;
                        }
                    }

                    const capabilitiesSection = modalCapabilities?.closest('.feature-section');
                    if (capabilitiesSection) {
                        modalBody.insertBefore(videoSection, capabilitiesSection);
                    } else {
                        modalBody.appendChild(videoSection);
                    }
                }

                // 4. Show the modal
                this.openModal(modal);

                const content = modal.querySelector('.modal-content, .ttp-modal-content');
                if (content) {
                    const handler = (e) => {
                        if (e.code === 'Space' || e.key === ' ') {
                            const vid = modal.querySelector('video');
                            const iframe = modal.querySelector('iframe');
                            if (vid) {
                                e.preventDefault();
                                vid.paused ? vid.play() : vid.pause();
                            } else if (iframe && iframe.contentWindow) {
                                e.preventDefault();
                                iframe.contentWindow.postMessage('{"event":"command","func":"playVideo","args":""}', 'https://www.youtube.com');
                            }
                        }
                    };
                    content.addEventListener('keydown', handler);
                    this.spaceHandler = { el: content, fn: handler };
                }
            }

            showCategoryModal(categoryInfo, categoryKey) {
                const modal = document.getElementById('categoryModal');
                const modalTitle = document.getElementById('categoryModalTitle');
                const modalDescription = document.getElementById('categoryModalDescription');
                const modalFeatures = document.getElementById('categoryModalFeatures');
                const modalBody = modal?.querySelector('.modal-body');

                if (modalTitle) modalTitle.textContent = categoryInfo.name;
                if (modalDescription) modalDescription.textContent = categoryInfo.description;

                if (modalFeatures) {
                    modalFeatures.innerHTML = '';
                    categoryInfo.features.forEach(feature => {
                        const li = document.createElement('li');
                        li.textContent = feature;
                        modalFeatures.appendChild(li);
                    });
                }
                
                // Clear previous dynamic content
                const existingVideoSection = modalBody?.querySelector('.video-demo-section');
                if (existingVideoSection) {
                    existingVideoSection.remove();
                }

                if (categoryInfo.videoUrl && modalBody) {
                    const videoSection = document.createElement('div');
                    videoSection.className = 'feature-section video-demo-section';
                    let contentHtml = '';
                    if (categoryInfo.videoUrl.includes('youtu.be/') || categoryInfo.videoUrl.includes('youtube.com/watch')) {
                        let embedUrl = categoryInfo.videoUrl;
                        if (categoryInfo.videoUrl.includes('youtu.be/')) {
                            const videoId = categoryInfo.videoUrl.split('youtu.be/')[1].split('?')[0];
                            embedUrl = `https://www.youtube.com/embed/${videoId}`;
                        } else if (categoryInfo.videoUrl.includes('youtube.com/watch')) {
                            const videoId = new URL(categoryInfo.videoUrl).searchParams.get('v');
                            embedUrl = `https://www.youtube.com/embed/${videoId}`;
                        }
                        embedUrl += (embedUrl.includes('?') ? '&' : '?') + 'enablejsapi=1&playsinline=1';
                        contentHtml = `<iframe src="${embedUrl}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy" playsinline></iframe>`;
                    } else {
                        const posterAttr = categoryInfo.videoPoster ? ` poster="${categoryInfo.videoPoster}"` : '';
                        contentHtml = `<video controls preload="metadata"${posterAttr} playsinline>
                                        <source src="${categoryInfo.videoUrl}" type="video/mp4">
                                        Your browser does not support the video tag.
                                      </video>`;
                    }

                    videoSection.innerHTML = `
                        <h4>🎥 Category Overview Video</h4>
                        <div class="video-container">
                             ${contentHtml}
                        </div>
                    `;

                    modalBody.insertBefore(videoSection, modalBody.firstChild);
                }

                this.openModal(modal);
            }

            closeModal(modalId) {
                const modal = document.getElementById(modalId);
                if (modal && modal.classList.contains('show')) {
                    modal.classList.remove('show');
                    portalRoot.classList.remove('modal-open');

                    if (modalId === 'toolModal' && this.currentToolId) {
                        const key = this.currentToolId;

                        modal.querySelectorAll('iframe').forEach(iframe => {
                            if (iframe.src.includes('youtube.com') && iframe.contentWindow) {
                                iframe.contentWindow.postMessage(JSON.stringify({
                                    event: 'command',
                                    func: 'getCurrentTime',
                                    id: `yt-${key}`
                                }), 'https://www.youtube.com');
                                iframe.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', 'https://www.youtube.com');
                            }
                        });

                        modal.querySelectorAll('video').forEach(video => {
                            this.videoTimes[key] = video.currentTime;
                            video.pause();
                        });

                        if (this.spaceHandler) {
                            this.spaceHandler.el.removeEventListener('keydown', this.spaceHandler.fn);
                            this.spaceHandler = null;
                        }
                    }

                    if (this.previousFocusedElement) {
                        this.previousFocusedElement.focus();
                        this.previousFocusedElement = null;
                    }
                }
            }

            filterAndDisplayTools() {
                let tools = [...this.TREASURY_TOOLS];

                if (this.searchTerm) {
                    const lowerCaseSearchTerm = this.searchTerm.toLowerCase();
                    tools = tools.filter(tool => {
                        const searchableText = [
                            tool.name,
                            tool.desc,
                            tool.target,
                            ...(tool.capabilities || [])
                        ].join(' ').toLowerCase();

                        return searchableText.includes(lowerCaseSearchTerm);
                    });
                } else {
                    tools = this.currentFilter === 'ALL' ?
                        tools :
                        tools.filter(tool => tool.category === this.currentFilter);
                }

                // Advanced Filters
                const { regions, categories, subcategories } = this.advancedFilters;
                if (regions.length) {
                    tools = tools.filter(t => (t.regions || []).some(r => regions.includes(r)));
                }
                if (categories.length) {
                    tools = tools.filter(t => categories.includes(t.categoryName));
                }
                if (subcategories.length) {
                    tools = tools.filter(t => (t.subCategories || []).some(sc => subcategories.includes(sc)));
                }
                if (this.advancedFilters.features.length) {
                    tools = tools.filter(t => this.advancedFilters.features.every(f => (t.capabilities || []).includes(f)));
                }
                if (this.advancedFilters.hasVideo) {
                    tools = tools.filter(t => t.videoUrl);
                }

                // Sorting
                if (this.currentSort === 'name') {
                    tools = tools.sort((a, b) => a.name.localeCompare(b.name));
                } else if (this.currentSort === 'category') {
                    tools = tools.sort((a, b) => a.category.localeCompare(b.category));
                }

                this.filteredTools = tools;
                this.displayFilteredTools();
                this.updateVisibleCounts();
            }

            displayFilteredTools() {
                const categories = this.enabledCategories;
                const hasResults = this.filteredTools.length > 0;

                const noResults = document.getElementById('noResults');
                if (noResults) noResults.style.display = hasResults ? 'none' : 'block';

                const listContainer = document.getElementById('listViewContainer');
                const ungroup = !this.groupByCategory || this.searchTerm ||
                                  this.advancedFilters.features.length || this.advancedFilters.hasVideo ||
                                  this.advancedFilters.regions.length || this.advancedFilters.categories.length || this.advancedFilters.subcategories.length;

                if (ungroup) {
                    categories.forEach(cat => {
                        const section = document.querySelector(`.category-section[data-category="${cat}"]`);
                        if (section) section.style.display = 'none';
                    });
                    if (listContainer) {
                        listContainer.innerHTML = '';
                        this.filteredTools.sort((a,b) => a.name.localeCompare(b.name)).forEach(tool => {
                            const card = this.createToolCard(tool, tool.category);
                            listContainer.appendChild(card);
                        });
                        listContainer.style.display = hasResults ? 'grid' : 'none';
                    }
                    return;
                } else {
                    if (listContainer) listContainer.style.display = 'none';
                }

                categories.forEach(category => {
                    const section = document.querySelector(`.category-section[data-category="${category}"]`);
                    const container = document.getElementById(`tools-${category}`);
                    const categoryTools = this.filteredTools
                        .filter(tool => tool.category === category)
                        .sort((a, b) => a.name.localeCompare(b.name));

                    // Clear the container first
                    if (container) {
                        container.innerHTML = '';
                    }

                    // Determine if this section should be visible
                    let shouldShowSection = false;

                    if (this.searchTerm) {
                        // When searching, show section only if it has matching tools
                        shouldShowSection = categoryTools.length > 0;
                    } else {
                        // When filtering by category, show section if it matches filter and has tools
                        shouldShowSection = (this.currentFilter === 'ALL' || this.currentFilter === category) && categoryTools.length > 0;
                    }

                    // Show/hide the section
                    if (section) {
                        section.style.display = shouldShowSection ? 'block' : 'none';
                    }

                    // Populate the container only if section is visible
                    if (shouldShowSection && container) {
                        categoryTools.forEach(tool => {
                            const card = this.createToolCard(tool, category);
                            container.appendChild(card);
                        });
                    }
                });

            }

            createToolCard(tool, category) {
                const card = document.createElement('div');
                card.className = `tool-card tool-${category.toLowerCase()}`;
                card.draggable = !this.isMobile();
                card.dataset.name = tool.name;

                const incomplete = containsRecordIds(tool) || tool.incomplete;
                const warningIcon = incomplete ? '<span class="ttp-warning" title="Some data may be incomplete">⚠️</span>' : '';

                const capabilities = tool.capabilities || [];
                const sortedCaps = [...capabilities].sort((a, b) => a.localeCompare(b));
                const displayCaps = sortedCaps.slice(0, 3);
                const hasMoreCaps = sortedCaps.length > 3;

                card.innerHTML = `
                    <div class="tool-card-content">
                        <div class="tool-header">
                            <div class="tool-info">
                                <div class="tool-name">
                                    <div class="tool-name-group">
                                        <span class="tool-name-title">${tool.name}</span>${warningIcon}
                                    </div>
                                    ${tool.logoUrl ? `<a href="${tool.websiteUrl || '#'}" target="_blank" rel="noopener noreferrer" class="tool-logo-link" ${!tool.websiteUrl ? 'style="pointer-events: none; cursor: default;"' : ''}><img class="tool-logo-inline" src="${tool.logoUrl}" alt="${tool.name} logo"></a>` : ''}
                                </div>
                                ${tool.videoUrl ? '<button type="button" class="video-indicator">\u25B6 Demo</button>' : ''}
                                <div class="tool-type">${this.categoryLabels[tool.category] || tool.category}</div>
                            </div>
                        </div>
                        <div class="tool-description">${tool.desc}</div>
                    </div>
                        <div class="tool-card-actions">
                        <div class="tool-capabilities">
                            ${displayCaps.map(cap => `<span class="tool-capability">${cap}</span>`).join('')}
                            ${hasMoreCaps ? `<button class="show-more-capabilities-btn" data-tool-name="${tool.name}">... more</button>` : ''}
                        </div>
                    </div>
                `;

                card.addEventListener('click', (e) => {
                    if (!e.target.closest('.show-more-capabilities-btn') &&
                        !e.target.closest('.show-less-capabilities-btn')) {
                        this.showToolModal(tool);
                    }
                });

                if (!this.isMobile()) {
                    card.addEventListener('dragstart', (e) => {
                        e.dataTransfer.setData('text/plain', tool.name);
                        this.openShortlistMenu('dragstart');
                    });

                    card.addEventListener('touchstart', () => {
                        this.touchDragTool = tool;
                        this.openShortlistMenu();
                    });
                    card.addEventListener('touchmove', (e) => {
                        const container = document.getElementById('shortlistContainer');
                        if (!container) return;
                        const touch = e.touches[0];
                        const el = document.elementFromPoint(touch.clientX, touch.clientY);
                        if (el && container.contains(el)) {
                            container.classList.add('drag-over');
                        } else {
                            container.classList.remove('drag-over');
                        }
                        e.preventDefault();
                    }, { passive: false });
                    card.addEventListener('touchend', (e) => {
                        const container = document.getElementById('shortlistContainer');
                        if (container) {
                            container.classList.remove('drag-over');
                        }
                        if (this.touchDragTool && container) {
                            const touch = e.changedTouches[0];
                            const el = document.elementFromPoint(touch.clientX, touch.clientY);
                            if (el && container.contains(el) && !this.shortlist.some(i => i.tool.name === this.touchDragTool.name)) {
                                this.shortlist.push({ tool: this.touchDragTool, notes: '' });
                                this.renderShortlist();
                            }
                        }
                        this.touchDragTool = null;
                    });
                }

                // Prevent logo click from triggering card modal when logo has a valid link
                if (tool.websiteUrl) {
                    const logoLink = card.querySelector('.tool-logo-link');
                    if (logoLink) {
                        logoLink.addEventListener('click', (e) => {
                            e.stopPropagation();
                        });
                    }
                }

                return card;
            }

            populateCategoryTags() {
                const categories = this.enabledCategories;
                categories.forEach(category => {
                    const container = document.getElementById(`category-tags-${category}`);
                    if (container) {
                        const tags = this.CATEGORY_TAGS[category] || [];
                        const sorted = [...tags].sort((a, b) => a.localeCompare(b));
                        const displayTags = sorted.slice(0, 3);
                        const hasMore = sorted.length > 3;
                        container.innerHTML = displayTags.map(tag => `<span class="category-tag">${tag}</span>`).join('');
                        if (hasMore) {
                            container.innerHTML += `<button class="show-more-category-tags-btn" data-category="${category}">... more</button>`;
                        }
                    }
                });
            }

            populateTagFilters() {
                const container = document.getElementById('tagFilters');
                if (!container) return;
                const tags = [...new Set(Object.values(this.CATEGORY_TAGS).flat())].sort((a, b) => a.localeCompare(b));
                this.allTags = tags;
                const displayCount = 4;
                const visible = tags.slice(0, displayCount);
                const hidden = tags.slice(displayCount);
                const makeCb = (tag) => {
                    const id = `tag-${tag.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}`;
                    return `<div class="checkbox-item"><input type="checkbox" id="${id}" value="${tag}"><label for="${id}">${tag}</label></div>`;
                };
                container.innerHTML = visible.map(makeCb).join('');
                if (hidden.length) {
                    container.innerHTML += `<div id="extraTagFilters" style="display:none;">${hidden.map(makeCb).join('')}</div>`;
                    container.innerHTML += `<button class="show-more-filter-tags-btn" id="showMoreTagFilters">Show more</button>`;
                    container.innerHTML += `<button class="show-less-filter-tags-btn" id="showLessTagFilters" style="display:none;">Show less</button>`;
                }
            }

            populateRegionFilters() {
                const container = document.getElementById('regionFilters');
                if (!container) return;
                const makeCb = (region) => {
                    const id = `region-${region.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}`;
                    return `<div class="checkbox-item"><input type="checkbox" id="${id}" value="${region}"><label for="${id}">${region}</label></div>`;
                };
                container.innerHTML = this.allRegions.map(makeCb).join('');
                const checkboxes = container.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(cb => {
                    cb.addEventListener('change', () => {
                        this.advancedFilters.regions = Array.from(checkboxes).filter(c => c.checked).map(c => c.value);
                        this.filterAndDisplayTools();
                        this.updateFilterCount();
                    });
                });
            }

            populateCategoryFilters() {
                const container = document.getElementById('categoryFilters');
                if (!container) return;
                container.innerHTML = '';
                const createCheckbox = (cat) => {
                    const id = `cat-${cat.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}`;
                    const wrapper = document.createElement('div');
                    wrapper.className = 'checkbox-item';
                    const input = document.createElement('input');
                    input.type = 'checkbox';
                    input.id = id;
                    input.value = cat;
                    const label = document.createElement('label');
                    label.setAttribute('for', id);
                    label.textContent = cat;
                    wrapper.appendChild(input);
                    wrapper.appendChild(label);
                    return wrapper;
                };
                this.allCategories.forEach(cat => container.appendChild(createCheckbox(cat)));
                const checkboxes = container.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(cb => {
                    cb.addEventListener('change', () => {
                        this.advancedFilters.categories = Array.from(checkboxes).filter(c => c.checked).map(c => c.value);
                        this.currentFilter = this.advancedFilters.categories.length === 1 ? this.normalizeCategory(this.advancedFilters.categories[0]) : 'ALL';
                        this.renderSubcategoryTabs(this.currentFilter === 'ALL' ? null : this.currentFilter);
                        this.filterAndDisplayTools();
                        this.updateFilterCount();
                    });
                });
            }

            populateSubcategoryFilters() {
                const container = document.getElementById('subcategoryFilters');
                if (!container) return;
                container.innerHTML = '';
                const createCheckbox = (sub) => {
                    const id = `sub-${sub.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}`;
                    const wrapper = document.createElement('div');
                    wrapper.className = 'checkbox-item';
                    const input = document.createElement('input');
                    input.type = 'checkbox';
                    input.id = id;
                    input.value = sub;
                    const label = document.createElement('label');
                    label.setAttribute('for', id);
                    label.textContent = sub;
                    wrapper.appendChild(input);
                    wrapper.appendChild(label);
                    return wrapper;
                };
                this.allSubcategories.forEach(sub => container.appendChild(createCheckbox(sub)));
                const checkboxes = container.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(cb => {
                    cb.addEventListener('change', () => {
                        this.advancedFilters.subcategories = Array.from(checkboxes).filter(c => c.checked).map(c => c.value);
                        this.filterAndDisplayTools();
                        this.updateFilterCount();
                    });
                });
            }

            renderSubcategoryTabs(category) {
                const container = document.querySelector('.filter-tabs');
                if (!container) return;
                container.innerHTML = '';
                container.style.display = 'none';
                this.advancedFilters.subcategories = [];
                const subcategoryCheckboxes = document.querySelectorAll('#subcategoryFilters input[type="checkbox"]');
                subcategoryCheckboxes.forEach(cb => (cb.checked = false));
                if (!category || !this.subcategoriesByCategory[category]) {
                    return;
                }
                const subs = this.subcategoriesByCategory[category];
                if (!subs.length) {
                    return;
                }
                container.style.display = 'flex';
                const createBtn = (label, value = '') => {
                    const btn = document.createElement('button');
                    btn.className = 'filter-tab';
                    btn.type = 'button';
                    btn.textContent = label;
                    btn.dataset.subcategory = value;
                    return btn;
                };
                const allBtn = createBtn('All');
                allBtn.classList.add('active');
                container.appendChild(allBtn);
                subs.forEach(sub => container.appendChild(createBtn(sub, sub)));
                container.querySelectorAll('.filter-tab').forEach(tab => {
                    tab.addEventListener('click', e => {
                        container.querySelector('.filter-tab.active')?.classList.remove('active');
                        e.target.classList.add('active');
                        const val = e.target.dataset.subcategory;
                        this.advancedFilters.subcategories = val ? [val] : [];
                        this.filterAndDisplayTools();
                        this.updateFilterCount();
                    });
                });
            }

            renderHeaderFilters() {
                const container = document.getElementById('headerFilters');
                if (!container) return;
                container.innerHTML = '';
                const regionSelect = document.createElement('select');
                regionSelect.id = 'headerRegionFilter';
                regionSelect.className = 'header-filter';
                const defaultRegion = document.createElement('option');
                defaultRegion.value = '';
                defaultRegion.textContent = 'All Regions';
                regionSelect.appendChild(defaultRegion);
                this.allRegions.forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r;
                    opt.textContent = r;
                    regionSelect.appendChild(opt);
                });

                const categorySelect = document.createElement('select');
                categorySelect.id = 'headerCategoryFilter';
                categorySelect.className = 'header-filter';
                const defaultCat = document.createElement('option');
                defaultCat.value = '';
                defaultCat.textContent = 'All Categories';
                categorySelect.appendChild(defaultCat);
                this.allCategories.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c;
                    opt.textContent = c;
                    categorySelect.appendChild(opt);
                });

                container.appendChild(regionSelect);
                container.appendChild(categorySelect);
                regionSelect.addEventListener('change', e => {
                    const val = e.target.value;
                    this.advancedFilters.regions = val ? [val] : [];
                    this.filterAndDisplayTools();
                    this.updateFilterCount();
                });
                categorySelect.addEventListener('change', e => {
                    const val = e.target.value;
                    this.advancedFilters.categories = val ? [val] : [];
                    this.currentFilter = this.advancedFilters.categories.length === 1 ? this.normalizeCategory(this.advancedFilters.categories[0]) : 'ALL';
                    this.renderSubcategoryTabs(this.currentFilter === 'ALL' ? null : this.currentFilter);
                    this.filterAndDisplayTools();
                    this.updateFilterCount();
                });
            }

            updateVisibleCounts() {
                const categories = this.enabledCategories;
                const categoryCounts = categories.map(category => {
                    const count = this.filteredTools.filter(tool => tool.category === category).length;
                    const countElement = document.getElementById(`count-${category}`);
                    if (countElement) {
                        countElement.textContent = count;
                    }
                    return count;
                });

                const visibleTotal = this.filteredTools.length;
                const filtersActive = this.searchTerm ||
                    this.advancedFilters.features.length || this.advancedFilters.hasVideo ||
                    this.advancedFilters.regions.length || this.advancedFilters.categories.length ||
                    this.advancedFilters.subcategories.length;

                const totalTools = document.getElementById('totalTools');
                if (totalTools) {
                    totalTools.textContent = filtersActive ? visibleTotal : this.TREASURY_TOOLS.length;
                }

                const totalCategories = document.getElementById('totalCategories');
                if (totalCategories) {
                    if (filtersActive) {
                        const visibleCategories = new Set(
                            this.filteredTools.map(t => t.categoryName).filter(Boolean)
                        );
                        totalCategories.textContent = visibleCategories.size;
                    } else {
                        totalCategories.textContent = this.allCategories.length;
                    }
                }

                const subtotal = categoryCounts.reduce((sum, n) => sum + n, 0);
                if (filtersActive && subtotal !== visibleTotal) {
                    console.warn(`Category counts (${subtotal}) do not sum to visible total (${visibleTotal})`);
                }
            }

            updateCounts() {
                const categories = this.enabledCategories;
                categories.forEach(category => {
                    const count = this.TREASURY_TOOLS.filter(tool => tool.category === category).length;
                    const countElement = document.getElementById(`count-${category}`);
                    if (countElement) {
                        countElement.textContent = count;
                    }
                });

                const totalTools = document.getElementById('totalTools');
                if (totalTools) {
                    totalTools.textContent = this.TREASURY_TOOLS.length;
                }

                const totalCategories = document.getElementById('totalCategories');
                if (totalCategories) {
                    totalCategories.textContent = this.allCategories.length;
                }
            }

            setupSideMenu() {

                const menuToggle = document.getElementById('sideMenuToggle');
                const externalMenuToggle = document.getElementById('externalMenuToggle');
                const sideMenu = document.getElementById('sideMenu');
                const overlay = document.getElementById('sideMenuOverlay');

                if (menuToggle) menuToggle.addEventListener('click', () => this.toggleSideMenu());
                if (externalMenuToggle) externalMenuToggle.addEventListener('click', () => this.toggleSideMenu());
                if (overlay) overlay.addEventListener('click', () => this.closeSideMenu());
                if (sideMenu) sideMenu.addEventListener('click', (e) => {
                    if (this.isMobile()) return;
                    if (!this.sideMenuOpen && e.target === sideMenu) {
                        e.stopPropagation();
                        this.openSideMenu();
                    }
                });

                // Setup swipe-to-close for side menu
                this.setupSwipeToClose('sideMenu', 'sideMenu', () => this.closeSideMenu());

                this.setupAdvancedFilters();
                this.setupViewOptions();
                this.setupQuickActions();

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && this.sideMenuOpen) this.closeSideMenu();
                });
            }

            setupAdvancedFilters() {
                this.populateTagFilters();
                this.setupTagSearch();
                const showMore = document.getElementById('showMoreTagFilters');
                const showLess = document.getElementById('showLessTagFilters');
                const extra = document.getElementById('extraTagFilters');
                if (showMore && showLess && extra) {
                    showMore.addEventListener('click', () => {
                        extra.style.display = 'block';
                        showMore.style.display = 'none';
                        showLess.style.display = 'inline-block';
                    });
                    showLess.addEventListener('click', () => {
                        extra.style.display = 'none';
                        showLess.style.display = 'none';
                        showMore.style.display = 'inline-block';
                    });
                }

                const featureCheckboxes = document.querySelectorAll('#tagFilters input[type="checkbox"]');
                featureCheckboxes.forEach(cb => {
                    cb.addEventListener('change', () => {
                        this.updateFeatureFilters();
                        this.filterAndDisplayTools();
                        this.updateFilterCount();
                    });
                });

                const hasVideoFilter = document.getElementById('hasVideoFilter');
                if (hasVideoFilter) {
                    hasVideoFilter.addEventListener('change', (e) => {
                        this.advancedFilters.hasVideo = e.target.checked;
                        this.filterAndDisplayTools();
                        this.updateFilterCount();
                    });
                }

                const sortFilter = document.getElementById('sortFilter');
                if (sortFilter) {
                    sortFilter.addEventListener('change', (e) => {
                        this.currentSort = e.target.value;
                        this.filterAndDisplayTools();
                    });
                }
            }

            setupViewOptions() {
                const viewOptions = document.querySelectorAll('.view-option');
                viewOptions.forEach(opt => {
                    opt.addEventListener('click', () => {
                        viewOptions.forEach(o => o.classList.remove('active'));
                        opt.classList.add('active');
                        this.currentView = opt.dataset.view;
                        this.applyViewStyles();
                    });
                });

                const groupByFilter = document.getElementById('groupByFilter');
                if (groupByFilter) {
                    groupByFilter.addEventListener('change', (e) => {
                        this.groupByCategory = e.target.value === 'category';
                        this.filterAndDisplayTools();
                    });
                }
            }

            setupQuickActions() {
                const clearAll = document.getElementById('clearAllFilters');
                if (clearAll) clearAll.addEventListener('click', () => this.clearAllFilters());

                const resetBtn = document.getElementById('resetToDefaults');
                if (resetBtn) resetBtn.addEventListener('click', () => this.resetToDefaults());

                const applyBtn = document.getElementById('applyFilters');
                if (applyBtn) applyBtn.addEventListener('click', () => {
                    this.filterAndDisplayTools();
                    this.closeSideMenu();
                });
            }

            toggleSideMenu() {
                if (this.sideMenuOpen) this.closeSideMenu();
                else this.openSideMenu();
            }

            openSideMenu() {
                this.closeShortlistMenu();
                const sideMenu = document.getElementById('sideMenu');
                const overlay = document.getElementById('sideMenuOverlay');
                const toggle = document.getElementById('sideMenuToggle');
                const externalToggle = document.getElementById('externalMenuToggle');

                sideMenu?.classList.add('open');
                portalRoot.classList.add('side-menu-open');
                overlay?.classList.add('show');
                toggle?.classList.add('active');
                if (externalToggle) {
                    externalToggle.classList.add('active');
                    externalToggle.style.display = 'none';
                }
                if (this.isMobile()) {
                    document.body.style.overflow = 'hidden';
                    document.body.style.position = 'fixed';
                    document.body.style.width = '100%';
                } else {
                    portalRoot.style.overflow = 'hidden';
                }
                document.addEventListener('click', this.handleOutsideSideMenuClick, true);
                this.sideMenuOpen = true;
            }

            closeSideMenu() {
                const sideMenu = document.getElementById('sideMenu');
                const overlay = document.getElementById('sideMenuOverlay');
                const toggle = document.getElementById('sideMenuToggle');
                const externalToggle = document.getElementById('externalMenuToggle');

                sideMenu?.classList.remove('open');
                overlay?.classList.remove('show');
                toggle?.classList.remove('active');
                if (externalToggle) {
                    externalToggle.classList.remove('active');
                    externalToggle.style.display = this.isMobile() ? 'none' : 'flex';
                }
                portalRoot.classList.remove('side-menu-open');
                if (this.isMobile()) {
                    document.body.style.overflow = '';
                    document.body.style.position = '';
                    document.body.style.width = '';
                } else {
                    portalRoot.style.overflow = '';
                }
                document.removeEventListener('click', this.handleOutsideSideMenuClick, true);
                this.sideMenuOpen = false;
            }

            setupShortlistMenu() {

                const menuToggle = document.getElementById('shortlistMenuToggle');
                const overlay = document.getElementById('shortlistMenuOverlay');
                const externalToggle = document.getElementById('externalShortlistToggle');
                const container = document.getElementById('shortlistContainer');
                const clearBtn = document.getElementById('clearShortlist');
                const exportBtn = document.getElementById('exportShortlistBtn');

                if (menuToggle) menuToggle.addEventListener('click', () => this.toggleShortlistMenu());
                if (externalToggle) externalToggle.addEventListener('click', () => this.toggleShortlistMenu());
                if (overlay) {
                    overlay.addEventListener('dragover', e => e.preventDefault());
                    overlay.addEventListener('drop', e => e.preventDefault());
                }

                // Setup swipe-to-close for shortlist menu
                this.setupSwipeToClose('shortlistMenu', 'shortlistMenu', () => this.closeShortlistMenu());
                const shortlistMenu = document.getElementById('shortlistMenu');
                if (shortlistMenu) shortlistMenu.addEventListener('click', (e) => {
                    if (this.isMobile()) return;
                    if (!this.shortlistMenuOpen && e.target === shortlistMenu) {
                        e.stopPropagation();
                        this.openShortlistMenu();
                    }
                });
                if (clearBtn) clearBtn.addEventListener('click', () => this.clearShortlist());
                if (exportBtn) exportBtn.addEventListener('click', () => this.exportShortlist());

                if (container) {
                    let draggedCard = null;
                    let touchDraggedCard = null;
                    let dragPreview = null;
                    let pointerMoveHandler = null;
                    const addHighlight = () => container.classList.add('drag-over');
                    const removeHighlight = () => container.classList.remove('drag-over');

                    container.addEventListener('dragstart', e => {
                        if (e.target.classList.contains('shortlist-card')) {
                            draggedCard = e.target;
                            e.dataTransfer.setData('text/plain', e.target.dataset.name);
                            e.dataTransfer.effectAllowed = 'move';
                            e.dataTransfer.setDragImage(new Image(), 0, 0);
                            dragPreview = e.target.cloneNode(true);
                            dragPreview.classList.add('drag-preview');
                            dragPreview.style.width = `${e.target.offsetWidth}px`;
                            dragPreview.style.height = `${e.target.offsetHeight}px`;
                            portalRoot.appendChild(dragPreview);
                            pointerMoveHandler = ev => {
                                dragPreview.style.left = `${ev.clientX}px`;
                                dragPreview.style.top = `${ev.clientY}px`;
                            };
                            window.addEventListener('pointermove', pointerMoveHandler);
                            pointerMoveHandler(e);
                        } else {
                            draggedCard = null;
                        }
                    });

                    container.addEventListener('dragenter', addHighlight);
                    container.addEventListener('dragleave', e => {
                        if (e.target === container) removeHighlight();
                    });
                    container.addEventListener('dragover', e => {
                        e.preventDefault();
                        if (draggedCard) {
                            const target = e.target.closest('.shortlist-card');
                            if (target && target !== draggedCard) {
                                const rect = target.getBoundingClientRect();
                                const next = (e.clientY - rect.top) > rect.height / 2;
                                container.insertBefore(draggedCard, next ? target.nextSibling : target);
                            }
                        }
                    });
                    container.addEventListener('drop', e => {
                        e.preventDefault();
                        removeHighlight();
                        if (draggedCard) {
                            this.shortlist = Array.from(container.querySelectorAll('.shortlist-card')).map(card => {
                                const name = card.dataset.name;
                                return this.shortlist.find(i => i.tool.name === name);
                            });
                            draggedCard = null;
                            this.renderShortlist();
                        } else {
                            const name = e.dataTransfer.getData('text/plain');
                            const tool = this.TREASURY_TOOLS.find(t => t.name === name);
                            if (tool && !this.shortlist.some(i => i.tool.name === name)) {
                                this.shortlist.push({ tool, notes: '' });
                                this.renderShortlist();
                            }
                        }
                    });

                    container.addEventListener('dragend', () => {
                        window.removeEventListener('pointermove', pointerMoveHandler);
                        if (dragPreview) {
                            dragPreview.remove();
                            dragPreview = null;
                        }
                    });

                    container.addEventListener('touchstart', e => {
                        const card = e.target.closest('.shortlist-card');
                        if (card) {
                            touchDraggedCard = card;
                            addHighlight();
                            dragPreview = card.cloneNode(true);
                            dragPreview.classList.add('drag-preview');
                            dragPreview.style.width = `${card.offsetWidth}px`;
                            dragPreview.style.height = `${card.offsetHeight}px`;
                            portalRoot.appendChild(dragPreview);
                            const touch = e.touches[0];
                            dragPreview.style.left = `${touch.clientX}px`;
                            dragPreview.style.top = `${touch.clientY}px`;
                        }
                    }, { passive: true });
                    container.addEventListener('touchmove', e => {
                        if (!touchDraggedCard) return;
                        const touch = e.touches[0];
                        if (dragPreview) {
                            dragPreview.style.left = `${touch.clientX}px`;
                            dragPreview.style.top = `${touch.clientY}px`;
                        }
                        const target = document.elementFromPoint(touch.clientX, touch.clientY)?.closest('.shortlist-card');
                        if (target && target !== touchDraggedCard) {
                            const rect = target.getBoundingClientRect();
                            const next = (touch.clientY - rect.top) > rect.height / 2;
                            container.insertBefore(touchDraggedCard, next ? target.nextSibling : target);
                        }
                        e.preventDefault();
                    }, { passive: false });
                    container.addEventListener('touchend', () => {
                        if (!touchDraggedCard) return;
                        removeHighlight();
                        this.shortlist = Array.from(container.querySelectorAll('.shortlist-card')).map(card => {
                            const name = card.dataset.name;
                            return this.shortlist.find(i => i.tool.name === name);
                        });
                        touchDraggedCard = null;
                        if (dragPreview) {
                            dragPreview.remove();
                            dragPreview = null;
                        }
                        this.renderShortlist();
                    });

                }



                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && this.shortlistMenuOpen) this.closeShortlistMenu();
                });

                this.renderShortlist();
                this.setupPermanentToolPicker();
            }

            toggleShortlistMenu() {
                if (this.shortlistMenuOpen) this.closeShortlistMenu();
                else this.openShortlistMenu();
            }

            openShortlistMenu(trigger) {
                this.closeSideMenu();
                const menu = document.getElementById('shortlistMenu');
                const overlay = document.getElementById('shortlistMenuOverlay');
                const toggle = document.getElementById('shortlistMenuToggle');
                const externalToggle = document.getElementById('externalShortlistToggle');

               menu?.classList.add('open');
               portalRoot.classList.add('shortlist-menu-open');
               overlay?.classList.add('show');
               document.addEventListener('click', this.handleOutsideShortlistMenuClick);
               toggle?.classList.add('active');
               externalToggle?.classList.add('active');
               if (this.isMobile()) {
                   document.body.style.overflow = 'hidden';
                   document.body.style.position = 'fixed';
                   document.body.style.width = '100%';
               } else {
                   portalRoot.style.overflow = 'hidden';
               }
               this.shortlistMenuOpen = true;
               if (this.permanentToolPickerInitialized) {
                   this.updatePermanentToolPicker();
               }
           }

            closeShortlistMenu() {
                const menu = document.getElementById('shortlistMenu');
                const overlay = document.getElementById('shortlistMenuOverlay');
                const toggle = document.getElementById('shortlistMenuToggle');
                const externalToggle = document.getElementById('externalShortlistToggle');

                menu?.classList.remove('open');
                portalRoot.classList.remove('shortlist-menu-open');
                overlay?.classList.remove('show');
                document.removeEventListener('click', this.handleOutsideShortlistMenuClick);
               toggle?.classList.remove('active');
               externalToggle?.classList.remove('active');
               if (this.isMobile()) {
                   document.body.style.overflow = '';
                   document.body.style.position = '';
                   document.body.style.width = '';
               } else {
                   portalRoot.style.overflow = '';
               }
               this.shortlistMenuOpen = false;
            }

            renderShortlist() {
                const container = document.getElementById('shortlistContainer');
                const emptyMsg = document.getElementById('shortlistEmptyMessage');
                if (!container) return;

                container.innerHTML = '';

                if (emptyMsg) container.appendChild(emptyMsg);

                if (this.shortlist.length === 0) {
                    container.classList.add('empty');
                    if (emptyMsg) emptyMsg.classList.remove('visually-hidden');
                } else {
                    container.classList.remove('empty');
                    if (emptyMsg) emptyMsg.classList.add('visually-hidden');

                    this.shortlist.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'shortlist-card';
                        div.draggable = true;
                        div.dataset.name = item.tool.name;
                        div.innerHTML = `
                            <div class="shortlist-card-header">
                                <div class="shortlist-card-title-wrapper">
                                    ${item.tool.logoUrl ? `<img class="shortlist-logo" src="${item.tool.logoUrl}" alt="${item.tool.name} logo">` : ''}
                                    <span class="shortlist-card-title">${item.tool.name}</span>
                                    ${item.tool.websiteUrl ? `<a class="shortlist-card-link" href="${item.tool.websiteUrl}" target="_blank" rel="noopener noreferrer" aria-label="Visit website">Visit Website</a>` : ''}
                                </div>
                                <div class="shortlist-card-buttons">
                                    <button class="move-up" data-name="${item.tool.name}" aria-label="Move up">▲</button>
                                    <button class="move-down" data-name="${item.tool.name}" aria-label="Move down">▼</button>
                                    <button class="remove-shortlist" data-name="${item.tool.name}" aria-label="Remove">×</button>
                                </div>
                            </div>
                            <textarea class="shortlist-note" data-name="${item.tool.name}" placeholder="Notes...">${item.notes || ''}</textarea>`;
                        container.appendChild(div);
                    });
                }
                container.querySelectorAll('.remove-shortlist').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const name = e.target.dataset.name;
                        this.shortlist = this.shortlist.filter(i => i.tool.name !== name);
                        this.renderShortlist();
                    });
                });
                container.querySelectorAll('.move-up').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const name = e.target.dataset.name;
                        const idx = this.shortlist.findIndex(i => i.tool.name === name);
                        if (idx > 0) {
                            const [item] = this.shortlist.splice(idx, 1);
                            this.shortlist.splice(idx - 1, 0, item);
                            this.renderShortlist();
                        }
                    });
                });
                container.querySelectorAll('.move-down').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const name = e.target.dataset.name;
                        const idx = this.shortlist.findIndex(i => i.tool.name === name);
                        if (idx < this.shortlist.length - 1 && idx !== -1) {
                            const [item] = this.shortlist.splice(idx, 1);
                            this.shortlist.splice(idx + 1, 0, item);
                            this.renderShortlist();
                        }
                    });
                });
                container.querySelectorAll('.shortlist-note').forEach(area => {
                    area.addEventListener('input', (e) => {
                        const name = e.target.dataset.name;
                        const item = this.shortlist.find(i => i.tool.name === name);
                        if (item) item.notes = e.target.value;
                    });
                });

                const exportBtn = document.getElementById('exportShortlistBtn');
                if (exportBtn) {
                    exportBtn.disabled = this.shortlist.length === 0;
                }

                // Always update the permanent tool picker
                this.updatePermanentToolPicker();
            }

            updatePermanentToolPicker() {
                const button = document.getElementById('permanentToolPicker')?.querySelector('.tool-picker-button');
                const dropdown = document.getElementById('permanentDropdown');
                const search = document.getElementById('permanentSearch');
                const list = document.getElementById('permanentList');

                if (!button || !dropdown || !search || !list) return;

                // Update available tools list
                const availableTools = this.TREASURY_TOOLS.filter(t =>
                    !this.shortlist.some(i => i.tool.name === t.name)
                );

                // Populate the list
                list.innerHTML = availableTools
                    .map(t => `<li data-name="${t.name}">${t.name}</li>`)
                    .join('');

                // Update button text
                button.textContent = availableTools.length > 0 ?
                    `Add a Tool (${availableTools.length} available)` :
                    'All tools added';
                button.disabled = availableTools.length === 0;

                // Clear search
                search.value = '';
                dropdown.style.display = 'none';
            }

            setupPermanentToolPicker() {
                const button = document.getElementById('permanentToolPicker')?.querySelector('.tool-picker-button');
                const dropdown = document.getElementById('permanentDropdown');
                const search = document.getElementById('permanentSearch');
                const list = document.getElementById('permanentList');

                if (!button || !dropdown || !search || !list) return;

                // Toggle dropdown
                button.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isVisible = dropdown.style.display === 'block';
                    dropdown.style.display = isVisible ? 'none' : 'block';
                    if (!isVisible) search.focus();
                });

                // Search functionality
                search.addEventListener('input', () => {
                    const term = search.value.toLowerCase();
                    list.querySelectorAll('li').forEach(li => {
                        const matches = li.textContent.toLowerCase().includes(term);
                        li.style.display = matches ? 'block' : 'none';
                    });
                });

                // Tool selection
                list.addEventListener('click', (e) => {
                    if (e.target.tagName === 'LI') {
                        const toolName = e.target.dataset.name;
                        const tool = this.TREASURY_TOOLS.find(t => t.name === toolName);
                        if (tool && !this.shortlist.some(i => i.tool.name === toolName)) {
                            this.shortlist.push({ tool, notes: '' });
                            this.renderShortlist();
                        }
                        dropdown.style.display = 'none';
                    }
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', (e) => {
                    const picker = document.getElementById('permanentToolPicker');
                    if (picker && !picker.contains(e.target)) {
                        dropdown.style.display = 'none';
                    }
                });

                // Initial update
                this.updatePermanentToolPicker();
                this.permanentToolPickerInitialized = true;
            }

            clearShortlist() {
                this.shortlist = [];
                this.renderShortlist();
            }


            setupBottomNav() {
                const search = document.getElementById('bottomSearch');
                const shortlist = document.getElementById('bottomShortlist');

                if (search) {
                    const handleSearch = (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        if (this.shortlistMenuOpen) {
                            this.closeShortlistMenu();
                        }

                        if (this.sideMenuOpen) {
                            this.closeSideMenu();
                        } else {
                            this.openSideMenu();
                            setTimeout(() => {
                                const input = document.getElementById('searchInput');
                                if (input) {
                                    input.focus();
                                    if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
                                        input.click();
                                    }
                                }
                            }, 350);
                        }
                    };

                    search.addEventListener('touchstart', (e) => e.preventDefault());
                    search.addEventListener('click', handleSearch);
                    search.addEventListener('touchend', (e) => {
                        e.preventDefault();
                        handleSearch(e);
                    });
                }

                if (shortlist) {
                    const handleShortlist = (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        if (this.sideMenuOpen) {
                            this.closeSideMenu();
                        }
                        this.toggleShortlistMenu();
                    };

                    shortlist.addEventListener('click', handleShortlist);
                    shortlist.addEventListener('touchend', (e) => {
                        e.preventDefault();
                        handleShortlist(e);
                    });
                }
            }


            updateFeatureFilters() {
                const cbs = document.querySelectorAll('#tagFilters input[type="checkbox"]');
                this.advancedFilters.features = Array.from(cbs).filter(cb => cb.checked).map(cb => cb.value);
            }

            applyViewStyles() {
                const grids = document.querySelectorAll('.tools-grid');
                grids.forEach(grid => {
                    grid.classList.remove('list-view');
                    if (this.currentView === 'list') {
                        grid.classList.add('list-view');
                        grid.style.gridTemplateColumns = '1fr';
                    } else {
                        if (this.isMobile()) {
                            grid.style.gridTemplateColumns = '1fr';
                        } else {
                            grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(320px, 1fr))';
                        }
                    }
                });
            }

            exportShortlist() {
                const data = this.shortlist.map(item => ({
                    name: item.tool.name,
                    category: item.tool.category,
                    website: item.tool.websiteUrl || '',
                    notes: item.notes || ''
                }));
                const csv = this.convertToCSV(data);
                this.downloadCSV(csv, 'shortlist.csv');
            }

            convertToCSV(data) {
                if (!data.length) return '';
                const headers = Object.keys(data[0]);
                return [
                    headers.join(','),
                    ...data.map(row => headers.map(h => `"${String(row[h]).replace(/"/g,'""')}"`).join(','))
                ].join('\n');
            }

            downloadCSV(csv, filename) {
                const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
                const link = document.createElement('a');
                if (link.download !== undefined) {
                    const url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', filename);
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    URL.revokeObjectURL(url);
                    document.body.removeChild(link);
                }
            }

            clearAllFilters() {
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.value = '';
                    this.searchTerm = '';
                }
                const tagSearchInput = document.getElementById('tagSearchInput');
                if (tagSearchInput) {
                    tagSearchInput.value = '';
                }
                const tagSearchClear = document.getElementById('tagSearchClear');
                if (tagSearchClear) tagSearchClear.style.display = 'none';
                this.filterTagCheckboxes('');
                document.querySelector('.filter-tab.active')?.classList.remove('active');
                this.currentFilter = 'ALL';
                this.renderSubcategoryTabs(null);

                this.advancedFilters = { features:[], hasVideo:false, regions:[], categories:[], subcategories:[] };

                const checkboxes = document.querySelectorAll('#tagFilters input[type="checkbox"],#hasVideoFilter,#regionFilters input[type="checkbox"],#categoryFilters input[type="checkbox"],#subcategoryFilters input[type="checkbox"]');
                checkboxes.forEach(cb => cb.checked = false);

                const headerRegion = document.getElementById('headerRegionFilter');
                if (headerRegion) headerRegion.value = '';
                const headerCategory = document.getElementById('headerCategoryFilter');
                if (headerCategory) headerCategory.value = '';

                this.filterAndDisplayTools();
                this.updateFilterCount();
            }

            resetToDefaults() {
                this.clearAllFilters();
                const sortFilter = document.getElementById('sortFilter');
                if (sortFilter) sortFilter.value = 'name';
                this.currentSort = 'name';

                document.querySelectorAll('.view-option').forEach(opt => opt.classList.remove('active'));
                document.querySelector('.view-option[data-view="grid"]')?.classList.add('active');
                this.currentView = 'grid';

                const groupByFilter = document.getElementById('groupByFilter');
                if (groupByFilter) groupByFilter.value = 'category';
                this.groupByCategory = true;

                this.applyViewStyles();
                this.filterAndDisplayTools();
            }

            updateFilterCount() {
                const { features, hasVideo, regions, categories, subcategories } = this.advancedFilters;
                let count = 0;
                count += features.length + regions.length + categories.length + subcategories.length;
                if (hasVideo) count++;
                const el = document.getElementById('filterCount');
                if (el) el.textContent = count > 0 ? `(${count})` : '';
            }
        }

        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, {
            passive: false
        });
