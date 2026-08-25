(function () {
    function parseDigits(value) {
        var raw = String(value).trim();
        if (!raw) {
            return '';
        }

        raw = raw.replace(/^rp\s*/i, '').replace(/[\s\u00a0]/g, '');

        if (/^\d+[.,]\d{1,2}$/.test(raw)) {
            raw = raw.replace(/[.,]\d{1,2}$/, '');
        } else if (/,\d{1,2}$/.test(raw)) {
            raw = raw.replace(/,\d{1,2}$/, '');
        }

        var digits = raw.replace(/\D/g, '');
        if (!digits) {
            return '';
        }

        return digits.replace(/^0+(?=\d)/, '');
    }

    function formatRupiah(angka) {
        var numberString = parseDigits(angka);
        if (!numberString) {
            return '';
        }

        return 'Rp ' + numberString.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function bind(input) {
        if (input.dataset.rupiahBound === '1') {
            return;
        }
        input.dataset.rupiahBound = '1';

        if (input.value) {
            input.value = formatRupiah(input.value);
        }

        input.addEventListener('input', function () {
            var digitsBefore = parseDigits(input.value.slice(0, input.selectionStart || 0)).length;
            input.value = formatRupiah(input.value);
            var next = 0;
            var seen = 0;
            while (next < input.value.length && seen < digitsBefore) {
                if (/\d/.test(input.value.charAt(next))) {
                    seen += 1;
                }
                next += 1;
            }
            input.setSelectionRange(next, next);
        });

        input.addEventListener('blur', function () {
            if (input.value) {
                input.value = formatRupiah(input.value);
            }
        });
    }

    function bindAll(root) {
        (root || document).querySelectorAll('.js-rupiah, .js-rupiah-input').forEach(bind);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            bindAll(document);
        });
    } else {
        bindAll(document);
    }

    window.KeuanganRupiah = {
        format: formatRupiah,
        bindAll: bindAll
    };
})();
