<?php

namespace WHMCS\Cron;

class Decorator
{
    protected $item = NULL;

    public function __construct(DecoratorItemInterface $item)
    {
        $this->item = $item;
    }

    public function render($data, $isDisabled)
    {
        if ($this->item->isBooleanStatusItem()) {
            return $this->renderBooleanItem($data, $isDisabled);
        }
        return $this->renderStatisticalItem($data, $isDisabled);
    }

    public function renderStatisticalItem($data, $isDisabled)
    {
        $date = \App::getFromRequest("date");
        if (!$date) {
            $date = \WHMCS\Carbon::today()->toDateString();
        }
        $date = \WHMCS\Carbon::createFromFormat("Y-m-d", $date)->toAdminDateFormat();
        $detailUrl = $this->item->getDetailUrl();
        $modalTitle = "";
        if (is_array($this->item->getSuccessCountIdentifier())) {
            $primarySuccessCount = 0;
            foreach ($this->item->getSuccessCountIdentifier() as $identifier) {
                $primarySuccessCount += (int) $data[$identifier];
            }
        } else {
            $primarySuccessCount = (int) $data[$this->item->getSuccessCountIdentifier()];
        }
        if ($detailUrl) {
            $modalTitle = $this->item->getName() . " - " . $date;
            $primarySuccessCount = "<a href=\"" . $detailUrl . "\"\n   class=\"open-modal\"\n   data-modal-size=\"modal-lg\"\n   data-modal-title=\"" . $modalTitle . "\"\n></a>" . $primarySuccessCount;
        }
        $successKeyword = $this->item->getSuccessKeyword();
        if ($this->item->getFailureCountIdentifier()) {
            $failedCountIdentifier = (int) $data[$this->item->getFailureCountIdentifier()];
            if ($detailUrl) {
                $failedLink = "<a href=\"" . $detailUrl . "/tab2\"\n   class=\"failed open-modal\"\n   data-modal-size=\"modal-lg\"\n   data-modal-title=\"" . $modalTitle . "\"\n>\n    " . $failedCountIdentifier . " " . $this->item->getFailureKeyword() . "\n</a>";
            } else {
                $failedLink = $failedCountIdentifier . " " . $this->item->getFailureKeyword();
            }
        } else {
            $failedLink = "";
        }
        $widgetClass = "";
        if ($detailUrl) {
            $widgetClass = " automation-clickable-widget";
        }
        $disabled = "";
        if ($isDisabled && $primarySuccessCount == 0) {
            $primarySuccessCount = "-";
            $successKeyword = "";
            $failedLink = "";
            $widgetClass = "";
            $disabled = "<small>Disabled</small>";
        }
        $langKey = $this->formatLanguageKey($this->item->getName());
        $name = \AdminLang::trans($langKey);
        return "<div class=\"widget" . $widgetClass . "\">\n    <div class=\"info-container\">\n        <div class=\"pull-right\">\n            <i class=\"" . $this->item->getIcon() . " fa-2x\"></i>\n        </div>\n        <p class=\"intro\">\n            " . $name . "\n        </p>\n        <h3 class=\"title\">\n            <span class=\"figure\">\n                " . $primarySuccessCount . "\n            </span>\n            <span class=\"note\">\n                " . $successKeyword . "\n            </span>\n        </h3>\n        " . $failedLink . $disabled . "\n    </div>\n</div>";
    }

    public function renderBooleanItem($data, $isDisabled)
    {
        $primarySuccessCount = (bool) $data[$this->item->getSuccessCountIdentifier()];
        $name = $this->item->getName();
        $langKey = $this->formatLanguageKey($name);
        $note = "";
        if ($name == "WHMCS Updates" && is_array($data) && array_key_exists("update.available", $data) && $data["update.available"] == 1) {
            $icon = "fas fa-exclamation";
            $note = "<a href=\"update.php\">An update is available.</a>";
            if (array_key_exists("update.version", $data) && $data["update.version"]) {
                $note = "<a href=\"update.php\">Version " . $data["update.version"] . " is available! Click here to update!</a>";
            }
        } else {
            if ($name == "WHMCS Updates") {
                $note = "WHMCS is up to date.";
                $primarySuccessCount = 1;
            }
        }
        if (empty($icon)) {
            $icon = $primarySuccessCount ? "fas fa-check" : "fas fa-times";
        }
        if ($isDisabled && !$primarySuccessCount) {
            $note = "Disabled";
        }
        if (!$note && !$primarySuccessCount) {
            $note = "Task has not completed.";
        } else {
            if (!$note && $primarySuccessCount) {
                $note = "Task completed successfully.";
            }
        }
        $name = \AdminLang::trans($langKey);
        return "<div class=\"widget\">\n    <div class=\"info-container\">\n        <div class=\"pull-right\">\n            <i class=\"" . $this->item->getIcon() . " fa-2x\"></i>\n        </div>\n        <p class=\"intro\">\n            " . $name . "\n        </p>\n        <h3 class=\"title\">\n            <span class=\"note\" style=\"padding-left: 0;\">\n                <i class=\"' . " . $icon . " . '\"></i> " . $note . "\n            </span>\n        </h3>\n    </div>\n</div>";
    }

    protected function formatLanguageKey($key)
    {
        $replaceArray = ["find" => ["&", " "], "replace" => ["And", ""]];
        $key = lcfirst(ucwords(strtolower($key)));
        $key = str_replace($replaceArray["find"], $replaceArray["replace"], $key);
        return "utilities.automationStatusDetail.task." . $key;
    }
}
