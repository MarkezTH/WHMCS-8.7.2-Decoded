<?php

class Plesk_ExtensionCommand
{
    protected function callExtension($extension, $command, $moduleParams, $cliParams)
    {
        $params = array_merge($moduleParams, ["extension" => $extension, "command" => $command, "commandParams" => $cliParams]);
        $responseContainer = Plesk_Registry::getInstance()->manager->callExtension($params);
        return (array) ($responseContainer->xpath("//" . $extension . "/" . $command)[0] ?? []);
    }

    public function callWpToolkitCli($command, $moduleParams, $cliParams)
    {
        return $this->callExtension("wp-toolkit", $command, $moduleParams, $cliParams);
    }
}
