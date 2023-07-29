<?php

namespace WHMCS\Domains;

class Idna
{
    private static $forcePolyfill = false;

    private function __construct()
    {
        if (!defined("INTL_IDNA_VARIANT_UTS46")) {
            $this->defineIntlConstants()->forcePolyfill();
        }
    }

    public static function toPunycode($domain)
    {
        return (new self())->encodeInput($domain);
    }

    public static function fromPunycode($domain)
    {
        return (new self())->decodeInput($domain);
    }

    protected function decodeInput($input)
    {
        if ($this->isPolyfillForced()) {
            $domain = \Symfony\Polyfill\Intl\Idn\Idn::idn_to_utf8($input, IDNA_USE_STD3_RULES, INTL_IDNA_VARIANT_UTS46, $log);
        } else {
            $domain = idn_to_utf8($input, IDNA_USE_STD3_RULES, INTL_IDNA_VARIANT_UTS46, $log);
        }
        $this->handleError($log["errors"]);
        return (string) $domain;
    }

    protected function encodeInput($input)
    {
        if ($this->isPolyfillForced()) {
            $domain = \Symfony\Polyfill\Intl\Idn\Idn::idn_to_ascii($input, IDNA_USE_STD3_RULES, INTL_IDNA_VARIANT_UTS46, $log);
        } else {
            $domain = idn_to_ascii($input, IDNA_USE_STD3_RULES, INTL_IDNA_VARIANT_UTS46, $log);
        }
        $this->handleError($log["errors"]);
        return (string) $domain;
    }

    protected function handleError($errorCode)
    {
        if (!$errorCode) {
            return NULL;
        }
        $trans = \App::isAdminAreaRequest() ? \AdminLang::self() : \Lang::self();
        switch ($errorCode) {
            case IDNA_ERROR_EMPTY_LABEL:
                $error = $trans->trans("idna.emptyLabel");
                break;
            case IDNA_ERROR_LABEL_TOO_LONG:
                $error = $trans->trans("idna.labelTooLong");
                break;
            case IDNA_ERROR_DOMAIN_NAME_TOO_LONG:
                $error = $trans->trans("idna.domainTooLong");
                break;
            case IDNA_ERROR_LEADING_HYPHEN:
            case IDNA_ERROR_TRAILING_HYPHEN:
            case IDNA_ERROR_HYPHEN_3_4:
            case IDNA_ERROR_LEADING_COMBINING_MARK:
            case IDNA_ERROR_DISALLOWED:
            case IDNA_ERROR_PUNYCODE:
            case IDNA_ERROR_LABEL_HAS_DOT:
            case IDNA_ERROR_INVALID_ACE_LABEL:
            case IDNA_ERROR_BIDI:
            case IDNA_ERROR_CONTEXTJ:
                $error = $trans->trans("idna.invalidDomain");
                break;
            default:
                $error = $trans->trans("idna.unknownError");
                throw new \WHMCS\Exception\InvalidDomain($error);
        }
    }

    protected function isPolyfillForced()
    {
        return self::$forcePolyfill;
    }

    protected function forcePolyfill()
    {
        self::$forcePolyfill = true;
        return $this;
    }

