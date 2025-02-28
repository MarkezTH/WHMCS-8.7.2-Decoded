<?php
declare(strict_types=1);

namespace League\OpenAPIValidation\Schema\TypeFormats;

use function filter_var;

use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

class StringIP6
{
    public function __invoke(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }
}
