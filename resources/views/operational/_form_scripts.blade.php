<script>
(function() {
    var amountEl = document.getElementById('amount');
    if (amountEl) {
        amountEl.addEventListener('keyup', function() {
            var angka = this.value.replace(/[^,\d]/g, '').toString();
            var split = angka.split(',');
            var sisa = split[0].length % 3;
            var rupiah = split[0].substr(0, sisa);
            var ribuan = split[0].substr(sisa).match(/\d{3}/gi);
            if (ribuan) {
                var separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }
            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            this.value = rupiah ? 'Rp ' + rupiah : '';
        });
    }

    var sel = document.getElementById('budget_id');
    var info = document.getElementById('budget-info');
    if (!sel || !info) return;

    function loadInfo() {
        var id = sel.value;
        if (!id) { info.style.display = 'none'; return; }
        fetch('/api/v1/operational-expenses/budget-info/' + id)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                document.getElementById('bi-amount').textContent = d.amount_formatted;
                document.getElementById('bi-terpakai').textContent = d.terpakai_formatted;
                document.getElementById('bi-sisa').textContent = d.sisa_formatted;
                info.style.display = 'block';
            })
            .catch(function () { info.style.display = 'none'; });
    }

    sel.addEventListener('change', loadInfo);
    if (sel.value) { loadInfo(); }
})();
</script>
