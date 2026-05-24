// Mobile nav toggle
const navToggle = document.getElementById('navToggle');
const navLinks  = document.getElementById('navLinks');
if (navToggle && navLinks) {
  navToggle.addEventListener('click', () => {
    navLinks.classList.toggle('open');
  });
}

// Modal helpers 
function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('open');
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('open');
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.classList.remove('open');
  });
});

// Close buttons
document.querySelectorAll('[data-close-modal]').forEach(btn => {
  btn.addEventListener('click', () => {
    btn.closest('.modal-overlay')?.classList.remove('open');
  });
});

// Auto-dismiss alerts
document.querySelectorAll('.alert').forEach(alert => {
  setTimeout(() => {
    alert.style.transition = 'opacity .4s';
    alert.style.opacity = '0';
    setTimeout(() => alert.remove(), 400);
  }, 4000);
});

// Confirm delete 
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    const msg = el.getAttribute('data-confirm') || 'Are you sure?';
    if (!confirm(msg)) e.preventDefault();
  });
});

// POS Cart logic (runs only on pos.php)
if (document.getElementById('cart-items-list')) {
  initPOS();
}

function initPOS() {
  let cart = {};

  const cartList  = document.getElementById('cart-items-list');
  const cartTotal = document.getElementById('cart-total');
  const cartCount = document.getElementById('cart-count');
  const emptyMsg  = document.getElementById('cart-empty');
  const submitBtn = document.getElementById('submit-sale');
  const cartInput = document.getElementById('cart-json');

  function renderCart() {
    const items = Object.values(cart);
    const total = items.reduce((s, i) => s + i.price * i.qty, 0);

    cartList.innerHTML = items.length === 0
      ? `<div class="empty-state" style="padding:2rem"><span class="icon">🛒</span><p>Cart is empty</p></div>`
      : items.map(i => `
        <div class="cart-item">
          <div class="ci-name">${i.name}</div>
          <div class="ci-qty">
            <button onclick="posChangeQty(${i.id}, -1)">−</button>
            <span>${i.qty}</span>
            <button onclick="posChangeQty(${i.id}, 1)">+</button>
          </div>
          <div class="ci-price">₱${(i.price * i.qty).toLocaleString('en-PH',{minimumFractionDigits:2})}</div>
          <div class="ci-remove" onclick="posRemove(${i.id})">✕</div>
        </div>`).join('');

    cartTotal.textContent = '₱' + total.toLocaleString('en-PH',{minimumFractionDigits:2});
    cartCount.textContent = items.reduce((s,i)=>s+i.qty,0);
    cartInput.value = JSON.stringify(cart);
    submitBtn.disabled = items.length === 0;
  }

  window.posAdd = function(id, name, price, stock) {
    if (stock < 1) return;
    if (cart[id]) {
      if (cart[id].qty >= stock) { alert('Max stock reached'); return; }
      cart[id].qty++;
    } else {
      cart[id] = { id, name, price: parseFloat(price), qty: 1, stock };
    }
    renderCart();
  };

  window.posChangeQty = function(id, delta) {
    if (!cart[id]) return;
    const newQty = cart[id].qty + delta;
    if (newQty <= 0)              { delete cart[id]; }
    else if (newQty > cart[id].stock) { alert('Max stock reached'); return; }
    else                          { cart[id].qty = newQty; }
    renderCart();
  };

  window.posRemove = function(id) {
    delete cart[id];
    renderCart();
  };

  renderCart();
}

// Simple search filter for tables
const tableSearch = document.getElementById('tableSearch');
if (tableSearch) {
  tableSearch.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('[data-searchable] tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}