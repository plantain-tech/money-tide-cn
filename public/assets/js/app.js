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

// 60秒看懂 copy button (public article page)
document.querySelectorAll('[data-shortformat-copy]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const card = btn.closest('.short-format-card');
        const textarea = card ? card.querySelector('[data-shortformat-text]') : null;
        const text = textarea ? textarea.value : '';
        if (!text) return;
        const done = () => {
            const original = btn.textContent;
            btn.textContent = '已复制 ✓';
            window.setTimeout(() => { btn.textContent = original; }, 1600);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, () => { legacyCopy(text); done(); });
        } else {
            legacyCopy(text);
            done();
        }
    });
});

function legacyCopy(text) {
    const input = document.createElement('textarea');
    input.value = text;
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(input);
}

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

document.querySelectorAll('[data-reaction-bar]').forEach((bar) => {
    const articleId = bar.getAttribute('data-article-id') || '';
    const slug = bar.getAttribute('data-slug') || '';
    const foot = bar.querySelector('[data-reaction-foot]');
    bar.querySelectorAll('.reaction-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            if (chip.classList.contains('is-loading')) {
                return;
            }
            const reaction = chip.getAttribute('data-reaction') || '';
            chip.classList.add('is-loading');
            const body = new URLSearchParams();
            body.set('article_id', articleId);
            body.set('reaction', reaction);
            body.set('slug', slug);
            fetch('/api/article/react', {
                method: 'POST',
                body,
                headers: { Accept: 'application/json' },
            })
                .then((response) => response.json())
                .then((data) => {
                    chip.classList.remove('is-loading');
                    if (!data || !data.ok) {
                        return;
                    }
                    const counts = data.counts || {};
                    bar.querySelectorAll('.reaction-chip').forEach((node) => {
                        const key = node.getAttribute('data-reaction') || '';
                        const countEl = node.querySelector('[data-reaction-count]');
                        if (countEl && Object.prototype.hasOwnProperty.call(counts, key)) {
                            countEl.textContent = String(counts[key]);
                        }
                    });
                    if (data.active) {
                        chip.classList.add('is-active');
                        chip.setAttribute('aria-pressed', 'true');
                        chip.classList.remove('reaction-pop');
                        void chip.offsetWidth;
                        chip.classList.add('reaction-pop');
                        if (foot) {
                            foot.hidden = false;
                        }
                    } else {
                        chip.classList.remove('is-active');
                        chip.setAttribute('aria-pressed', 'false');
                    }
                })
                .catch(() => {
                    chip.classList.remove('is-loading');
                });
        });
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

/* =============================================================
   AI generation progress modal (non-dismissible).
   Any form with data-ai-progress shows a blocking modal on submit
   and lets the form post naturally. The modal stays visible until
   the server responds and the page navigates.
   ============================================================= */
