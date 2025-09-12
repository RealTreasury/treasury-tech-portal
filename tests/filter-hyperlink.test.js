const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal-admin.js', 'utf8');

test('hyperlink filtering uses data-filter-value', async () => {
  const dom = new JSDOM(`
    <input type="search" id="treasury-portal-admin-search-input" />
    <table class="treasury-portal-admin-table">
      <thead><tr><th data-sort-key="website"></th></tr></thead>
      <tbody>
        <tr class="tp-filter-row">
          <td><input id="tp-filter-website" class="tp-filter-control" data-filter-key="website"></td>
        </tr>
        <tr id="row1">
          <td data-filter-value="https://example.com"><a href="https://example.com">Visit</a></td>
        </tr>
      </tbody>
    </table>
  `, { runScripts: 'outside-only' });

  const { window } = dom;
  global.window = window;
  global.document = window.document;

  window.eval(script);
  window.document.dispatchEvent(new window.Event('DOMContentLoaded'));

  const input = window.document.getElementById('tp-filter-website');
  const row = window.document.getElementById('row1');

  input.value = 'example.com';
  input.dispatchEvent(new window.Event('input', { bubbles: true }));
  await new Promise(r => setTimeout(r, 250));
  assert.equal(row.style.display, '', 'Row should be visible when filter matches data-filter-value');

  input.value = 'visit';
  input.dispatchEvent(new window.Event('input', { bubbles: true }));
  await new Promise(r => setTimeout(r, 250));
  assert.equal(row.style.display, 'none', 'Row should hide when filter does not match data-filter-value');
});
