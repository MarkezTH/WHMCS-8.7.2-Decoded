<?php

namespace WHMCS\Admin\Controller;

class GlobalWarningController
{
    public function dismiss(\WHMCS\Http\Message\ServerRequest $request)
    {
        $alertToDismiss = $request->get("alert");
        (new \WHMCS\Admin\ApplicationSupport\View\Html\Helper\GlobalWarning())->updateDismissalTracker($alertToDismiss);
        return new \WHMCS\Http\Message\JsonResponse(["status" => "success"]);
    }
}
