<?php

namespace App\Enums;

enum ExternalFinancialRecordType: string
{
    case TRANSACTION = 'transaction';
    case RECEIVABLE = 'receivable';
    case RECEIVABLE_PAYMENT = 'receivable_payment';
}
