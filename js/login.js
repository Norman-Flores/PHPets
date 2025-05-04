document.addEventListener("DOMContentLoaded", () => {
  const loginTab = document.getElementById("loginTab");
  const registerTab = document.getElementById("registerTab");
  const loginForm = document.getElementById("loginForm");
  const registerForm = document.getElementById("registerForm");

  if (loginTab && registerTab && loginForm && registerForm) {
    loginTab.addEventListener("click", () => {
      loginTab.classList.add("active");
      registerTab.classList.remove("active");
      loginForm.classList.remove("hidden");
      registerForm.classList.add("hidden");
    });

    registerTab.addEventListener("click", () => {
      registerTab.classList.add("active");
      loginTab.classList.remove("active");
      registerForm.classList.remove("hidden");
      loginForm.classList.add("hidden");
    });
  }

  const authToggle = document.getElementById("authToggle");
  const authPanel = document.getElementById("authPanel");

  if (authToggle && authPanel) {
    // Start hidden
    authPanel.style.display = "none";

    // Toggle dropdown
    authToggle.addEventListener("click", (e) => {
      e.stopPropagation();
      authPanel.style.display =
        authPanel.style.display === "block" ? "none" : "block";
    });

    // Hide if click outside
    document.addEventListener("click", (e) => {
      if (!authPanel.contains(e.target) && !authToggle.contains(e.target)) {
        authPanel.style.display = "none";
      }
    });
  }
});
