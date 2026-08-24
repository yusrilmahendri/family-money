<script>
(function () {
    function formatRupiah(angka) {
        var numberString = String(angka).replace(/[^0-9]/g, '');
        if (!numberString) return '';
        var sisa = numberString.length % 3;
        var rupiah = numberString.substr(0, sisa);
        var ribuan = numberString.substr(sisa).match(/\d{3}/g);
        if (ribuan) {
            rupiah += (sisa ? '.' : '') + ribuan.join('.');
        }
        return 'Rp ' + rupiah;
    }

    document.querySelectorAll('.js-rupiah').forEach(function (input) {
        if (input.value) {
            input.value = formatRupiah(input.value);
        }
        input.addEventListener('input', function () {
            input.value = formatRupiah(input.value);
        });
    });
})();
</script>
