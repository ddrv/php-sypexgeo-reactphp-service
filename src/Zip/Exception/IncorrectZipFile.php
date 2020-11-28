<?php

declare(strict_types=1);

namespace App\Zip\Exception;

use Exception;
use Throwable;

class IncorrectZipFile extends Exception
{

    public function __construct(string $message, int $code, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
