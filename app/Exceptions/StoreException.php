<?php

namespace App\Exceptions;

use RuntimeException;

/** A business-rule refusal whose message is safe to show the user as is. */
class StoreException extends RuntimeException
{
}
