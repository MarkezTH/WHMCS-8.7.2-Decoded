<?php

namespace WHMCS\MarketConnect\Promotion;

class Promotion
{
    protected $promotion = NULL;
    protected $product = NULL;
    protected $upsellService = NULL;
    protected $cartItem = NULL;

    public function __construct(PromotionContentWrapper $promotion, \WHMCS\Product\Product $product, $upsellService = NULL, $cartItem = NULL)
    {
        $this->promotion = $promotion;
        $this->product = $product;
        $this->upsellService = $upsellService;
        $this->cartItem = $cartItem;
    }

    public function getPromotion()
    {
        return $this->promotion;
    }

    public function getProduct()
    {
        return $this->product;
    }

    public function getUpsellService()
    {
        return $this->upsellService;
    }

    public function getCartItem()
    {
        return $this->cartItem;
    }

    protected function getTemplate()
    {
        $theme = \WHMCS\View\Template\Theme::factory();
        return $theme->resolveFilePath("/store/promos/upsell.tpl");
    }

    protected function getTargetUrl()
    {
        return routePath("cart-order");
    }

    protected function getInputParameters()
    {
        $params = ["pid" => $this->getProduct()->id];
        if ($this->getUpsellService() && $this->getUpsellService()->isService()) {
            $params["serviceid"] = $this->getUpsellService()->id;
        }
        return $params;
    }

    public function render()
    {
        try {
            $cartItem = $this->getCartItem();
            if (!$cartItem) {
                $cartItem = ["qty" => 1];
            }
            $result = (new \WHMCS\Smarty())->fetch($this->getTemplate(), ["targetUrl" => $this->getTargetUrl(), "inputParameters" => $this->getInputParameters(), "product" => $this->getProduct(), "promotion" => $this->getPromotion(), "upsellService" => $this->getUpsellService(), "allowsMultipleQuantities" => $this->getProduct()->allowMultipleQuantities === 2, "cartItem" => $cartItem, "currency" => \Currency::factoryForClientArea()]);
        } catch (\WHMCS\Exception $e) {
            $result = "";
        }
        return $result;
    }

    public function __toString()
    {
        return $this->render();
    }
}
