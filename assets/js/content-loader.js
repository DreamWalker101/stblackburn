/* content-loader.js — applies editable content from content.json to the page.
 *  - Text:   data-pp="path.to.key"            -> element.textContent
 *  - Links:  data-pp-href="path.to.key"       -> element.href
 *  - Images: data-pp-src="path.to.key"        -> element.src
 *  - Image map: content.imageMap { oldUrl: newUrl } swaps any matching
 *    <img src>, lazy-load data-src/url, and CSS background-image across the page.
 */
(function () {
  'use strict';

  var CONTENT_PATH = (function () {
    var depth = window.location.pathname.replace(/\/[^\/]*$/, '').split('/').length - 1;
    return depth > 0 ? '../'.repeat(depth) + 'content.json' : 'content.json';
  }());

  function get(obj, path) {
    return path.split('.').reduce(function (o, k) {
      return (o && o[k] !== undefined) ? o[k] : null;
    }, obj);
  }

  function applyFields(content) {
    document.querySelectorAll('[data-pp]').forEach(function (el) {
      var val = get(content, el.getAttribute('data-pp'));
      if (val !== null && val !== '') el.textContent = val;
    });
    document.querySelectorAll('[data-pp-href]').forEach(function (el) {
      var val = get(content, el.getAttribute('data-pp-href'));
      if (val !== null && val !== '') el.href = val;
    });
    document.querySelectorAll('[data-pp-src]').forEach(function (el) {
      var val = get(content, el.getAttribute('data-pp-src'));
      if (val !== null && val !== '') el.src = val;
    });
  }

  function applyImageMap(map) {
    if (!map) return;
    Object.keys(map).forEach(function (oldUrl) {
      var nu = map[oldUrl];
      if (!nu || !oldUrl) return;
      /* <img> via src / lazy-load attributes */
      document.querySelectorAll('img').forEach(function (img) {
        ['src', 'data-src', 'data-lazyload', 'url', 'data-sticky'].forEach(function (attr) {
          var v = img.getAttribute(attr);
          if (v && v.indexOf(oldUrl) !== -1) img.setAttribute(attr, nu);
        });
        if (img.getAttribute('data-src') === nu || img.getAttribute('url') === nu) img.src = nu;
      });
      /* CSS background-image (inline styles) */
      document.querySelectorAll('[style*="background-image"]').forEach(function (el) {
        if (el.style.backgroundImage.indexOf(oldUrl) !== -1) {
          el.style.backgroundImage = 'url("' + nu + '")';
        }
      });
    });
  }

  fetch(CONTENT_PATH)
    .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
    .then(function (content) { applyFields(content); applyImageMap(content.imageMap); })
    .catch(function (e) { console.warn('[content-loader] Could not load content.json:', e); });
}());
