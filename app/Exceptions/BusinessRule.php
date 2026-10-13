<?php

namespace App\Exceptions;

use RuntimeException;

/** A business-rule violation shown to the user as a toast / form error, e.g. "Remarks are mandatory for return / reject." */
class BusinessRule extends RuntimeException
{
}
