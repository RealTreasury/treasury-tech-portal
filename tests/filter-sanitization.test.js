const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

test('filters render with escaped user input', async () => {
  const dom = new JSDOM(`<!DOCTYPE html><div id="categoryFilters"></div><div id="subcategoryFilters"></div><div id="headerFilters"></div>`);
  const sandbox = {
    window: dom.window,
    document: dom.window.document,
    navigator: dom.window.navigator,
    ResizeObserver: class { observe() {} unobserve() {} disconnect() {} },
    MutationObserver: class { observe() {} disconnect() {} },
    TTP_DATA: { rest_url: 'https://example.com' },
    fetch: async () => ({ json: async () => [] }),
    setTimeout: () => 0,
    clearTimeout: () => {},
    console,
    URL,
    URLSearchParams
  };
  const script = fs.readFileSync(path.resolve(__dirname, '../assets/js/treasury-portal.js'), 'utf-8');
  vm.runInNewContext(script, sandbox);
  const PortalClass = vm.runInNewContext('TreasuryTechPortal', sandbox);
  PortalClass.prototype.init = function() {};
  PortalClass.prototype.fetchTools = async function() { return []; };
  const portal = new PortalClass();
  await portal.toolsLoaded;
  portal.allCategories = ['<img src=x onerror=alert(1)>'];
  portal.allSubcategories = ['<script>alert(2)</script>'];
  portal.allRegions = ['EU & Asia'];
  portal.advancedFilters = { categories: [], subcategories: [], regions: [], features: [], hasVideo: false };
  portal.populateCategoryFilters();
  portal.populateSubcategoryFilters();
  portal.renderHeaderFilters();
  const { document } = sandbox;
  assert.equal(document.querySelectorAll('#categoryFilters img').length, 0);
  assert.equal(document.querySelector('#categoryFilters label').textContent, '<img src=x onerror=alert(1)>');
  assert.equal(document.querySelectorAll('#subcategoryFilters script').length, 0);
  assert.equal(document.querySelector('#subcategoryFilters label').textContent, '<script>alert(2)</script>');
  assert.equal(document.querySelector('#headerRegionFilter option[value="EU & Asia"]').textContent, 'EU & Asia');
});
