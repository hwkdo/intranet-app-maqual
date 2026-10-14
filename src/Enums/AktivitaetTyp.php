<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Enums;

enum AktivitaetTyp: string
{
    case StatusChange = 'status_change';
    case FieldChange = 'field_change';
    case CostChange = 'cost_change';
    case FileReplace = 'file_replace';
    case BudgetChange = 'budget_change';
}
