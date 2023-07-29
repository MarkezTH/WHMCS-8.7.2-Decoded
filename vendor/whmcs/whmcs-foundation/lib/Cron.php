<?php

namespace WHMCS;

class Cron
{
    public static function getCronsPath($fileName)
    {
        $whmcs = \DI::make("app");
        $cronDirectory = $whmcs->getCronDirectory();
        if ($cronDirectory !== ROOTDIR . DIRECTORY_SEPARATOR . "crons") {
            throw new Exception\Fatal("Crons folder not in WHMCS root.");
        }
        $path = realpath($cronDirectory . DIRECTORY_SEPARATOR . $fileName);
        if (!$path) {
            throw new Exception("Unable to locate WHMCS crons folder.");
        }
        return $path;
    }

    public static function getCronPathErrorMessage()
    {
        return "Unable to communicate with the Custom Crons Directory.<br />\nPlease verify the path configured within the configuration.php file.<br />\nFor more information, please see <a href=\"https://docs.whmcs.com/Custom_Crons_Directory\">https://docs.whmcs.com/Custom_Crons_Directory</a>\n";
    }

    public static function getCronRootDirErrorMessage()
    {
        return "This proxy file is only valid when the crons directory is in the default location.<br />\nAs you have customised your crons directory location, you must update your cron commands to use the new path.<br />\nFor more information, please see <a href=\"https://docs.whmcs.com/Custom_Crons_Directory\">https://docs.whmcs.com/Custom_Crons_Directory</a>\n";
    }

    public static function formatOutput($output)
    {
        if (Environment\Php::isCli()) {
            $output = strip_tags(str_replace(["<br>", "<br />", "<br/>", "<hr>"], ["\n", "\n", "\n", "\n---\n"], $output));
        }
        return $output;
    }

    public static function getLegacyCronMessage()
    {
        $message = "<div style=\"margin:0;padding:15px;border-color:#aa6708;border:1px solid #eee;border-left-width:5px;border-radius:3px;\">\n    <h4 style=\"margin:0 0 10px 0;color:#aa6708;font-size:1.2em;font-weight:500;line-height:1.1;\">\n        Cron Task Configuration\n    </h4>\n    <p style=\"margin:0;line-height:1.4;color:#333;\">\n        This cron file was invoked from a legacy filepath.<br />\n        WHMCS currently provides backwards compatibility for legacy paths so that your scheduled cron tasks will continue to invoke a valid WHMCS cron file.<br />\n        It is recommended however that you update the cron task command on your server at your earliest convenience.<br />\n        For more information, please refer to <a href=\"https://docs.whmcs.com/Cron_Tasks#Legacy_Cron_File_Locations\">\n        https://docs.whmcs.com/Cron_Tasks#Legacy_Cron_File_Locations</a>\n    </p>\n</div>";
        return $message;
    }
}
