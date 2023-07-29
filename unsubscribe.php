<?php

require "init.php";
$redirectUrl = routePath("subscription-manage");
if (strpos($redirectUrl, "?") === false) {
    $redirectUrl .= "?";
} else {
    $redirectUrl .= "&";
}
$redirectUrl .= "action=optout&email=" . App::getFromRequest("email") . "&key=" . App::getFromRequest("key");
header("Location: " . $redirectUrl);
