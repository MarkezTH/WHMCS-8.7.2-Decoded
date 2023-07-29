<?php

namespace WHMCS\Module\Gateway\Paypalcheckout;

class PaypalDispute
{
    const MERCHANDISE_OR_SERVICE_NOT_RECEIVED = "merchandise_or_service_not_received";
    const MERCHANDISE_OR_SERVICE_NOT_AS_DESCRIBED = "merchandise_or_service_not_as_described";
    const UNAUTHORISED = "unauthorised";
    const CREDIT_NOT_PROCESSED = "credit_not_processed";
    const DUPLICATE_TRANSACTION = "duplicate_transaction";
    const INCORRECT_AMOUNT = "incorrect_amount";
    const PAYMENT_BY_OTHER_MEANS = "payment_by_other_means";
    const CANCELED_RECURRING_BILLING = "canceled_recurring_billing";
    const OTHER = "other";
    const PROOF_OF_FULFILLMENT = "proof_of_fulfillment";
    const PROOF_OF_REFUND = "proof_of_refund";
    const PROOF_OF_SHIPMENT = "proof_of_shipment";

    public function getCustomData($evidenceKey)
    {
        $requiredString = \AdminLang::trans("global.required");
        $customDataDetails = ["notes" => ["name" => "notes", "type" => "textarea", "placeholder" => \AdminLang::trans("disputes.evidence.label.notes")], "document" => ["name" => "document", "type" => "file"], "carrierName" => ["name" => "carrier_name", "type" => "text", "placeholder" => \AdminLang::trans("disputes.evidence.label.carrierName") . " (" . $requiredString . ")"], "trackingNumber" => ["name" => "tracking_number", "type" => "text", "placeholder" => \AdminLang::trans("disputes.evidence.label.trackingNumber") . " (" . $requiredString . ")"], "refundIds" => ["name" => "refund_ids", "type" => "text", "placeholder" => \AdminLang::trans("disputes.evidence.label.refundId") . " (" . $requiredString . ")"]];
        $customDataMap = [self::PROOF_OF_FULFILLMENT => [$customDataDetails["trackingNumber"], $customDataDetails["carrierName"]], self::PROOF_OF_REFUND => [$customDataDetails["refundIds"]], self::PROOF_OF_SHIPMENT => [$customDataDetails["trackingNumber"], $customDataDetails["carrierName"]], self::OTHER => [$customDataDetails["document"], $customDataDetails["notes"]]];
        $customData = "";
        if (!empty($customDataMap[$evidenceKey])) {
            $customData = $customDataMap[$evidenceKey];
        }
        return json_encode($customData);
    }

    public function getVisibleTypes()
    {
        return [self::PROOF_OF_FULFILLMENT, self::PROOF_OF_REFUND, self::PROOF_OF_SHIPMENT, self::OTHER];
    }

    public function getFieldType($key)
    {
        $fieldTypes = [self::PROOF_OF_FULFILLMENT => "custom", self::PROOF_OF_REFUND => "custom", self::PROOF_OF_SHIPMENT => "custom", self::OTHER => "custom"];
        return $fieldTypes[$key];
    }

    public function getEvidenceTypes($key)
    {
        $map = [self::MERCHANDISE_OR_SERVICE_NOT_RECEIVED => [self::PROOF_OF_FULFILLMENT, self::PROOF_OF_REFUND, self::OTHER], self::MERCHANDISE_OR_SERVICE_NOT_AS_DESCRIBED => [self::OTHER, self::PROOF_OF_REFUND], self::UNAUTHORISED => [self::PROOF_OF_FULFILLMENT, self::PROOF_OF_REFUND, self::OTHER], self::CREDIT_NOT_PROCESSED => [self::PROOF_OF_REFUND, self::OTHER], self::DUPLICATE_TRANSACTION => [self::PROOF_OF_FULFILLMENT, self::PROOF_OF_REFUND, self::OTHER], self::INCORRECT_AMOUNT => [self::PROOF_OF_REFUND, self::OTHER], self::PAYMENT_BY_OTHER_MEANS => [self::PROOF_OF_REFUND, self::OTHER], self::CANCELED_RECURRING_BILLING => [self::PROOF_OF_REFUND, self::OTHER], self::OTHER => [self::PROOF_OF_SHIPMENT, self::PROOF_OF_REFUND, self::OTHER]];
        return $map[$key];
    }

