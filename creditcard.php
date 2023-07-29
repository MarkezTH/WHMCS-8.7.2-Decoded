<?php

require "init.php";
$invoiceId = App::getFromRequest("invoiceid");
if (!$invoiceId) {
    App::redirect("clientarea.php");
}
App::redirectToRoutePath("invoice-pay", [$invoiceId]);
