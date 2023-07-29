<?php

namespace WHMCS\Mail\Job;

class AdminNotification implements \WHMCS\Scheduling\Contract\JobInterface
{
    use \WHMCS\Scheduling\Jobs\JobTrait;
    const JOB_NAME_GENERIC = "sendAdminNotification.deferred";

    public function send()
    {
        $args = func_get_args();
        if (!function_exists("sendAdminNotificationNow")) {
            include_once ROOTDIR . "/includes/functions.php";
        }
        call_user_func_array("sendAdminNotificationNow", $args);
    }
}
