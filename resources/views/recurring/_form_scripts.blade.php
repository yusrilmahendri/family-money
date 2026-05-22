<script>
(function() {
    var freq = document.getElementById('frequency');
    var dowGroup = document.getElementById('day-of-week-group');
    var domGroup = document.getElementById('day-of-month-group');
    var amountEl = document.getElementById('amount');

    function toggleFreqFields() {
        var v = freq.value;
        dowGroup.style.display = v === 'weekly' ? 'block' : 'none';
        domGroup.style.display = (v === 'monthly' || v === 'yearly') ? 'block' : 'none';
    }

    freq.addEventListener('change', toggleFreqFields);
    toggleFreqFields();

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
})();
</script>
