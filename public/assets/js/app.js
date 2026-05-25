document.querySelectorAll('[data-newsletter-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(form);
        const email = formData.get('email');
        const button = form.querySelector('button[type="submit"]');
        if (!email || !button) {
            return;
        }

        const originalText = button.textContent;
        button.textContent = '提交中';
        button.disabled = true;

        try {
            const response = await fetch(form.getAttribute('action') || '/api/newsletter/subscribe', {
                method: 'POST',
                body: formData,
                headers: {
                    Accept: 'application/json',
                },
            });
            const result = await response.json();

            button.textContent = result.ok ? '已加入' : originalText;
            button.disabled = result.ok;
            form.setAttribute('data-submitted', result.ok ? 'true' : 'false');
            showFormMessage(form, result.message || (result.ok ? '订阅成功。' : '订阅失败。'), result.ok);
        } catch (error) {
            button.textContent = originalText;
            button.disabled = false;
            showFormMessage(form, '网络暂时不可用，请稍后再试。', false);
        }
    });
});

function showFormMessage(form, message, isSuccess) {
    let element = form.querySelector('[data-form-message]');
    if (!element) {
        element = document.createElement('p');
        element.setAttribute('data-form-message', '');
        element.className = 'form-message';
        form.appendChild(element);
    }

    element.textContent = message;
    element.classList.toggle('form-message-success', isSuccess);
    element.classList.toggle('form-message-error', !isSuccess);
}