    public function isUpdatable($response)
    {
        $claimLifecycleStages = ["CHARGEBACK", "PRE_ARBITRATION", "ARBITRATION"];
        return collect($response->links)->where("rel", "provide_evidence")->count() == 1 && in_array($response->dispute_life_cycle_stage, $claimLifecycleStages) && $response->status === "WAITING_FOR_SELLER_RESPONSE";
    }

    public function buildEvidencesObject($evidenceRequest)
    {
        $evidenceData = [];
        foreach ($evidenceRequest as $key => $value) {
            list($evidenceType, $evidenceName) = explode("-", $key);
            if (!$evidenceData[$evidenceType]) {
                $evidenceData[$evidenceType] = [];
            }
            $evidenceData[$evidenceType][] = ["name" => $evidenceName, "value" => $value];
        }
        $evidencesObject = ["evidences" => []];
        foreach ($evidenceData as $evidenceKey => $evidenceValues) {
            $trackingInfo = $refundIds = [];
            $evidenceObject = ["evidence_type" => strtoupper($evidenceKey)];
            foreach ($evidenceValues as $evidenceItem) {
                switch ($evidenceItem["name"]) {
                    case "carrier_name":
                    case "tracking_number":
                        $trackingInfo[$evidenceItem["name"]] = $evidenceItem["value"];
                        break;
                    case "refund_ids":
                        $refundIds[] = ["refund_id" => $evidenceItem["value"]];
                        break;
                    case "notes":
                        $evidenceObject["notes"] = $evidenceItem["value"];
                        break;
                    case "document":
                        $evidenceObject["documents"] = [["name" => $evidenceItem["value"]]];
                        break;
                }
            }
            if (!empty($trackingInfo)) {
                $evidenceObject["evidence_info"]["tracking_info"][] = $trackingInfo;
            }
            if (!empty($refundIds)) {
                $evidenceObject["evidence_info"]["refund_ids"] = $refundIds;
            }
            $evidencesObject["evidences"][] = $evidenceObject;
        }
        return $evidencesObject;
    }

    public function parseResponseEvidence($response)
    {
        $evidence = [];
        foreach ($response->evidences as $disputeEvidence) {
            $evidenceDetails = [["name" => "date", "string" => \WHMCS\Carbon::parse($disputeEvidence->date)->toAdminDateFormat()]];
            if ($disputeEvidence->action_info) {
                $evidenceDetails[] = ["name" => "action", "string" => $disputeEvidence->action_info->action];
            }
            if ($evidenceInfo = $disputeEvidence->evidence_info) {
                if ($trackingInfo = $evidenceInfo->tracking_info) {
                    $evidenceDetails[] = ["name" => "carrierName", "string" => $evidenceInfo->tracking_info[0]->carrier_name];
                    $evidenceDetails[] = ["name" => "trackingNumber", "string" => $evidenceInfo->tracking_info[0]->tracking_number];
                }
                if ($refundIds = $evidenceInfo->refund_ids) {
                    $evidenceDetails[] = ["name" => "refundIds", "string" => implode(",", collect($refundIds)->pluck("refund_id")->all())];
                }
            }
            if ($documents = $disputeEvidence->documents) {
                $evidenceDetails[] = ["name" => "documents", "string" => implode(",", collect($documents)->pluck("name")->all())];
            }
            if ($notes = $disputeEvidence->notes) {
                $evidenceDetails[] = ["name" => "notes", "string" => $notes];
            }
            $evidence[] = ["name" => strtolower($disputeEvidence->evidence_type), "value" => $this->generateEvidenceString($evidenceDetails)];
        }
        return $evidence;
    }

    protected function generateEvidenceString($evidenceDetails)
    {
        $evidenceString = "";
        foreach ($evidenceDetails as $evidenceDetail) {
            $evidenceName = \AdminLang::trans("disputes.evidence.label." . $evidenceDetail["name"]);
            $evidenceString .= "<p>\n    <strong>" . $evidenceName . "</strong><br>\n    " . $evidenceDetail["string"] . "\n</p>";
        }
        return $evidenceString;
    }
}
