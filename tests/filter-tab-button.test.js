const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal.js', 'utf8');

test('subcategory tab buttons do not submit forms', () => {
  const dom = new JSDOM(`
    <div class="treasury-portal"></div>
    <form id="testForm"><div class="filter-tabs"></div></form>
    <div id="subcategoryFilters">
      <div class="checkbox-item"><input type="checkbox" value="Sub1"></div>
    </div>
  `, { runScripts: 'outside-only' });
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
  portal.subcategoriesByCategory = { CASH: ['Sub1'] };
  portal.advancedFilters = { subcategories: [] };
  portal.filterAndDisplayTools = () => {};
  portal.updateFilterCount = () => {};

  let submitted = 0;
  const form = document.getElementById('testForm');
  form.addEventListener('submit', e => {
    submitted++;
    e.preventDefault();
  });

  portal.renderSubcategoryTabs('CASH');
  const btn = document.querySelector('.filter-tab[data-subcategory="Sub1"]');
  btn.dispatchEvent(new window.Event('click', { bubbles: true }));

  assert.equal(btn.type, 'button');
  assert.equal(submitted, 0);
  assert.equal(portal.advancedFilters.subcategories.length, 1);
  assert.equal(portal.advancedFilters.subcategories[0], 'Sub1');
});
