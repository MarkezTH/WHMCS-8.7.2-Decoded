<?php

namespace WHMCS\Cron\Console\Input;

class HttpInput extends \Symfony\Component\Console\Input\ArgvInput
{
    use LegacyOptionsTrait;

    public function __construct($parameters)
    {
        $input = ["stub"];
        if ($this->isLegacyInput($parameters)) {
            $input = $this->convertLegacyOptions($this->getMutatedLegacyInput($parameters));
            array_unshift($input, "stub");
        } else {
            if (!empty($parameters["command"])) {
                $input[] = $parameters["command"];
            } else {
                $input[] = "all";
            }
            if (!empty($parameters["options"])) {
                foreach (explode(",", $parameters["options"]) as $option) {
                    if (strpos($option, "-") === 0) {
                        $input[] = $option;
                    } else {
                        $input[] = "--" . $option;
                    }
                }
            }
        }
        $input = $this->convertRenamedOptions($input);
        $controllingOptions = $this->getControllingOptions($parameters);
        $input = array_merge($input, $controllingOptions);
        parent::__construct($input);
    }

    public function isLegacyInput($input = [])
    {
        $isLegacyInputOptions = false;
        if (array_key_exists("skip_report", $input)) {
            unset($input["skip_report"]);
        }
        if (array_key_exists("do_report", $input)) {
            unset($input["do_report"]);
        }
        $query = http_build_query($input);
        if (strpos($query, "do_") !== false || strpos($query, "skip_") !== false) {
            $isLegacyInputOptions = true;
        }
        if (array_key_exists("escalations", $input)) {
            $isLegacyInputOptions = true;
        }
        return $isLegacyInputOptions;
    }

    public function getControllingOptions($parameters)
    {
        $options = [];
        foreach ($parameters as $key => $value) {
            $key = (string) $key;
            if ($key == "debug") {
                $options[] = "-vvv";
            }
            if ($key == "skip_report") {
                $options[] = "--email-report=0";
            } else {
                if ($key == "do_report") {
                    $options[] = "--email-report=1";
                }
            }
        }
        return $options;
    }

    public function getMutatedLegacyInput($input = [])
    {
        $isDoCommand = false;
        $options = [];
        if (array_key_exists("escalations", $input)) {
            unset($input["escalations"]);
            $input["do_escalations"] = "1";
        }
        foreach ($input as $key => $value) {
            if (strpos($key, "do") === 0) {
                if ($value) {
                    if (strpos($key, "_") !== false) {
                        $isDoCommand = true;
                        $options[] = "--" . explode("_", $key, 2)[1];
                    }
                }
            }
        }
        if (!$isDoCommand) {
            foreach ($input as $key => $value) {
                if (strpos($key, "skip") === 0) {
                    if ($value) {
                        if (strpos($key, "_") !== false) {
                            $options[] = "--" . explode("_", $key, 2)[1];
                        }
                    }
                }
            }
        }
        if (empty($options)) {
            array_unshift($options, "all");
        } else {
            if ($isDoCommand) {
                array_unshift($options, "do");
            } else {
                array_unshift($options, "skip");
            }
        }
        return $options;
    }
}
