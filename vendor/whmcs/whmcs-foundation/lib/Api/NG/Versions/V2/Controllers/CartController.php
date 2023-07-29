<?php

namespace WHMCS\Api\NG\Versions\V2\Controllers;

class CartController extends \WHMCS\Api\NG\Versions\V2\AbstractApiController implements \WHMCS\Api\NG\Versions\V2\PagedResponseInterface
{
    use \WHMCS\Api\NG\Versions\V2\PagedResponseTrait;

    public function __construct()
    {
        if (!function_exists("calcCartTotals")) {
            require_once ROOTDIR . "/includes/orderfunctions.php";
        }
    }

    protected function getCartFromRequest($Cart, $request)
    {
        $cartTag = $request->get("cart_id");
        $loggedInUser = \Auth::user();
        if (empty($cartTag)) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
        }
        $cart = \WHMCS\Cart\Models\Cart::byTag($cartTag)->firstOrFail();
        if ($cart->user_id) {
            if (!$loggedInUser || $loggedInUser->id !== $cart->user_id) {
                throw new \WHMCS\Exception\Authorization\AccessDenied();
            }
        } else {
            if ($loggedInUser) {
                $cart->user_id = $loggedInUser->id;
                $cart->save();
            }
        }
        return $cart;
    }

    protected function processCartRequest(\WHMCS\Http\Message\ServerRequest $request, $callback)
    {
        $cart = $this->getCartFromRequest($request);
        $cart->exportToSession();
        $result = $callback();
        $cart->importFromSession();
        $cart->save();
        return $result;
    }

    protected function generateCartItemId()
    {
        return \Illuminate\Support\Str::random(8);
    }

    public function createCart(\WHMCS\Http\Message\ServerRequest $request)
    {
        $cart = new \WHMCS\Cart\Models\Cart();
        $cart->importFromSession();
        if (\Auth::user()) {
            $cart->user()->associate(\Auth::user());
        }
        $cart->save();
        return $this->createResponse(["id" => $cart->tag]);
    }

    public function getCarts($JsonResponse, $request)
    {
        $responseData = [];
        if (\Auth::user() && \Auth::client()) {
            $responseData = \WHMCS\Api\NG\Versions\V2\ApiEntityDecoratorFactory::decorate($this->paginateData(\WHMCS\Cart\Models\Cart::byUser(\Auth::user())->get(), $request));
        }
        return $this->createResponse($responseData);
    }

    public function getTotals($JsonResponse, $request)
    {
        $responseData = $this->processCartRequest($request, function () {
            return $this->getCartTotal();
        });
        return $this->createResponse($responseData);
    }

    public function getItems($JsonResponse, $request)
    {
        $items = $this->processCartRequest($request, function () {
            return (new \WHMCS\OrderForm())->getCartData();
        });
        $responseData = [];
        foreach ($items["products"] ?? [] as $item) {
            $productData = ["type" => "product", "product_id" => $item["pid"], "billing_cycle" => $item["billingcycle"], "domain" => $item["domain"], "item_id" => $item["item_id"]];
            foreach ($item["addons"] ?? [] as $addonData) {
                $productData["addons"][] = ["item_id" => $addonData["item_id"], "quantity" => $addonData["qty"] ?? 1, "addon_id" => $addonData["addonid"]];
            }
            $responseData[] = $productData;
        }
        foreach ($items["addons"] ?? [] as $item) {
            $responseData[] = ["type" => "addon", "addon_id" => $item["id"], "service_id" => $item["productid"], "billing_cycle" => $item["billingcycle"], "quantity" => $item["qty"] ?? 1, "item_id" => $item["item_id"]];
        }
        return $this->createResponse($this->paginateData($responseData, $request));
    }

    public function addItems($JsonResponse, $request)
    {
        $responseData = $this->processCartRequest($request, function () use($request) {
            $items = $request->getFromJson("items", []);
            foreach ($items as $item) {
                switch ($item["type"] ?? "") {
                    case "product":
                        $extra = ["item_id" => $this->generateCartItemId()];
                        foreach ($item["addons"] ?? [] as $addonData) {
                            $extra["addons"][] = ["item_id" => $this->generateCartItemId(), "addonid" => $addonData["addon_id"], "qty" => $addonData["quantity"]];
                        }
                        \WHMCS\OrderForm::addProductToCart($item["product_id"], $item["billing_cycle"], $item["domain"], $extra);
                        break;
                    case "addon":
                        \WHMCS\OrderForm::addAddonToCart($item["addon_id"], $item["product_id"], $item["billing_cycle"], ["item_id" => $this->generateCartItemId(), "qty" => $item["quantity"] ?? 1]);
                        break;
                }
            }
            return $this->getCartTotal();
        });
        return $this->createResponse($responseData);
    }

    public function addPromo($JsonResponse, $request)
    {
        $promoCode = $request->getFromJson("code", "");
        $this->assertValidPromoCode($promoCode);
        $responseData = $this->processCartRequest($request, function () use($promoCode) {
            $orderForm = new \WHMCS\OrderForm();
            $cartData = $orderForm->getCartData();
            $cartData["promo"] = $promoCode;
            $orderForm->setCartData($cartData);
            return $this->getCartTotal();
        });
        return $this->createResponse($responseData);
    }

    public function getPromo($JsonResponse, $request)
    {
        $cart = $this->processCartRequest($request, function () {
            return (new \WHMCS\OrderForm())->getCartData();
        });
        $responseData = ["code" => $cart["promo"] ?? NULL];
        return $this->createResponse($responseData);
    }

    public function removePromo($JsonResponse, $request)
    {
        $responseData = $this->processCartRequest($request, function () use($request) {
            $orderForm = new \WHMCS\OrderForm();
            $cartData = $orderForm->getCartData();
            unset($cartData["promo"]);
            $orderForm->setCartData($cartData);
            return $this->getCartTotal();
        });
        return $this->createResponse($responseData);
    }

    protected function removeItemsFromCart($itemIdsToRemove)
    {
        $orderForm = new \WHMCS\OrderForm();
        $cartData = $orderForm->getCartData();
        $requestedItemIdsToRemove = $itemIdsToRemove;
        $filterItems = function (&$items) use($itemIdsToRemove) {
            $items = array_values(array_filter($items ?? [], function ($item) use($itemIdsToRemove) {
                if (in_array($item["item_id"], $itemIdsToRemove)) {
                    $itemIdsToRemove = array_diff($itemIdsToRemove, [$item["item_id"]]);
                    return false;
                }
                return true;
            }));
        };
        if ($itemIdsToRemove && is_array($cartData["products"])) {
            $filterItems($cartData["products"]);
            if ($itemIdsToRemove) {
                foreach ($cartData["products"] as &$product) {
                    if ($product["addons"] ?? []) {
                        $filterItems($product["addons"]);
                    }
                }
                unset($product);
            }
        }
        if ($itemIdsToRemove && is_array($cartData["addons"])) {
            $filterItems($cartData["addons"]);
        }
        $orderForm->setCartData($cartData);
        return count($itemIdsToRemove) < count($requestedItemIdsToRemove);
    }

    public function removeItem($JsonResponse, $request)
    {
        $responseData = $this->processCartRequest($request, function () use($request, $foundItems) {
            $this->removeItemsFromCart(array_filter([$request->get("item_id")]));
            return $this->getCartTotal();
        });
        return $this->createResponse($responseData);
    }

    public function removeAllItems($JsonResponse, $request)
    {
        $responseData = $this->processCartRequest($request, function () {
            (new \WHMCS\OrderForm())->setCartData([]);
            return $this->getCartTotal();
        });
        return $this->createResponse($responseData);
    }

    public function startCheckout($JsonResponse, $request)
    {
        $cart = $this->getCartFromRequest($request);
        $params = ["a" => "checkout", "cart_id" => $cart->tag];
        if (\Auth::user() && \Auth::client()) {
            $tempHttpRequest = new \OAuth2\HttpFoundationBridge\Request();
            $tempHttpRequest->request->add(["module" => "ClientAccessSsoToken", "module_type" => "api"]);
            $clientOtpServer = \DI::make("oauth2_sso", ["request" => $tempHttpRequest]);
            $clientOtpServer->setUserClient(\Auth::user(), \Auth::client());
            $tempHttpResponse = new \OAuth2\HttpFoundationBridge\Response();
            $tempHttpResponse->prepare($tempHttpRequest);
            $tempHttpResponse = $clientOtpServer->handleTokenRequest($tempHttpRequest, $tempHttpResponse);
            $data = json_decode($tempHttpResponse->getContent(), true);
            if (!$data || !is_array($data)) {
                throw new \WHMCS\Exception("Unexpected internal structure");
            }
            $params["access_token"] = $data["access_token"] ?? "";
        }
        $checkoutUrl = \App::getSystemURL() . "cart.php?" . http_build_query($params);
        return $this->createResponse(["url" => $checkoutUrl]);
    }

    private function getCartTotal()
    {
        $calculator = \WHMCS\Cart\CartCalculator::fromSession();
        $total = $calculator->getTotal();
        $formatRecurring = [];
        foreach ($calculator->getRecurringTotalCycles() as $cycle => $price) {
            $formatRecurring[] = ["cycle" => $cycle, "amount" => ["value" => format_as_currency($price), "code" => $total->getCurrency()->code]];
        }
        return ["recurring" => $formatRecurring, "subtotal" => ["value" => $calculator->getSubTotal()->toNumeric(), "code" => $calculator->getSubTotal()->getCurrency()->code], "discount" => \WHMCS\Api\NG\Versions\V2\ApiEntityDecoratorFactory::decorate($calculator->getDiscounts()), "tax" => \WHMCS\Api\NG\Versions\V2\ApiEntityDecoratorFactory::decorate($calculator->getTaxTotals()), "total" => ["value" => $total->toNumeric(), "code" => $total->getCurrency()->code]];
    }

    private function assertValidPromoCode($code)
    {
        if (is_null($code) || !\WHMCS\Product\Promotion::byCode($code)->exists()) {
            throw new \WHMCS\Exception\Api\NG\ApiNgInvalidArgument("Invalid Promo Code");
        }
    }
}
