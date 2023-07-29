<?php

namespace WHMCS\Table\Contracts;

interface TableInterface
{
    public function list($JsonResponse, $request);
}
