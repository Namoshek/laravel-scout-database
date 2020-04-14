<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database;

use Throwable;

/**
 * A universal exception to be thrown by the Laravel Scout database search.
 *
 * @package Namoshek\Scout\Database
 */
class ScoutDatabaseException extends \Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
