
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

	<div class="divider"></div>
	<ul class="nav menu">

		<li class="{{ request()->is('/') || request()->is('dashboard') ? 'active' : '' }}">
			<a href="{{ url('dashboard') }}">
				<em class="fa fa-dashboard">&nbsp;</em>
				Dashboard</a>
		</li>

		<li class="{{ request()->is('financial-planner*') ? 'active' : '' }}">
			<a href="{{ route('financial-planner.index') }}">
				<em class="fa fa-line-chart">&nbsp;</em>
				Financial planner</a>
		</li>

		<li class="{{ request()->is('budgets*') ? 'active' : '' }}">
			<a href="{{ route('budgets.index') }}">
				<em class="fa fa-sliders">&nbsp;</em>
				Anggaran</a>
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

		<li class="{{ request()->is('saldos*') ? 'active' : '' }}">
			<a href="{{ url('saldos') }}">
				<em class="fa fa-money">&nbsp;</em>
				Saldo</a>
		</li>


        <li class="{{ request()->is('transactions*') ? 'active' : '' }}">
            <a href="{{ url('transactions') }}">
                <em class="fa fa-shopping-cart">&nbsp;</em>
                Transactions</a>
        </li>
	</ul>

	
</div>