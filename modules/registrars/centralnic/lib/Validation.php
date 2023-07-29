<?php

namespace WHMCS\Module\Registrar\CentralNic;

class Validation
{
    protected $items = NULL;
    protected $validatedItems = NULL;
    const ASSERT_NOT_EMPTY = "NOT_EMPTY";
    const ASSERT_IS_EMAIL = "IS_EMAIL";

    public function __construct()
    {
        $this->items = collect();
        $this->validatedItems = collect();
    }

    public function addValidationItem($self, ...$items)
    {
        foreach ($items as $index => $item) {
            $this->items->add($item);
        }
        return $this;
    }

    public function getValidationItems()
    {
        return $this->items;
    }

    public function getValidatedItems()
    {
        return $this->validatedItems;
    }

    public function completed()
    {
        return $this->getValidationItems()->count() == 0;
    }

    public function validate()
    {
        while (!$this->completed()) {
            $this->validatedItems->add($this->validating($this->getValidationItems()->shift()));
        }
        return $this;
    }

    protected function validating($ValidationItem, $item)
    {
        $item->getRule();
        switch ($item->getRule()) {
            case self::ASSERT_NOT_EMPTY:
                if (!$item->getValue()) {
                    $item->setAssertionMessage($item->getName() . " can not be empty");
                }
                break;
            case self::ASSERT_IS_EMAIL:
                if (!filter_var($item->getValue(), FILTER_VALIDATE_EMAIL)) {
                    $item->setAssertionMessage($item->getName() . " is not a valid email address");
                }
                break;
            default:
                return $item;
        }
    }
}
