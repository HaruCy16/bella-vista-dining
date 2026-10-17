(() => {
  const toggle = document.querySelector('[data-mobile-menu-toggle]');
  const menu = document.querySelector('[data-mobile-menu]');
  const header = document.querySelector('[data-header]');
  const toast = document.querySelector('[data-toast]');

  window.BellaVista = window.BellaVista || {};

  window.BellaVista.showToast = (message, tone = 'default') => {
    if (!toast) return;
    toast.textContent = message;
    toast.dataset.tone = tone;
    toast.classList.add('is-visible');
    window.clearTimeout(window.BellaVista.toastTimer);
    window.BellaVista.toastTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 3200);
  };

  toggle?.addEventListener('click', () => {
    const isOpen = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!isOpen));
    if (menu) {
      menu.hidden = isOpen;
      menu.classList.toggle('is-open', !isOpen);
    }
  });

  document.querySelectorAll('[data-cart-close]').forEach((button) => {
    button.addEventListener('click', () => window.BellaVista.closeCart?.());
  });

  document.querySelectorAll('[data-demo-checkout]').forEach((button) => {
    button.addEventListener('click', () => {
      window.BellaVista.showToast('Preview only — no payment has been processed.', 'notice');
    });
  });

  document.querySelectorAll('[data-demo-login]').forEach((button) => {
    button.addEventListener('click', () => {
      window.BellaVista.showToast('Preview only — authentication connects in Stage 2.', 'notice');
    });
  });

  document.querySelectorAll('.payment-option input').forEach((input) => {
    input.addEventListener('change', () => {
      document.querySelectorAll('.payment-option').forEach((option) => option.classList.remove('is-selected'));
      input.closest('.payment-option')?.classList.add('is-selected');
    });
  });

  document.querySelectorAll('.kitchen-action:not(:disabled)').forEach((button) => {
    button.addEventListener('click', () => {
      const card = button.closest('.kitchen-order');
      const status = card?.querySelector('.order-status');
      const isPending = button.classList.contains('pending-action');
      button.classList.remove('pending-action', 'preparing-action');
      button.classList.add(isPending ? 'preparing-action' : 'served-action');
      button.innerHTML = isPending ? 'Mark as served <span>→</span>' : 'Order complete <span>✓</span>';
      if (isPending) {
        status.textContent = 'Preparing';
        status.className = 'order-status status-preparing';
      } else {
        status.textContent = 'Served';
        status.className = 'order-status status-served';
        button.disabled = true;
      }
      window.BellaVista.showToast(isPending ? 'Order moved to preparing.' : 'Order marked as served.', 'success');
    });
  });

  const updateHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 18);
  updateHeader();
  window.addEventListener('scroll', updateHeader, { passive: true });
})();