    protected function defineIntlConstants()
    {
        if (!defined("U_IDNA_PROHIBITED_ERROR")) {
            define("U_IDNA_PROHIBITED_ERROR", 66560);
        }
        if (!defined("U_IDNA_ERROR_START")) {
            define("U_IDNA_ERROR_START", 66560);
        }
        if (!defined("U_IDNA_UNASSIGNED_ERROR")) {
            define("U_IDNA_UNASSIGNED_ERROR", 66561);
        }
        if (!defined("U_IDNA_CHECK_BIDI_ERROR")) {
            define("U_IDNA_CHECK_BIDI_ERROR", 66562);
        }
        if (!defined("U_IDNA_STD3_ASCII_RULES_ERROR")) {
            define("U_IDNA_STD3_ASCII_RULES_ERROR", 66563);
        }
        if (!defined("U_IDNA_ACE_PREFIX_ERROR")) {
            define("U_IDNA_ACE_PREFIX_ERROR", 66564);
        }
        if (!defined("U_IDNA_VERIFICATION_ERROR")) {
            define("U_IDNA_VERIFICATION_ERROR", 66565);
        }
        if (!defined("U_IDNA_LABEL_TOO_LONG_ERROR")) {
            define("U_IDNA_LABEL_TOO_LONG_ERROR", 66566);
        }
        if (!defined("U_IDNA_ZERO_LENGTH_LABEL_ERROR")) {
            define("U_IDNA_ZERO_LENGTH_LABEL_ERROR", 66567);
        }
        if (!defined("U_IDNA_DOMAIN_NAME_TOO_LONG_ERROR")) {
            define("U_IDNA_DOMAIN_NAME_TOO_LONG_ERROR", 66568);
        }
        if (!defined("U_IDNA_ERROR_LIMIT")) {
            define("U_IDNA_ERROR_LIMIT", 66569);
        }
        if (!defined("U_STRINGPREP_PROHIBITED_ERROR")) {
            define("U_STRINGPREP_PROHIBITED_ERROR", 66560);
        }
        if (!defined("U_STRINGPREP_UNASSIGNED_ERROR")) {
            define("U_STRINGPREP_UNASSIGNED_ERROR", 66561);
        }
        if (!defined("U_STRINGPREP_CHECK_BIDI_ERROR")) {
            define("U_STRINGPREP_CHECK_BIDI_ERROR", 66562);
        }
        if (!defined("IDNA_DEFAULT")) {
            define("IDNA_DEFAULT", 0);
        }
        if (!defined("IDNA_ALLOW_UNASSIGNED")) {
            define("IDNA_ALLOW_UNASSIGNED", 1);
        }
        if (!defined("IDNA_USE_STD3_RULES")) {
            define("IDNA_USE_STD3_RULES", 2);
        }
        if (!defined("IDNA_CHECK_BIDI")) {
            define("IDNA_CHECK_BIDI", 4);
        }
        if (!defined("IDNA_CHECK_CONTEXTJ")) {
            define("IDNA_CHECK_CONTEXTJ", 8);
        }
        if (!defined("IDNA_NONTRANSITIONAL_TO_ASCII")) {
            define("IDNA_NONTRANSITIONAL_TO_ASCII", 16);
        }
        if (!defined("IDNA_NONTRANSITIONAL_TO_UNICODE")) {
            define("IDNA_NONTRANSITIONAL_TO_UNICODE", 32);
        }
        if (!defined("INTL_IDNA_VARIANT_2003")) {
            define("INTL_IDNA_VARIANT_2003", 0);
        }
        if (!defined("INTL_IDNA_VARIANT_UTS46")) {
            define("INTL_IDNA_VARIANT_UTS46", 1);
        }
        if (!defined("IDNA_ERROR_EMPTY_LABEL")) {
            define("IDNA_ERROR_EMPTY_LABEL", 1);
        }
        if (!defined("IDNA_ERROR_LABEL_TOO_LONG")) {
            define("IDNA_ERROR_LABEL_TOO_LONG", 2);
        }
        if (!defined("IDNA_ERROR_DOMAIN_NAME_TOO_LONG")) {
            define("IDNA_ERROR_DOMAIN_NAME_TOO_LONG", 4);
        }
        if (!defined("IDNA_ERROR_LEADING_HYPHEN")) {
            define("IDNA_ERROR_LEADING_HYPHEN", 8);
        }
        if (!defined("IDNA_ERROR_TRAILING_HYPHEN")) {
            define("IDNA_ERROR_TRAILING_HYPHEN", 16);
        }
        if (!defined("IDNA_ERROR_HYPHEN_3_4")) {
            define("IDNA_ERROR_HYPHEN_3_4", 32);
        }
        if (!defined("IDNA_ERROR_LEADING_COMBINING_MARK")) {
            define("IDNA_ERROR_LEADING_COMBINING_MARK", 64);
        }
        if (!defined("IDNA_ERROR_DISALLOWED")) {
            define("IDNA_ERROR_DISALLOWED", 128);
        }
        if (!defined("IDNA_ERROR_PUNYCODE")) {
            define("IDNA_ERROR_PUNYCODE", 256);
        }
        if (!defined("IDNA_ERROR_LABEL_HAS_DOT")) {
            define("IDNA_ERROR_LABEL_HAS_DOT", 512);
        }
        if (!defined("IDNA_ERROR_INVALID_ACE_LABEL")) {
            define("IDNA_ERROR_INVALID_ACE_LABEL", 1024);
        }
        if (!defined("IDNA_ERROR_BIDI")) {
            define("IDNA_ERROR_BIDI", 2048);
        }
        if (!defined("IDNA_ERROR_CONTEXTJ")) {
            define("IDNA_ERROR_CONTEXTJ", 4096);
        }
        return $this;
    }

