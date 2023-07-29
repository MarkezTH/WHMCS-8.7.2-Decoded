<?php

require "../init.php";
if (!function_exists("imagecreatefrompng")) {
    exit("You need to recompile with the GD library included in PHP for this feature to be able to function");
}
$rand = WHMCS\Session::get("captchaValue");
if ($rand) {
    $storedRandData = WHMCS\Session::get($rand);
    $rand = "";
    if ($storedRandData && is_array($storedRandData) && !empty($storedRandData["expiry"]) && !empty($storedRandData["value"]) && WHMCS\Carbon::now()->lessThan(WHMCS\Carbon::parse($storedRandData["expiry"]))) {
        $rand = $storedRandData["value"];
    }
}
if (!$rand) {
    $rand = generateNewCaptchaCode();
}
$image = imagecreatefrompng("../assets/img/verify.png");
$imageWidth = imagesx($image);
$imageHeight = imagesy($image);
for ($i = 0; $i < 5; $i++) {
    $lineLength = random_int(8, 12);
    $lineColor = imagecolorallocate($image, random_int(240, 255), random_int(240, 255), random_int(240, 255));
    $lineAngle = random_int(0, 90) * pi() / 90;
    $lineXOrigin = random_int($lineLength, $imageWidth - $lineLength);
    $lineYOrigin = random_int($lineLength, $imageHeight - $lineLength);
    $lineXOffset = $lineLength * sin($lineAngle);
    $lineYOffset = $lineLength * cos($lineAngle);
    imageline($image, $lineXOrigin, $lineYOrigin, $lineXOrigin + $lineXOffset, $lineYOrigin + $lineYOffset, $lineColor);
}
$textColor = imagecolorallocate($image, random_int(0, 10), random_int(0, 10), random_int(0, 10));
imagestring($image, 5, 24, 4, $rand, $textColor);
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Content-type: image/png");
imagepng($image);
imagedestroy($image);
