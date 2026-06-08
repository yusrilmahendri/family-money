<?php

use App\Support\FinanceContext;

/*
|--------------------------------------------------------------------------
| Feature Map per Konteks Keuangan
|--------------------------------------------------------------------------
|
| Memetakan fitur (menu sidebar & widget dashboard) untuk tiap konteks
| "aplikasi": PRIBADI dan USAHA_KEBUN. Saldo TETAP global/shared dan tidak
| pernah dipisah per konteks.
|
| `menu`              : key menu yang boleh tampil pada konteks tsb.
| `dashboard_widgets` : widget yang ditampilkan di dashboard konteks tsb.
| `route_prefix`      : prefix opsional (saat ini tidak mengubah URL).
|
*/

return [

    'contexts' => [

        FinanceContext::PRIBADI => [
            'label' => 'Keuangan Pribadi',
            'route_prefix' => null,
            'menu' => [
                // neutral (selalu tampil)
                'dashboard', 'insight', 'saldos',
                // khusus pribadi
                'transactions', 'debts', 'savings_goals', 'financial_planner', 'recurring',
            ],
            'dashboard_widgets' => [
                'pengeluaran_bulan_ini',
                'total_cicilan',
                'goals',
            ],
        ],

        FinanceContext::USAHA_KEBUN => [
            'label' => 'Keuangan Usaha Kebun',
            'route_prefix' => 'farm',
            'menu' => [
                // neutral (selalu tampil)
                'dashboard', 'insight', 'saldos',
                // khusus usaha
                'incomes', 'operational_costs', 'profit_loss', 'budgets', 'categories', 'recurring',
            ],
            'dashboard_widgets' => [
                'pemasukan_bulan_ini',
                'biaya_operasional_bulan_ini',
                'laba_rugi_bulan_ini',
                'top_biaya',
            ],
        ],

    ],

    /*
    | Menu netral yang selalu tampil pada konteks apa pun.
    */
    'neutral_menu' => ['dashboard', 'insight', 'saldos'],

];
