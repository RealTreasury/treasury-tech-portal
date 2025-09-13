const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal.js', 'utf8');

test('filterAndDisplayTools updates mobile product count to filtered length', () => {
  const dom = new JSDOM(`
    <div id="mobileProductCount"></div>
    <div id="totalTools"></div>
    <div id="noResults"></div>
  `, { runScripts: 'outside-only' });
  const { window } = dom;
  global.window = window;
  global.document = window.document;
  window.ResizeObserver = class { constructor() {} observe() {} };
  window.MutationObserver = class { constructor() {} observe() {} };

  window.eval(`${script}\nwindow.TreasuryTechPortal = TreasuryTechPortal;`);
  const Portal = window.TreasuryTechPortal;
  const portal = Object.create(Portal.prototype);

  portal.TREASURY_TOOLS = [
    { name: 'Cash Tool', category: 'CASH' },
    { name: 'Lite Tool', category: 'LITE' }
  ];
  portal.enabledCategories = ['CASH', 'LITE'];
  portal.allCategories = [];
  portal.searchTerm = 'Cash';
  portal.advancedFilters = { features: [], hasVideo: false, regions: [], categories: [], subcategories: [] };
  portal.currentFilter = 'ALL';
  portal.currentSort = 'name';
  portal.displayFilteredTools = () => {};
  portal.updateIntroVideoSource = () => {};

  portal.filterAndDisplayTools();

  assert.equal(portal.filteredTools.length, 1);
  assert.equal(document.getElementById('mobileProductCount').textContent, '1');
});
