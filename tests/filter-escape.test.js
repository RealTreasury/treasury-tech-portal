const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal.js', 'utf8');

test('filters render escaped user input', () => {
  const dom = new JSDOM('<div id="categoryFilters"></div><div id="subcategoryFilters"></div><div id="headerFilters"></div>', { runScripts: 'outside-only' });
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
  const Portal = window.TreasuryTechPortal;
  const portal = Object.create(Portal.prototype);
  portal.allCategories = ['Normal', 'Bad <img src=x onerror=1>'];
  portal.allSubcategories = ['Sub <b>bold</b>'];
  portal.allRegions = ['US', 'EU<script>alert(1)</script>'];
  portal.advancedFilters = { categories: [], subcategories: [], regions: [] };
  portal.filterAndDisplayTools = () => {};
  portal.updateFilterCount = () => {};
  portal.renderSubcategoryTabs = () => {};
  portal.normalizeCategory = c => c;

  portal.populateCategoryFilters();
  portal.populateSubcategoryFilters();
  portal.renderHeaderFilters();

  const catLabels = [...document.querySelectorAll('#categoryFilters label')];
  assert.equal(catLabels.length, 2);
  const badCatLabel = catLabels.find(l => l.textContent.includes('Bad'));
  assert.ok(badCatLabel);
  assert.equal(badCatLabel.textContent, 'Bad <img src=x onerror=1>');
  assert.equal(badCatLabel.querySelector('img'), null);

  const subLabels = [...document.querySelectorAll('#subcategoryFilters label')];
  assert.equal(subLabels.length, 1);
  assert.equal(subLabels[0].textContent, 'Sub <b>bold</b>');
  assert.equal(subLabels[0].querySelector('b'), null);

  const headerCatOpts = [...document.querySelectorAll('#headerCategoryFilter option')];
  const badCatOpt = headerCatOpts.find(o => o.value.includes('<img'));
  assert.ok(badCatOpt);
  assert.equal(badCatOpt.textContent, 'Bad <img src=x onerror=1>');
  assert.equal(document.querySelector('#headerCategoryFilter img'), null);

  const headerRegionOpts = [...document.querySelectorAll('#headerRegionFilter option')];
  const badRegionOpt = headerRegionOpts.find(o => o.value.includes('script'));
  assert.ok(badRegionOpt);
  assert.equal(badRegionOpt.textContent, 'EU<script>alert(1)</script>');
  assert.equal(document.querySelector('#headerRegionFilter script'), null);
});

