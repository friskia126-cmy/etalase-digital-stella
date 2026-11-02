document.addEventListener('DOMContentLoaded', () => {
  const menuToggle = document.querySelector('.menu-toggle');
  const primaryNav = document.querySelector('.primary-nav');
  if (menuToggle && primaryNav) {
    menuToggle.addEventListener('click', () => {
      const isOpen = primaryNav.classList.toggle('is-open');
      menuToggle.setAttribute('aria-expanded', String(isOpen));
    });
  }

  document.querySelectorAll('.password-toggle').forEach((button) => {
    button.addEventListener('click', () => {
      const input = button.closest('.password-field').querySelector('input');
      const visible = input.type === 'password';
      input.type = visible ? 'text' : 'password';
      button.textContent = visible ? 'Sembunyikan' : 'Tampilkan';
      button.setAttribute('aria-label', visible ? 'Sembunyikan password' : 'Tampilkan password');
    });
  });

  document.querySelectorAll('form[data-password-confirmation]').forEach((form) => {
    const password = form.querySelector('[name="password"]');
    const confirmation = form.querySelector('[name="password_confirmation"]');
    if (!password || !confirmation) return;
    const validateConfirmation = () => {
      confirmation.setCustomValidity(
        confirmation.value && confirmation.value !== password.value
          ? 'Konfirmasi password tidak sama.'
          : ''
      );
    };
    password.addEventListener('input', validateConfirmation);
    confirmation.addEventListener('input', validateConfirmation);
    validateConfirmation();
  });

  document.querySelectorAll('form[data-client-validation]').forEach((form) => {
    const errorNotice = form.querySelector('[data-client-error]');
    if (!errorNotice) return;
    form.addEventListener('invalid', (event) => {
      const field = event.target;
      if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) return;
      const fieldLabels = {
        nama: 'nama lengkap',
        email: 'email',
        kelas: 'kelas',
        jurusan: 'jurusan',
        password: 'password',
        password_confirmation: 'konfirmasi password',
      };
      const label = fieldLabels[field.name] || 'data yang dimasukkan';
      if (field.validity.valueMissing) {
        errorNotice.textContent = `Kolom ${label} wajib diisi.`;
      } else if (field.validity.typeMismatch) {
        errorNotice.textContent = `Masukkan ${label} dengan format yang valid.`;
      } else if (field.validity.tooShort) {
        errorNotice.textContent = `Kolom ${label} belum memenuhi panjang minimum.`;
      } else if (field.validity.tooLong) {
        errorNotice.textContent = `Kolom ${label} melebihi panjang maksimum.`;
      } else {
        errorNotice.textContent = field.validationMessage || `Periksa kembali ${label}.`;
      }
      errorNotice.hidden = false;
    }, true);
    form.addEventListener('input', (event) => {
      const field = event.target;
      if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) return;
      if (field.validity.valid) {
        errorNotice.textContent = '';
        errorNotice.hidden = true;
      }
    });
  });

  document.querySelectorAll('form[data-loading-form]').forEach((form) => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('[data-loading-button]');
      if (!button) return;
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.textContent = form.dataset.loadingText || 'Memproses...';
    });
  });

  document.querySelectorAll('[data-image-input]').forEach((input) => {
    input.addEventListener('change', () => {
      const preview = input.closest('form').querySelector('[data-image-preview]');
      if (!preview || !input.files || !input.files[0]) return;
      if (input.files[0].size > 2 * 1024 * 1024) {
        input.value = '';
        window.alert('Ukuran gambar maksimal 2 MB.');
        return;
      }
      preview.src = URL.createObjectURL(input.files[0]);
      preview.classList.remove('is-hidden');
    });
  });

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
  });

  document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const sidebar = document.querySelector('[data-sidebar]');
      const isOpen = sidebar.classList.toggle('is-open');
      button.setAttribute('aria-expanded', String(isOpen));
    });
  });
});
