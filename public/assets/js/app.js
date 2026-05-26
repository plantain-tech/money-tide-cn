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
            if (result.ok) {
                window.localStorage.setItem('moneyTideSubscribed', 'true');
                window.localStorage.setItem('moneyTideReferralUrl', result.referral_url || '');
                document.querySelectorAll('[data-newsletter-slidein]').forEach((panel) => {
                    panel.hidden = true;
                    panel.classList.remove('is-visible');
                });
            }
            showFormMessage(form, result.message || (result.ok ? '订阅成功。' : '订阅失败。'), result.ok);
        } catch (error) {
            button.textContent = originalText;
            button.disabled = false;
            showFormMessage(form, '网络暂时不可用，请稍后再试。', false);
        }
    });
});

const referralCode = new URLSearchParams(window.location.search).get('ref') || window.localStorage.getItem('moneyTideReferralCode') || '';
if (referralCode) {
    window.localStorage.setItem('moneyTideReferralCode', referralCode);
    document.querySelectorAll('[data-referral-input]').forEach((input) => {
        input.value = referralCode;
    });
}

const newsletterSlidein = document.querySelector('[data-newsletter-slidein]');
if (newsletterSlidein && window.localStorage.getItem('moneyTideSubscribed') !== 'true' && window.localStorage.getItem('moneyTideSlideinDismissed') !== 'true') {
    const showSlidein = () => {
        newsletterSlidein.hidden = false;
        window.setTimeout(() => newsletterSlidein.classList.add('is-visible'), 40);
    };
    window.setTimeout(showSlidein, 5200);
    window.addEventListener('scroll', () => {
        if (window.scrollY > 820 && newsletterSlidein.hidden) {
            showSlidein();
        }
    }, { passive: true });
}

document.querySelectorAll('[data-newsletter-slidein-close]').forEach((button) => {
    button.addEventListener('click', () => {
        window.localStorage.setItem('moneyTideSlideinDismissed', 'true');
        const panel = button.closest('[data-newsletter-slidein]');
        if (panel) {
            panel.classList.remove('is-visible');
            window.setTimeout(() => {
                panel.hidden = true;
            }, 220);
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

const revealElements = document.querySelectorAll('.reveal-on-scroll');
if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    revealElements.forEach((element) => revealObserver.observe(element));
} else {
    revealElements.forEach((element) => element.classList.add('is-visible'));
}

const backToTopButton = document.querySelector('[data-back-to-top]');
if (backToTopButton) {
    const updateBackToTop = () => {
        backToTopButton.classList.toggle('is-visible', window.scrollY > 520);
    };

    backToTopButton.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    window.addEventListener('scroll', updateBackToTop, { passive: true });
    updateBackToTop();
}

document.querySelectorAll('[data-share-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
        const url = button.getAttribute('data-share-copy') || window.location.href;
        const originalText = button.textContent;

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(url);
            } else {
                const input = document.createElement('input');
                input.value = url;
                input.setAttribute('readonly', '');
                input.style.position = 'fixed';
                input.style.opacity = '0';
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
            }
            button.textContent = '已复制';
            button.classList.add('is-copied');
            window.setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove('is-copied');
            }, 1800);
        } catch (error) {
            button.textContent = '复制失败';
            window.setTimeout(() => {
                button.textContent = originalText;
            }, 1800);
        }
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
