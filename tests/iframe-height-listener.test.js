const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');

let html = fs.readFileSync('docs/embedding.html', 'utf8');
html = html.replace('https://example.com/treasury-portal', '');

test('adjusts iframe height from treasury-height message', () => {
  const dom = new JSDOM(html, { runScripts: 'dangerously' });
  const { window } = dom;

  const iframe = window.document.getElementById('treasury-portal');
  assert.equal(iframe.style.height, '', 'height initially empty');

  window.dispatchEvent(new window.MessageEvent('message', {
    data: { type: 'treasury-height', height: 450 }
  }));

  assert.equal(iframe.style.height, '450px');
});