(function () {
    var backdrop;
    var titleEl;
    var statusEl;
    var timerEl;
    var stepEl;
    var footEl;
    var startedAt = 0;
    var stepInterval = null;
    var timerInterval = null;
    var blockKeyHandler;
    var defaultPhases = [
        '正在加载模板和上下文',
        '正在调用 AI 模型',
        '模型正在生成内容',
        '正在结构化返回结果',
        '正在保存到数据库',
        '即将刷新页面',
    ];

    function ensureModal() {
        if (backdrop) return;
        backdrop = document.createElement('div');
        backdrop.className = 'mt-ai-progress-backdrop';
        backdrop.setAttribute('role', 'dialog');
        backdrop.setAttribute('aria-modal', 'true');
        backdrop.setAttribute('aria-labelledby', 'mt-ai-progress-title');
        backdrop.innerHTML = '\n<div class="mt-ai-progress-modal">\n  <div class="mt-ai-progress-head">\n    <p class="eyebrow">AI 生成中</p>\n    <h2 id="mt-ai-progress-title">AI 正在工作</h2>\n  </div>\n  <div class="mt-ai-progress-body">\n    <p class="mt-ai-progress-status" id="mt-ai-progress-status"></p>\n    <div class="mt-ai-progress-bar" role="progressbar" aria-valuetext="processing"></div>\n    <div class="mt-ai-progress-meta">\n      <span id="mt-ai-progress-step">processing</span>\n      <span class="mt-ai-timer" id="mt-ai-progress-timer">0s</span>\n    </div>\n  </div>\n  <div class="mt-ai-progress-foot" id="mt-ai-progress-foot">\n    请勿关闭页面或后退。AI 调用通常需要 20–90 秒，完成后会自动跳转。\n  </div>\n</div>';
        document.body.appendChild(backdrop);
        titleEl = backdrop.querySelector('#mt-ai-progress-title');
        statusEl = backdrop.querySelector('#mt-ai-progress-status');
        timerEl = backdrop.querySelector('#mt-ai-progress-timer');
        stepEl = backdrop.querySelector('#mt-ai-progress-step');
        footEl = backdrop.querySelector('#mt-ai-progress-foot');

        // Block Escape entirely while progress modal is open.
        blockKeyHandler = function (event) {
            if (!backdrop.classList.contains('is-open')) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
            }
        };
        document.addEventListener('keydown', blockKeyHandler, true);

        // Block backdrop clicks — they do nothing.
        backdrop.addEventListener('click', function (event) {
            event.stopPropagation();
        });
    }

    function fmtElapsed(seconds) {
        if (seconds < 60) return seconds + 's';
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        return m + 'm ' + (s < 10 ? '0' + s : s) + 's';
    }

    function setStatus(text) {
        if (!statusEl) return;
        statusEl.classList.add('is-changing');
        setTimeout(function () {
            statusEl.textContent = text;
            statusEl.classList.remove('is-changing');
        }, 140);
    }

    function startProgress(options) {
        ensureModal();
        var opts = options || {};
        titleEl.textContent = opts.title || 'AI 正在工作';
        var phases = opts.phases && opts.phases.length ? opts.phases : defaultPhases;
        statusEl.textContent = phases[0];
        stepEl.textContent = '1 / ' + phases.length;
        timerEl.textContent = '0s';
        footEl.textContent = opts.foot || '请勿关闭页面或后退。AI 调用通常需要 20–90 秒，完成后会自动跳转。';
        startedAt = Date.now();
        backdrop.classList.add('is-open');

        var phaseIndex = 0;
        // Rotate through phases on a slow, slightly-jittered cadence.
        var phaseDurations = [3000, 6000, 12000, 12000, 6000, 99999];
        var scheduleNextPhase = function () {
            if (phaseIndex >= phases.length - 1) return;
            var jitter = (Math.random() * 1200) - 400;
            stepInterval = setTimeout(function () {
                phaseIndex++;
                setStatus(phases[phaseIndex]);
                stepEl.textContent = (phaseIndex + 1) + ' / ' + phases.length;
                scheduleNextPhase();
            }, (phaseDurations[phaseIndex] || 6000) + jitter);
        };
        scheduleNextPhase();

        timerInterval = setInterval(function () {
            var elapsed = Math.floor((Date.now() - startedAt) / 1000);
            timerEl.textContent = fmtElapsed(elapsed);
            // After 90s, soften the footer copy.
            if (elapsed === 90) {
                footEl.textContent = '比平常稍慢一些，AI 服务偶尔需要更长时间。请继续等待，不要刷新或后退。';
            }
            if (elapsed === 180) {
                footEl.textContent = 'AI 还在处理。如果一直停在这里，可以打开新标签页查看 /admin/diagnostics。';
            }
        }, 1000);

        // Prevent user from navigating away accidentally.
        window.addEventListener('beforeunload', noop);
    }

    function noop() {}

    function bindForm(form) {
        if (form.dataset.aiProgressBound === '1') return;
        form.dataset.aiProgressBound = '1';
        form.addEventListener('submit', function () {
            var opts = {
                title: form.getAttribute('data-ai-progress-title') || 'AI 正在工作',
                foot: form.getAttribute('data-ai-progress-foot') || '',
            };
            var phasesAttr = form.getAttribute('data-ai-progress-phases');
            if (phasesAttr) {
                try {
                    var parsed = JSON.parse(phasesAttr);
                    if (Array.isArray(parsed) && parsed.length) opts.phases = parsed;
                } catch (_) {}
            }
            startProgress(opts);

            // Disable every submit button in the form so users can't double-fire.
            form.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type])').forEach(function (btn) {
                btn.disabled = true;
                btn.setAttribute('aria-busy', 'true');
            });
            // Let the form post normally — modal stays until page navigation completes.
        }, false);
    }

    function init() {
        document.querySelectorAll('form[data-ai-progress]').forEach(bindForm);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.moneyTideAiProgress = startProgress;
})();



// Week 8 Day 4: lazy image fade-in observer
if ('IntersectionObserver' in window) {
    var lazyImgObserver = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                var img = entry.target;
                img.classList.add('is-loaded');
                lazyImgObserver.unobserve(img);
            }
        });
    }, { rootMargin: '200px' });
    document.querySelectorAll('img[loading="lazy"]').forEach(function(img) {
        if (img.complete) {
            img.classList.add('is-loaded');
        } else {
            img.addEventListener('load', function() { img.classList.add('is-loaded'); });
            lazyImgObserver.observe(img);
        }
    });
}

