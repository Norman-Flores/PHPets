document.addEventListener("DOMContentLoaded", function () {
  const addUserFormContainer = document.getElementById('addUserFormContainer');
  const editUserFormContainer = document.getElementById('editUserFormContainer');
  const showAddUserBtn = document.getElementById('showAddUserBtn');
  const cancelAddUserBtn = document.getElementById('cancelAddUserBtn');
  const cancelEditUserBtn = document.getElementById('cancelEditUserBtn');

  if (!addUserFormContainer || !editUserFormContainer || !showAddUserBtn) {
    // Required elements not found, exit
    return;
  }

  showAddUserBtn.addEventListener('click', () => {
    addUserFormContainer.style.display = 'block';
    editUserFormContainer.style.display = 'none';
    showAddUserBtn.style.display = 'none';
  });

  cancelAddUserBtn.addEventListener('click', () => {
    addUserFormContainer.style.display = 'none';
    showAddUserBtn.style.display = 'inline-block';
  });

  cancelEditUserBtn.addEventListener('click', () => {
    editUserFormContainer.style.display = 'none';
    showAddUserBtn.style.display = 'inline-block';
    // Remove edit query param from URL without reload
    const url = new URL(window.location);
    url.searchParams.delete('edit');
    window.history.replaceState({}, document.title, url.toString());
  });

  // This flag will be set dynamically by PHP below
  const isEditing = window.isEditing || false;

  if (isEditing) {
    addUserFormContainer.style.display = 'none';
    editUserFormContainer.style.display = 'block';
    showAddUserBtn.style.display = 'none';
  } else {
    addUserFormContainer.style.display = 'none';
    editUserFormContainer.style.display = 'none';
    showAddUserBtn.style.display = 'inline-block';
  }
});
