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

document.querySelectorAll('[data-ai-draft-form]').forEach((form) => {
    let submitted = false;

    form.addEventListener('submit', (event) => {
        if (submitted) {
            return;
        }

        event.preventDefault();
        submitted = true;
        startAiGenerationProgress(form);

        window.setTimeout(() => {
            HTMLFormElement.prototype.submit.call(form);
        }, 260);
    });
});

function startAiGenerationProgress(form) {
    const panel = form.querySelector('[data-ai-generation-panel]');
    const bar = form.querySelector('[data-ai-generation-bar]');
    const progressbar = form.querySelector('[data-ai-generation-progressbar]');
    const percent = form.querySelector('[data-ai-generation-percent]');
    const step = form.querySelector('[data-ai-generation-step]');
    const time = form.querySelector('[data-ai-generation-time]');
    const button = form.querySelector('[data-ai-generate-button]');

    if (!panel || !bar || !progressbar || !percent || !step || !time) {
        return;
    }

    const steps = [
        [8, 'Reading source links and building the editorial brief.'],
        [24, 'Extracting the market angle and reader context.'],
        [42, 'Asking the section bot to draft the story structure.'],
        [63, 'Writing headline, dek, brief and article body.'],
        [78, 'Adding source notes, risk notes and newsletter copy.'],
        [91, 'Saving the generated draft for editor review.'],
        [96, 'Almost done. Waiting for the AI response to finish.'],
    ];
    const startedAt = Date.now();
    let current = 0;

    panel.hidden = false;
    form.classList.add('is-generating');
    form.setAttribute('aria-busy', 'true');
    if (button) {
        button.disabled = true;
        button.textContent = 'Generating...';
    }

    const tick = () => {
        const elapsedSeconds = Math.max(0, Math.floor((Date.now() - startedAt) / 1000));
        const target = fallbackAiStep(steps, elapsedSeconds);
        const ceiling = elapsedSeconds < 8 ? 72 : elapsedSeconds < 18 ? 88 : 96;
        current = Math.min(ceiling, Math.max(current + 1, Math.round((elapsedSeconds / 24) * 96), target[0]));

        bar.style.width = `${current}%`;
        progressbar.setAttribute('aria-valuenow', String(current));
        percent.textContent = `${current}%`;
        step.textContent = target[1];
        time.textContent = `${elapsedSeconds}s elapsed`;

        window.setTimeout(tick, 850);
    };

    tick();
}

function fallbackAiStep(steps, elapsedSeconds) {
    let current = steps[0];
    steps.forEach((item) => {
        if (elapsedSeconds >= item[0] / 3) {
            current = item;
        }
    });
    return current;
}

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
