<?php

namespace WHMCS\Order;

class OrderProducts
{
    private $orderForm = NULL;
    private $formProducts = [];
    private $products = NULL;

    public function __construct(\WHMCS\OrderForm $orderForm)
    {
        $this->orderForm = $orderForm;
    }

    public function getFormProducts()
    {
        return $this->formProducts;
    }

    public function getProducts()
    {
        if ($this->areProductsNotFetched()) {
            $this->fetchProducts();
        }
        return is_null($this->products) ? new \Illuminate\Database\Eloquent\Collection() : $this->products;
    }

    public function obtainProducts()
    {
        $productsData = $this->orderForm->getCartDataByKey("products", []);
        $this->formProducts = is_array($productsData) ? $this->filterFormProducts($productsData, "isProductDataCorrect") : [];
        $this->formProducts = $this->filterFormProducts($this->formProducts, "isProductExists");
        return $this;
    }

    private function filterFormProducts($productsData, $filter)
    {
        return array_values(array_filter($productsData, function ($product) use ($filter) {
            return $this->{$filter}($product);
        }));
    }

    private function isProductDataCorrect($productData)
    {
        return (bool) ($productData["pid"] ?? false);
    }

    private function isProductExists($productData)
    {
        $products = $this->getProducts();
        return isset($products[$productData["pid"]]);
    }

    private function areProductsNotFetched()
    {
        return is_null($this->products) && !empty($this->formProducts);
    }

    private function fetchProducts()
    {
        $productIds = array_unique(array_column($this->formProducts, "pid"));
        $this->products = \WHMCS\Product\Product::whereIn("id", $productIds)->with("productGroup")->get()->keyBy("id");
    }
}
