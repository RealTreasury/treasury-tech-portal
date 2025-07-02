# Treasury Tech Portal

This page is designed to be embedded within another site via an iframe. In order for height updates to work securely, it posts messages to the parent window.

The parent window origin expected to embed this page is defined in `index.html` by the `EMBED_ORIGIN` constant. Currently this value is set to `https://realtreasury.com`. If you embed the page on a different domain, update the constant to match the parent origin so that the `postMessage` calls succeed.

```javascript
const EMBED_ORIGIN = 'https://realtreasury.com';
```

Any deployment embedding this page **must** come from the configured origin, or adjust the constant accordingly.
