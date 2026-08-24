// Watermark (content protection) — the settings page's live-ish preview + inline
// save. Everything is server-rendered and the form posts on its own, so this
// only layers on: switching accounts loads that scope's saved config, the 3x3
// grid / sliders / inputs drive a preview chip, and Save goes over fetch with a
// toast. Nothing here is required for the page to work.

function init() {
    const root = document.getElementById('ig-wm-form');
    if (!root) return;

    const t = (s) => (window.t ? window.t(s) : s);
    const toast = (m, kind) => (window.toast ? window.toast(m, kind) : undefined);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // --- Parse the data island --------------------------------------------
    let data = { saveUrl: '/instagram/watermark', configs: {}, current: {} };
    try {
        const el = document.getElementById('ig-wm-data');
        if (el) data = { ...data, ...JSON.parse(el.textContent || '{}') };
    } catch (_) { /* keep defaults */ }

    const DEFAULTS = {
        type: 'image', image_url: null, text: '', text_color: '#ffffff',
        position: 'br', size: 22, opacity: 70, is_active: true,
    };

    // --- Element handles ---------------------------------------------------
    const scope = document.getElementById('ig-wm-scope');
    const typeRadios = Array.from(document.querySelectorAll('.ig-wm-type'));
    const panelImage = document.getElementById('ig-wm-panel-image');
    const panelText = document.getElementById('ig-wm-panel-text');
    const imgInput = document.getElementById('ig-wm-img-input');
    const imgThumb = document.getElementById('ig-wm-img-thumb');
    const textInput = document.getElementById('ig-wm-text');
    const colorInput = document.getElementById('ig-wm-color');
    const posInput = document.getElementById('ig-wm-position');
    const grid = document.getElementById('ig-wm-grid');
    const sizeInput = document.getElementById('ig-wm-size');
    const sizeVal = document.getElementById('ig-wm-size-val');
    const opacityInput = document.getElementById('ig-wm-opacity');
    const opacityVal = document.getElementById('ig-wm-opacity-val');
    const activeInput = document.getElementById('ig-wm-active');
    const preview = document.getElementById('ig-wm-preview');
    const overlay = document.getElementById('ig-wm-overlay');
    const saveBtn = document.getElementById('ig-wm-save');

    // A locally-chosen image (object URL) overrides the saved one in the preview.
    let localImageUrl = null;

    // --- Type toggle -------------------------------------------------------
    const currentType = () => (typeRadios.find((r) => r.checked)?.value) || 'image';

    const paintTypeButtons = () => {
        const type = currentType();
        document.querySelectorAll('.ig-wm-type-btn').forEach((btn) => {
            const on = btn.dataset.type === type;
            btn.style.background = on ? 'linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888)' : 'transparent';
            btn.style.color = on ? '#fff' : '';
            btn.style.fontWeight = on ? '600' : '400';
        });
        if (panelImage) panelImage.classList.toggle('hidden', type !== 'image');
        if (panelText) panelText.classList.toggle('hidden', type !== 'text');
    };

    typeRadios.forEach((r) => r.addEventListener('change', () => { paintTypeButtons(); renderPreview(); }));

    // --- Position grid -----------------------------------------------------
    const paintGrid = () => {
        const pos = posInput?.value || 'br';
        grid?.querySelectorAll('.ig-wm-cell').forEach((cell) => {
            const on = cell.dataset.pos === pos;
            cell.style.background = on ? 'rgba(193,53,132,.12)' : '#faf9f7';
            cell.style.borderColor = on ? '#c13584' : '';
            const dot = cell.firstElementChild;
            if (dot) dot.style.background = on ? '#c13584' : '#cfc9c0';
        });
    };
    grid?.querySelectorAll('.ig-wm-cell').forEach((cell) => {
        cell.addEventListener('click', () => {
            if (posInput) posInput.value = cell.dataset.pos;
            paintGrid();
            renderPreview();
        });
    });

    // --- Sliders -----------------------------------------------------------
    sizeInput?.addEventListener('input', () => {
        if (sizeVal) sizeVal.textContent = sizeInput.value + '%';
        renderPreview();
    });
    opacityInput?.addEventListener('input', () => {
        if (opacityVal) opacityVal.textContent = opacityInput.value + '%';
        renderPreview();
    });

    // --- Text / colour -----------------------------------------------------
    textInput?.addEventListener('input', renderPreview);
    colorInput?.addEventListener('input', renderPreview);

    // --- Image pick --------------------------------------------------------
    imgInput?.addEventListener('change', () => {
        const f = imgInput.files && imgInput.files[0];
        if (!f) return;
        if (localImageUrl) URL.revokeObjectURL(localImageUrl);
        localImageUrl = URL.createObjectURL(f);
        if (imgThumb) imgThumb.style.backgroundImage = `url('${localImageUrl}')`;
        renderPreview();
    });

    // --- Preview render ----------------------------------------------------
    function renderPreview() {
        if (!preview || !overlay) return;
        const pw = preview.clientWidth || 1;
        const ph = preview.clientHeight || 1;
        const size = Math.max(5, Math.min(90, parseInt(sizeInput?.value || '22', 10)));
        const opacity = Math.max(5, Math.min(100, parseInt(opacityInput?.value || '70', 10)));
        const pos = posInput?.value || 'br';
        const type = currentType();

        overlay.style.opacity = String(opacity / 100);
        const wpx = pw * (size / 100);

        if (type === 'text') {
            overlay.style.backgroundImage = '';
            overlay.style.height = 'auto';
            overlay.textContent = (textInput?.value || t('@yourbrand')).slice(0, 60);
            overlay.style.color = colorInput?.value || '#ffffff';
            // Font size so the label roughly spans `size%` of the preview width.
            const len = Math.max(3, (overlay.textContent || '').length);
            overlay.style.width = 'auto';
            overlay.style.fontSize = Math.max(9, (wpx / len) * 1.5) + 'px';
        } else {
            overlay.textContent = '';
            overlay.style.width = wpx + 'px';
            overlay.style.height = wpx + 'px';
            const url = localImageUrl || data?.current?.image_url || null;
            const scoped = currentScopeConfig();
            const src = localImageUrl || (scoped && scoped.image_url) || url;
            if (src) {
                overlay.style.backgroundImage = `url('${src}')`;
                overlay.style.backgroundSize = 'contain';
                overlay.style.backgroundPosition = 'center';
                overlay.style.backgroundRepeat = 'no-repeat';
            } else {
                // No image yet — show a dashed placeholder box.
                overlay.style.backgroundImage = '';
                overlay.style.background = 'rgba(255,255,255,.35)';
                overlay.style.border = '1px dashed rgba(255,255,255,.8)';
            }
        }

        // Anchor after layout so we can measure the rendered overlay.
        requestAnimationFrame(() => {
            const ow = overlay.offsetWidth;
            const oh = overlay.offsetHeight;
            const mx = pw * 0.04;
            const my = ph * 0.04;
            const col = pos.charAt(1);
            const row = pos.charAt(0);
            let left = col === 'l' ? mx : col === 'r' ? (pw - ow - mx) : (pw - ow) / 2;
            let top = row === 't' ? my : row === 'b' ? (ph - oh - my) : (ph - oh) / 2;
            overlay.style.left = Math.max(0, left) + 'px';
            overlay.style.top = Math.max(0, top) + 'px';
        });
    }

    // --- Config loading on scope change -----------------------------------
    const currentScopeConfig = () => (data.configs && data.configs[scope?.value]) || null;

    function loadConfig(cfg) {
        const c = { ...DEFAULTS, ...(cfg || {}) };
        // Type
        typeRadios.forEach((r) => { r.checked = r.value === c.type; });
        // Text + colour
        if (textInput) textInput.value = c.text || '';
        if (colorInput) colorInput.value = c.text_color || '#ffffff';
        // Position
        if (posInput) posInput.value = c.position || 'br';
        // Sliders
        if (sizeInput) sizeInput.value = c.size;
        if (sizeVal) sizeVal.textContent = c.size + '%';
        if (opacityInput) opacityInput.value = c.opacity;
        if (opacityVal) opacityVal.textContent = c.opacity + '%';
        // Active
        if (activeInput) activeInput.checked = !!c.is_active;
        // Image thumb (saved image; a fresh local pick wins until reload)
        if (localImageUrl) { URL.revokeObjectURL(localImageUrl); localImageUrl = null; }
        if (imgInput) imgInput.value = '';
        if (imgThumb) imgThumb.style.backgroundImage = c.image_url ? `url('${c.image_url}')` : '';

        paintTypeButtons();
        paintGrid();
        renderPreview();
    }

    scope?.addEventListener('change', () => loadConfig(currentScopeConfig()));

    // --- Save over fetch ---------------------------------------------------
    root.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (saveBtn) saveBtn.disabled = true;
        try {
            const res = await fetch(data.saveUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: new FormData(root),
            });
            const json = await res.json().catch(() => ({}));
            if (res.ok && json.ok) {
                toast(json.message || t('Watermark saved.'));
                // Reload so the saved-watermarks list + configs reflect the change.
                setTimeout(() => window.location.reload(), 500);
            } else {
                toast(json.message || t('Could not save the watermark.'), 'error');
                if (saveBtn) saveBtn.disabled = false;
            }
        } catch (_) {
            // Network/JS failure — fall back to a real submit.
            root.submit();
        }
    });

    // --- First paint -------------------------------------------------------
    paintTypeButtons();
    paintGrid();
    loadConfig(currentScopeConfig() || data.current);
    // Re-anchor on resize (the preview is fluid-width).
    window.addEventListener('resize', () => renderPreview());
}

/* runtime shim — matches vite.instagram.config.mjs wrapper */
(function(){var run=function(){try{init();}catch(e){console.error('[instagram] instagram-watermark failed',e);}};if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);else run();})();
