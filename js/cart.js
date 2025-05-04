document.addEventListener("DOMContentLoaded", () => {
  // Quantity increase/decrease
  document.querySelectorAll(".qty-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      const cartID = btn.dataset.id;
      const action = btn.dataset.action;

      fetch("update_cart_quantity.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded"
        },
        body: `cart_id=${encodeURIComponent(cartID)}&action=${encodeURIComponent(action)}`
      })
      .then(res => res.text())
      .then(() => location.reload())
      .catch(err => console.error("Error updating quantity:", err));
    });
  });

  // Remove confirmation
  document.querySelectorAll('form button[type="submit"]').forEach(btn => {
    btn.addEventListener("click", (e) => {
      if (!confirm("Are you sure you want to remove this item from your cart?")) {
        e.preventDefault();
      }
    });
  });
});
