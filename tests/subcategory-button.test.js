const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal.js', 'utf8');

test('subcategory filter buttons do not submit forms', () => {
  const dom = new JSDOM('<form><div class="filter-tabs"></div></form>', { runScripts: 'outside-only' });
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
  const portal = Object.create(window.TreasuryTechPortal.prototype);
  portal.subcategoriesByCategory = { TEST: ['Sub'] };
  portal.advancedFilters = { subcategories: [] };
  let filterCalled = false;
  portal.filterAndDisplayTools = () => { filterCalled = true; };
  portal.updateFilterCount = () => {};

  portal.renderSubcategoryTabs('TEST');
  const btn = window.document.querySelector('.filter-tab[data-subcategory="Sub"]');
  assert.equal(btn.type, 'button');

  let submitCount = 0;
  const form = window.document.querySelector('form');
  form.addEventListener('submit', e => { submitCount++; e.preventDefault(); });

  btn.click();
  assert.equal(submitCount, 0);
  assert.deepEqual(Array.from(portal.advancedFilters.subcategories), ['Sub']);
  assert.ok(filterCalled);
});

