// Toggle inquiry form visibility
document.addEventListener('DOMContentLoaded', function () {
  const toggleBtn = document.getElementById('toggleInquiryBtn');
  const inquiryForm = document.getElementById('inquiryForm');

  toggleBtn.addEventListener('click', () => {
    const isHidden = inquiryForm.style.display === 'none' || inquiryForm.style.display === '';
    inquiryForm.style.display = isHidden ? 'block' : 'none';
    toggleBtn.setAttribute('aria-expanded', isHidden);
  });
});
