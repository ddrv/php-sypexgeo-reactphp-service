<?php

declare(strict_types=1);

namespace App\Zip\Exception;

use Throwable;

class IncorrectCDFHSignature extends IncorrectZipFile
{

    public function __construct(Throwable $previous = null)
    {
        parent::__construct('incorrect CDFH signature', 2, $previous);
    }
}
