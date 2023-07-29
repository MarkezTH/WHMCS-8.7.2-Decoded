<?php

namespace WHMCS\Admin\Utilities\Assent\Controller;

class EulaController
{
    public function eulaAcceptanceRequired(\WHMCS\Http\Message\ServerRequest $request)
    {
        $eula = new \WHMCS\Utility\Eula();
        if ($eula->isEulaAccepted()) {
            $view = new \WHMCS\Admin\ApplicationSupport\View\Html\Smarty\ErrorPage();
        } else {
            $data = ["eulaText" => $eula->getEulaText(), "effectiveDate" => $eula->getEffectiveDate()->format("Y-m-d")];
            $view = new \WHMCS\Admin\Utilities\Assent\View\AssentPage("eula", $data);
            $view->setTitle("End User License Agreement");
            $view->setAdminUser($request->getAttribute("authenticatedUser"));
        }
        return $view;
    }

    public function acceptEula(\WHMCS\Http\Message\ServerRequest $request)
    {
        if ($request->has("eulaAccepted") && $request->get("eulaAccepted")) {
            (new \WHMCS\Utility\Eula())->markAsAccepted($request->getAttribute("authenticatedUser"));
        }
        return new \Laminas\Diactoros\Response\RedirectResponse(routePath("admin-homepage"));
    }
}
