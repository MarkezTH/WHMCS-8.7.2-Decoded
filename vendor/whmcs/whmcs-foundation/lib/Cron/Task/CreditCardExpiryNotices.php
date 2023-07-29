<?php

namespace WHMCS\Cron\Task;

class CreditCardExpiryNotices extends \WHMCS\Scheduling\Task\AbstractTask
{
    protected $defaultPriority = 1650;
    protected $defaultFrequency = 43200;
    protected $defaultDescription = "Sending Credit Card Expiry Reminders";
    protected $defaultName = "Credit Card Expiry Notices";
    protected $systemName = "CreditCardExpiryNotices";
    protected $outputs = ["notices" => ["defaultValue" => 0, "identifier" => "notices", "name" => "Credit Card Expiry Notices"]];
    protected $icon = "fas fa-credit-card";
    protected $isBooleanStatus = false;
    protected $successCountIdentifier = "notices";
    protected $successKeyword = "Sent";

    public function monthlyDayOfExecution()
    {
        $dayForNotices = (int) \WHMCS\Config\Setting::getValue("CCDaySendExpiryNotices");
        $daysInThisMonth = \WHMCS\Carbon::now()->daysInMonth;
        if ($daysInThisMonth < $dayForNotices) {
            $dayForNotices = $daysInThisMonth;
        }
        return \WHMCS\Carbon::now()->startOfDay()->day($dayForNotices);
    }

    public function anticipatedNextRun(\WHMCS\Carbon $date = NULL)
    {
        $correctDayDate = $this->anticipatedNextMonthlyRun((int) \WHMCS\Config\Setting::getValue("CCDaySendExpiryNotices"), $date);
        if ($date) {
            $correctDayDate->hour($date->format("H"))->minute($date->format("i"));
        }
        return $correctDayDate;
    }

    public function __invoke()
    {
        if (!\WHMCS\Carbon::now()->isSameDay($this->monthlyDayOfExecution())) {
            return $this;
        }
        $expiryEmailCount = 0;
        $expiringCardPaymethods = \WHMCS\Payment\PayMethod\Model::getCreditCardsWhere(function (\Illuminate\Database\Eloquent\Builder $query) {
            $today = \WHMCS\Carbon::today();
            $expiryYear = $today->year;
            $expiryMonth = $today->month;
            return $query->whereYear("expiry_date", "=", $expiryYear)->whereMonth("expiry_date", "=", $expiryMonth);
        });
        $expiringCards = $expiringCardPaymethods->map(function (\WHMCS\Payment\PayMethod\Model $payMethod) {
            return $payMethod->payment;
        });
        foreach ($expiringCards as $expiringCard) {
            if ($expiringCard->client->status === "Active") {
                sendMessage("Credit Card Expiring Soon", $expiringCard->client->id, ["card_id" => $expiringCard->id, "card_type" => $expiringCard->card_type, "card_expiry" => $expiringCard->expiry_date->toCreditCard(), "card_last_four" => $expiringCard->last_four, "card_description" => $expiringCard->payMethod->description]);
                $expiryEmailCount++;
            }
        }
        logActivity("Cron Job: Sent " . $expiryEmailCount . " Credit Card Expiry Notices");
        $this->output("notices")->write($expiryEmailCount);
        return $this;
    }
}
