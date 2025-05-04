document.addEventListener('DOMContentLoaded', () => {
  // Update/Save button logic
  document.querySelectorAll('.inventory-form').forEach(form => {
    const updateBtn = form.querySelector('.update-btn');
    const saveBtn = form.querySelector('.save-btn');
    const productId = form.querySelector('input[name="product_id"]').value;

    // Find inventory text and input in the same row
    const row = form.closest('tr');
    const inventoryText = row.querySelector('.inventory-text');
    const inventoryInput = row.querySelector('.inventory-input');

    updateBtn.addEventListener('click', () => {
      // Toggle inventory display/input
      inventoryText.style.display = 'none';
      inventoryInput.style.display = 'inline-block';
      inventoryInput.focus();

      // Toggle buttons
      updateBtn.style.display = 'none';
      saveBtn.style.display = 'inline-block';
    });
  });

  // -------------------------------------------------------
  // Filter buttons logic (OUTSIDE the Update button logic)
  // -------------------------------------------------------
  const filterButtons = document.querySelectorAll('.filter-btn');
  const tableRows = document.querySelectorAll('table tbody tr');

  filterButtons.forEach(button => {
    button.addEventListener('click', () => {
      // Remove active class from all buttons
      filterButtons.forEach(btn => btn.classList.remove('active'));
      // Add active to clicked button
      button.classList.add('active');

      const filter = button.getAttribute('data-filter');

      tableRows.forEach(row => {
        if (filter === 'all') {
          row.style.display = '';
        } else if (filter === 'out-of-stock') {
          row.style.display = row.classList.contains('out-of-stock') ? '' : 'none';
        } else if (filter === 'low-stock') {
          row.style.display = row.classList.contains('low-stock') ? '' : 'none';
        }
      });
    });
  });
});
