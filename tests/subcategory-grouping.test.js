const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal.js', 'utf8');

test('tools render under subcategory headings', () => {
  const html = `\
    <div class="treasury-portal">
      <div class="container">
        <div id="noResults"></div>
        <div id="listViewContainer"></div>
        <div class="category-section" data-category="CASH" style="display:block;">
          <div class="category-header"><div class="category-video-target"></div></div>
          <div class="tools-grid" id="tools-CASH"></div>
        </div>
      </div>
    </div>`;

  const dom = new JSDOM(html, { runScripts: 'outside-only' });
  const { window } = dom;
  global.window = window;
  global.document = window.document;
  window.ResizeObserver = class { constructor(){} observe(){} };
  window.MutationObserver = class { constructor(){} observe(){} };
  window.matchMedia = () => ({ matches: false, addEventListener() {}, removeEventListener() {} });

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

  window.TTP_DATA = {
    available_categories: ['CASH'],
    enabled_categories: ['CASH'],
    category_labels: { CASH: 'Cash Tools' }
  };

  window.eval(`${script}\nwindow.TreasuryTechPortal = TreasuryTechPortal;`);
  const Portal = window.TreasuryTechPortal;
  const portal = Object.create(Portal.prototype);
  portal.isMobile = () => false;
  portal.createToolCard = tool => {
    const card = window.document.createElement('div');
    card.textContent = tool.name;
    return card;
  };
  portal.updateVisibleCounts = () => {};
  portal.CATEGORY_TAGS = {};
  portal.categoryLabels = window.TTP_DATA.category_labels;
  portal.enabledCategories = ['CASH'];
  portal.currentFilter = 'ALL';
  portal.groupByCategory = true;
  portal.searchTerm = '';
  portal.advancedFilters = { regions: [], categories: [], subcategories: [], features: [], hasVideo: false };
  portal.filteredTools = portal.TREASURY_TOOLS = [
    { name: 'AlphaTool', category: 'CASH', categoryName: 'Cash Tools', subCategories: ['Alpha'] },
    { name: 'BetaTool', category: 'CASH', categoryName: 'Cash Tools', subCategories: [] }
  ];

  portal.displayFilteredTools();

  const container = window.document.getElementById('tools-CASH');
  const headers = [...container.querySelectorAll('.subcategory-header')];
  const names = headers.map(h => h.childNodes[0].textContent.trim());
  assert.deepEqual(names, ['Alpha', 'Other']);

  const counts = headers.map(h => h.querySelector('.subcategory-count').textContent);
  assert.deepEqual(counts, ['1', '1']);

  const groups = container.querySelectorAll('.subcategory-group');
  const alphaGroup = groups[0].querySelector('.subcategory-grid');
  const otherGroup = groups[1].querySelector('.subcategory-grid');
  assert.ok(alphaGroup.textContent.includes('AlphaTool'));
  assert.ok(otherGroup.textContent.includes('BetaTool'));
});
