<?php

namespace WHMCS\Module\Server\Cpanel\Cpanel\WordPress;

class WordPressManager
{
    protected function formatCommandData($cliParams)
    {
        $paramIndex = 1;
        $commandData = [];
        foreach ($cliParams as $cliParam => $cliValue) {
            $commandData["command-param-" . $paramIndex++] = "-" . trim($cliParam, "-");
            $commandData["command-param-" . $paramIndex++] = $cliValue;
        }
        return $commandData;
    }

    public function callWpToolkitCli($command, $moduleParams, $cliParams)
    {
        $apiData = array_merge(["api.version" => "1", "cpanel.module" => "WpToolkitCli", "cpanel.function" => "execute_command", "cpanel.user" => $moduleParams["username"], "command" => $command], $this->formatCommandData($cliParams));
        $response = cpanel_jsonRequest($moduleParams, "json-api/uapi_cpanel", $apiData);
        if (empty($response["data"]["uapi"]["status"])) {
            throw new \WHMCS\Exception\Module\NotServicable(trim(implode(". ", $response["data"]["uapi"]["errors"])));
        }
        return $response["data"]["uapi"]["data"];
    }
}