// Sprint 1 D9.1: news fetch button — show spinner on submit (full RSS pull can take ~20s)
document.querySelectorAll('[data-news-fetch]').forEach(function(form) {
    form.addEventListener('submit', function() {
        var btn = form.querySelector('[data-news-fetch-btn]');
        if (!btn) return;
        var label = btn.querySelector('.news-fetch-label');
        var spinner = btn.querySelector('.news-fetch-spinner');
        if (label) label.textContent = btn.getAttribute('data-loading-label') || '抓取中…';
        if (spinner) spinner.hidden = false;
        btn.classList.add('is-loading');
        btn.disabled = true;
        // Re-enable as a safety net if navigation is blocked for any reason.
        setTimeout(function() { btn.disabled = false; }, 60000);
    });
});

// Sprint 1 D9.6: autopilot master toggle — flip in place (AJAX) with animation,
// no page reload. Falls back to the normal form POST if JS is unavailable.
document.querySelectorAll('[data-autopilot-toggle]').forEach(function(btn) {
    btn.addEventListener('click', function(event) {
        event.preventDefault();
        if (btn.dataset.busy === '1') return;
        btn.dataset.busy = '1';
        btn.classList.add('is-switching');
        fetch('/admin/autopilot/toggle', { method: 'POST', headers: { Accept: 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                btn.dataset.busy = '0';
                btn.classList.remove('is-switching');
                if (!d || !d.ok) return;
                var on = !!d.enabled;
                var master = document.querySelector('[data-autopilot-master]');

                btn.classList.toggle('is-on', on);
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                var lbl = btn.querySelector('.autopilot-toggle-label');
                if (lbl) lbl.textContent = on ? 'ON' : 'OFF';

                if (master) {
                    master.classList.toggle('is-on', on);
                    master.classList.toggle('is-off', !on);
                    master.classList.remove('ap-flash');
                    void master.offsetWidth; // restart animation
                    master.classList.add('ap-flash');
                }
                var t = document.querySelector('[data-ap-title]');
                if (t) t.textContent = d.title;
                var ds = document.querySelector('[data-ap-desc]');
                if (ds) ds.textContent = d.desc;

                var hint = document.querySelector('[data-ap-hint]');
                if (hint) {
                    hint.textContent = d.hint;
                    hint.hidden = false;
                    hint.classList.toggle('ap-hint-on', on);
                    hint.classList.toggle('ap-hint-off', !on);
                    hint.classList.remove('ap-hint-show');
                    void hint.offsetWidth;
                    hint.classList.add('ap-hint-show');
                }
            })
            .catch(function() {
                btn.dataset.busy = '0';
                btn.classList.remove('is-switching');
            });
    });
});

// Day 9·7 — Autonomous engine control room (week9 finale) interactions.
(function () {
    var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Count-up animation for [data-countup] (readiness %, dry-run stage numbers).
    function countUp(el) {
        var target = parseInt(el.getAttribute('data-countup'), 10) || 0;
        if (prefersReduced || target === 0) { el.textContent = String(target); return; }
        var start = null, dur = 900;
        function step(ts) {
            if (start === null) start = ts;
            var p = Math.min((ts - start) / dur, 1);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = String(Math.round(eased * target));
            if (p < 1) requestAnimationFrame(step);
            else el.textContent = String(target);
        }
        requestAnimationFrame(step);
    }
    function runCountUps(scope) {
        (scope || document).querySelectorAll('[data-countup]').forEach(function (el) {
            if (el.dataset.counted === '1') return;
            el.dataset.counted = '1';
            countUp(el);
        });
    }
    // Run when visible (IntersectionObserver) so numbers animate as you scroll in.
    if ('IntersectionObserver' in window && !prefersReduced) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { countUp(en.target); en.target.dataset.counted = '1'; io.unobserve(en.target); }
            });
        }, { threshold: 0.4 });
        document.querySelectorAll('[data-countup]').forEach(function (el) {
            if (el.dataset.counted !== '1') io.observe(el);
        });
    } else {
        runCountUps(document);
    }

    // Threshold slider: live bubble that tracks the thumb.
    var range = document.querySelector('[data-threshold-range]');
    var bubble = document.querySelector('[data-threshold-bubble]');
    if (range && bubble) {
        var positionBubble = function () {
            var min = parseFloat(range.min) || 50;
            var max = parseFloat(range.max) || 100;
            var val = parseFloat(range.value);
            var pct = (val - min) / (max - min);
            bubble.textContent = String(val);
            // 24px thumb -> offset so the bubble stays over the thumb center.
            var width = range.offsetWidth;
            bubble.style.left = (pct * (width - 24) + 12) + 'px';
        };
        range.addEventListener('input', positionBubble);
        window.addEventListener('resize', positionBubble);
        positionBubble();
    }

    // Scroll a fresh dry-run result into view for the demo.
    var dryrun = document.querySelector('[data-dryrun]');
    if (dryrun && !prefersReduced) {
        dryrun.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
})();

