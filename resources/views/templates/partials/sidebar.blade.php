
<div id="sidebar-collapse" class="col-sm-3 col-lg-2 sidebar">
	<div class="profile-sidebar">
		<!-- <div class="profile-userpic">
			<img src="{{ asset('../images/default.jpg') }}" class="img-responsive" alt="#">
		</div> -->
		<div class="profile-usertitle">
			<div class="profile-usertitle-name" style="
                margin-top:10px;
                font-size: 22px;
                font-weight: 800;
                color: #333;
                text-shadow: 0 0 3px rgba(0,0,0,0.15);
                font-family: 'Montserrat', sans-serif;
            ">
                Welcome to our finances
            </div>
		</div>
		<div class="clear"></div>
	</div>

	{{-- Context switcher: ganti aplikasi (PRIBADI / USAHA_KEBUN) --}}
	@php($__ctx = \App\Support\FinanceContext::current())
	<div style="padding: 10px 14px;">
		<label style="font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: .5px;">
			<i class="fa fa-th-large"></i> Aplikasi Aktif
		</label>
		<form action="{{ route('apps.select') }}" method="POST" id="ctxSwitchForm">
			@csrf
			<input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
			<select name="context" class="form-control input-sm" id="ctxSwitchSelect"
				onchange="document.getElementById('ctxSwitchForm').submit()"
				style="border-radius: 8px; font-weight: 600;">
				@foreach(\App\Support\FinanceContext::all() as $val => $label)
					<option value="{{ $val }}" @selected($__ctx === $val)>{{ $label }}</option>
				@endforeach
			</select>
		</form>
		<a href="{{ route('apps.index') }}" style="display:inline-block; margin-top:6px; font-size:11px; color:#30a5ff;">
			<i class="fa fa-exchange"></i> Portal aplikasi
		</a>
	</div>

	<div class="divider"></div>
	<ul class="nav menu">

		{{-- ===== Menu NETRAL (semua konteks) ===== --}}
		<li class="{{ request()->is('/') || request()->is('dashboard') ? 'active' : '' }}">
			<a href="{{ url('dashboard') }}">
				<em class="fa fa-dashboard">&nbsp;</em>
				Dashboard</a>
		</li>

		<li class="{{ request()->is('insight*') ? 'active' : '' }}">
			<a href="{{ route('insight.index') }}">
				<em class="fa fa-lightbulb-o">&nbsp;</em>
				Insight AI</a>
		</li>

		<li class="{{ request()->is('saldos*') ? 'active' : '' }}">
			<a href="{{ url('saldos') }}">
				<em class="fa fa-money">&nbsp;</em>
				Saldo</a>
		</li>

		@if($__ctx === \App\Support\FinanceContext::PRIBADI)
			{{-- ===== Menu KHUSUS PRIBADI ===== --}}
			<li class="nav-divider-label" style="padding:6px 14px; font-size:10px; font-weight:700; color:#aaa; text-transform:uppercase; letter-spacing:.5px;">Pribadi</li>

			<li class="{{ request()->is('transactions*') ? 'active' : '' }}">
				<a href="{{ url('transactions') }}">
					<em class="fa fa-shopping-cart">&nbsp;</em>
					Transaksi Pribadi</a>
			</li>

			<li class="{{ request()->is('debts*') ? 'active' : '' }}">
				<a href="{{ route('debts.index') }}">
					<em class="fa fa-credit-card">&nbsp;</em>
					Utang &amp; cicilan</a>
			</li>

			<li class="{{ request()->is('savings-goals*') ? 'active' : '' }}">
				<a href="{{ route('savings-goals.index') }}">
					<em class="fa fa-bullseye">&nbsp;</em>
					Goals tabungan</a>
			</li>

			<li class="{{ request()->is('financial-planner*') ? 'active' : '' }}">
				<a href="{{ route('financial-planner.index') }}">
					<em class="fa fa-line-chart">&nbsp;</em>
					Financial planner</a>
			</li>

			<li class="{{ request()->is('recurring-transactions*') ? 'active' : '' }}">
				<a href="{{ route('recurring-transactions.index') }}">
					<em class="fa fa-refresh">&nbsp;</em>
					Transaksi Berulang</a>
			</li>

		@elseif($__ctx === \App\Support\FinanceContext::USAHA_KEBUN)
			{{-- ===== Menu KHUSUS USAHA KEBUN ===== --}}
			<li class="nav-divider-label" style="padding:6px 14px; font-size:10px; font-weight:700; color:#aaa; text-transform:uppercase; letter-spacing:.5px;">Usaha Kebun</li>

			<li class="{{ request()->is('incomes*') ? 'active' : '' }}">
				<a href="{{ route('incomes.index') }}">
					<em class="fa fa-arrow-up">&nbsp;</em>
					Pemasukan Usaha</a>
			</li>

			<li class="{{ request()->is('operational-expenses*') ? 'active' : '' }}">
				<a href="{{ route('operational.index') }}">
					<em class="fa fa-money">&nbsp;</em>
					Biaya Operasional</a>
			</li>

			<li class="{{ request()->is('laba-rugi*') ? 'active' : '' }}">
				<a href="{{ route('profit-loss.index') }}">
					<em class="fa fa-bar-chart">&nbsp;</em>
					Laba / Rugi</a>
			</li>

			<li class="{{ request()->is('budgets*') ? 'active' : '' }}">
				<a href="{{ route('budgets.index') }}">
					<em class="fa fa-sliders">&nbsp;</em>
					Anggaran</a>
			</li>

			<li class="{{ request()->is('categories*') ? 'active' : '' }}">
				<a href="{{ url('categories') }}">
					<em class="fa fa-tags">&nbsp;</em>
					Jenis Usaha</a>
			</li>

			<li class="{{ request()->is('recurring-transactions*') ? 'active' : '' }}">
				<a href="{{ route('recurring-transactions.index') }}">
					<em class="fa fa-refresh">&nbsp;</em>
					Transaksi Berulang</a>
			</li>
		@endif

	</ul>

	
</div>