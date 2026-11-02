document.addEventListener('DOMContentLoaded', () => {
  const editSection = document.querySelector('[data-profile-edit-section]');
  const editButton = document.querySelector('[data-profile-edit]');
  const cancelButton = document.querySelector('[data-profile-cancel]');

  const showEditor = () => {
    if (!editSection) return;
    editSection.hidden = false;
    editSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    editSection.querySelector('input:not([type="hidden"]):not([disabled])')?.focus({ preventScroll: true });
  };

  editButton?.addEventListener('click', showEditor);
  cancelButton?.addEventListener('click', () => {
    if (!editSection) return;
    editSection.hidden = true;
    editButton?.focus();
  });

  document.querySelectorAll('[data-tag-editor]').forEach((editor) => {
    const input = editor.querySelector('[data-tag-input]');
    const list = editor.querySelector('[data-tag-list]');
    const hidden = editor.querySelector('[data-tag-value]');
    if (!input || !list || !hidden) return;

    const values = () => [...list.querySelectorAll('.profile-edit-tag')].map((tag) => tag.dataset.value);
    const sync = () => {
      hidden.value = values().join(', ');
    };
    const removeTag = (tag) => {
      tag.remove();
      sync();
    };
    const addTag = (value) => {
      const cleanValue = value.trim().replace(/,+$/g, '').trim();
      if (!cleanValue || values().some((item) => item.toLocaleLowerCase() === cleanValue.toLocaleLowerCase())) return;

      const tag = document.createElement('span');
      tag.className = 'profile-edit-tag';
      tag.dataset.value = cleanValue;
      tag.append(document.createTextNode(cleanValue));
      const removeButton = document.createElement('button');
      removeButton.type = 'button';
      removeButton.textContent = '×';
      removeButton.setAttribute('aria-label', `Hapus ${cleanValue}`);
      removeButton.dataset.tagRemove = '';
      removeButton.addEventListener('click', () => removeTag(tag));
      tag.append(removeButton);
      list.append(tag);
      sync();
    };

    list.querySelectorAll('[data-tag-remove]').forEach((button) => {
      button.closest('.profile-edit-tag')?.setAttribute('data-value', button.getAttribute('aria-label')?.replace(/^Hapus\s+/u, '') || '');
      button.addEventListener('click', () => removeTag(button.closest('.profile-edit-tag')));
    });
    sync();
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        addTag(input.value);
        input.value = '';
      }
    });
    input.addEventListener('blur', () => {
      if (!input.value.trim()) return;
      input.value.split(',').forEach(addTag);
      input.value = '';
    });
  });

  const photoDialog = document.querySelector('[data-profile-photo-dialog]');
  document.querySelector('[data-profile-photo-open]')?.addEventListener('click', () => photoDialog?.showModal());
  document.querySelector('[data-profile-dialog-close]')?.addEventListener('click', () => photoDialog?.close());
  photoDialog?.addEventListener('click', (event) => {
    if (event.target === photoDialog) photoDialog.close();
  });

  const previewDialog = document.querySelector('[data-profile-preview-dialog]');
  document.querySelector('[data-profile-preview]')?.addEventListener('click', () => previewDialog?.showModal());
  document.querySelector('[data-profile-preview-close]')?.addEventListener('click', () => previewDialog?.close());
  previewDialog?.addEventListener('click', (event) => {
    if (event.target === previewDialog) previewDialog.close();
  });

  document.querySelector('[data-profile-image-input]')?.addEventListener('change', (event) => {
    const input = event.currentTarget;
    const file = input.files?.[0];
    if (!file) return;
    const supportedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!supportedTypes.includes(file.type) || file.size < 1 || file.size > 2 * 1024 * 1024) {
      input.value = '';
      window.alert('Pilih gambar JPG, PNG, atau WEBP berukuran maksimal 2 MB.');
      return;
    }

    const reader = new FileReader();
    reader.addEventListener('load', () => {
      const preview = document.querySelector('[data-image-preview]');
      if (!(preview instanceof HTMLImageElement) || typeof reader.result !== 'string') return;
      preview.src = reader.result;
      preview.classList.remove('is-hidden');
      const placeholder = document.querySelector('.profile-preview-placeholder');
      if (placeholder) placeholder.hidden = true;
    });
    reader.addEventListener('error', () => window.alert('Preview foto tidak dapat dibaca. Coba pilih gambar lain.'));
    reader.readAsDataURL(file);
  });

  document.querySelector('[data-profile-form]')?.addEventListener('submit', (event) => {
    if (event.submitter?.value === 'remove_photo' && !window.confirm('Hapus foto profil dari akun ini?')) {
      event.preventDefault();
      return;
    }
    if (event.submitter?.matches('[data-profile-submit]')) {
      const button = event.submitter;
      button.disabled = true;
      button.querySelector('[data-submit-label]').hidden = true;
      button.querySelector('[data-submit-loading]').hidden = false;
    }
  });

  const categoryFilter = document.querySelector('#profile-category-filter');
  categoryFilter?.addEventListener('change', () => categoryFilter.form?.requestSubmit());
});
