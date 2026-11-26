<?php

namespace App\Domain\Sales;

use RuntimeException;

/**
 * Base class for sales-domain rule violations. The message is the
 * user-facing Persian text; controllers turn these into 422 responses.
 */
abstract class SalesException extends RuntimeException {}
