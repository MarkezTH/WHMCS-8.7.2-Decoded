<?php

namespace WHMCS\MarketConnect\Promotion;

class CartPromotion extends Promotion
{
    protected function getTemplate()
    {
        $orderFormTemplate = \WHMCS\View\Template\OrderForm::factory("marketconnect-promo.tpl");
        return $orderFormTemplate->getTemplatePath() . "marketconnect-promo.tpl";
    }
}
