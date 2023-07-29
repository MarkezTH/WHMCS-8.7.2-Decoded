<?php

namespace WHMCS\Module\Gateway\Stripe\Widget;

class Stripe extends \WHMCS\Module\AbstractWidget
{
    protected $title = "Stripe Balance";
    protected $description = "An overview of connected Stripe account balance information.";
    protected $weight = 175;
    protected $cache = true;
    protected $cacheExpiry = 60;
    protected $requiredPermission = "View Gateway Balances";

    public function getId()
    {
        return "Stripe";
    }

    public function getData()
    {
        try {
            $gatewayInterface = \WHMCS\Module\Gateway::factory("stripe");
            $balanceCollection = $gatewayInterface->call("account_balance");
            if ($balanceCollection instanceof \WHMCS\Module\Gateway\BalanceCollection) {
                return $balanceCollection->all();
            }
        } catch (\Throwable $t) {
            if ($t instanceof \Stripe\Exception\ApiConnectionException) {
                return ["error" => \AdminLang::trans("error.connection")];
            }
            if ($t instanceof \Stripe\Exception\AuthenticationException) {
                return ["error" => \AdminLang::trans("error.authentication")];
            }
            logTransaction("Stripe", ["error" => $t->getMessage()], \AdminLang::trans("error.widgetError"));
            return ["error" => sprintf("%s (%s)", \AdminLang::trans("global.error"), \AdminLang::trans("global.seeGatewayLog"))];
        }
        return [];
    }

    public function generateOutput($data)
    {
        $output = [];
        if (array_key_exists("error", $data)) {
            $errorData = $data["error"];
            $balanceError = \AdminLang::trans("error.balanceWidgetError");
            $balanceErrorDescription = \AdminLang::trans("error.balanceWidgetDescription", [":moduleName" => "Stripe"]);
            return "<div class=\"row\">\n    <div class=\"col-sm-12\">\n        <div class=\"error\">\n            <i class=\"fas fa-exclamation-circle\"></i> " . $balanceError . " - " . $errorData . ". \n            " . $balanceErrorDescription . "\n        </div>\n    </div>\n</div>";
        }
        foreach ($data as $index => $balanceObject) {
            if (is_array($balanceObject)) {
                $balanceObject = \WHMCS\Module\Gateway\Balance::factoryFromArray($balanceObject);
            }
            $currencyObject = $balanceObject->getCurrencyObject();
            if ($currencyObject) {
                $textColor = $balanceObject->colorCodeAsString();
                $additionalStyle = "";
                if (is_null($textColor)) {
                    $additionalStyle = " style=\"color: " . $balanceObject->getColor() . ";\"";
                }
                $additionalClasses = [];
                if (!in_array($index, [0, 2])) {
                    $additionalClasses[] = "bordered-top";
                }
                if ($balanceObject->getRawLabel() !== "status.pending") {
                    $additionalClasses[] = "bordered-right";
                }
                $additionalClass = implode(" ", $additionalClasses);
                $output[$currencyObject->code] = ($output[$currencyObject->code] ?? "") . "<div class=\"col-sm-6 " . $additionalClass . "\">\n    <div class=\"item\">\n        <div class=\"data " . $textColor . "\"" . $additionalStyle . ">" . $balanceObject->getAmount()->toPrefixed() . "</div>\n        <div class=\"note\">" . $balanceObject->getLabel() . "</div>\n    </div>\n</div>";
            }
        }
        $output = implode("", $output);
        return "<div class=\"row\">" . $output . "</div>";
    }
}