    public static function getLanguages()
    {
        return ["afr" => "Afrikaans", "alb" => "Albanian", "ara" => "Arabic", "arg" => "Aragonese", "arm" => "Armenian", "asm" => "Assamese", "ast" => "Asturian", "ave" => "Avestan", "awa" => "Awadhi", "aze" => "Azerbaijani", "ban" => "Balinese", "bal" => "Baluchi", "bas" => "Basa", "bak" => "Bashkir", "baq" => "Basque", "bel" => "Belarusian", "ben" => "Bengali", "bho" => "Bhojpuri", "bos" => "Bosnian", "bul" => "Bulgarian", "bur" => "Burmese", "car" => "Carib", "cat" => "Catalan", "che" => "Chechen", "chi" => "Chinese", "chv" => "Chuvash", "cop" => "Coptic", "cos" => "Corsican", "scr" => "Croatian", "cze" => "Czech", "dan" => "Danish", "div" => "Divehi", "doi" => "Dogri", "dut" => "Dutch", "eng" => "English", "est" => "Estonian", "fao" => "Faroese", "fij" => "Fijian", "fin" => "Finnish", "fre" => "French", "fry" => "Frisian", "gla" => "Gaelic; Scottish Gaelic", "geo" => "Georgian", "ger" => "German", "gon" => "Gondi", "gre" => "Greek", "guj" => "Gujarati", "heb" => "Hebrew", "hin" => "Hindi", "hun" => "Hungarian", "ice" => "Icelandic", "inc" => "Indic", "ind" => "Indonesian", "inh" => "Ingush", "gle" => "Irish", "ita" => "Italian", "jpn" => "Japanese", "jav" => "Javanese", "kas" => "Kashmiri", "kaz" => "Kazakh", "khm" => "Khmer", "kir" => "Kirghiz", "kor" => "Korean", "kur" => "Kurdish", "lao" => "Lao", "lat" => "Latin", "lav" => "Latvian", "lit" => "Lithuanian", "ltz" => "Luxembourgish", "mac" => "Macedonian", "may" => "Malay", "mal" => "Malayalam", "mlt" => "Maltese", "mao" => "Maori", "mol" => "Moldavian", "mon" => "Mongolian", "nep" => "Nepali", "nor" => "Norwegian", "ori" => "Oriya", "oss" => "Ossetian", "per" => "Persian", "pol" => "Polish", "por" => "Portuguese", "pan" => "Punjabi", "pus" => "Pushto", "raj" => "Rajasthani", "rum" => "Romanian", "rus" => "Russian", "smo" => "Samoan", "san" => "Sanskrit", "srd" => "Sardinian", "scc" => "Serbian", "snd" => "Sindhi", "sin" => "Sinhalese", "slo" => "Slovak", "slv" => "Slovenian", "som" => "Somali", "spa" => "Spanish", "swa" => "Swahili", "swe" => "Swedish", "syr" => "Syriac", "tgk" => "Tajik", "tam" => "Tamil", "tel" => "Telugu", "tha" => "Thai", "tib" => "Tibetan", "tur" => "Turkish", "ukr" => "Ukrainian", "urd" => "Urdu", "uzb" => "Uzbek", "vie" => "Vietnamese", "wel" => "Welsh", "yid" => "Yiddish"];
    }
}
