// Instaflow Meta Ads — create/edit form. Page key: instagram-ads-create.
// Progressive enhancement only: the form fully submits without any of this.
//   • live image preview
//   • ad-type radio toggles the ig_direct / link only sections + card highlight
//   • "Write with AI" fills the copy fields from the ai-generate endpoint
function init() {
    imagePreview();
    adTypeToggle();
    aiGenerate();
}

function imagePreview() {
    const input = document.querySelector('[data-image-input]');
    const box = document.querySelector('[data-image-preview]');
    if (!input || !box) return;
    input.addEventListener('change', () => {
        const f = input.files && input.files[0];
        if (!f) return;
        const url = URL.createObjectURL(f);
        box.style.backgroundImage = `url('${url}')`;
        box.innerHTML = '';
    });
}

function adTypeToggle() {
    const radios = document.querySelectorAll('[data-adtype-radio]');
    if (!radios.length) return;

    const apply = () => {
        const active = document.querySelector('[data-adtype-radio]:checked');
        const val = active ? active.value : 'ig_direct';
        document.querySelectorAll('[data-when-adtype]').forEach((el) => {
            el.style.display = el.getAttribute('data-when-adtype') === val ? '' : 'none';
        });
        document.querySelectorAll('[data-adtype-card]').forEach((card) => {
            const on = card.getAttribute('data-adtype-card') === val;
            card.style.borderColor = on ? 'var(--ig-pink, #C13584)' : '';
            card.style.background = on ? 'rgba(193,53,132,0.04)' : '';
        });
    };
    radios.forEach((r) => r.addEventListener('change', apply));
    apply();
}

function aiGenerate() {
    const btn = document.querySelector('[data-ai-generate]');
    if (!btn) return;
    const status = document.querySelector('[data-ai-status]');
    const setStatus = (t) => { if (status) status.textContent = t; };
    const val = (k) => (document.querySelector(`[data-ai-input="${k}"]`) || {}).value || '';
    const setField = (k, v) => {
        if (!v) return;
        const el = document.querySelector(`[data-ai-field="${k}"]`);
        if (el && !el.value) el.value = v;
        else if (el) el.value = v;
    };

    btn.addEventListener('click', async () => {
        const biz = val('business_name').trim();
        if (!biz) { setStatus('Enter a business name first.'); return; }
        const token = document.querySelector('meta[name="csrf-token"]');
        setStatus('Generating…');
        btn.disabled = true;
        try {
            const r = await fetch(btn.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token ? token.content : '',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    provider: 'openai',
                    model: 'gpt-4o-mini',
                    business_name: biz,
                    product: val('product'),
                    audience: val('audience'),
                    dm_message: 1,
                }),
            });
            const data = await r.json();
            if (!data.ok) { setStatus(data.message || 'Could not generate copy.'); return; }
            const ad = data.ad || {};
            setField('campaign_name', ad.campaign_name);
            setField('headline', ad.headline);
            setField('body', ad.body);
            setField('dm_welcome', ad.dm_welcome);
            const interests = document.querySelector('textarea[name="interests"]');
            if (interests && !interests.value && ad.interests) interests.value = ad.interests;
            setStatus('Copy filled in — review before publishing.');
        } catch (e) {
            setStatus('Could not reach the AI service.');
        } finally {
            btn.disabled = false;
        }
    });
}

(function(){var run=function(){try{init();}catch(e){console.error("[instagram] instagram-ads-create failed",e);}};if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",run);else run();})();
