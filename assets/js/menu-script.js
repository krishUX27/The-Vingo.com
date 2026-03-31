/**
 * js/menu-script.js
 * Menu Manager — client-side logic
 *
 * Each page configures `window.MENU_CONFIG` before loading this script:
 *
 *   window.MENU_CONFIG = {
 *     addCategoryUrl : '../api/add_category.php',   // admin pages
 *     fetchDishesUrl : '../api/fetch_dishes.php',   // public/menu.php
 *     uploadsBase    : '../uploads/',               // path to dish images
 *   };
 */

'use strict';

/* ── Resolve config with safe defaults ── */
const MENU_CFG = Object.assign({
  addCategoryUrl : 'api/add_category.php',
  fetchDishesUrl : 'api/fetch_dishes.php',
  uploadsBase    : 'uploads/',
}, window.MENU_CONFIG || {});

/* ════════════════════════════════════════════════════════════
   1.  INLINE "ADD CATEGORY" MODAL
       Initialised on any page that contains #btn-add-category
   ════════════════════════════════════════════════════════════ */

function initCategoryModal() {
  const btn      = document.getElementById('btn-add-category');
  const modal    = document.getElementById('cat-modal');
  const overlay  = document.getElementById('cat-overlay');
  const closeBtn = document.getElementById('cat-modal-close');
  const cancelBtn= document.getElementById('cat-modal-cancel');
  const saveBtn  = document.getElementById('cat-modal-save');
  const input    = document.getElementById('cat-modal-input');
  const msgEl    = document.getElementById('cat-modal-msg');
  const dropdown = document.getElementById('category_id');

  if (!btn || !modal) return;   // not on a page with the modal

  /* open / close helpers */
  const open  = () => { input.value = ''; clearMsg(); modal.classList.add('open'); overlay.classList.add('open'); setTimeout(() => input.focus(), 100); };
  const close = () => { modal.classList.remove('open'); overlay.classList.remove('open'); };

  btn.addEventListener('click', open);
  closeBtn  ?.addEventListener('click', close);
  cancelBtn ?.addEventListener('click', close);
  overlay.addEventListener('click', close);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
  input.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); save(); } });
  saveBtn.addEventListener('click', save);

  /* status message helpers */
  function setMsg(text, type) { msgEl.textContent = text; msgEl.className = `m-msg m-${type}`; }
  function clearMsg()         { msgEl.textContent = '';   msgEl.className = 'm-msg'; }

  /* AJAX save */
  function save() {
    const name = input.value.trim();
    if (!name) { setMsg('Category name cannot be empty.', 'error'); input.focus(); return; }

    saveBtn.disabled    = true;
    saveBtn.textContent = 'Saving…';
    clearMsg();

    const fd = new FormData();
    fd.append('name', name);

    fetch(MENU_CFG.addCategoryUrl, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(json => {
        if (json.success) {
          /* add new <option> and select it */
          addOption(json.id, json.name, true);
          setMsg('✅ ' + json.message, 'success');
          setTimeout(close, 900);

        } else if (json.duplicate) {
          /* already exists — just select it */
          selectOrAdd(json.id, json.name);
          setMsg('ℹ️ Already exists — selected for you.', 'info');
          setTimeout(close, 1100);

        } else {
          setMsg('❌ ' + (json.error || 'Unknown error.'), 'error');
        }
      })
      .catch(() => setMsg('❌ Network error. Try again.', 'error'))
      .finally(() => {
        saveBtn.disabled    = false;
        saveBtn.textContent = 'Save Category';
      });
  }

  /* dropdown helpers */
  function addOption(id, name, selectIt) {
    if (dropdown.querySelector(`option[value="${id}"]`)) {
      if (selectIt) dropdown.value = id;
      return;
    }
    const opt = new Option(name, id);
    dropdown.add(opt);
    if (selectIt) dropdown.value = id;
  }

  function selectOrAdd(id, name) {
    dropdown.querySelector(`option[value="${id}"]`)
      ? (dropdown.value = id)
      : addOption(id, name, true);
  }
}

/* ════════════════════════════════════════════════════════════
   2.  LIVE MENU PAGE  (public/menu.php)
       Polls fetch_dishes.php every 3 seconds.
   ════════════════════════════════════════════════════════════ */

function initLiveMenu() {
  const root = document.getElementById('menu-root');
  if (!root) return;

  let lastHash = '';

  /* HTML escape */
  function esc(s) {
    return String(s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  /* Build one dish card */
  function buildCard(d) {
    const price  = '₹' + parseFloat(d.price).toFixed(2);
    const imgSrc = d.image ? MENU_CFG.uploadsBase + esc(d.image) : null;

    const imgHTML = imgSrc
      ? `<div class="mc-img"><img src="${imgSrc}" alt="${esc(d.name)}" loading="lazy"></div>`
      : `<div class="mc-img mc-noimg">🍽️</div>`;

    const avBadge = d.availability === 'Available'
      ? '<span class="badge badge-success">✅ Available</span>'
      : '<span class="badge badge-danger">❌ Not Available</span>';

    return `<div class="dish-card">
      ${imgHTML}
      <div class="dc-body">
        <div class="dc-name">${esc(d.name)}</div>
        <div class="dc-price">${price}</div>
        <div class="dc-foot">${avBadge}</div>
      </div>
    </div>`;
  }

  /* Build full menu HTML from grouped object */
  function buildMenu(grouped) {
    const cats = Object.keys(grouped);
    if (!cats.length) {
      return `<div class="no-data"><span class="nd-icon">🍽️</span><p>No dishes available right now.</p></div>`;
    }
    return cats.map(cat => `
      <section class="cat-section">
        <h2 class="cat-heading"><span>📂</span> ${esc(cat)}</h2>
        <div class="dishes-grid">${grouped[cat].map(buildCard).join('')}</div>
      </section>
    `).join('');
  }

  /* Update toast */
  function showToast() {
    const t = document.getElementById('update-toast');
    if (!t) return;
    t.classList.add('show');
    clearTimeout(t._t);
    t._t = setTimeout(() => t.classList.remove('show'), 2600);
  }

  /* Fetch and diff */
  async function fetch_menu() {
    try {
      const res  = await fetch(MENU_CFG.fetchDishesUrl + '?_=' + Date.now());
      const json = await res.json();
      if (!json.success) return;

      const hash = JSON.stringify(json.data);
      if (hash === lastHash) return;         // nothing changed

      const isFirst = lastHash === '';
      root.innerHTML = buildMenu(json.data);
      if (!isFirst) showToast();
      lastHash = hash;
    } catch (e) {
      console.warn('[LiveMenu]', e);
    }
  }

  fetch_menu();
  setInterval(fetch_menu, 3000);
}

/* ════════════════════════════════════════════════════════════
   BOOT
   ════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
  initCategoryModal();
  initLiveMenu();
});
