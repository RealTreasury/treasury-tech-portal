const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal.js', 'utf8');

test('updateVisibleCounts updates mobile product count with filtered length', () => {
  const dom = new JSDOM(`
    <div class="treasury-portal"></div>
    <span id="mobileProductCount"></span>
    <div id="count-CASH"></div>
    <div id="count-LITE"></div>
    <div id="totalTools"></div>
    <div id="totalCategories"></div>
  `, { runScripts: 'outside-only' });
  const { window } = dom;
  global.window = window;
  global.document = window.document;
  window.ResizeObserver = class { constructor() {} observe() {} };
  window.MutationObserver = class { constructor() {} observe() {} };

  window.TTP_DATA = {
    available_categories: ['CASH', 'LITE'],
    enabled_categories: ['CASH', 'LITE'],
    category_labels: { CASH: 'Cash', LITE: 'TMS-Lite' }
  };

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
  const TreasuryTechPortal = window.TreasuryTechPortal;
  const portal = Object.create(TreasuryTechPortal.prototype);

  portal.enabledCategories = ['CASH', 'LITE'];
  portal.allCategories = ['CASH', 'LITE'];
  portal.filteredTools = [
    { category: 'CASH', categoryName: 'Cash' },
    { category: 'LITE', categoryName: 'TMS-Lite' }
  ];
  portal.TREASURY_TOOLS = [
    { category: 'CASH', categoryName: 'Cash' },
    { category: 'LITE', categoryName: 'TMS-Lite' },
    { category: 'CASH', categoryName: 'Cash' }
  ];
  portal.searchTerm = 'x';
  portal.advancedFilters = { features: [], hasVideo: false, regions: [], categories: [], subcategories: [] };

  portal.updateVisibleCounts();

  const mobileCount = document.getElementById('mobileProductCount').textContent;
  assert.equal(mobileCount, '2');
});
