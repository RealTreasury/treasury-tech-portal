const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal.js', 'utf8');

test('filter tab buttons do not submit forms', () => {
  const dom = new JSDOM(`
    <form id="testForm">
      <div class="treasury-portal"></div>
      <div id="subcategoryFilters"></div>
      <div class="filter-tabs"></div>
    </form>
  `, { runScripts: 'outside-only' });
  const { window } = dom;
  global.window = window;
  global.document = window.document;
  window.ResizeObserver = class { constructor() {} observe() {} };
  window.MutationObserver = class { constructor() {} observe() {} };

  // Prevent DOMContentLoaded/load handlers from executing during tests
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
  portal.subcategoriesByCategory = { CASH: ['SubA'] };
  portal.advancedFilters = { subcategories: [] };
  portal.filterAndDisplayTools = () => {};
  portal.updateFilterCount = () => {};

  let submitted = false;
  const form = document.getElementById('testForm');
  form.addEventListener('submit', e => {
    submitted = true;
    e.preventDefault();
  });

  portal.renderSubcategoryTabs('CASH');
  const subBtn = document.querySelector('.filter-tab[data-subcategory="SubA"]');
  subBtn.click();

  assert.equal(submitted, false);
  assert.deepEqual([...portal.advancedFilters.subcategories], ['SubA']);
});
