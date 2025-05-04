document.addEventListener("DOMContentLoaded", function() {
  const form = document.getElementById("product-form");
  const showBtn = document.getElementById("showAddProductBtn");
  const cancelBtn = document.getElementById("cancelFormBtn");

  if (!form || !showBtn || !cancelBtn) return;

  showBtn.addEventListener("click", () => {
    form.classList.add("visible");
    form.classList.remove("hidden");
    showBtn.style.display = "none";
    form.scrollIntoView({ behavior: "smooth", block: "start" });
  });

  cancelBtn.addEventListener("click", () => {
    form.classList.add("hidden");
    form.classList.remove("visible");
    showBtn.style.display = "inline-block";
  });

  // If editing, show form and hide button on page load
  if (form.classList.contains("visible")) {
    showBtn.style.display = "none";
    form.scrollIntoView({ behavior: "smooth", block: "start" });
  }
});
