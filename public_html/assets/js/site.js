// Copy a colour value to the clipboard from any swatch.
document.addEventListener('click', async (event) => {
  const chip = event.target.closest('[data-copy]');
  if (!chip) return;
  try {
    await navigator.clipboard.writeText(chip.dataset.copy);
    const label = chip.querySelector('.swatch__copy');
    chip.classList.add('is-copied');
    if (label) label.textContent = 'Copied';
    setTimeout(() => {
      chip.classList.remove('is-copied');
      if (label) label.textContent = 'Copy';
    }, 1400);
  } catch (error) {
    // Clipboard blocked, leave the value visible for manual selection.
  }
});

// Index filters: show only the chosen section, without a page load.
(function () {
  const filters = document.querySelectorAll('.filter');
  const items = document.querySelectorAll('.cards__item');
  if (!filters.length || !items.length) return;

  filters.forEach((button) => {
    button.addEventListener('click', () => {
      const wanted = button.dataset.filter;
      filters.forEach((other) => other.classList.toggle('is-on', other === button));
      items.forEach((item) => {
        item.hidden = wanted !== 'all' && item.dataset.section !== wanted;
      });
    });
  });
})();
