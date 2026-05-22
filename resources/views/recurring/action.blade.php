<form action="{{ route('recurring.post', $model) }}" method="POST" style="display:inline;">
    @csrf
    <button type="submit" class="btn btn-success btn-xs" title="Posting sekarang">
        <i class="fa fa-play"></i>
    </button>
</form>

<a href="{{ route('recurring-transactions.edit', $model) }}" class="btn btn-warning btn-xs">
    <i class="fa fa-edit"></i>
</a>

<a href="#" class="btn btn-danger btn-xs btn-delete-recurring"
   data-form="delete-recurring-{{ $model->id }}">
    <i class="fa fa-trash"></i>
</a>
<form id="delete-recurring-{{ $model->id }}" action="{{ route('recurring-transactions.destroy', $model) }}" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
</form>

<script>
(function() {
    document.querySelectorAll('.btn-delete-recurring').forEach(function(btn) {
        if (btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var formId = this.getAttribute('data-form');
            swal({
                title: 'Hapus aturan berulang ini?',
                text: 'Transaksi yang sudah diposting tidak akan dihapus.',
                icon: 'warning',
                buttons: {
                    cancel: { text: 'Batal', value: null, visible: true },
                    confirm: { text: 'Ya, Hapus', value: true, className: 'btn-danger' }
                },
                dangerMode: true,
            }).then(function(ok) {
                if (ok) document.getElementById(formId).submit();
            });
        });
    });
})();
</script>
