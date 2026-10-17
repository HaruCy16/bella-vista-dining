(() => {
  const storageKey = 'bella-vista-cart';
  const readCart = () => {
    try { return JSON.parse(localStorage.getItem(storageKey) || '[]'); } catch { return []; }
  };
  const writeCart = (cart) => localStorage.setItem(storageKey, JSON.stringify(cart));
  const formatPeso = (amount) => `₱${Number(amount).toLocaleString('en-PH')}`;
  const drawer = document.querySelector('[data-cart-drawer]');
  const itemsTarget = document.querySelector('[data-cart-items]');
  const subtotalTarget = document.querySelector('[data-cart-subtotal]');
  const catalog = {
    'crispy-pata': { name: 'Crispy Pata', category: 'Main Courses', price: 1380, image: '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-3.webp' },
    'beef-kare-kare': { name: 'Beef Kare-Kare', category: 'Main Courses', price: 1180, image: '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-3.webp' },
    'grilled-salmon': { name: 'Grilled Salmon', category: 'Main Courses', price: 1280, image: '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-5.webp' },
    'caesar-salad': { name: 'Caesar Salad', category: 'Appetizers & Soups', price: 420, image: '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-5.webp' },
    'sinigang-na-hipon': { name: 'Sinigang na Hipon', category: 'Appetizers & Soups', price: 580, image: '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-5.webp' },
    'creamy-mushroom-soup': { name: 'Creamy Mushroom Soup', category: 'Appetizers & Soups', price: 360, image: '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-4.webp' },
    'halo-halo-cheesecake': { name: 'Halo-Halo Cheesecake', category: 'Desserts & Drinks', price: 480, image: '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-4.webp' },
    'fresh-fruit-shakes': { name: 'Fresh Fruit Shakes', category: 'Desserts & Drinks', price: 280, image: '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-4.webp' },
    'iced-tea': { name: 'House Iced Tea', category: 'Desserts & Drinks', price: 180, image: '/manus-storage/async-images/MVJ2eKbVhy7fnNbkbkhxs2/image-4.webp' },
  };

  const render = () => {
    const cart = readCart();
    if (!itemsTarget) return;
    const subtotal = cart.reduce((total, item) => total + item.price * item.quantity, 0);
    if (subtotalTarget) subtotalTarget.textContent = formatPeso(subtotal);
    if (!cart.length) {
      itemsTarget.innerHTML = '<div class="cart-empty"><span class="empty-state-mark">✦</span><p class="font-display text-2xl text-forest mt-4">Your cart is ready.</p><p class="text-sm text-walnut/60 mt-2">Add something delicious from the menu.</p></div>';
      return;
    }
    itemsTarget.innerHTML = cart.map((item) => `<div class="cart-line"><img src="${item.image}" alt=""><div class="flex-1"><div class="flex justify-between gap-3"><strong>${item.name}</strong><span class="price">${formatPeso(item.price * item.quantity)}</span></div><div class="flex items-center gap-2 mt-3"><button class="quantity-button" type="button" data-cart-decrease="${item.id}" aria-label="Decrease ${item.name}">−</button><span class="quantity-value">${item.quantity}</span><button class="quantity-button" type="button" data-cart-increase="${item.id}" aria-label="Increase ${item.name}">+</button></div></div></div>`).join('');
    itemsTarget.querySelectorAll('[data-cart-increase], [data-cart-decrease]').forEach((button) => button.addEventListener('click', () => {
      const id = button.dataset.cartIncrease || button.dataset.cartDecrease;
      const nextCart = readCart().map((item) => item.id === id ? { ...item, quantity: Math.max(0, item.quantity + (button.dataset.cartIncrease ? 1 : -1)) } : item).filter((item) => item.quantity > 0);
      writeCart(nextCart); render();
    }));
  };

  window.BellaVista = window.BellaVista || {};
  window.BellaVista.openCart = () => { if (!drawer) return; drawer.classList.add('is-open'); drawer.setAttribute('aria-hidden', 'false'); document.body.classList.add('cart-open'); };
  window.BellaVista.closeCart = () => { if (!drawer) return; drawer.classList.remove('is-open'); drawer.setAttribute('aria-hidden', 'true'); document.body.classList.remove('cart-open'); };

  document.querySelectorAll('[data-add-to-cart]').forEach((button) => button.addEventListener('click', () => {
    const item = catalog[button.dataset.itemId];
    if (!item) return;
    const cart = readCart();
    const existing = cart.find((entry) => entry.id === button.dataset.itemId);
    if (existing) existing.quantity += 1; else cart.push({ ...item, id: button.dataset.itemId, quantity: 1 });
    writeCart(cart); render(); window.BellaVista.openCart?.(); window.BellaVista.showToast?.(`${item.name} added to your order.`, 'success');
  }));
  render();
})();
