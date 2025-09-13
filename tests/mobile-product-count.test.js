const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal.js', 'utf8');

test('updateVisibleCounts updates mobile product count', () => {
  const dom = new JSDOM(`
    <div id="mobileProductCount"></div>
    <div id="totalTools"></div>
  `, { runScripts: 'outside-only' });
  const { window } = dom;
  global.window = window;
  global.document = window.document;
  window.ResizeObserver = class { constructor() {} observe() {} };
  window.MutationObserver = class { constructor() {} observe() {} };

  window.eval(`${script}\nwindow.TreasuryTechPortal = TreasuryTechPortal;`);
  const TreasuryTechPortal = window.TreasuryTechPortal;
  const portal = Object.create(TreasuryTechPortal.prototype);

  portal.enabledCategories = [];
  portal.filteredTools = [{}, {}];
  portal.TREASURY_TOOLS = [{}, {}, {}];
  portal.allCategories = [];
  portal.searchTerm = 'a';
  portal.advancedFilters = { features: [], hasVideo: false, regions: [], categories: [], subcategories: [] };

  portal.updateVisibleCounts();

  assert.equal(document.getElementById('mobileProductCount').textContent, '2');
  assert.equal(document.getElementById('totalTools').textContent, '2');
});
