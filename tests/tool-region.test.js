const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal.js', 'utf8');

test('tool regions render in card', () => {
  const dom = new JSDOM('<div class="treasury-portal"></div>', { runScripts: 'outside-only' });
  const { window } = dom;
  global.window = window;
  global.document = window.document;
  window.ResizeObserver = class { constructor() {} observe() {} };
  window.MutationObserver = class { constructor() {} observe() {} };

  window.TTP_DATA = {
    available_categories: ['CASH'],
    enabled_categories: ['CASH'],
    category_labels: { CASH: 'Cash Tools' }
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
  portal.isMobile = () => false;
  portal.CATEGORY_TAGS = {};
  portal.categoryLabels = window.TTP_DATA.category_labels;

  const tool = { name: 'Vendor', category: 'CASH', desc: '', regions: ['North America', 'Europe'] };
  const card = portal.createToolCard(tool, 'CASH');

  const regionEl = card.querySelector('.tool-region');
  assert.ok(regionEl);
  assert.strictEqual(regionEl.textContent, 'North America, Europe');
});

