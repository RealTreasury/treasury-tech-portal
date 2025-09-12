const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal.js', 'utf8');

test('region filter keeps categories grouped and intro video on TRMS header', () => {
  const dom = new JSDOM(`
    <div class="treasury-portal">
      <div class="header"><div class="intro-video-target"></div></div>
      <div class="category-section" data-category="CASH">
        <div class="category-header" data-category="CASH"><div class="category-count"></div></div>
        <div id="tools-CASH"></div>
      </div>
      <div class="category-section" data-category="TRMS">
        <div class="category-header" data-category="TRMS">
          <div class="category-count"></div>
          <div class="category-video-target"></div>
        </div>
        <div id="tools-TRMS"></div>
      </div>
    </div>
    <div id="listViewContainer"></div>
    <div id="noResults"></div>
  `, { runScripts: 'outside-only' });

  const { window } = dom;
  global.window = window;
  global.document = window.document;
  window.ResizeObserver = class { constructor() {} observe() {} };
  window.MutationObserver = class { constructor() {} observe() {} };

  const origDocAdd = window.document.addEventListener.bind(window.document);
  window.document.addEventListener = (type, listener, opts) => {
    if (type !== 'DOMContentLoaded') {
      origDocAdd(type, listener, opts);
    }
  };
  const origWinAdd = window.addEventListener.bind(window);
  window.addEventListener = (type, listener, opts) => {
    if (type !== 'load') {
      origWinAdd(type, listener, opts);
    }
  };

  window.TTP_DATA = {
    available_categories: ['CASH', 'TRMS'],
    enabled_categories: ['CASH', 'TRMS'],
    category_labels: { CASH: 'Cash Tools', TRMS: 'TRMS' }
  };

  window.eval(`${script}\nwindow.TreasuryTechPortal = TreasuryTechPortal;`);
  const Portal = window.TreasuryTechPortal;

  const portal = Object.create(Portal.prototype);
  portal.isMobile = () => false;
  portal.TREASURY_TOOLS = [
    { name: 'CashTool', category: 'CASH', regions: ['NORAM'] },
    { name: 'TrmsTool', category: 'TRMS', regions: ['NORAM'] }
  ];
  portal.enabledCategories = ['CASH', 'TRMS'];
  portal.categoryLabels = window.TTP_DATA.category_labels;
  portal.groupByCategory = true;
  portal.searchTerm = '';
  portal.currentFilter = 'ALL';
  portal.currentSort = 'name';
  portal.advancedFilters = { regions: ['NORAM'], features: [], hasVideo: false, categories: [], subcategories: [] };
  portal.updateVisibleCounts = () => {};

  portal.filterAndDisplayTools();
  portal.handleIntroVideoRegion();

  const cashSection = document.querySelector('.category-section[data-category="CASH"]');
  const trmsSection = document.querySelector('.category-section[data-category="TRMS"]');
  assert.equal(cashSection.style.display, 'block');
  assert.equal(trmsSection.style.display, 'block');

  const trmsHeader = trmsSection.querySelector('.category-header');
  const introVideo = document.querySelector('.intro-video-target');
  assert.equal(introVideo.parentElement, trmsHeader);
});

