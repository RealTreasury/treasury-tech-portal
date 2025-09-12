const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal-admin.js', 'utf8');

test('filtering uses data-filter-value for hyperlink fields', async () => {
  const html = `\
    <table class="treasury-portal-admin-table">
      <thead><tr><th data-sort-key="website"><div></div></th></tr></thead>
      <tbody>
        <tr class="tp-filter-row">
          <td><input class="tp-filter-control" data-filter-key="website" /></td>
        </tr>
        <tr><td data-filter-value="https://example.com"><a href="https://example.com">Visit</a></td></tr>
      </tbody>
    </table>`;

  const dom = new JSDOM(html, { runScripts: 'outside-only' });
  const { window } = dom;
  global.window = window;
  global.document = window.document;

  window.eval(script);
  window.document.dispatchEvent(new window.Event('DOMContentLoaded'));

  const input = window.document.querySelector('.tp-filter-control');
  input.value = 'example.com';
  input.dispatchEvent(new window.Event('input', { bubbles: true }));

  await new Promise(r => setTimeout(r, 300));

  const row = window.document.querySelector('tbody tr:not(.tp-filter-row)');
  assert.equal(row.style.display, '');
});
