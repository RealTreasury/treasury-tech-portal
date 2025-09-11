const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal.js', 'utf8');

test('changing category clears subcategory selections', () => {
  const dom = new JSDOM('<div class="filter-tabs"></div><div id="subcategoryFilters"></div>', { runScripts: 'outside-only' });
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
  const TreasuryTechPortal = window.TreasuryTechPortal;

  const portal = Object.create(TreasuryTechPortal.prototype);
  portal.advancedFilters = { subcategories: [] };
  portal.allSubcategories = ['Sub1', 'Sub2', 'SubA'];
  portal.subcategoriesByCategory = { CASH: ['Sub1', 'Sub2'], LITE: ['SubA'] };
  portal.filterAndDisplayTools = () => {};
  portal.updateFilterCount = () => {};

  portal.populateSubcategoryFilters();

  // simulate an existing subcategory selection
  const firstCb = document.querySelector('#subcategoryFilters input');
  firstCb.checked = true;
  portal.advancedFilters.subcategories = ['Sub1'];

  portal.renderSubcategoryTabs('LITE');

    assert.equal(portal.advancedFilters.subcategories.length, 0);
    const checked = [...document.querySelectorAll('#subcategoryFilters input:checked')];
    assert.equal(checked.length, 0);
});
