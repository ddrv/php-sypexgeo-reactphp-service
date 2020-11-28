<?php

declare(strict_types=1);

namespace App\Zip\Exception;

use Throwable;

class IncorrectEOCDSignature extends IncorrectZipFile
{

    public function __construct(Throwable $previous = null)
    {
        parent::__construct('incorrect EOCD signature', 1, $previous);
    }
}
