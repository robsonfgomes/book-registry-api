<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use DomainException;

abstract class BusinessRuleException extends DomainException
{
    abstract public function field(): string;
}