// Day 9 polish — staged pipeline run with a blocking progress modal.
// Splits the long pipeline into short AJAX steps (no host request timeout),
// paces AI calls to dodge free-tier throttling, and shows live progress.
(function () {
    var btn = document.querySelector('[data-run-pipeline]');
    var modal = document.querySelector('[data-run-modal]');
    if (!btn || !modal) return;

    var stepUrl = btn.getAttribute('data-step-url');
    var fill = modal.querySelector('[data-run-fill]');
    var pctEl = modal.querySelector('[data-run-percent]');
    var stepCountEl = modal.querySelector('[data-run-stepcount]');
    var detailEl = modal.querySelector('[data-run-detail]');
    var titleEl = modal.querySelector('[data-run-title]');
    var subEl = modal.querySelector('[data-run-subtitle]');
    var footEl = modal.querySelector('[data-run-foot]');
    var summaryEl = modal.querySelector('[data-run-summary]');
    var closeBtn = modal.querySelector('[data-run-close]');
    var sparkEl = modal.querySelector('[data-run-spark]');

    var total = 0, done = 0, running = false;
    var stages;

    function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
    function setBar() {
        var p = total > 0 ? Math.round(done / total * 100) : 0;
        if (p > 100) p = 100;
        fill.style.width = p + '%';
        pctEl.textContent = p + '%';
        stepCountEl.textContent = '步骤 ' + done + ' / ' + total;
    }
    function stepState(key, state) {
        var li = modal.querySelector('[data-run-step="' + key + '"]');
        if (!li) return;
        li.classList.remove('is-active', 'is-done', 'is-error');
        if (state) li.classList.add('is-' + state);
        var st = li.querySelector('.run-step-state');
        if (st) st.textContent = state === 'done' ? '✓' : (state === 'error' ? '!' : '');
    }
    function detail(msg) { if (detailEl) detailEl.textContent = msg; }

    function post(payload) {
        return fetch(stepUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status + '（主机可能仍限制了该步骤时长）');
            return res.json();
        });
    }

    function run() {
        if (running) return;
        running = true;
        btn.disabled = true;
        stages = { ingest: {}, cluster: { clusters: 0 }, synthesize: { drafts: 0 }, assess: {}, publish: {}, assemble: {} };
        ['ingest', 'cluster', 'synthesize', 'assess', 'publish'].forEach(function (k) { stepState(k, ''); });
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        if (footEl) footEl.hidden = true;
        if (sparkEl) sparkEl.textContent = '🛰';
        if (titleEl) titleEl.textContent = '正在运行流水线…';
        if (subEl) subEl.textContent = '请稍候，分步执行中，请勿关闭本页。';
        var t0 = Date.now();

        (async function () {
            try {
                detail('正在规划…');
                var plan = await post({ op: 'plan' });
                if (!plan.ok) throw new Error(plan.error || '规划失败');
                var cats = plan.categories || [];
                total = 1 + cats.length + 1 + 1;
                done = 0; setBar();

                stepState('ingest', 'active'); detail('抓取新闻素材…');
                var ing = await post({ op: 'ingest' });
                if (!ing.ok) throw new Error(ing.error || '抓取失败');
                stages.ingest = { new_items: ing.count || 0 };
                detail(ing.detail || ''); done++; setBar(); stepState('ingest', 'done');

                stepState('cluster', 'active');
                for (var i = 0; i < cats.length; i++) {
                    detail('聚类：' + cats[i].name + ' …（' + (i + 1) + '/' + cats.length + '）');
                    var cl = await post({ op: 'cluster', slug: cats[i].slug });
                    if (cl.ok) stages.cluster.clusters += (cl.count || 0);
                    detail('聚类 ' + cats[i].name + '：' + (cl.detail || ''));
                    done++; setBar();
                    if (i < cats.length - 1) await sleep(1200);
                }
                stepState('cluster', 'done');

                stepState('synthesize', 'active'); detail('挑选待写稿选题…');
                var tg = await post({ op: 'synth_targets' });
                var ids = (tg && tg.ids) || [];
                total += ids.length; setBar();
                for (var j = 0; j < ids.length; j++) {
                    detail('AI 写稿 ' + (j + 1) + '/' + ids.length + ' …');
                    var sy = await post({ op: 'synthesize', id: ids[j] });
                    if (sy.created) stages.synthesize.drafts++;
                    detail((sy.detail || '') + '（已写 ' + stages.synthesize.drafts + ' 篇）');
                    done++; setBar();
                    if (j < ids.length - 1) await sleep(1500);
                }
                stepState('synthesize', 'done');

                stepState('assess', 'active'); detail('AI 审核闸门…');
                var as = await post({ op: 'assess' });
                stages.assess = { auto: as.auto || 0, review: as.review || 0 };
                detail(as.detail || ''); done++; setBar(); stepState('assess', 'done');

                stepState('publish', 'active'); detail('发布与早报组装…');
                var pb = await post({ op: 'publish' });
                stages.publish = { articles: pb.articles || 0 };
                stages.assemble = { issues: pb.issues || 0 };
                detail(pb.detail || ''); done++; setBar(); stepState('publish', 'done');

                var elapsed = Math.round((Date.now() - t0) / 1000);
                var fin = await post({ op: 'finish', stages: stages, elapsed: elapsed });
                done = total; setBar();
                if (titleEl) titleEl.textContent = '✅ 运行完成';
                if (subEl) subEl.textContent = '本次运行已记录到「运行记录」。';
                if (sparkEl) sparkEl.textContent = '✅';
                if (summaryEl) summaryEl.textContent = (fin.summary || '') + ' · 用时 ' + elapsed + ' 秒';
                if (footEl) footEl.hidden = false;
            } catch (err) {
                if (titleEl) titleEl.textContent = '⚠️ 运行中断';
                if (subEl) subEl.textContent = '某一步出错，已停止。已完成步骤的进度已保存，可关闭后重试或交给 Cron 续跑。';
                detail('错误：' + (err && err.message ? err.message : err));
                if (sparkEl) sparkEl.textContent = '⚠️';
                var active = modal.querySelector('.run-steps .is-active');
                if (active) { active.classList.remove('is-active'); active.classList.add('is-error'); var s = active.querySelector('.run-step-state'); if (s) s.textContent = '!'; }
                if (footEl) footEl.hidden = false;
            } finally {
                running = false;
                btn.disabled = false;
            }
        })();
    }

    btn.addEventListener('click', run);
    if (closeBtn) closeBtn.addEventListener('click', function () {
        modal.hidden = true;
        document.body.style.overflow = '';
        window.location.reload();
    });
})();

