<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class RankingDateRangeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RankingDateRange) {
            return;
        }

        if (!is_object($value) || !property_exists($value, 'startDate') || !property_exists($value, 'endDate')) {
            return;
        }

        $startDate = $value->startDate;
        $endDate = $value->endDate;

        if ($startDate === null && $endDate === null) {
            return;
        }

        if ($endDate === null) {
            $endDate = (new \DateTimeImmutable())->format('Y-m-d');
        }

        try {
            $start = new \DateTimeImmutable((string) $startDate);
            $end = new \DateTimeImmutable((string) $endDate);
        } catch (\Exception) {
            $this->context->buildViolation($constraint->invalidDateMessage)->addViolation();
            return;
        }

        $diff = $start->diff($end);

        if ($diff->days > 92) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
