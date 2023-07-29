<?php

namespace WHMCS\Billing\Payment;

interface DisputeInterface
{
    public static function factory($DisputeInterface, $id, $amount, $currencyCode, $transactionId, \WHMCS\Carbon $createdDate, \WHMCS\Carbon $respondBy, $reason, $status);

    public function setEvidence($DisputeInterface, $evidence);

    public function setEvidenceType($DisputeInterface, $evidenceKey, $evidenceType);

    public function setGateway($DisputeInterface, $gateway);

    public function setIsClosable($DisputeInterface, $closable);

    public function setIsSubmittable($DisputeInterface, $submittable);

    public function setIsUpdatable($DisputeInterface, $updatable);

    public function setTransactionId($DisputeInterface, $transactionId);
}
