@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\FinanceAccount> $accounts */
    $accounts = $accounts ?? collect();
    $selected = old('finance_account_id', $selectedAccountId ?? $entity->defaultAccount()?->id);
    $accountLabel = $accountLabel ?? 'Kas / Rekening';
@endphp
<div class="form-group">
    <label for="finance_account_id">{{ $accountLabel }}</label>
    <select id="finance_account_id" name="finance_account_id" class="form-control" required>
        @forelse($accounts as $account)
            <option value="{{ $account->id }}" @selected((string) $selected === (string) $account->id)>
                {{ $account->name }}@if($account->is_default) (default)@endif
            </option>
        @empty
            <option value="">Belum ada kas / rekening aktif</option>
        @endforelse
    </select>
</div>
