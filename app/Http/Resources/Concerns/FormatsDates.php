<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use DateTimeInterface;

trait FormatsDates
{
    protected function formatDate(?DateTimeInterface $date): ?string
    {
        return $date?->format(DateTimeInterface::ATOM);
    }
}
