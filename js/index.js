document.addEventListener("DOMContentLoaded", () => {
  // === AUTH TOGGLE LOGIC ===
  const authToggle = document.getElementById("authToggle");
  const authPanel = document.getElementById("authPanel");

  if (authToggle && authPanel) {
    authPanel.style.display = "none";

    authToggle.addEventListener("click", (e) => {
      e.stopPropagation();
      authPanel.style.display = authPanel.style.display === "block" ? "none" : "block";
    });

    document.addEventListener("click", (e) => {
      if (!authPanel.contains(e.target) && !authToggle.contains(e.target)) {
        authPanel.style.display = "none";
      }
    });
  }

const carouselTrack = document.querySelector(".carousel-track");
  const carouselItems = document.querySelectorAll(".carousel-item");
  const leftBtn = document.querySelector(".carousel-btn.left");
  const rightBtn = document.querySelector(".carousel-btn.right");

  if (!carouselTrack || !leftBtn || !rightBtn || carouselItems.length === 0) return;

  let currentIndex = 0;
  let itemsToShow = 3; // default for desktop

  function updateItemsToShow() {
    if (window.innerWidth <= 480) {
      itemsToShow = 1;
    } else if (window.innerWidth <= 768) {
      itemsToShow = 2;
    } else {
      itemsToShow = 3;
    }
  }

  function getItemWidth() {
    const itemStyle = getComputedStyle(carouselItems[0]);
    const marginRight = parseFloat(itemStyle.marginRight || 0);
    return carouselItems[0].offsetWidth + marginRight;
  }

  function updateCarousel() {
    const itemWidth = getItemWidth();
    const maxOffset = (carouselItems.length - itemsToShow) * itemWidth;
    const offset = Math.min(currentIndex * itemWidth, maxOffset);
    carouselTrack.style.transform = `translateX(-${offset}px)`;
    disableButtons();
  }

  function disableButtons() {
    leftBtn.disabled = currentIndex === 0;
    rightBtn.disabled = currentIndex >= carouselItems.length - itemsToShow;
  }

  leftBtn.addEventListener("click", () => {
    if (currentIndex > 0) {
      currentIndex--;
      updateCarousel();
    }
  });

  rightBtn.addEventListener("click", () => {
    if (currentIndex < carouselItems.length - itemsToShow) {
      currentIndex++;
      updateCarousel();
    }
  });

  window.addEventListener("resize", () => {
    const oldItemsToShow = itemsToShow;
    updateItemsToShow();
    if (currentIndex > carouselItems.length - itemsToShow) {
      currentIndex = Math.max(0, carouselItems.length - itemsToShow);
    }
    updateCarousel();
  });

  function initializeCarousel() {
    updateItemsToShow();
    updateCarousel();
  }

  initializeCarousel();
});