<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class PoisonMessageException extends RuntimeException implements ShouldntReport
{
    //
}
