<?php

namespace WHMCS\Updater\Version;

class Version360 extends IncrementalVersion
{
    protected function runUpdateCode()
    {
        $query = "SELECT COUNT(*) FROM tblpaymentgateways WHERE gateway='paypal'";
        $result = mysql_query($query);
        $data = mysql_fetch_array($result);
        $paypalenabled = $data[0];
        if ($paypalenabled) {
            $query = "INSERT INTO `tblpaymentgateways` (`id`, `gateway`, `type`, `setting`, `value`, `name`, `size`, `notes`, `description`, `order`) VALUES('', 'paypal', 'yesno', 'forceonetime', '', 'Force One Time Payments', 0, '', 'Check to never show the subscription payment button', 0)";
            $result = mysql_query($query);
        }
        return $this;
    }
}
