document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.querySelector('#sidebar');
  const toggle = document.querySelector('[data-sidebar-toggle]');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
  }

  document.querySelectorAll('[data-confirm]').forEach((element) => {
    element.addEventListener('click', (event) => {
      if (!window.confirm(element.dataset.confirm || 'Lanjutkan tindakan ini?')) {
        event.preventDefault();
      }
    });
  });

  document.querySelectorAll('[data-image-input]').forEach((input) => {
    input.addEventListener('change', () => {
      const preview = document.querySelector(input.dataset.imageInput);
      const file = input.files && input.files[0];
      if (preview && file) {
        preview.src = URL.createObjectURL(file);
        preview.hidden = false;
      }
    });
  });

  document.querySelectorAll('[data-countdown]').forEach((element) => {
    const end = new Date(element.dataset.countdown).getTime();
    const update = () => {
      const distance = end - Date.now();
      if (distance <= 0) {
        element.textContent = 'Waktu lelang berakhir';
        return;
      }
      const days = Math.floor(distance / 86400000);
      const hours = Math.floor((distance % 86400000) / 3600000);
      const minutes = Math.floor((distance % 3600000) / 60000);
      const seconds = Math.floor((distance % 60000) / 1000);
      element.textContent = `${days}h ${hours}j ${minutes}m ${seconds}d`;
      window.setTimeout(update, 1000);
    };
    update();
  });
});
