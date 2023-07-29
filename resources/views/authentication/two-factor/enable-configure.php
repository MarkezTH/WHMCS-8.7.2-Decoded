<?php

echo "<form action=\"";
echo routePath(($isAdmin ? "admin-" : "") . "account-security-two-factor-enable-verify");
echo "\" onsubmit=\"dialogSubmit();return false\">\n    ";
echo generate_token("form");
echo "    <input type=\"hidden\" name=\"step\" value=\"verify\" />\n    <input type=\"hidden\" name=\"module\" value=\"";
echo $module;
echo "\" />\n    ";
echo $twoFactorConfigurationOutput;
echo "</form>\n";
