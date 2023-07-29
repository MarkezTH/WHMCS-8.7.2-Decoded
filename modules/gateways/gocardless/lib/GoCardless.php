<?php

namespace WHMCS\Module\Gateway\GoCardless;

class GoCardless
{
    const SUPPORTED_CURRENCIES = ["AUD", "CAD", "DKK", "EUR", "GBP", "NZD", "SEK", "USD"];
    const SCHEMES = ["AUD" => "becs", "CAD" => "pad", "DKK" => "betalingsservice", "EUR" => "sepa", "GBP" => "bacs", "NZD" => "becs_nz", "SEK" => "autogiro", "USD" => "ach"];
    const SCHEME_NAMES = ["becs" => "BECS", "pad" => "PAD", "betalingsservice" => "Betalingsservice", "sepa" => "SEPA", "bacs" => "BACS", "becs_nz" => "BECS NZ", "autogiro" => "Autogiro", "ach" => "ACH"];
}
