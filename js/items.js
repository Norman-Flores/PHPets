<script>
  document.addEventListener('DOMContentLoaded', () => {
    // Smooth scroll on pagination link click
    const paginationLinks = document.querySelectorAll('.pagination a');
    paginationLinks.forEach(link => {
      link.addEventListener('click', function(event) {
        setTimeout(() => {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }, 50); // smooth scroll after clicking page
      });
    });

    // Smooth scroll on search form submit
    const searchForm = document.querySelector('.search-bar form');
    if (searchForm) {
      searchForm.addEventListener('submit', (event) => {
        // Optional: scroll to top immediately on submit (before page reload)
        window.scrollTo({ top: 0, behavior: 'smooth' });
        // No preventDefault here, so form submits normally
      });
    }
  });
</script>