// Staged AI assessment runner (review queue): drives "批量审核" and "重新评估"
// one draft per AJAX call so no single request times out (fixes the 503), with a
// blocking, animated progress modal like the pipeline run.
(function () {
    var modal = document.querySelector('[data-assess-modal]');
    var btns = document.querySelectorAll('[data-assess-run]');
    if (!modal || !btns.length) return;

    var fill = modal.querySelector('[data-run-fill]');
    var pctEl = modal.querySelector('[data-run-percent]');
    var stepCountEl = modal.querySelector('[data-run-stepcount]');
    var detailEl = modal.querySelector('[data-run-detail]');
    var titleEl = modal.querySelector('[data-run-title]');
    var subEl = modal.querySelector('[data-run-subtitle]');
    var footEl = modal.querySelector('[data-run-foot]');
    var summaryEl = modal.querySelector('[data-run-summary]');
    var closeBtn = modal.querySelector('[data-run-close]');
    var sparkEl = modal.querySelector('[data-run-spark]');
    var running = false;

    function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
    function post(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status + '（主机可能限制了该步骤时长）');
            return res.json();
        });
    }
    function setBar(done, total) {
        var p = total > 0 ? Math.round(done / total * 100) : 0;
        if (p > 100) p = 100;
        fill.style.width = p + '%';
        pctEl.textContent = p + '%';
        stepCountEl.textContent = '已评估 ' + done + ' / ' + total;
    }

    function run(btn) {
        if (running) return;
        running = true;
        btns.forEach(function (b) { b.disabled = true; });
        var url = btn.getAttribute('data-step-url');
        var mode = btn.getAttribute('data-mode') || 'new';
        var title = btn.getAttribute('data-title') || 'AI 审核中…';
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        if (footEl) footEl.hidden = true;
        if (sparkEl) sparkEl.textContent = '🔍';
        if (titleEl) titleEl.textContent = title;
        if (subEl) subEl.textContent = '正在逐篇调用 AI 事实核查，请勿关闭本页。';
        if (detailEl) detailEl.textContent = '正在规划…';
        setBar(0, 0);
        var auto = 0, review = 0, failed = 0, t0 = Date.now();

        (async function () {
            try {
                var plan = await post(url, { op: 'plan', mode: mode });
                if (!plan.ok) throw new Error(plan.error || '规划失败');
                var ids = plan.ids || [];
                var total = ids.length;
                setBar(0, total);
                if (total === 0) {
                    if (titleEl) titleEl.textContent = '没有需要处理的草稿';
                    if (sparkEl) sparkEl.textContent = '👍';
                    if (subEl) subEl.textContent = ' ';
                    if (detailEl) detailEl.textContent = mode === 'requeue' ? '人工队列里没有待重新评估的草稿。' : '没有「已起草未审核」的草稿。';
                    if (summaryEl) summaryEl.textContent = '无需处理。';
                    if (footEl) footEl.hidden = false;
                    return;
                }
                for (var i = 0; i < ids.length; i++) {
                    if (detailEl) detailEl.textContent = '评估草稿 #' + ids[i] + ' …（' + (i + 1) + '/' + total + '）';
                    var r;
                    try { r = await post(url, { op: 'assess', id: ids[i] }); }
                    catch (e) { r = { ok: false, detail: e.message }; }
                    if (r.ok) { (r.recommendation === 'auto_approve') ? auto++ : review++; }
                    else { failed++; }
                    if (detailEl) detailEl.textContent = '#' + ids[i] + '：' + (r.detail || '') + ' · 累计 自动通过 ' + auto + ' / 转人工 ' + review;
                    setBar(i + 1, total);
                    if (i < ids.length - 1) await sleep(1500);
                }
                var elapsed = Math.round((Date.now() - t0) / 1000);
                if (titleEl) titleEl.textContent = '✅ 评估完成';
                if (subEl) subEl.textContent = '结果已写入审核台。';
                if (sparkEl) sparkEl.textContent = '✅';
                if (summaryEl) summaryEl.textContent = '自动通过 ' + auto + ' · 转人工 ' + review + (failed ? (' · 失败 ' + failed) : '') + ' · 用时 ' + elapsed + ' 秒';
                if (footEl) footEl.hidden = false;
            } catch (err) {
                if (titleEl) titleEl.textContent = '⚠️ 评估中断';
                if (subEl) subEl.textContent = '出错已停止，已完成的进度已保存。';
                if (detailEl) detailEl.textContent = '错误：' + (err && err.message ? err.message : err);
                if (sparkEl) sparkEl.textContent = '⚠️';
                if (footEl) footEl.hidden = false;
            } finally {
                running = false;
                btns.forEach(function (b) { b.disabled = false; });
            }
        })();
    }

    btns.forEach(function (btn) { btn.addEventListener('click', function () { run(btn); }); });
    if (closeBtn) closeBtn.addEventListener('click', function () {
        modal.hidden = true;
        document.body.style.overflow = '';
        window.location.reload();
    });
})();

