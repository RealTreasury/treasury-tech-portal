const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal.js', 'utf8');

test('renderSubcategoryTabs clears side menu subcategory selections', () => {
  const dom = new JSDOM(`
    <div class="treasury-portal"></div>
    <div id="subcategoryFilters">
      <div class="checkbox-item"><input type="checkbox" value="SubA" checked></div>
      <div class="checkbox-item"><input type="checkbox" value="SubB" checked></div>
    </div>
    <div class="filter-tabs"></div>
  `, { runScripts: 'outside-only' });
  const { window } = dom;
  global.window = window;
  global.document = window.document;
  window.ResizeObserver = class { constructor() {} observe() {} };
  window.MutationObserver = class { constructor() {} observe() {} };

  window.TTP_DATA = {
    available_categories: ['CASH', 'LITE', 'TRMS'],
    enabled_categories: ['CASH', 'LITE', 'TRMS'],
    category_labels: { CASH: 'Cash Tools', LITE: 'TMS-Lite', TRMS: 'TRMS' },
    category_icons: { CASH: '💰', LITE: '⚡', TRMS: '🏢' }
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
  portal.subcategoriesByCategory = { CASH: ['SubA', 'SubB'] };
  portal.advancedFilters = { subcategories: ['SubA'] };

  portal.renderSubcategoryTabs('CASH');

  const checkboxes = [...document.querySelectorAll('#subcategoryFilters input[type="checkbox"]')];
  assert.equal(portal.advancedFilters.subcategories.length, 0);
  checkboxes.forEach(cb => assert.equal(cb.checked, false));
});
