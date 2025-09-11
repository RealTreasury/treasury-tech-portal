const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal.js', 'utf8');

test('subcategory tab buttons are type button and do not submit forms', () => {
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
  portal.subcategoriesByCategory = { TEST: ['One'] };
  portal.advancedFilters = { subcategories: [] };
  portal.filterAndDisplayTools = () => {};
  portal.updateFilterCount = () => {};

  portal.renderSubcategoryTabs('TEST');
  const buttons = window.document.querySelectorAll('.filter-tab');
  assert.equal(buttons.length, 2);
  buttons.forEach(btn => assert.equal(btn.type, 'button'));

  let submitted = false;
  window.document.querySelector('form').addEventListener('submit', e => {
    submitted = true;
    e.preventDefault();
  });
  buttons[1].click();
  assert.equal(submitted, false);
});
