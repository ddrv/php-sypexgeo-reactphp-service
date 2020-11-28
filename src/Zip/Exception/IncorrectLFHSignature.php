<?php

declare(strict_types=1);

namespace App\Zip\Exception;

use Throwable;

class IncorrectLFHSignature extends IncorrectZipFile
{

    public function __construct(Throwable $previous = null)
    {
        parent::__construct('incorrect LFH signature', 3, $previous);
    }
}
