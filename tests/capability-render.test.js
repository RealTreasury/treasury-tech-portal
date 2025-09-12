const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal.js', 'utf8');

function setupDom(html) {
  const dom = new JSDOM(html, { runScripts: 'outside-only' });
  const { window } = dom;
  global.window = window;
  global.document = window.document;
  window.ResizeObserver = class { constructor() {} observe() {} };
  window.MutationObserver = class { constructor() {} observe() {} };
  window.TTP_DATA = { available_categories: [], enabled_categories: [], category_labels: {} };

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
  window.eval('portalRoot = document.querySelector(".treasury-portal");');
  return window;
}

test('tool card capability show more/less toggles', () => {
  const window = setupDom(`
    <div class="treasury-portal">
      <div id="mainContent"></div>
    </div>
  `);
  const TreasuryTechPortal = window.TreasuryTechPortal;
  const portal = Object.create(TreasuryTechPortal.prototype);
  portal.isMobile = () => false;
  portal.categoryLabels = { TEST: 'Test' };
  portal.TREASURY_TOOLS = [{
    name: 'Tool1',
    subCategories: [],
    core_capabilities: ['A', 'B', 'C'],
    capabilities: ['D', 'E', 'F']
  }];

  portal.setupInteractions();
  const card = portal.createToolCard(portal.TREASURY_TOOLS[0], 'TEST');
  document.getElementById('mainContent').appendChild(card);

  const capContainer = card.querySelector('.tool-capabilities');
  assert.equal(capContainer.querySelectorAll('.tool-capability').length, 3);
  capContainer.querySelector('.show-more-capabilities-btn').dispatchEvent(new window.Event('click', { bubbles: true }));
  assert.equal(capContainer.querySelectorAll('.tool-capability').length, 6);
  capContainer.querySelector('.show-less-capabilities-btn').dispatchEvent(new window.Event('click', { bubbles: true }));
  assert.equal(capContainer.querySelectorAll('.tool-capability').length, 3);
});

test('modal capability sections expand and collapse', () => {
  const window = setupDom(`
    <div class="treasury-portal">
      <div id="toolModal" class="ttp-modal">
        <div class="ttp-modal-content">
          <div class="modal-header">
            <h3 id="modalTitle"></h3>
            <img id="modalToolLogo" />
            <a id="modalWebsiteLink"></a>
            <button id="modalClose"></button>
          </div>
          <div class="modal-body">
            <div class="feature-section">
              <h4>🎯 Overview</h4>
              <p id="modalSummary" class="tool-summary"></p>
              <p id="modalDescription"></p>
            </div>
            <div class="feature-section">
              <h4>🛠️ Core Capabilities</h4>
              <div id="modalCoreCapabilities" class="tool-capabilities"></div>
            </div>
            <div class="feature-section">
              <h4>🛠️ Additional Capabilities</h4>
              <div id="modalCapabilities" class="tool-capabilities"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  `);
  const TreasuryTechPortal = window.TreasuryTechPortal;
  const portal = Object.create(TreasuryTechPortal.prototype);
  portal.openModal = () => {};
  portal.TREASURY_TOOLS = [{
    name: 'Tool1',
    product_summary: 'Summary here',
    subCategories: ['Sub1', 'Sub2'],
    core_capabilities: ['C1', 'C2', 'C3', 'C4', 'C5', 'C6'],
    capabilities: ['A1', 'A2', 'A3', 'A4', 'A5', 'A6']
  }];

  portal.showToolModal(portal.TREASURY_TOOLS[0]);

  const coreContainer = document.getElementById('modalCoreCapabilities');
  const addContainer = document.getElementById('modalCapabilities');

  const descEl = document.getElementById('modalDescription');
  const summaryEl = document.getElementById('modalSummary');
  assert.equal(descEl.textContent, 'Sub1, Sub2');
  assert.equal(summaryEl.textContent, 'Summary here');

  assert.equal(coreContainer.querySelectorAll('.tool-capability').length, 5);
  coreContainer.querySelector('.show-more-modal-core-capabilities-btn').dispatchEvent(new window.Event('click', { bubbles: true }));
  assert.equal(coreContainer.querySelectorAll('.tool-capability').length, 6);
  coreContainer.querySelector('.show-less-modal-core-capabilities-btn').dispatchEvent(new window.Event('click', { bubbles: true }));
  assert.equal(coreContainer.querySelectorAll('.tool-capability').length, 5);

  assert.equal(addContainer.querySelectorAll('.tool-capability').length, 5);
  addContainer.querySelector('.show-more-modal-capabilities-btn').dispatchEvent(new window.Event('click', { bubbles: true }));
  assert.equal(addContainer.querySelectorAll('.tool-capability').length, 6);
  addContainer.querySelector('.show-less-modal-capabilities-btn').dispatchEvent(new window.Event('click', { bubbles: true }));
  assert.equal(addContainer.querySelectorAll('.tool-capability').length, 5);
});
