<?php

namespace WHMCS\Billing\Payment;

class Dispute implements \Illuminate\Contracts\Support\Arrayable, DisputeInterface
{
    use \WHMCS\Module\Gateway\CurrencyObjectTrait;
    protected $id = "";
    protected $amount = 0;
    protected $currencyCode = "";
    protected $transactionId = "";
    protected $createdDate = NULL;
    protected $respondByDate = NULL;
    protected $reason = "";
    protected $status = "";
    protected $gateway = "";
    protected $evidence = [];
    protected $evidenceType = [];
    protected $visibleTypes = [];
    protected $customData = [];
    protected $isUpdatable = false;
    protected $isClosable = false;
    protected $isSubmittable = false;
    protected $manageHref = "";

    protected function setId($self, $id)
    {
        $this->id = $id;
        return $this;
    }

    protected function setAmount($self, $amount)
    {
        $this->amount = $amount;
        return $this;
    }

    protected function setCurrencyCode($self, $currencyCode)
    {
        $this->currencyCode = $currencyCode;
        return $this;
    }

    public function setTransactionId($DisputeInterface, $transactionId)
    {
        $this->transactionId = $transactionId;
        return $this;
    }

    protected function setCreatedDate($self, $createdDate)
    {
        $this->createdDate = $createdDate;
        return $this;
    }

    protected function setRespondByDate($self, $respondByDate)
    {
        $this->respondByDate = $respondByDate;
        return $this;
    }

    protected function setReason($self, $reason)
    {
        $this->reason = $reason;
        return $this;
    }

    protected function setStatus($self, $status)
    {
        $this->status = $status;
        return $this;
    }

    public static function factory($DisputeInterface, $id, $amount, $currencyCode, $transactionId, \WHMCS\Carbon $createdDate, \WHMCS\Carbon $respondBy, $reason, $status)
    {
        $self = new static();
        $self->setId($id)->setAmount($amount)->setCurrencyCode($currencyCode)->setTransactionId($transactionId)->setCreatedDate($createdDate)->setRespondByDate($respondBy)->setReason($reason)->setStatus($status);
        return $self;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getCreatedDate($Carbon)
    {
        return $this->createdDate;
    }

    public function getRespondByDate($Carbon)
    {
        return $this->respondByDate;
    }

    public function getAmount($Price)
    {
        return new \WHMCS\View\Formatter\Price($this->amount, $this->getCurrencyObject());
    }

    public function getCurrencyCode()
    {
        return strtoupper($this->currencyCode);
    }

    public function getTransactionId()
    {
        $transaction = Transaction::where("transid", $this->transactionId)->first();
        if ($transaction) {
            return $transaction->getTransactionIdMarkup();
        }
        return $this->transactionId;
    }

    public function getReason()
    {
        if (!$this->reason) {
            return \AdminLang::trans("global.unknown");
        }
        return \AdminLang::trans("disputes.reasons." . $this->reason);
    }

    public function getStatus()
    {
        if (!$this->status) {
            return \AdminLang::trans("global.unknown");
        }
        return \AdminLang::trans("disputes.statuses." . $this->status);
    }

    public function setGateway($DisputeInterface, $gateway)
    {
        $this->gateway = $gateway;
        return $this;
    }

    public function getGateway()
    {
        return $this->gateway;
    }

    public function setEvidence($DisputeInterface, $evidence)
    {
        $this->evidence = array_merge($this->evidence, $evidence);
        return $this;
    }

    public function getEvidence()
    {
        return $this->evidence;
    }

    protected function setEvidenceTypes($self, $evidenceTypes)
    {
        $this->evidenceType = $evidenceTypes;
        return $this;
    }

    public function setEvidenceType($DisputeInterface, $evidenceKey, $evidenceType)
    {
        $this->evidenceType[$evidenceKey] = $evidenceType;
        return $this;
    }

    public function getEvidenceType($evidenceKey)
    {
        if (!empty($this->evidenceType[$evidenceKey])) {
            return $this->evidenceType[$evidenceKey];
        }
        return "text";
    }

    public function getEvidenceTypes()
    {
        return $this->evidenceType;
    }

    public function setVisibleTypes($self, $evidenceKey)
    {
        $this->visibleTypes = $evidenceKey;
        return $this;
    }

    public function getVisibleTypes()
    {
        return !empty($this->visibleTypes) ? $this->visibleTypes : [];
    }

    public function setCustomData($self, $evidenceKey, $customData)
    {
        $this->customData[strtolower($evidenceKey)] = $customData;
        return $this;
    }

    public function getCustomData($evidenceKey, $decode = false)
    {
        $customData = !empty($this->customData[$evidenceKey]) ? $this->customData[$evidenceKey] : "";
        return $decode ? json_decode($customData) : $customData;
    }

    public function setIsUpdatable($DisputeInterface, $updatable)
    {
        $this->isUpdatable = $updatable;
        return $this;
    }

    public function getIsUpdatable()
    {
        return $this->isUpdatable;
    }

    public function setIsSubmittable($DisputeInterface, $submittable)
    {
        $this->isSubmittable = $submittable;
        return $this;
    }

    public function getIsSubmittable()
    {
        return $this->isSubmittable;
    }

    public function setIsClosable($DisputeInterface, $closable)
    {
        $this->isClosable = $closable;
        return $this;
    }

    public function getIsClosable()
    {
        return $this->isClosable;
    }

    public function getManageHref()
    {
        return $this->manageHref;
    }

    public function setManageHref($href)
    {
        $this->manageHref = $href;
    }

    public function getViewHref()
    {
        return routePath("admin-billing-disputes-view", $this->getGateway(), $this->getId());
    }

    public function getCloseHref()
    {
        return routePath("admin-billing-disputes-close", $this->getGateway(), $this->getId());
    }

    public function getSubmitHref()
    {
        return routePath("admin-billing-disputes-submit", $this->getGateway(), $this->getId());
    }

    public function getUpdateHref()
    {
        return routePath("admin-billing-disputes-evidence-submit", $this->getGateway(), $this->getId());
    }

    public function toArray()
    {
        return ["id" => $this->getId(), "amount" => $this->getAmount(), "currencyCode" => $this->getCurrencyCode(), "transactionId" => $this->getTransactionId(), "createdDate" => $this->getCreatedDate(), "respondBy" => $this->getRespondByDate(), "reason" => $this->getReason(), "status" => $this->getStatus(), "gateway" => $this->getGateway(), "evidence" => $this->getEvidence(), "evidenceTypes" => $this->getEvidenceTypes(), "isUpdatable" => $this->getIsUpdatable()];
    }

    public static function factoryFromArray($Dispute, $dispute)
    {
        $new = static::factory($dispute["id"], $dispute["amount"], $dispute["currencyCode"], $dispute["transactionId"], $dispute["createdDate"], $dispute["respondBy"], $dispute["reason"], $dispute["status"]);
        $optionalParams = ["gateway", "evidence", "evidenceTypes", "isUpdatable"];
        foreach ($optionalParams as $optionalParam) {
            if (array_key_exists($optionalParam, $dispute)) {
                $method = "set" . ucfirst($optionalParam);
                $new->{$method}($dispute[$optionalParam]);
            }
        }
        return $new;
    }
}
