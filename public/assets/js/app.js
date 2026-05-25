document.querySelectorAll('[data-newsletter-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const email = new FormData(form).get('email');
        const button = form.querySelector('button[type="submit"]');
        if (!email || !button) {
            return;
        }

        button.textContent = '已加入';
        button.disabled = true;
        form.setAttribute('data-submitted', 'true');
    });
});