// Staged cluster/synthesis runner (story-clusters): drives "AI 聚类选题" and
// "批量生成草稿" one item per AJAX call (no host 503), with the same blocking,
// animated progress modal.
(function () {
    var modal = document.querySelector('[data-cluster-modal]');
    var btns = document.querySelectorAll('[data-cluster-run]');
    if (!modal || !btns.length) return;

    var fill = modal.querySelector('[data-run-fill]');
    var pctEl = modal.querySelector('[data-run-percent]');
    var stepCountEl = modal.querySelector('[data-run-stepcount]');
    var detailEl = modal.querySelector('[data-run-detail]');
    var titleEl = modal.querySelector('[data-run-title]');
    var subEl = modal.querySelector('[data-run-subtitle]');
    var footEl = modal.querySelector('[data-run-foot]');
    var summaryEl = modal.querySelector('[data-run-summary]');
    var closeBtn = modal.querySelector('[data-run-close]');
    var sparkEl = modal.querySelector('[data-run-spark]');
    var running = false;

    function sleep(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }
    function post(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status + '（主机可能限制了该步骤时长）');
            return res.json();
        });
    }
    function setBar(done, total) {
        var p = total > 0 ? Math.round(done / total * 100) : 0;
        if (p > 100) p = 100;
        fill.style.width = p + '%';
        pctEl.textContent = p + '%';
        stepCountEl.textContent = done + ' / ' + total;
    }

    function run(btn) {
        if (running) return;
        running = true;
        btns.forEach(function (b) { b.disabled = true; });
        var url = btn.getAttribute('data-step-url');
        var planOp = btn.getAttribute('data-plan-op');
        var stepOp = btn.getAttribute('data-step-op');
        var noun = btn.getAttribute('data-noun') || '项';
        var spark = btn.getAttribute('data-spark') || '⚙️';
        var title = btn.getAttribute('data-title') || '处理中…';
        // Category may come from a sibling <select> (clustering) or a static attr.
        var slug = btn.getAttribute('data-slug') || '';
        var wrap = btn.closest('[data-cluster-wrap]');
        if (wrap) {
            var sel = wrap.querySelector('select[name="category_slug"]');
            if (sel) slug = sel.value;
        }
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        if (footEl) footEl.hidden = true;
        if (sparkEl) sparkEl.textContent = spark;
        if (titleEl) titleEl.textContent = title;
        if (subEl) subEl.textContent = '正在分步调用 AI，请勿关闭本页。';
        if (detailEl) detailEl.textContent = '正在规划…';
        setBar(0, 0);
        var made = 0, fail = 0, t0 = Date.now();

        (async function () {
            try {
                var plan = await post(url, { op: planOp, slug: slug });
                if (!plan.ok) throw new Error(plan.error || '规划失败');
                var items = plan.items || [];
                var total = items.length;
                setBar(0, total);
                if (total === 0) {
                    if (titleEl) titleEl.textContent = '没有需要处理的项目';
                    if (sparkEl) sparkEl.textContent = '👍';
                    if (subEl) subEl.textContent = ' ';
                    if (detailEl) detailEl.textContent = planOp === 'synth_plan' ? '没有「已选用且未生成草稿」的 cluster。先在下面选用一些 cluster。' : '没有可聚类的栏目。';
                    if (summaryEl) summaryEl.textContent = '无需处理。';
                    if (footEl) footEl.hidden = false;
                    return;
                }
                for (var i = 0; i < items.length; i++) {
                    if (detailEl) detailEl.textContent = items[i].label + ' …（' + (i + 1) + '/' + total + '）';
                    var r;
                    try { r = await post(url, { op: stepOp, key: items[i].key }); }
                    catch (e) { r = { ok: false, detail: e.message }; }
                    if (r.ok) { made += (r.count != null ? r.count : (r.created ? 1 : 0)); }
                    else { fail++; }
                    if (detailEl) detailEl.textContent = items[i].label + '：' + (r.detail || '') + ' · 累计 ' + made + ' ' + noun;
                    setBar(i + 1, total);
                    if (i < items.length - 1) await sleep(1500);
                }
                var elapsed = Math.round((Date.now() - t0) / 1000);
                if (titleEl) titleEl.textContent = '✅ 完成';
                if (subEl) subEl.textContent = '结果已保存。';
                if (sparkEl) sparkEl.textContent = '✅';
                if (summaryEl) summaryEl.textContent = '共 ' + made + ' ' + noun + (fail ? (' · 失败 ' + fail) : '') + ' · 用时 ' + elapsed + ' 秒';
                if (footEl) footEl.hidden = false;
            } catch (err) {
                if (titleEl) titleEl.textContent = '⚠️ 中断';
                if (subEl) subEl.textContent = '出错已停止，已完成的进度已保存。';
                if (detailEl) detailEl.textContent = '错误：' + (err && err.message ? err.message : err);
                if (sparkEl) sparkEl.textContent = '⚠️';
                if (footEl) footEl.hidden = false;
            } finally {
                running = false;
                btns.forEach(function (b) { b.disabled = false; });
            }
        })();
    }

    btns.forEach(function (btn) { btn.addEventListener('click', function () { run(btn); }); });
    if (closeBtn) closeBtn.addEventListener('click', function () {
        modal.hidden = true;
        document.body.style.overflow = '';
        window.location.reload();
    });
})();

