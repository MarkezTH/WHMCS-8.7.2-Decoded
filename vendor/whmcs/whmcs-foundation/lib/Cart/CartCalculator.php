<?php

namespace WHMCS\Cart;

class CartCalculator
{
    public $invoiceId = 0;
    public $items = [];
    public $total = NULL;
    private $subTotal = NULL;
    private $discounts = NULL;
    private $taxes = NULL;
    public $taxCalculator = NULL;
    public $client = NULL;
    const QUANTITY_NONE = 0;
    const QUANTITY_MULTIPLE = 1;
    const QUANTITY_SCALING = 2;

    public static function fromSession()
    {
        if (!function_exists("calcCartTotals")) {
            require_once ROOTDIR . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "orderfunctions.php";
        }
        $cartData = calcCartTotals(\Auth::client(), false, true);
        $items = self::getItemsFromCartData($cartData);
        return (new self())->setClient(\Auth::client())->setItems($items)->setSubTotal($cartData["subtotal"])->addDiscount(new Discount("promo", $cartData["discount"]))->addTaxTotal(new TaxTotal($cartData["taxname"], $cartData["taxrate"], $cartData["taxtotal"]))->addTaxTotal(new TaxTotal($cartData["taxname2"], $cartData["taxrate2"], $cartData["taxtotal2"]))->applyTax()->setTotal($cartData["total"])->applyClientGroupDiscount();
    }

    public static function getItemsFromCartData($cartData)
    {
        $items = [];
        foreach ($cartData["products"] as $product) {
            $recurringexcltax = NULL;
            if (is_array($product["pricing"]["recurringexcltax"] ?? NULL) && 0 < count($product["pricing"]["recurringexcltax"])) {
                $recurringexcltax = current($product["pricing"]["recurringexcltax"]);
            }
            $productItem = (new Item\Product())->setId("pid-" . $product["pid"])->setName($product["productinfo"]["groupname"] . " - " . $product["productinfo"]["name"])->setBillingCycle($product["billingcycle"])->setQuantity($product["qty"])->setAmount($product["pricing"]["totaltodayexcltax"])->setRecurringAmount($recurringexcltax)->setTaxed($product["taxed"]);
            unset($recurringexcltax);
            if (array_key_exists("proratadate", $product) && !empty($product["proratadate"])) {
                try {
                    $days = \WHMCS\Carbon::createFromFormat("Y-m-d", toMySQLDate($product["proratadate"]));
                    $productItem->setInitialPeriod($days->diffInDays(), "days");
                } catch (\Exception $e) {
                }
            }
            $items[] = $productItem;
            foreach ($product["addons"] as $addon) {
                $items[] = (new Item\Addon())->setId("aid-" . $addon["addonid"])->setName($addon["name"])->setBillingCycle($addon["billingcycle"])->setQuantity($addon["qty"])->setAmount($addon["totaltoday"])->setRecurringAmount($addon["isRecurring"] === true ? $addon["recurring"] : NULL)->setTaxed($addon["taxed"]);
            }
        }
        foreach ($cartData["addons"] as $addon) {
            $items[] = (new Item\Addon())->setId("aid-" . $addon["addonid"])->setName($addon["name"])->setBillingCycle($addon["billingcycle"])->setQuantity($addon["qty"])->setAmount($addon["totaltoday"])->setRecurringAmount($addon["isRecurring"] === true ? $addon["recurring"] : NULL)->setTaxed($addon["taxed"]);
        }
        foreach ($cartData["domains"] as $domain) {
            $itemName = $domain["type"] === "register" ? "Domain Registration" : "Domain Transfer";
            $domainParts = explode(".", $domain["domain"], 2);
            $items[] = (new Item\Domain())->setId("domain-" . $domainParts[1])->setName($itemName)->setBillingCycle("annually")->setBillingPeriod($domain["regperiod"])->setAmount($domain["totaltoday"])->setRecurringAmount($domain["renewprice"])->setTaxed($domain["taxed"]);
        }
        foreach ($cartData["renewals"] as $domain) {
            $domainParts = explode(".", $domain["domain"], 2);
            $items[] = (new Item\DomainRenewal())->setId("renewal-" . $domainParts[1])->setName("Domain Renewal")->setBillingCycle("annually")->setBillingPeriod($domain["regperiod"])->setAmount($domain["price"])->setRecurringAmount($domain["priceWithoutGraceAndRedemption"])->setTaxed($domain["taxed"]);
        }
        return $items;
    }

    public function setInvoiceId($invoiceId)
    {
        $this->invoiceId = $invoiceId;
        return $this;
    }

    public function getInvoiceModel()
    {
        return \WHMCS\Billing\Invoice::find($this->invoiceId);
    }

    public function setClient(\WHMCS\User\Client $client = NULL)
    {
        $this->client = $client;
        return $this;
    }

