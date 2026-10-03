document.addEventListener('DOMContentLoaded', function () {
   const registerForm = document.getElementById('registoForm');
   const loginForm = document.getElementById('formLogin');

   if (registerForm) {
      registerForm.addEventListener('submit', function (event) {
         const username = document.getElementById('username').value.trim();
         const email = document.getElementById('email').value.trim();
         const password = document.getElementById('password').value;
         const errors = [];

         if (!/^[a-zA-Z0-9_-]{3,20}$/.test(username)) {
            errors.push('Utilize 3 a 20 caracteres no nome de utilizador (letras, números, _ ou -).');
         }

         if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errors.push('Introduza um e-mail válido.');
         }

         if (password.length < 8) {
            errors.push('A palavra-passe deve ter pelo menos 8 caracteres.');
         }

         if (errors.length > 0) {
            event.preventDefault();
            alert(errors.join('\n'));
         }
      });
   }

   if (loginForm) {
      loginForm.addEventListener('submit', function (event) {
         const username = document.getElementById('username').value.trim();
         const password = document.getElementById('password').value;

         if (!username || !password) {
            event.preventDefault();
            alert('Preencha o nome de utilizador e a palavra-passe.');
         }
      });
   }
      // lithbox
      
    const links = document.querySelectorAll('.event-lightbox');

    links.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();

            const modal = document.getElementById('lightboxModal');
            const img = document.getElementById('lightboxImage');

            if (!modal || !img) {
                const overlay = document.createElement('div');
                overlay.id = 'lightboxModal';
                overlay.style.position = 'fixed';
                overlay.style.top = '0';
                overlay.style.left = '0';
                overlay.style.width = '100%';
                overlay.style.height = '100%';
                overlay.style.background = 'rgba(0,0,0,0.8)';
                overlay.style.display = 'flex';
                overlay.style.alignItems = 'center';
                overlay.style.justifyContent = 'center';
                overlay.style.zIndex = '9999';

                const image = document.createElement('img');
                image.id = 'lightboxImage';
                image.style.maxWidth = '90%';
                image.style.maxHeight = '90%';
                image.style.borderRadius = '12px';
                image.style.boxShadow = '0 10px 25px rgba(0,0,0,0.4)';
                image.style.cursor = 'pointer';

                overlay.appendChild(image);
                document.body.appendChild(overlay);

                overlay.addEventListener('click', function () {
                    overlay.remove();
                });

                const modalEl = overlay;
                modalEl.querySelector('img').src = this.href;

                return;
            }

            img.src = this.href;
            modal.style.display = 'flex';
            modal.onclick = function () {
                modal.style.display = 'none';
            };
        });
    });

   const modal = document.getElementById('event-lightbox');
   const image = modal?.querySelector('.event-lightbox__image');
   const date = modal?.querySelector('.event-lightbox__date');
   const title = modal?.querySelector('#event-lightbox-title');
   const description = modal?.querySelector('.event-lightbox__description');
   const location = modal?.querySelector('.event-lightbox__location');
   const time = modal?.querySelector('.event-lightbox__time');
   const closeBtn = modal?.querySelector('.event-lightbox__close');

   if (modal && image && date && title && description && location && time && closeBtn) {
      document.querySelectorAll('.event-detail-btn').forEach(function (button) {
         button.addEventListener('click', function () {
            image.src = button.dataset.image || '';
            title.textContent = button.dataset.title || 'Evento';
            description.textContent = button.dataset.description || '';
            date.textContent = button.dataset.date || '';
            location.textContent = button.dataset.location || '';
            time.textContent = button.dataset.time || '';
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
         });
      });

      function closeModal() {
         modal.classList.remove('is-open');
         modal.setAttribute('aria-hidden', 'true');
      }

      closeBtn.addEventListener('click', closeModal);
      modal.addEventListener('click', function (event) {
         if (event.target === modal) {
            closeModal();
         }
      });
   }
    
});