// Live market ticker: poll the snapshot endpoint; update price + change, redraw
// the sparkline, and flash on change. Cached server-side, so polling is cheap.
(function () {
    var strip = document.querySelector('[data-market-strip]');
    if (!strip) return;

    function sparkPoints(arr) {
        if (!arr || arr.length < 2) return '';
        var w = 64, h = 22, pad = 2, min = Math.min.apply(null, arr), max = Math.max.apply(null, arr);
        var range = (max - min) || 1, n = arr.length, out = [];
        for (var i = 0; i < n; i++) {
            var x = pad + i * (w - 2 * pad) / (n - 1);
            var y = h - pad - ((arr[i] - min) / range) * (h - 2 * pad);
            out.push(x.toFixed(1) + ',' + y.toFixed(1));
        }
        return out.join(' ');
    }

    function setDir(el, dir) {
        el.classList.remove('is-up', 'is-down');
        if (dir > 0) el.classList.add('is-up'); else if (dir < 0) el.classList.add('is-down');
    }

    function render(data) {
        if (!data) return;
        Object.keys(data).forEach(function (k) {
            var item = strip.querySelector('[data-market-item="' + k + '"]');
            if (!item) return;
            var m = data[k] || {};
            var dir = m.dir || 0;
            var priceEl = item.querySelector('[data-market-price]');
            var changeEl = item.querySelector('[data-market-change]');
            var sparkEl = item.querySelector('[data-market-spark]');
            var price = m.price || '—';
            var changeText = ((m.change || '') + ' ' + (m.pct || '')).trim();
            var arrow = dir > 0 ? '▲' : (dir < 0 ? '▼' : '');

            // Sparkline (always redraw with current direction color)
            if (sparkEl && m.spark && m.spark.length > 1) {
                var color = dir > 0 ? '#3fb950' : (dir < 0 ? '#ff5c5c' : '#9aa0a6');
                sparkEl.innerHTML = '<polyline fill="none" stroke="' + color + '" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" points="' + sparkPoints(m.spark) + '"/>';
                setDir(sparkEl, dir);
            }
            if (changeEl) {
                changeEl.innerHTML = (arrow ? '<span class="ticker-arrow" aria-hidden="true">' + arrow + '</span>' : '') + changeText;
                setDir(changeEl, dir);
            }
            // Flash + update price only when it actually changed
            if (priceEl && priceEl.getAttribute('data-last') !== price) {
                priceEl.textContent = price;
                priceEl.setAttribute('data-last', price);
                priceEl.classList.remove('market-flash');
                void priceEl.offsetWidth;
                priceEl.classList.add('market-flash');
            }
        });
    }

    function poll() {
        fetch('/api/market-snapshot', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) { if (j && j.data) render(j.data); })
            .catch(function () { /* keep last values on failure */ });
    }

    poll();
    setInterval(poll, 60000); // refresh every 60s (matches server cache TTL)
})();

