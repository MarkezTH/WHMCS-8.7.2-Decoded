<?php

namespace WHMCS\Module\Gateway\AcceptJs;

class AcceptJsAuthenticateTestController extends \net\authorize\api\controller\AuthenticateTestController
{
    public function __construct(\net\authorize\api\contract\v1\AnetApiRequestType $request)
    {
        parent::__construct($request);
        $this->httpClient = new AcceptJsHttpClient();
    }
}
