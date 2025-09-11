const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal.js', 'utf8');

test('filterAndDisplayTools keeps tools from all categories', () => {
  const dom = new JSDOM('<div id="listViewContainer"></div><div id="noResults"></div>', { runScripts: 'outside-only' });
  const { window } = dom;
  global.window = window;
  global.document = window.document;
  window.ResizeObserver = class { constructor() {} observe() {} };
  window.MutationObserver = class { constructor() {} observe() {} };

  const origDocAdd = window.document.addEventListener.bind(window.document);
  window.document.addEventListener = (type, listener, options) => {
    if (type !== 'DOMContentLoaded') {
      origDocAdd(type, listener, options);
    }
  };
  const origWinAdd = window.addEventListener.bind(window);
  window.addEventListener = (type, listener, options) => {
    if (type !== 'load') {
      origWinAdd(type, listener, options);
    }
  };

  window.eval(`${script}\nwindow.TreasuryTechPortal = TreasuryTechPortal;`);
  const Portal = window.TreasuryTechPortal;
  const portal = Object.create(Portal.prototype);
  portal.TREASURY_TOOLS = [
    { name: 'Cash Tool', category: 'CASH' },
    { name: 'Lite Tool', category: 'LITE' }
  ];
  portal.enabledCategories = ['CASH'];
  portal.advancedFilters = { features: [], hasVideo: false, regions: [], categories: [], subcategories: [] };
  portal.searchTerm = '';
  portal.currentFilter = 'ALL';
  portal.currentSort = 'name';
  portal.displayFilteredTools = () => {};
  portal.updateVisibleCounts = () => {};

  portal.filterAndDisplayTools();
  assert.equal(portal.filteredTools.length, 2);
});

test('fetchTools expands enabledCategories with API data', async () => {
  const dom = new JSDOM(`
    <div id="loadingScreen"></div>
    <div class="treasury-portal"><div class="container"></div></div>
    <div id="bottomNav"></div>
  `, { runScripts: 'outside-only' });
  const { window } = dom;
  global.window = window;
  global.document = window.document;
  window.ResizeObserver = class { constructor() {} observe() {} };
  window.MutationObserver = class { constructor() {} observe() {} };
  const origDocAdd2 = window.document.addEventListener.bind(window.document);
  window.document.addEventListener = (type, listener, options) => {
    if (type !== 'DOMContentLoaded') {
      origDocAdd2(type, listener, options);
    }
  };
  const origWinAdd2 = window.addEventListener.bind(window);
  window.addEventListener = (type, listener, options) => {
    if (type !== 'load') {
      origWinAdd2(type, listener, options);
    }
  };
  window.TTP_DATA = {
    rest_url: 'https://example.test/api',
    category_labels: { CASH: 'Cash Tools', LITE: 'TMS-Lite', TRMS: 'TRMS' },
    category_icons: { CASH: '💰', LITE: '⚡', TRMS: '🏢' }
  };

  window.fetch = async () => ({
    json: async () => ([
      { name: 'Cash Tool', category: 'Cash' },
      { name: 'Lite Tool', category: 'Lite' }
    ])
  });
  global.fetch = window.fetch;

  window.eval(`${script}\nwindow.TreasuryTechPortal = TreasuryTechPortal;`);
  const Portal = window.TreasuryTechPortal;
  const portal = Object.create(Portal.prototype);
  portal.enabledCategories = ['CASH'];
  portal.availableCategories = [];
  portal.CATEGORY_TAGS = {};
  portal.categoryIcons = window.TTP_DATA.category_icons;
  portal.categoryLabels = window.TTP_DATA.category_labels;
  portal.advancedFilters = { regions: [], categories: [], subcategories: [], features: [], hasVideo: false };
  portal.currentFilter = 'ALL';
  portal.updateCounts = () => {};
  portal.populateCategoryTags = () => {};
  portal.populateRegionFilters = () => {};
  portal.populateCategoryFilters = () => {};
  portal.populateSubcategoryFilters = () => {};
  portal.renderHeaderFilters = () => {};
  portal.renderSubcategoryTabs = () => {};
  portal.filterAndDisplayTools = () => {};
  portal.applyViewStyles = () => {};

  await portal.fetchTools();
  const cats = portal.enabledCategories.slice().sort();
  assert.equal(cats.length, 2);
  assert.ok(cats.includes('CASH'));
  assert.ok(cats.includes('LITE'));
});

