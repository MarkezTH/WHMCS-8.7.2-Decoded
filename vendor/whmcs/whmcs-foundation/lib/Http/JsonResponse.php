<?php

namespace WHMCS\Http;

class JsonResponse extends \Symfony\Component\HttpFoundation\JsonResponse
{
    use DataTrait;
    use PriceDataTrait;

    public function setData($data = [])
    {
        $data = $this->mutatePriceToFull($data);
        $this->setRawData($data);
        parent::setData($data);
        return $this;
    }
}
