<?php

namespace WHMCS\Version;

class CompoundExpression implements MatchInterface
{
    use AnythingExpressionTrait;
    private $bases = [];

    public function __construct($value = MatchInterface::VALUE_ANYTHING)
    {
        $value = trim($value);
        $this->preventAnyMatch();
        foreach ($this->parseBaselines($value) as $base) {
            if ($base === static::VALUE_ANYTHING || empty($base)) {
                $this->allowAnyMatch();
            } else {
                try {
                    $expression = new ExpressionVersion($base);
                    $this->bases[] = $expression;
                } catch (\Throwable $e) {
                }
            }
        }
    }

    protected function parseBaselines($value)
    {
        return array_map("trim", array_filter(explode("||", $value)));
    }

    public function matches($value)
    {
        if ($this->matchesAnything()) {
            return true;
        }
        $matched = false;
        foreach ($this->bases as $versionExpression) {
            if ($versionExpression->matches($value)) {
                $matched = true;
                return $matched;
            }
        }
    }
}