    public function setItems($items)
    {
        $this->items = collect($items);
        return $this;
    }

    public function getItems()
    {
        return $this->items;
    }

    public function setTotal(\WHMCS\View\Formatter\Price $total)
    {
        $this->total = $total;
        return $this;
    }

    public function setSubTotal(\WHMCS\View\Formatter\Price $subTotal)
    {
        $this->subTotal = $subTotal;
        return $this;
    }

    public function getFirstRecurringItem()
    {
        foreach ($this->items as $item) {
            if ($item->isRecurring()) {
                return $item;
            }
        }
        return NULL;
    }

    public function isRecurring()
    {
        if (0 < $this->items->count()) {
            return !is_null($this->getFirstRecurringItem());
        }
        return false;
    }

    public function getRecurringTotals()
    {
        $recurringTotals = [];
        foreach ($this->items as $item) {
            if (!is_null($item->recurring) && !$item->hasInitialPeriod() && $item->getRecurringAmount()) {
                $recurringTotal = $item->getRecurringAmount()->toNumeric();
                if (!isset($recurringTotals[$item->billingCycle][$item->billingPeriod])) {
                    $recurringTotals[$item->billingCycle][$item->billingPeriod] = 0;
                }
                $recurringTotals[$item->billingCycle][$item->billingPeriod] += $recurringTotal;
            }
        }
        foreach ($recurringTotals as $cycle => &$periods) {
            foreach ($periods as $period => &$value) {
                $value = format_as_currency($value);
            }
        }
        return $recurringTotals;
    }

    public function getRecurringTotalCycles()
    {
        return $this->getItems()->groupBy(function ($item) {
            if (!is_null($item->recurring) && !$item->hasInitialPeriod()) {
                return $item->normalisedBillingCycle;
            }
            return NULL;
        })->filter(function ($item, $key) {
            return 0 < strlen($key);
        })->map(function ($items) {
            return $items->sum(function ($price) {
                if ($price->getRecurringAmount()) {
                    return $price->getRecurringAmount()->toNumeric();
                }
                return 0;
            });
        })->toArray();
    }

    public function getRecurringTotal()
    {
        $firstItem = $this->getFirstRecurringItem();
        if (!$firstItem) {
            return NULL;
        }
        if ($firstItem->hasInitialPeriod()) {
            $total = 0;
            foreach ($this->items as $item) {
                if ($item->hasInitialPeriod() && $item->billingPeriod == $firstItem->billingPeriod && $item->billingCycle == $firstItem->billingCycle && $item->initialPeriod == $firstItem->initialPeriod && $item->initialCycle == $firstItem->initialCycle) {
                    $total += $item->recurring->toNumeric();
                }
            }
            return format_as_currency($total);
        } else {
            return $this->getRecurringTotals()[$firstItem->billingCycle][$firstItem->billingPeriod] ?? NULL;
        }
    }

    public function setTaxCalculator(\WHMCS\Billing\Tax $taxCalculator)
    {
        $this->taxCalculator = $taxCalculator;
        return $this;
    }

    public function getTaxCalculator($Tax, $client)
    {
        if ($this->taxCalculator) {
            return $this->taxCalculator;
        }
        $taxCalculator = (new \WHMCS\Billing\Tax())->setIsInclusive(\WHMCS\Config\Setting::getValue("TaxType") === "Inclusive")->setIsCompound(\WHMCS\Config\Setting::getValue("TaxL2Compound"));
        require_once ROOTDIR . "/includes/invoicefunctions.php";
        $taxdata = getTaxRate(1, $client->state, $client->country);
        $taxCalculator->setLevel1Percentage($taxdata["rate"]);
        $taxdata = getTaxRate(2, $client->state, $client->country);
        $taxCalculator->setLevel2Percentage($taxdata["rate"]);
        $this->setTaxCalculator($taxCalculator);
        return $taxCalculator;
    }

