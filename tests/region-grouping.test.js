const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal.js', 'utf8');

test('region filter keeps category sections grouped and intro video in TRMS', () => {
  const html = `\
    <div class="treasury-portal">
      <div class="container">
        <div class="intro-video-target"></div>
        <div id="noResults"></div>
        <div id="listViewContainer"></div>
        <div class="category-section" data-category="CASH" style="display:block;">
          <div class="category-header"><div class="category-video-target"></div></div>
          <div class="tools-grid" id="tools-CASH"></div>
        </div>
        <div class="category-section" data-category="LITE" style="display:block;">
          <div class="category-header"><div class="category-video-target"></div></div>
          <div class="tools-grid" id="tools-LITE"></div>
        </div>
        <div class="category-section" data-category="TRMS" style="display:block;">
          <div class="category-header"><div class="category-video-target"></div></div>
          <div class="tools-grid" id="tools-TRMS"></div>
        </div>
      </div>
    </div>`;

  const dom = new JSDOM(html, { runScripts: 'outside-only' });
  const { window } = dom;
  global.window = window;
  global.document = window.document;
  window.ResizeObserver = class { constructor(){} observe(){} };
  window.MutationObserver = class { constructor(){} observe(){} };
  window.matchMedia = () => ({ matches: false, addEventListener() {}, removeEventListener() {} });

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

  window.TTP_DATA = {
    available_categories: ['CASH', 'LITE', 'TRMS'],
    enabled_categories: ['CASH', 'LITE', 'TRMS'],
    category_labels: { CASH: 'Cash Tools', LITE: 'TMS-Lite', TRMS: 'TRMS' }
  };

  window.eval(`${script}\nwindow.TreasuryTechPortal = TreasuryTechPortal;`);
  const Portal = window.TreasuryTechPortal;
  const portal = Object.create(Portal.prototype);
  portal.isMobile = () => false;
  portal.createToolCard = () => window.document.createElement('div');
  portal.updateVisibleCounts = () => {};
  portal.CATEGORY_TAGS = {};
  portal.categoryLabels = window.TTP_DATA.category_labels;
  portal.enabledCategories = ['CASH', 'LITE', 'TRMS'];
  portal.currentFilter = 'ALL';
  portal.groupByCategory = true;
  portal.searchTerm = '';
  portal.advancedFilters = {
    regions: ['NORAM'],
    categories: [],
    subcategories: [],
    features: [],
    hasVideo: false
  };
  portal.TREASURY_TOOLS = [
    { name: 'CashTool', category: 'CASH', categoryName: 'Cash Tools', regions: ['Europe'] },
    { name: 'LiteTool', category: 'LITE', categoryName: 'TMS-Lite', regions: ['NORAM'] },
    { name: 'TrmsTool', category: 'TRMS', categoryName: 'TRMS', regions: ['Asia'] }
  ];

  portal.filterAndDisplayTools();
  portal.handleIntroVideoRegion();

  ['CASH', 'LITE', 'TRMS'].forEach(cat => {
    const section = window.document.querySelector(`.category-section[data-category="${cat}"]`);
    assert.ok(section, `section for ${cat} exists`);
    assert.notStrictEqual(section.style.display, 'none', `section for ${cat} is visible`);
  });

  const introVideo = window.document.querySelector('.intro-video-target');
  const trmsHeader = window.document.querySelector('.category-section[data-category="TRMS"] .category-header');
  assert.ok(introVideo, 'intro video exists');
  assert.strictEqual(introVideo.parentElement, trmsHeader, 'intro video attached to TRMS header');
});
