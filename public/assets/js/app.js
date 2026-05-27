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
            recordPublicEvent('share_copy', button.getAttribute('data-share-slug') || '', 'copy-link');
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

document.querySelectorAll('[data-share-event]').forEach((link) => {
    link.addEventListener('click', () => {
        recordPublicEvent('article_share', link.getAttribute('data-share-event') || '', link.textContent || 'share');
    });
});

const completionMarker = document.querySelector('[data-article-complete]');
if (completionMarker && 'IntersectionObserver' in window) {
    let completed = false;
    const completeObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!completed && entry.isIntersecting) {
                completed = true;
                recordPublicEvent('article_complete', completionMarker.getAttribute('data-article-complete') || '', 'scroll-depth');
                completeObserver.disconnect();
            }
        });
    }, { threshold: 0.2 });
    completeObserver.observe(completionMarker);
}

function recordPublicEvent(eventType, slug, source) {
    try {
        const body = new URLSearchParams();
        body.set('event_type', eventType);
        body.set('slug', slug || '');
        body.set('source', source || 'public');
        body.set('path', window.location.pathname.replace(/^\//, ''));
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/api/analytics/event', body);
            return;
        }
        fetch('/api/analytics/event', {
            method: 'POST',
            body,
            headers: { Accept: 'application/json' },
            keepalive: true,
        }).catch(() => {});
    } catch (error) {
    }
}

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

/* =============================================================
   Modal confirmation dialog (replaces window.confirm)
   ============================================================= */
(function () {
    let backdrop;
    let titleEl;
    let bodyEl;
    let iconEl;
    let confirmBtn;
    let cancelBtn;
    let modalEl;
    let lastFocus = null;
    let pending = null;

    function ensureModal() {
        if (backdrop) return;
        backdrop = document.createElement('div');
        backdrop.className = 'mt-modal-backdrop';
        backdrop.setAttribute('role', 'dialog');
        backdrop.setAttribute('aria-modal', 'true');
        backdrop.setAttribute('aria-labelledby', 'mt-modal-title');
        backdrop.innerHTML = '\n<div class="mt-modal" tabindex="-1">\n  <div class="mt-modal-header">\n    <div class="mt-modal-icon" aria-hidden="true">?</div>\n    <h2 class="mt-modal-title" id="mt-modal-title"></h2>\n  </div>\n  <div class="mt-modal-body" id="mt-modal-body"></div>\n  <div class="mt-modal-footer">\n    <button type="button" class="button button-small button-ghost button-cancel"></button>\n    <button type="button" class="button button-small button-confirm"></button>\n  </div>\n</div>';
        document.body.appendChild(backdrop);
        modalEl = backdrop.querySelector('.mt-modal');
        iconEl = backdrop.querySelector('.mt-modal-icon');
        titleEl = backdrop.querySelector('.mt-modal-title');
        bodyEl = backdrop.querySelector('.mt-modal-body');
        confirmBtn = backdrop.querySelector('.button-confirm');
        cancelBtn = backdrop.querySelector('.button-cancel');

        confirmBtn.addEventListener('click', () => resolve(true));
        cancelBtn.addEventListener('click', () => resolve(false));
        backdrop.addEventListener('click', (event) => {
            if (event.target === backdrop) resolve(false);
        });
        document.addEventListener('keydown', (event) => {
            if (!backdrop.classList.contains('is-open')) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                resolve(false);
            } else if (event.key === 'Enter' && event.target !== cancelBtn) {
                event.preventDefault();
                resolve(true);
            }
        });
    }

    function resolve(value) {
        if (!pending) return;
        const cb = pending;
        pending = null;
        backdrop.classList.remove('is-open');
        modalEl.classList.remove('is-danger', 'is-broadcast', 'is-info');
        if (lastFocus && typeof lastFocus.focus === 'function') {
            try { lastFocus.focus(); } catch (_) {}
        }
        lastFocus = null;
        cb(value);
    }

    function openModal(options) {
        ensureModal();
        const variant = options.variant || 'info';
        modalEl.classList.add('is-' + variant);

        const iconChar = variant === 'danger' ? '!' : variant === 'broadcast' ? '↑' : '?';
        iconEl.textContent = iconChar;

        titleEl.textContent = options.title || (variant === 'danger' ? '确认删除' : '请确认');

        bodyEl.innerHTML = '';
        const main = document.createElement('p');
        main.textContent = options.message || '';
        bodyEl.appendChild(main);
        if (options.sub) {
            const sub = document.createElement('p');
            sub.className = 'mt-modal-sub';
            sub.textContent = options.sub;
            bodyEl.appendChild(sub);
        }

        confirmBtn.textContent = options.confirmLabel || (variant === 'danger' ? '删除' : '确认');
        cancelBtn.textContent = options.cancelLabel || '取消';

        lastFocus = document.activeElement;
        backdrop.classList.add('is-open');

        return new Promise((res) => {
            pending = res;
            requestAnimationFrame(() => {
                (variant === 'danger' ? cancelBtn : confirmBtn).focus();
            });
        });
    }

    function parseDataset(button) {
        const ds = button.dataset;
        return {
            message: ds.confirm || '',
            sub: ds.confirmSub || '',
            title: ds.confirmTitle || '',
            variant: ds.confirmVariant || 'info',
            confirmLabel: ds.confirmConfirm || '',
            cancelLabel: ds.confirmCancel || '',
        };
    }

    function bindButton(button) {
        if (button.dataset.confirmBound === '1') return;
        button.dataset.confirmBound = '1';
        button.addEventListener('click', async (event) => {
            if (button.dataset.confirmBypass === '1') {
                button.dataset.confirmBypass = '0';
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            const choice = await openModal(parseDataset(button));
            if (!choice) return;
            button.dataset.confirmBypass = '1';
            // Re-trigger the original behavior — submit form or link nav.
            const form = button.form;
            if (form && (button.type === 'submit' || !button.type)) {
                if (button.name) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = button.name;
                    hidden.value = button.value || '';
                    hidden.setAttribute('data-confirm-temp', '1');
                    form.appendChild(hidden);
                }
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            } else if (button.tagName === 'A' && button.href) {
                window.location.href = button.href;
            } else {
                button.click();
            }
        });
    }

    function init() {
        document.querySelectorAll('[data-confirm]').forEach(bindButton);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose for ad-hoc programmatic use.
    window.moneyTideConfirm = openModal;
})();

