// IgDesk calendar — click a day cell (or its + button) to slide in the
// right-side scheduler drawer, pre-filled with that date. "Schedule" submits
// the date+time; "Publish now" clears the date so composerPublish posts
// immediately. Page key: instagram-calendar (registered in app.js).
export default function init() {
    const overlay = document.getElementById('cal-overlay');
    const drawer = document.getElementById('cal-drawer');
    const form = document.getElementById('cal-form');
    if (!overlay || !drawer || !form) return;

    const dateLabel = document.getElementById('cal-date-label');
    const sendDate = document.getElementById('cal-send-date');
    const sendTime = document.getElementById('cal-send-time');
    const mediaType = document.getElementById('cal-media-type');
    const fileSingle = document.getElementById('cal-file');
    const fileMulti = document.getElementById('cal-files');
    const fileName = document.getElementById('cal-file-name');
    const filesName = document.getElementById('cal-files-name');

    // ── Open / close ──
    const open = (dayKey, label) => {
        if (dayKey && sendDate) sendDate.value = dayKey;
        if (label && dateLabel) dateLabel.textContent = label;
        overlay.classList.remove('hidden');
        // next frame → fade/slide in
        requestAnimationFrame(() => {
            overlay.classList.remove('opacity-0');
            drawer.classList.remove('translate-x-full');
        });
        drawer.setAttribute('aria-hidden', 'false');
    };
    const close = () => {
        overlay.classList.add('opacity-0');
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
        setTimeout(() => overlay.classList.add('hidden'), 200);
    };

    // Day-cell + "add" button clicks open the drawer for that date.
    document.querySelectorAll('[data-cal-day]').forEach((cell) => {
        cell.addEventListener('click', (e) => {
            // Ignore clicks on an existing post chip (those are just previews).
            if (e.target.closest('.post-chip')) return;
            open(cell.dataset.calDay, cell.dataset.calLabel);
        });
    });

    document.getElementById('cal-close')?.addEventListener('click', close);
    overlay.addEventListener('click', close);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !overlay.classList.contains('hidden')) close();
    });

    // ── Media-type tabs (Post / Reel / Story / Carousel) ──
    const tabs = Array.from(document.querySelectorAll('[data-cal-type]'));
    const setType = (type) => {
        if (mediaType) mediaType.value = type;
        tabs.forEach((t) => {
            const on = t.dataset.calType === type;
            t.classList.toggle('ig-grad-soft', on);
            t.classList.toggle('text-white', on);
            t.classList.toggle('text-ink-600', !on);
        });
        // Carousel needs 2–10 files → swap the single dropzone for the multi one,
        // and clear the other input so a stale file doesn't break validation.
        const carousel = type === 'carousel';
        document.querySelector('[data-cal-media="single"]')?.classList.toggle('hidden', carousel);
        document.querySelector('[data-cal-media="multi"]')?.classList.toggle('hidden', !carousel);
        if (carousel && fileSingle) { fileSingle.value = ''; if (fileName) fileName.textContent = 'Upload image or video'; }
        if (!carousel && fileMulti) { fileMulti.value = ''; if (filesName) filesName.textContent = 'Upload 2–10 images/videos'; }
    };
    tabs.forEach((t) => t.addEventListener('click', () => setType(t.dataset.calType)));

    // ── File name feedback ──
    fileSingle?.addEventListener('change', () => {
        if (fileName) fileName.textContent = fileSingle.files?.[0]?.name || 'Upload image or video';
    });
    fileMulti?.addEventListener('change', () => {
        const n = fileMulti.files?.length || 0;
        if (filesName) filesName.textContent = n ? `${n} file${n > 1 ? 's' : ''} selected` : 'Upload 2–10 images/videos';
    });

    // ── Guard: an account + media are required for either action ──
    const hasMedia = () => {
        const carousel = mediaType?.value === 'carousel';
        return carousel ? (fileMulti?.files?.length || 0) >= 2 : (fileSingle?.files?.length || 0) >= 1;
    };
    const validate = () => {
        if (!form.querySelector('input[name="instagram_account_id"]:checked')) {
            window.toast?.('Pick an account to post to.', 'error');
            return false;
        }
        if (!hasMedia()) {
            const carousel = mediaType?.value === 'carousel';
            window.toast?.(carousel ? 'Add at least 2 media files for a carousel.' : 'Upload an image or video first.', 'error');
            return false;
        }
        return true;
    };

    // ── Actions ──
    // "Schedule" → keep send_date/send_time (composerPublish schedules it).
    document.getElementById('cal-schedule')?.addEventListener('click', () => {
        if (!validate()) return;
        if (!sendDate?.value) { window.toast?.('Pick a date on the calendar first.', 'error'); return; }
        form.submit();
    });
    // "Publish now" → clear the date so composerPublish publishes immediately.
    document.getElementById('cal-post-now')?.addEventListener('click', () => {
        if (!validate()) return;
        if (sendDate) sendDate.value = '';
        if (sendTime) sendTime.value = '';
        form.submit();
    });

    // ── Manage drawer: click an EXISTING post chip → send now / reschedule / delete ──
    const cm = document.getElementById('cal-manage');
    const cmOverlay = document.getElementById('cm-overlay');
    if (cm && cmOverlay) {
        const base = cm.dataset.schedBase || '';
        const set = (id, txt) => { const el = document.getElementById(id); if (el) el.textContent = txt; };
        const cmDate = document.getElementById('cm-date');
        const cmTime = document.getElementById('cm-time');
        const fUpdate = document.getElementById('cm-form-update');
        const fPublish = document.getElementById('cm-form-publish');
        const fDelete = document.getElementById('cm-form-delete');

        const openCm = (d) => {
            const id = d.postId;
            set('cm-title', d.postCaption || 'Scheduled post');
            set('cm-caption', d.postCaption || '—');
            set('cm-type', d.postType || '—');
            set('cm-when', d.postWhenLabel || '—');
            const thumb = document.getElementById('cm-thumb');
            if (thumb) {
                if (d.postImg) { thumb.style.backgroundImage = `url("${d.postImg}")`; thumb.classList.remove('ig-grad-soft'); }
                else { thumb.style.backgroundImage = ''; thumb.classList.add('ig-grad-soft'); }
            }
            // "YYYY-MM-DDTHH:mm" → date + time inputs.
            const when = d.postWhen || '';
            if (cmDate) cmDate.value = when.slice(0, 10);
            if (cmTime) cmTime.value = when.slice(11, 16);
            // Point the hidden forms at this post id.
            if (fUpdate) fUpdate.action = `${base}/${id}`;
            if (fPublish) fPublish.action = `${base}/${id}/publish`;
            if (fDelete) fDelete.action = `${base}/${id}`;
            // Only a pending post can be rescheduled / published / deleted.
            const pending = (d.postStatus || 'pending') === 'pending';
            document.getElementById('cm-reschedule')?.classList.toggle('hidden', !pending);
            document.getElementById('cm-footer')?.classList.toggle('hidden', !pending);
            document.getElementById('cm-note')?.classList.toggle('hidden', pending);

            cmOverlay.classList.remove('hidden');
            requestAnimationFrame(() => { cmOverlay.classList.remove('opacity-0'); cm.classList.remove('translate-x-full'); });
            cm.setAttribute('aria-hidden', 'false');
        };
        const closeCm = () => {
            cmOverlay.classList.add('opacity-0');
            cm.classList.add('translate-x-full');
            cm.setAttribute('aria-hidden', 'true');
            setTimeout(() => cmOverlay.classList.add('hidden'), 200);
        };

        // Chip click (any view) → open manage; stopPropagation so the day-cell
        // scheduler drawer doesn't also fire.
        document.querySelectorAll('[data-post-id]').forEach((el) => {
            el.addEventListener('click', (e) => { e.stopPropagation(); openCm(el.dataset); });
        });
        document.getElementById('cm-close')?.addEventListener('click', closeCm);
        cmOverlay.addEventListener('click', closeCm);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !cmOverlay.classList.contains('hidden')) closeCm();
        });

        // Reschedule → combine date+time into schedule_at, submit.
        document.getElementById('cm-save')?.addEventListener('click', () => {
            if (!cmDate?.value || !cmTime?.value) { window.toast?.('Pick a date and time.', 'error'); return; }
            const sa = document.getElementById('cm-schedule-at');
            if (sa) sa.value = `${cmDate.value}T${cmTime.value}`;
            fUpdate?.submit();
        });
        document.getElementById('cm-send-now')?.addEventListener('click', () => {
            if (confirm('Publish this post to Instagram right now?')) fPublish?.submit();
        });
        document.getElementById('cm-delete')?.addEventListener('click', () => {
            if (confirm('Delete this scheduled post?')) fDelete?.submit();
        });
    }
}
