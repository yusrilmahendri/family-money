<?php

return [
    'min_history_samples' => 3,
    'min_history_months' => 3,

    'unusual_expense' => [
        'warning_ratio' => 1.0,
        'critical_ratio' => 2.0,
        'min_amount' => 10_000,
        'limit' => 3,
    ],

    'category_spike' => [
        'warning_ratio' => 0.5,
        'critical_ratio' => 1.0,
        'min_amount' => 10_000,
    ],

    'income_drop' => [
        'warning_ratio' => 0.25,
        'critical_ratio' => 0.4,
    ],

    'negative_cash_flow' => [
        'critical_abs' => 1_000_000,
    ],

    'repeated_transaction' => [
        'amount_tolerance' => 0.02,
        'max_days_apart' => 3,
        'min_amount' => 10_000,
    ],

    'budget_overrun' => [
        'warning_ratio' => 0.0,
        'critical_ratio' => 0.2,
    ],

    'overdue' => [
        'critical_abs' => 2_000_000,
    ],

    'capital_prive' => [
        'warning_ratio' => 1.0,
        'critical_ratio' => 2.0,
        'min_amount' => 100_000,
    ],
];