// Interstitial (house/sponsor) modal: blocking on open, close unlocks after a
// countdown (Android-app style), with a frequency cap.
(function () {
    var box = document.querySelector('[data-interstitial]');
    if (!box) return;
    var delay = parseInt(box.getAttribute('data-delay') || '8', 10);
    if (isNaN(delay) || delay < 0) delay = 8;
    var freq = box.getAttribute('data-freq') || 'session';
    var KEY = 'mtInterstitialSeen';
    var now = Date.now();

    // Frequency cap: 'always' = every open, 'session' = once per tab session,
    // 'daily' = once per 24h.
    try {
        if (freq === 'session' && sessionStorage.getItem(KEY)) return;
        if (freq === 'daily') {
            var last = parseInt(localStorage.getItem(KEY) || '0', 10);
            if (last && (now - last) < 86400000) return;
        }
    } catch (e) { /* storage blocked — just show it */ }

    var timerEl = box.querySelector('[data-interstitial-timer]');
    var closeBtn = box.querySelector('[data-interstitial-close]');
    var remaining = delay;

    function markSeen() {
        try {
            if (freq === 'session') sessionStorage.setItem(KEY, '1');
            else if (freq === 'daily') localStorage.setItem(KEY, String(now));
        } catch (e) {}
    }
    function dismiss() {
        box.hidden = true;
        document.body.style.overflow = '';
        markSeen();
    }

    // Show, lock the page.
    box.hidden = false;
    document.body.style.overflow = 'hidden';

    if (remaining > 0 && timerEl) {
        timerEl.textContent = String(remaining);
        var iv = setInterval(function () {
            remaining--;
            if (remaining > 0) {
                timerEl.textContent = String(remaining);
            } else {
                clearInterval(iv);
                timerEl.hidden = true;
                if (closeBtn) closeBtn.hidden = false;
            }
        }, 1000);
    } else if (closeBtn) {
        if (timerEl) timerEl.hidden = true;
        closeBtn.hidden = false;
    }

    if (closeBtn) closeBtn.addEventListener('click', dismiss);
    // Closing via the CTA also counts as seen.
    var cta = box.querySelector('.interstitial-cta');
    if (cta) cta.addEventListener('click', markSeen);
})();
