const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

const script = fs.readFileSync('assets/js/treasury-portal-admin.js', 'utf8');

test('admin table filters support multiple tokens', () => {
  const dom = new JSDOM(`\
    <input id="treasury-portal-admin-search-input" />\
    <table class="treasury-portal-admin-table">\
      <thead><tr><th data-sort-key="name"></th><th data-sort-key="vendor"></th></tr></thead>\
      <tbody>\
        <tr class="tp-filter-row">\
          <td><input class="tp-filter-control" data-filter-key="name" /></td>\
          <td><input class="tp-filter-control" data-filter-key="vendor" /></td>\
        </tr>\
        <tr><td>Tool A</td><td>Foo Bar Inc</td></tr>\
        <tr><td>Tool B</td><td>Foo Corp</td></tr>\
        <tr><td>Tool C</td><td>Bar Corp</td></tr>\
      </tbody>\
    </table>`, { runScripts: 'outside-only' });

  const { window } = dom;
  global.window = window;
  global.document = window.document;
  window.setTimeout = fn => { fn(); return 0; };
  window.clearTimeout = () => {};

  window.eval(script);
  window.document.dispatchEvent(new window.Event('DOMContentLoaded'));

  const vendorInput = document.querySelector('[data-filter-key="vendor"]');

  vendorInput.value = 'foo bar';
  vendorInput.dispatchEvent(new window.Event('input', { bubbles: true }));

  let rows = [...document.querySelectorAll('tbody tr:not(.tp-filter-row)')];
  assert.equal(rows[0].style.display, '');
  assert.equal(rows[1].style.display, 'none');
  assert.equal(rows[2].style.display, 'none');

  vendorInput.value = 'foo, bar';
  vendorInput.dispatchEvent(new window.Event('input', { bubbles: true }));

  rows = [...document.querySelectorAll('tbody tr:not(.tp-filter-row)')];
  assert.equal(rows[0].style.display, '');
  assert.equal(rows[1].style.display, 'none');
  assert.equal(rows[2].style.display, 'none');
});
