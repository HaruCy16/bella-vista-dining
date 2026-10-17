(() => {
  const form = document.querySelector('[data-reservation-form]');
  if (!form) return;
  const tableType = form.querySelector('[data-table-type]');
  const guestCount = form.querySelector('[data-guest-count]');
  const feeBreakdown = document.querySelector('[data-fee-breakdown]');
  const placeholder = document.querySelector('.summary-placeholder');
  const fee = document.querySelector('[data-reservation-fee]');
  const extra = document.querySelector('[data-extra-fee]');
  const total = document.querySelector('[data-fee-total]');
  const note = document.querySelector('[data-fee-note]');
  const peso = (amount) => `₱${Number(amount).toLocaleString('en-PH')}`;
  const tableRules = {
    standard: { fee: 0, minimum: 1000, note: 'Standard tables require a minimum food order of ₱1,000.' },
    family: { fee: 500, minimum: 0, note: 'The ₱500 Family reservation fee is fully consumable toward eligible food purchases.' },
    vip: { fee: 2500, minimum: 0, note: 'VIP rooms include up to 15 guests. Guests 16–20 add ₱300 each.' },
  };

  const updateFee = () => {
    const selectedType = tableRules[tableType?.value];
    const guests = Number(guestCount?.value || 0);
    if (!selectedType) { feeBreakdown?.setAttribute('hidden', ''); if (placeholder) placeholder.hidden = false; return; }
    const extraFee = tableType.value === 'vip' ? Math.max(0, guests - 15) * 300 : 0;
    if (placeholder) placeholder.hidden = true;
    feeBreakdown?.removeAttribute('hidden');
    if (fee) fee.textContent = peso(selectedType.fee);
    if (extra) extra.textContent = peso(extraFee);
    if (total) total.textContent = peso(selectedType.fee + extraFee);
    if (note) note.textContent = selectedType.note;
  };
  tableType?.addEventListener('change', updateFee);
  guestCount?.addEventListener('change', updateFee);

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    let firstInvalid = null;
    form.querySelectorAll('[required]').forEach((field) => {
      const error = form.querySelector(`[data-error-for="${field.name}"]`);
      const invalid = field.type === 'checkbox' ? !field.checked : !field.value.trim();
      field.classList.toggle('has-error', invalid);
      if (error) error.textContent = invalid ? (field.type === 'checkbox' ? 'Please acknowledge the reservation policies.' : 'Please complete this field.') : '';
      if (invalid && !firstInvalid) firstInvalid = field;
    });
    if (firstInvalid) { firstInvalid.focus(); window.BellaVista.showToast?.('A few reservation details still need your attention.', 'notice'); return; }
    window.BellaVista.showToast?.('Reservation details saved for this preview. Checkout is next.', 'success');
    setTimeout(() => { window.location.href = '/pages/checkout.php'; }, 650);
  });
})();
