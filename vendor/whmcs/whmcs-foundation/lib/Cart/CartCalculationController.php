<?php

namespace WHMCS\Cart;

class CartCalculationController
{
    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        return \WHMCS\Http\RedirectResponse::legacyPath("cart.php");
    }

    public function selectAccount(\WHMCS\Http\Message\ServerRequest $request)
    {
        $response = ["existingCards" => "", "canUseCreditOnCheckout" => false, "full" => false, "availableCreditBalance" => "", "creditBalance" => 0];
        $selectedAccountId = $request->get("account_id");
        try {
            $orderfrm = new \WHMCS\OrderForm();
            if ($orderfrm->inExpressCheckout()) {
                throw new \Exception("Express checkout does not permit account changes");
            }
            if ($selectedAccountId === "new") {
                $currency = \Currency::factoryForClientArea();
                $response["creditBalance"] = new \WHMCS\View\Formatter\Price(0, $currency);
                $cart = CartCalculator::fromSession();
            } else {
                $selectedAccountId = (int) $selectedAccountId;
                \Auth::setClientId($selectedAccountId);
                if (!\Auth::hasPermission("orders")) {
                    throw new \WHMCS\Exception\Authorization\AccessDenied();
                }
                $client = \Auth::client();
                $currency = \Currency::factoryForClientArea();
                $cart = CartCalculator::fromSession();
                $smarty = new \WHMCS\Smarty();
                $orderFormTemplate = \WHMCS\View\Template\OrderForm::factory();
                $orderFormTemplateName = $orderFormTemplate->getName();
                if ($client->payMethods->validateGateways()->count()) {
                    $existingPayMethodsTemplate = "/templates/orderforms/" . \WHMCS\View\Template\OrderForm::factory("includes/existing-paymethods.tpl", $orderFormTemplateName)->getName() . "/includes/existing-paymethods.tpl";
                    $response["existingCards"] = processSingleSmartyTemplate($smarty, $existingPayMethodsTemplate, ["selectedAccountId" => $selectedAccountId, "client" => $client]);
                }
                $canUseCreditOnCheckout = false;
                $amountOfCredit = $client->credit;
                if (0 < $amountOfCredit) {
                    $canUseCreditOnCheckout = true;
                }
                $creditBalance = new \WHMCS\View\Formatter\Price($amountOfCredit, $currency);
                $response["availableCreditBalance"] = \Lang::trans("cart.availableCreditBalance", [":amount" => $creditBalance]);
                $response["canUseCreditOnCheckout"] = $canUseCreditOnCheckout;
                $response["full"] = $cart->getTotal()->toNumeric() < $creditBalance->toNumeric();
                $response["creditBalance"] = $creditBalance;
            }
            $response["total"] = $cart->getTotal()->toFull();
            return new \WHMCS\Http\Message\JsonResponse($response);
        } catch (\Exception $e) {
            return new \WHMCS\Http\Message\JsonResponse(["error" => "Invalid Access Attempt"]);
        }
    }
}
