/* ============================================================
   Richiamo Coffee — Global JavaScript
   assets/js/app.js
   ============================================================ */

// ── Cart management ───────────────────────────────────────────
const RichiamoCart = {
  key: 'richiamo_cart',

  get() {
    try { return JSON.parse(sessionStorage.getItem(this.key) || '[]'); }
    catch(e) { return []; }
  },

  save(cart) {
    sessionStorage.setItem(this.key, JSON.stringify(cart));
  },

  add(id, name, price, category = 'espresso', qty = 1) {
    const cart = this.get();
    const existing = cart.find(i => i.id === id);
    if (existing) existing.qty += qty;
    else cart.push({ id, name, price, qty, category });
    this.save(cart);
    this.updateCount();
    return cart;
  },

  remove(id) {
    const cart = this.get().filter(i => i.id !== id);
    this.save(cart);
    this.updateCount();
    return cart;
  },

  changeQty(id, delta) {
    const cart = this.get();
    const idx  = cart.findIndex(i => i.id === id);
    if (idx < 0) return cart;
    cart[idx].qty += delta;
    if (cart[idx].qty <= 0) cart.splice(idx, 1);
    this.save(cart);
    this.updateCount();
    return cart;
  },

  clear() {
    sessionStorage.removeItem(this.key);
    this.updateCount();
  },

  count() {
    return this.get().reduce((s, i) => s + i.qty, 0);
  },

  subtotal() {
    return this.get().reduce((s, i) => s + i.price * i.qty, 0);
  },

  total() {
    const sub = this.subtotal();
    return sub + sub * 0.06; // +6% SST
  },

  updateCount() {
    const el = document.getElementById('cart-count');
    if (el) {
      const c = this.count();
      el.textContent = c;
      el.style.display = c > 0 ? '' : 'none';
    }
  }
};

// ── Flash message auto-dismiss ────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.flash-auto, .alert-dismissible').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .4s';
      el.style.opacity    = '0';
      setTimeout(() => el.remove(), 400);
    }, 4000);
  });

  // Update cart count on load
  RichiamoCart.updateCount();
});

// ── Format price ──────────────────────────────────────────────
function formatPrice(amount) {
  return 'RM ' + parseFloat(amount).toFixed(2);
}

// ── Toggle password visibility ────────────────────────────────
function togglePw(inputId, iconId) {
  const input = document.getElementById(inputId);
  const icon  = document.getElementById(iconId);
  if (!input || !icon) return;
  input.type     = input.type === 'password' ? 'text' : 'password';
  icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

// ── Password strength checker ─────────────────────────────────
function checkPasswordStrength(val, fillId, labelId) {
  const fill  = document.getElementById(fillId);
  const label = document.getElementById(labelId);
  if (!fill) return;

  const hasLen   = val.length >= 8;
  const hasUpper = /[A-Z]/.test(val);
  const hasNum   = /[0-9]/.test(val);
  const hasSpec  = /[^a-zA-Z0-9]/.test(val);
  const score    = [hasLen, hasUpper, hasNum, hasSpec].filter(Boolean).length;

  const configs = [
    { w: '0%',   bg: '#eee',     text: '' },
    { w: '25%',  bg: '#E24B4A',  text: 'Weak' },
    { w: '50%',  bg: '#E8A045',  text: 'Fair' },
    { w: '75%',  bg: '#3B6DD8',  text: 'Good' },
    { w: '100%', bg: '#1D9E75',  text: 'Strong ✓' },
  ];
  const c = configs[score];
  fill.style.width      = c.w;
  fill.style.background = c.bg;
  if (label) { label.textContent = c.text; label.style.color = c.bg; }
}

// ── Confirm dialog ────────────────────────────────────────────
function confirmAction(message) {
  return window.confirm(message || 'Are you sure?');
}

// ── Auto-submit form on select change ────────────────────────
function autoSubmit(selectEl) {
  selectEl.closest('form').submit();
}

// ── Debounce ──────────────────────────────────────────────────
function debounce(fn, delay = 300) {
  let timer;
  return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); };
}

// ── Toast notification ────────────────────────────────────────
function showToast(message, type = 'success', duration = 3000) {
  let toast = document.getElementById('rc-global-toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'rc-global-toast';
    toast.style.cssText = `
      position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
      background:#1C0A00;color:#F5E6C8;border-radius:.75rem;
      padding:.75rem 1.25rem;font-size:.875rem;font-family:'DM Sans',sans-serif;
      display:flex;align-items:center;gap:.6rem;
      box-shadow:0 8px 24px rgba(0,0,0,.2);
      transition:opacity .3s;max-width:320px;
    `;
    document.body.appendChild(toast);
  }

  const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
  toast.innerHTML = `<span style="color:#C68642;">${icons[type]||'✓'}</span> ${message}`;
  toast.style.opacity = '1';
  toast.style.display = 'flex';

  clearTimeout(toast._timer);
  toast._timer = setTimeout(() => {
    toast.style.opacity = '0';
    setTimeout(() => toast.style.display = 'none', 300);
  }, duration);
}

// ── Category icon helper ───────────────────────────────────────
const categoryIcons = {
  'espresso':   '☕',
  'cold-brew':  '🧊',
  'seasonal':   '🌿',
  'non-coffee': '🍵',
  'food':       '🥐',
};

function getCategoryIcon(slug) {
  return categoryIcons[slug] || '☕';
}
