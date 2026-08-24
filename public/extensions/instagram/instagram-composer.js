// Instaflow composer — mode tabs, schedule toggle, caption counter, live phone
// preview, and submit guards. Page key: instagram-composer (registered in app.js).
function init() {
    const form = document.getElementById('composer-form');
    if (!form) return;

    const typeInput = document.getElementById('cmp-media-type');
    const modeTabs = Array.from(document.querySelectorAll('[data-mode]'));
    const mediaGroups = Array.from(document.querySelectorAll('[data-media-for]'));
    const pvImage = document.getElementById('pv-image');

    // Render an uploaded file into the phone preview — IMAGE via background,
    // VIDEO/REEL via an injected <video> (a div background can't play video,
    // which is why reels/videos never previewed before).
    const pvPlaceholder = () => document.getElementById('pv-placeholder');
    const setPreviewMedia = (f) => {
        if (!pvImage || !f) return;
        pvPlaceholder()?.classList.add('hidden');   // real media replaces the photo placeholder
        pvImage.querySelector('video')?.remove();
        if (f.type.startsWith('video/')) {
            pvImage.style.backgroundImage = '';
            const v = document.createElement('video');
            v.src = URL.createObjectURL(f);
            v.muted = true; v.autoplay = true; v.loop = true; v.playsInline = true;
            v.setAttribute('playsinline', '');
            v.className = 'absolute inset-0 w-full h-full object-cover';
            pvImage.classList.add('relative');
            pvImage.appendChild(v);
        } else if (f.type.startsWith('image/')) {
            const r = new FileReader();
            r.onload = (e) => {
                pvImage.style.backgroundImage = `url("${e.target.result}")`;
                pvImage.style.backgroundSize = 'cover';
                pvImage.style.backgroundPosition = 'center';
            };
            r.readAsDataURL(f);
        }
    };
    const clearPreviewMedia = () => {
        if (!pvImage) return;
        pvImage.querySelector('video')?.remove();
        pvImage.style.backgroundImage = '';
        pvPlaceholder()?.classList.remove('hidden');   // bring the photo placeholder back
    };

    const showMedia = (mode) => {
        mediaGroups.forEach((g) => {
            const forList = (g.dataset.mediaFor || '').split(',');
            g.classList.toggle('hidden', !forList.includes(mode));
        });
        // Story + Reel are vertical (9:16); Post + Carousel use Instagram's 4:5 feed ratio.
        if (pvImage) {
            const vertical = mode === 'story' || mode === 'reel';
            pvImage.style.aspectRatio = vertical ? '9 / 16' : '4 / 5';
        }
    };
    const setMode = (mode) => {
        if (typeInput) typeInput.value = mode;
        modeTabs.forEach((t) => {
            const on = t.dataset.mode === mode;
            t.classList.toggle('active', on);
            t.classList.toggle('ig-grad-soft', on);
            t.classList.toggle('text-white', on);
        });
        showMedia(mode);
    };
    modeTabs.forEach((t) => t.addEventListener('click', () => setMode(t.dataset.mode)));
    setMode(typeInput?.value || 'image');

    // ── Single file upload → thumb + phone preview ──
    const fileInput = document.getElementById('cmp-file');
    const fileThumb = document.getElementById('cmp-file-thumb');
    const fileImg = document.getElementById('cmp-file-img');
    const fileName = document.getElementById('cmp-file-name');
    fileInput?.addEventListener('change', () => {
        const f = fileInput.files?.[0];
        if (!f) return;
        if (fileName) fileName.textContent = f.name;
        fileThumb?.classList.remove('hidden');
        fileThumb?.classList.add('flex');
        // Small upload thumbnail (images only; a video shows a play glyph via the file name).
        if (fileImg) {
            if (f.type.startsWith('image/')) {
                const r = new FileReader();
                r.onload = (e) => { fileImg.style.backgroundImage = `url("${e.target.result}")`; };
                r.readAsDataURL(f);
            } else {
                fileImg.style.backgroundImage = '';
            }
        }
        // Phone preview — handles both image and video.
        setPreviewMedia(f);
    });
    document.getElementById('cmp-file-clear')?.addEventListener('click', () => {
        if (fileInput) fileInput.value = '';
        fileThumb?.classList.add('hidden');
        fileThumb?.classList.remove('flex');
        if (fileImg) fileImg.style.backgroundImage = '';
        clearPreviewMedia();
    });

    // ── Carousel multi-file → thumbnails ──
    const filesInput = document.getElementById('cmp-files');
    const filesThumbs = document.getElementById('cmp-files-thumbs');
    filesInput?.addEventListener('change', () => {
        if (!filesThumbs) return;
        filesThumbs.innerHTML = '';
        const files = Array.from(filesInput.files || []).slice(0, 10);
        files.forEach((f) => {
            const d = document.createElement('div');
            d.className = 'w-14 h-14 rounded-lg bg-paper-100 bg-center bg-cover ring-1 ring-paper-200';
            if (f.type.startsWith('image/')) {
                const r = new FileReader();
                r.onload = (e) => { d.style.backgroundImage = `url("${e.target.result}")`; };
                r.readAsDataURL(f);
            }
            filesThumbs.appendChild(d);
        });
        filesThumbs.classList.toggle('hidden', files.length === 0);
        filesThumbs.classList.toggle('flex', files.length > 0);
        // Show the first carousel slide in the phone preview.
        if (files.length) setPreviewMedia(files[0]); else clearPreviewMedia();
    });

    // ── Drag & drop onto the upload zones ──
    const wireDrop = (zoneId, input) => {
        const zone = document.getElementById(zoneId);
        if (!zone || !input) return;
        ['dragenter', 'dragover'].forEach((ev) => zone.addEventListener(ev, (e) => {
            e.preventDefault();
            zone.classList.add('border-ig-pink', 'bg-paper-50');
        }));
        ['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, (e) => {
            e.preventDefault();
            zone.classList.remove('border-ig-pink', 'bg-paper-50');
        }));
        zone.addEventListener('drop', (e) => {
            const files = e.dataTransfer?.files;
            if (files && files.length) {
                input.files = files;
                input.dispatchEvent(new Event('change'));
            }
        });
    };
    wireDrop('cmp-drop', fileInput);
    wireDrop('cmp-drop-multi', filesInput);

    // ── When to publish: now vs schedule (separate date + time + timezone,
    //    WaDesk-campaign style — the single datetime-local looked broken) ──
    let whenMode = 'now';
    const whenBtns = Array.from(document.querySelectorAll('[data-when]'));
    const schedFields = document.getElementById('cmp-schedule-fields');
    const schedDate = document.getElementById('cmp-sched-date');
    const schedTime = document.getElementById('cmp-sched-time');
    const submitLabel = document.getElementById('cmp-submit-label');
    const pad = (n) => String(n).padStart(2, '0');
    // Sensible default = ~1h from now; min date = today (no scheduling in the past).
    const soon = new Date(Date.now() + 60 * 60 * 1000);
    const dfltDate = `${soon.getFullYear()}-${pad(soon.getMonth() + 1)}-${pad(soon.getDate())}`;
    const dfltTime = `${pad(soon.getHours())}:${pad(soon.getMinutes())}`;
    const today = new Date();
    if (schedDate) schedDate.min = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;
    const setWhen = (mode) => {
        whenMode = mode;
        whenBtns.forEach((b) => {
            const on = b.dataset.when === mode;
            b.classList.toggle('bg-ig-pink/5', on);
            b.classList.toggle('border-ig-pink/40', on);
        });
        const sched = mode === 'schedule';
        schedFields?.classList.toggle('hidden', !sched);
        if (sched) {
            // Pre-fill on first open so the fields are never blank.
            if (schedDate && !schedDate.value) schedDate.value = dfltDate;
            if (schedTime && !schedTime.value) schedTime.value = dfltTime;
        } else {
            // "Publish now" → clear so the server never treats it as scheduled.
            if (schedDate) schedDate.value = '';
            if (schedTime) schedTime.value = '';
        }
        if (submitLabel) submitLabel.textContent = sched ? 'Schedule post' : 'Publish now';
    };
    whenBtns.forEach((b) => b.addEventListener('click', () => setWhen(b.dataset.when)));
    setWhen('now');

    // ── Caption counter + live preview ──
    const caption = document.getElementById('cmp-caption');
    const count = document.getElementById('cmp-count');
    const pvCaption = document.getElementById('pv-caption');
    const syncCaption = () => {
        const v = caption?.value || '';
        if (count) {
            count.textContent = `${v.length} / 2,200`;
            count.classList.toggle('text-accent-coral', v.length > 2200);
            count.classList.toggle('text-ink-400', v.length <= 2200);
        }
        if (pvCaption) pvCaption.textContent = v || 'Your caption preview…';
    };
    caption?.addEventListener('input', syncCaption);
    syncCaption();
    // (Media preview is driven by the file-upload handler above — composer is
    // upload-only, so there are no URL inputs to watch.)

    // ── Account radio → preview name/avatar ──
    const pvName = document.getElementById('pv-name');
    const pvInitials = document.getElementById('pv-initials');
    const pvAvatarImg = document.getElementById('pv-avatar-img');
    document.querySelectorAll('input[name="instagram_account_id"]').forEach((r) => {
        r.addEventListener('change', () => {
            if (pvName) pvName.textContent = r.dataset.username || 'your.account';
            if (pvInitials) pvInitials.textContent = r.dataset.initials || 'IG';
            // Swap the profile photo; hide it (revealing initials) when this
            // account has none or the URL fails to load.
            if (pvAvatarImg) {
                const url = r.dataset.avatar || '';
                if (url) { pvAvatarImg.src = url; pvAvatarImg.classList.remove('hidden'); }
                else { pvAvatarImg.removeAttribute('src'); pvAvatarImg.classList.add('hidden'); }
            }
        });
    });

    // ── AI composer tools ──
    // Five tools wired to /instagram/composer/ai/* JSON endpoints. Each fails
    // gracefully: a { ok:false, error } response is toasted, never thrown.
    (function wireAiTools() {
        const aiCard = document.getElementById('cmp-ai');
        if (!aiCard) return;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const notes = document.getElementById('cmp-ai-notes');
        const toast = (m, t) => window.toast?.(m, t);

        // POST helper — root-absolute URL (layout has a base-path fetch shim),
        // same-origin + CSRF token, always resolves to a parsed object.
        const post = async (path, body) => {
            const res = await fetch(path, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body || {}),
            });
            return res.json().catch(() => ({ ok: false, error: 'Unexpected response.' }));
        };

        // Loading state: disable every AI button, mark the clicked one busy.
        const allBtns = () => Array.from(aiCard.querySelectorAll('[data-ai]'));
        const setBusy = (btn, on) => {
            allBtns().forEach((b) => (b.disabled = on));
            if (btn) btn.classList.toggle('opacity-70', on);
        };

        const accountId = () => form.querySelector('input[name="instagram_account_id"]:checked')?.value || '';

        // Dismiss (×) buttons on the output panels.
        aiCard.querySelectorAll('[data-ai-dismiss]').forEach((b) => {
            b.addEventListener('click', () => document.getElementById(b.dataset.aiDismiss)?.classList.add('hidden'));
        });

        const runCaption = async (btn) => {
            setBusy(btn, true);
            try {
                const j = await post('/instagram/composer/ai/caption', {
                    notes: notes?.value || '',
                    media_type: typeInput?.value || 'image',
                });
                if (!j.ok) return toast(j.error || 'Could not generate a caption.', 'error');
                if (caption) { caption.value = j.text; syncCaption(); }
                toast('Caption generated.', 'success');
            } catch (_) { toast('Network error.', 'error'); }
            finally { setBusy(btn, false); }
        };

        const runRepurpose = async (btn) => {
            const text = caption?.value.trim() || '';
            if (!text) return toast('Write a caption first, then Repurpose rewrites it.', 'error');
            setBusy(btn, true);
            try {
                const j = await post('/instagram/composer/ai/repurpose', { caption: text });
                if (!j.ok) return toast(j.error || 'Could not repurpose.', 'error');
                if (caption) { caption.value = j.text; syncCaption(); }
                toast('Caption repurposed.', 'success');
            } catch (_) { toast('Network error.', 'error'); }
            finally { setBusy(btn, false); }
        };

        const runReview = async (btn) => {
            const text = caption?.value.trim() || '';
            if (!text) return toast('Write a caption first, then Review gives feedback.', 'error');
            setBusy(btn, true);
            try {
                const j = await post('/instagram/composer/ai/review', { caption: text });
                if (!j.ok) return toast(j.error || 'Could not review.', 'error');
                const panel = document.getElementById('cmp-ai-review');
                const body = document.getElementById('cmp-ai-review-body');
                if (body) body.textContent = j.text;
                panel?.classList.remove('hidden');
            } catch (_) { toast('Network error.', 'error'); }
            finally { setBusy(btn, false); }
        };

        const runBestTime = async (btn) => {
            setBusy(btn, true);
            try {
                const j = await post('/instagram/composer/ai/best-time', { instagram_account_id: accountId() });
                if (!j.ok) return toast(j.error || 'Could not compute best time.', 'error');
                const body = document.getElementById('cmp-ai-times-body');
                if (body) {
                    body.innerHTML = '';
                    (j.windows || []).forEach((w) => {
                        const row = document.createElement('div');
                        row.className = 'flex items-center gap-2.5';
                        row.innerHTML =
                            '<span class="mono text-[12px] font-semibold tabular text-ink-800 shrink-0"></span>' +
                            '<span class="text-[11.5px] text-ink-500"></span>';
                        row.children[0].textContent = w.time || '';
                        row.children[1].textContent = [w.label, w.why].filter(Boolean).join(' · ');
                        body.appendChild(row);
                    });
                }
                const note = document.getElementById('cmp-ai-times-note');
                if (note) note.textContent = j.note || '';
                document.getElementById('cmp-ai-times')?.classList.remove('hidden');
            } catch (_) { toast('Network error.', 'error'); }
            finally { setBusy(btn, false); }
        };

        const runImage = async (btn) => {
            const prompt = (notes?.value.trim() || caption?.value.trim() || '');
            if (!prompt) return toast('Describe the image in the box above (or the caption), then AI Image.', 'error');
            setBusy(btn, true);
            toast('Generating image… this can take ~15s.', 'info');
            try {
                const j = await post('/instagram/composer/ai/image', { prompt });
                if (!j.ok || !j.url) return toast(j.error || 'Could not generate an image.', 'error');
                const panel = document.getElementById('cmp-ai-image');
                const img = document.getElementById('cmp-ai-image-img');
                if (img) img.src = j.url;
                panel?.dataset && (panel.dataset.url = j.url);
                panel?.classList.remove('hidden');
                toast('Image generated.', 'success');
            } catch (_) { toast('Network error.', 'error'); }
            finally { setBusy(btn, false); }
        };

        // Attach the generated image as the post media.
        document.getElementById('cmp-ai-image-attach')?.addEventListener('click', () => {
            const url = document.getElementById('cmp-ai-image')?.dataset.url;
            if (!url) return;
            const hidden = document.getElementById('cmp-image-url');
            if (hidden) hidden.value = url;
            // A generated image is always a single photo post.
            setMode('image');
            // Clear any uploaded file so the server doesn't override image_url.
            if (fileInput) { fileInput.value = ''; }
            fileThumb?.classList.add('hidden');
            fileThumb?.classList.remove('flex');
            // Show it in the phone preview.
            if (pvImage) {
                pvImage.querySelector('video')?.remove();
                pvImage.style.backgroundImage = `url("${url}")`;
                pvImage.style.backgroundSize = 'cover';
                pvImage.style.backgroundPosition = 'center';
                pvPlaceholder()?.classList.add('hidden');
            }
            toast('Image attached to the post.', 'success');
        });

        const actions = { caption: runCaption, repurpose: runRepurpose, review: runReview, 'best-time': runBestTime, image: runImage };
        aiCard.querySelectorAll('[data-ai]').forEach((btn) => {
            btn.addEventListener('click', () => actions[btn.dataset.ai]?.(btn));
        });

        // Save caption — client-side keep so a good draft isn't lost on reload.
        document.getElementById('cmp-ai-save')?.addEventListener('click', () => {
            const text = caption?.value.trim() || '';
            if (!text) return toast('Nothing to save — write a caption first.', 'error');
            try { localStorage.setItem('instaflow.saved_caption', text); toast('Caption saved.', 'success'); }
            catch (_) { toast('Could not save.', 'error'); }
        });
    })();

    // ── Submit guards ──
    form.addEventListener('submit', (e) => {
        if (!form.querySelector('input[name="instagram_account_id"]:checked')) {
            e.preventDefault();
            window.toast?.('Pick an account to publish to.', 'error');
            return;
        }
        if (whenMode === 'schedule' && (!schedDate?.value || !schedTime?.value)) {
            e.preventDefault();
            window.toast?.('Pick a date & time to schedule, or choose Publish now.', 'error');
            (schedDate?.value ? schedTime : schedDate)?.focus();
        }
    });
}

/* runtime shim — matches vite.instagram.config.mjs wrapper */
(function(){var run=function(){try{init();}catch(e){console.error('[instagram] instagram-composer failed',e);}};if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);else run();})();
