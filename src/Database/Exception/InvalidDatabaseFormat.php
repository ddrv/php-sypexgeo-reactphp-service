<?php

declare(strict_types=1);

namespace App\Database\Exception;

use Exception;
use Throwable;

class InvalidDatabaseFormat extends Exception
{

    public function __construct(Throwable $previous = null)
    {
        parent::__construct('Invalid database format', 1, $previous);
    }
}