    public function applyTax()
    {
        if (!\WHMCS\Config\Setting::getValue("TaxEnabled")) {
            return $this;
        }
        $client = $this->client;
        if (!$client) {
            $cartSessionData = \WHMCS\Session::get("cart");
            $client = new \WHMCS\User\Client();
            $client->state = isset($cartSessionData["user"]["state"]) ? $cartSessionData["user"]["state"] : "";
            $client->country = isset($cartSessionData["user"]["country"]) ? $cartSessionData["user"]["country"] : \WHMCS\Config\Setting::getValue("DefaultCountry");
        }
        if ($client->taxExempt) {
            return $this;
        }
        $cartItems = $this->getItems();
        $hookCartData = [];
        if (\HookMgr::getRegistered("CartItemsTax")) {
            foreach ($cartItems as $hookItem) {
                $hookCartData[] = clone $hookItem;
            }
        }
        $taxOverride = run_hook("CartItemsTax", ["clientData" => $client->toArray(), "cartData" => $hookCartData]);
        if (!empty($taxOverride)) {
            $taxOverride = $taxOverride[0];
            foreach ($taxOverride["cartData"] as $itemOverride) {
                $originalItem = $cartItems->firstWhere("uuid", "=", $itemOverride->getUuid());
                if ($originalItem) {
                    $originalItem->setAmount($itemOverride->getAmount());
                    if ($originalItem->isRecurring() && $itemOverride->getRecurringAmount()) {
                        $originalItem->setRecurringAmount($itemOverride->getRecurringAmount());
                    }
                }
            }
            $this->setItems($cartItems->toArray());
            return $this;
        } else {
            $taxCalculator = $this->getTaxCalculator($client);
            foreach ($this->items as $item) {
                if ($item->isTaxed()) {
                    if ($item->getAmount() instanceof \WHMCS\View\Formatter\Price && 0 < $item->getAmount()->toNumeric()) {
                        $item->amount = new \WHMCS\View\Formatter\Price($taxCalculator->setTaxBase($item->getAmount()->toNumeric())->getTotalAfterTaxes(), $item->getAmount()->getCurrency());
                    }
                    if ($item->getRecurringAmount() instanceof \WHMCS\View\Formatter\Price && 0 < $item->getRecurringAmount()->toNumeric()) {
                        $item->recurring = new \WHMCS\View\Formatter\Price($taxCalculator->setTaxBase($item->getRecurringAmount()->toNumeric())->getTotalAfterTaxes(), $item->getRecurringAmount()->getCurrency());
                    }
                }
            }
            if (!is_null($this->total)) {
                $this->total = new \WHMCS\View\Formatter\Price($taxCalculator->setTaxBase($this->total->toNumeric())->getTotalAfterTaxes(), $this->total->getCurrency());
            }
            return $this;
        }
    }

    public function getDescription()
    {
        if ($this->isRecurring()) {
            $firstItem = $this->getFirstRecurringItem();
            if ($firstItem && 0 < strlen($firstItem->name)) {
                return $firstItem->name;
            }
        }
        if (0 < $this->invoiceId) {
            return "Invoice #" . $this->invoiceId;
        }
        return "Shopping Cart Checkout";
    }

    public function applyClientGroupDiscount()
    {
        $clientGroupDiscount = 0;
        if ($this->client && $this->client instanceof \WHMCS\User\Client) {
            $clientGroupDiscount = $this->client->getClientDiscountPercentage();
        }
        if (0 < $clientGroupDiscount) {
            $discount = 1 - $clientGroupDiscount / 100;
            foreach ($this->items as $item) {
                $amount = $item->amount;
                $itemCurrency = $amount->getCurrency();
                $amount = $amount->toNumeric() * $discount;
                $amount = round($amount, 2);
                $item->setAmount(new \WHMCS\View\Formatter\Price($amount, $itemCurrency));
                if ($item->recurring) {
                    $recurringAmount = $item->recurring->toNumeric() * $discount;
                    $recurringAmount = round($recurringAmount, 2);
                    $item->setRecurringAmount(new \WHMCS\View\Formatter\Price($recurringAmount, $itemCurrency));
                }
            }
            if (!is_null($this->total)) {
                $totalAmount = $this->total->toNumeric();
                $totalCurrency = $this->total->getCurrency();
                $discountedTotalAmount = $totalAmount * $discount;
                $this->addDiscount(new Discount("client_group", new \WHMCS\View\Formatter\Price($totalAmount - $discountedTotalAmount, $totalCurrency)));
                $this->total = new \WHMCS\View\Formatter\Price($discountedTotalAmount, $totalCurrency);
            }
        }
        return $this;
    }

    public function getTotal($Price)
    {
        $total = $this->total;
        if (is_null($total)) {
            $total = new \WHMCS\View\Formatter\Price(0);
        }
        return $total;
    }

    public function getSubTotal($Price)
    {
        $subTotal = $this->subTotal;
        if (is_null($subTotal)) {
            $subTotal = new \WHMCS\View\Formatter\Price(0);
        }
        return $subTotal;
    }

    public function addDiscount($self, $discount)
    {
        $this->getDiscounts()->add($discount);
        return $this;
    }

    public function getDiscounts($Collection)
    {
        if (is_null($this->discounts)) {
            $this->discounts = new \Illuminate\Support\Collection();
        }
        return $this->discounts;
    }

    public function getTaxTotals($Collection)
    {
        if (is_null($this->taxes)) {
            $this->taxes = new \Illuminate\Support\Collection();
        }
        return $this->taxes;
    }

    public function addTaxTotal($self, $taxTotal)
    {
        $this->getTaxTotals()->add($taxTotal);
        return $this;
    }
}
