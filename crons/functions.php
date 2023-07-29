<?php

function getWhmcsInitPath()
{
    $whmcspath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
    if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . "config.php")) {
        require __DIR__ . DIRECTORY_SEPARATOR . "config.php";
    }
    $path = realpath($whmcspath . DIRECTORY_SEPARATOR . "init.php");
    if (!$path) {
        throw new Exception("Unable to determine WHMCS init.php path.");
    }
    return $path;
}

function getInitPathErrorMessage()
{
    return "Unable to communicate with the WHMCS installation.<br />\nPlease verify the path configured within the crons directory config.php file.<br />\nFor more information, please see <a href=\"https://docs.whmcs.com/Custom_Crons_Directory\">https://docs.whmcs.com/Custom_Crons_Directory</a>\n";
}

function cronsFormatOutput($output)
{
    if (cronsIsCli()) {
        $output = strip_tags(str_replace(["<br>", "<br />", "<br/>", "<hr>"], ["\n", "\n", "\n", "\n---\n"], $output));
    }
    return $output;
}

function cronsIsCli()
{
    php_sapi_name();
    switch (php_sapi_name()) {
        case "cli":
        case "cli-server":
            return true;
            break;
        default:
            if (!isset($_SERVER["SERVER_NAME"]) && !isset($_SERVER["HTTP_HOST"])) {
                return true;
            }
            return false;
    }
}

function logCronMemoryLimit()
{
    $memoryLimit = (string) round(WHMCS\Environment\Php::getPhpMemoryLimitInBytes() / 1024 / 1024);
    WHMCS\TransientData::getInstance()->store(WHMCS\Cron\Status::LAST_MEMORY_LIMIT_TRANSIENT_KEY, $memoryLimit, 604800);
}
