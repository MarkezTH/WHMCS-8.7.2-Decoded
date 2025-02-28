<?php

namespace WHMCS\Updater\Version;

class Version5313release1 extends IncrementalVersion
{
    protected $runUpdateCodeBeforeDatabase = true;

    protected function runUpdateCode()
    {
        $messageHash = "6dd1a70917ebbed0ed5681f1c9fe7e5a";
        $query = "SELECT md5(`message`) as message FROM tblemailtemplates WHERE `name` = 'Expired Domain Notice' AND `language` = '';";
        $result = mysql_query($query);
        $data = mysql_fetch_assoc($result);
        if ($data["message"] == $messageHash) {
            $message = "<p>Dear {\$client_name},</p>" . PHP_EOL . "<p>The domain name listed below expired {\$domain_days_after_expiry} days ago.</p>" . PHP_EOL . "<p>{\$domain_name}</p>" . PHP_EOL . "<p>To ensure that the domain isn't registered by someone else, you should renew it now." . " To renew the domain, please visit the following page and follow the steps shown:" . " <a title=\"{\$whmcs_url}/cart.php?gid=renewals\"" . " href=\"{\$whmcs_url}/cart.php?gid=renewals\">{\$whmcs_url}/cart.php?gid=renewals</a>" . "</p>" . PHP_EOL . "<p>Due to the domain expiring, the domain will not be accessible so" . " any web site or email services associated with it will stop working. You may be able to renew it" . " for up to 30 days after the renewal date.</p>" . PHP_EOL . "<p>{\$signature}</p>";
            $query = "UPDATE tblemailtemplates SET message = '" . $message . "'" . " WHERE `name` = 'Expired Domain Notice' AND language = '';";
            mysql_query($query);
        }
    }
}
