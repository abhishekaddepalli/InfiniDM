/**
 * Admin · Manual invoice modal.
 *
 * Opens the "New manual invoice" dialog, manages the line-item repeater
 * (add / remove rows, re-index the name= keys so PHP receives a clean array),
 * and live-computes the running total. No framework — plain DOM.
 */
(function () {
    'use strict';

    var modal = document.getElementById('ig-invoice-modal');
    if (!modal) return;

    var linesWrap = document.getElementById('ig-invoice-lines');
    var totalEl = document.getElementById('ig-invoice-total');

    function open() { modal.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
    function close() { modal.classList.add('hidden'); document.body.style.overflow = ''; }

    // Re-number items[N][...] so removing a middle row never leaves a gap.
    function reindex() {
        var rows = linesWrap.querySelectorAll('.ig-invoice-line');
        rows.forEach(function (row, i) {
            row.querySelectorAll('input').forEach(function (inp) {
                var name = inp.getAttribute('name');
                if (name) inp.setAttribute('name', name.replace(/items\[\d+\]/, 'items[' + i + ']'));
            });
        });
    }

    function recomputeTotal() {
        var total = 0;
        linesWrap.querySelectorAll('.ig-invoice-line').forEach(function (row) {
            var qty = parseFloat(row.querySelector('[data-line-qty]').value) || 0;
            var price = parseFloat(row.querySelector('[data-line-price]').value) || 0;
            total += qty * price;
        });
        if (totalEl) totalEl.textContent = total.toFixed(2);
    }

    function addLine() {
        var rows = linesWrap.querySelectorAll('.ig-invoice-line');
        var clone = rows[0].cloneNode(true);
        clone.querySelectorAll('input').forEach(function (inp) {
            if (inp.hasAttribute('data-line-qty')) inp.value = '1';
            else inp.value = '';
        });
        linesWrap.appendChild(clone);
        reindex();
    }

    function removeLine(row) {
        var rows = linesWrap.querySelectorAll('.ig-invoice-line');
        if (rows.length <= 1) {
            // Never remove the last row — just clear it.
            row.querySelectorAll('input').forEach(function (inp) {
                inp.value = inp.hasAttribute('data-line-qty') ? '1' : '';
            });
        } else {
            row.remove();
        }
        reindex();
        recomputeTotal();
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-open-invoice-modal]')) { open(); return; }
        if (e.target.closest('[data-close-invoice-modal]')) { close(); return; }
        if (e.target.closest('[data-add-invoice-line]')) { addLine(); return; }
        var rm = e.target.closest('[data-remove-invoice-line]');
        if (rm) { removeLine(rm.closest('.ig-invoice-line')); return; }
    });

    linesWrap.addEventListener('input', function (e) {
        if (e.target.matches('[data-line-qty], [data-line-price]')) recomputeTotal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });

    // Reopen automatically if the server bounced back with validation errors.
    if (document.querySelector('#ig-invoice-modal [data-server-open]')) open();
})();
