<?php

namespace App\Enums;

enum PlantationOperatingBudgetStatus: string
{
    case DRAFT = 'DRAFT';
    case ACTIVE = 'ACTIVE';
    case SYNC_ERROR = 'SYNC_ERROR';
}
