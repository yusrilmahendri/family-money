<?php

namespace App\Enums;

enum PlantationIntegrationStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case ERROR = 'ERROR';
}
