<?php

namespace WHMCS\View\Markup\Error\Message\MatchDecorator;

trait GenericMatchDecorationTrait
{
    public function toHtml()
    {
        return $this->toGenericHtml(implode("\n", $this->getParsedMessageList()));
    }

    public function toPlain()
    {
        return $this->toGenericPlain(implode("\n", $this->getParsedMessageList()));
    }
}
