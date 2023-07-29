<?php

namespace WHMCS\Payment\Adapter;

interface AdapterInterface
{
    public function getConfigurationParameters();

    public function setConfigurationParameters($configuration);

    public function getSolutionType();

    public function setSolutionType($type);

    public function isLinkCapable();

    public function isCaptureCapable();

    public function isRefundCapable();

    public function isRemotePaymentDetailsStorageCapable();

    public function getHtmlLink($params);

    public function captureTransaction($params);

    public function refundTransaction($params);

    public function storePaymentDetailsRemotely($params);
}
