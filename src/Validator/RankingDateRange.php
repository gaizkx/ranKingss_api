<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class RankingDateRange extends Constraint
{
    public string $message = 'El rango de fechas no puede superar los 92 días.';
    public string $invalidDateMessage = 'Formato de fecha inválido. Use Y-m-d.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
