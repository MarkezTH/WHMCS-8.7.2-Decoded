<?php

namespace WHMCS\Utility;

class Captcha
{
    private $enabled = false;
    private $forms = [];
    public $recaptcha = NULL;
    private $captchaType = "";
    public static $defaultFormSettings = NULL;
    const SETTING_CAPTCHA_FORMS = "CaptchaForms";
    const FORM_CHECKOUT_COMPLETION = "checkoutCompletion";
    const FORM_DOMAIN_CHECKER = "domainChecker";
    const FORM_REGISTRATION = "registration";
    const FORM_CONTACT_US = "contactUs";
    const FORM_SUBMIT_TICKET = "submitTicket";
    const FORM_LOGIN = "login";

    public function __construct()
    {
        $isEnabled = $this->isSystemEnabledRuntime();
        $this->captchaType = \WHMCS\Config\Setting::getValue("CaptchaType");
        $this->setEnabled($isEnabled);
        $storedForms = $this->getStoredFormSettings();
        $defaultForms = static::getDefaultFormSettings();
        $this->setForms(array_merge($defaultForms, $storedForms));
        $this->recaptcha = new Recaptcha($this);
        if (in_array($this->captchaType, [Recaptcha::CAPTCHA_INVISIBLE, Recaptcha::CAPTCHA_RECAPTCHA]) && !$this->recaptcha->isEnabled()) {
            $this->captchaType = "";
        }
    }

    public function isSystemEnabledRuntime()
    {
        $setting = trim((string) \WHMCS\Config\Setting::getValue("CaptchaSetting"));
        if ($setting == "on") {
            return true;
        }
        $clientAreaLoggedIn = defined("CLIENTAREA") && \Auth::user();
        $adminAreaLoggedIn = defined("ADMINAREA") && \WHMCS\Session::get("adminid");
        $isLoggedIn = $clientAreaLoggedIn || $adminAreaLoggedIn;
        if (!$setting || $setting && $isLoggedIn) {
            return false;
        }
        return true;
    }

    public static function getDefaultFormSettings()
    {
        return static::$defaultFormSettings;
    }

    public function validateAppropriateCaptcha($form, \WHMCS\Validate $validate)
    {
        if ($this->isEnabledForForm($form)) {
            if ($this->isEnabled() && $this->recaptcha->isEnabled()) {
                try {
                    $this->recaptcha->validate((string) \App::getFromRequest("g-recaptcha-response"));
                } catch (\Exception $e) {
                    if ($e->getMessage() === "captchaverifyincorrect") {
                        $validate->addError("captchaverifyincorrect");
                    } else {
                        if ($e->getMessage() === "googleRecaptchaIncorrect") {
                            $languageKey = "googleRecaptchaIncorrect";
                            if (defined("ADMINAREA")) {
                                $validate->addError("Please complete the captcha and try again.");
                            } else {
                                $validate->addError($languageKey);
                            }
                        } else {
                            $validate->addError($e->getMessage());
                        }
                    }
                    return false;
                }
            } else {
                if ($this->isEnabled() && !$this->recaptcha->isEnabled()) {
                    $languageKey = "captchaverifyincorrect";
                    if (defined("ADMINAREA")) {
                        $languageKey = "The characters you entered didn't match the image shown. Please try again.";
                    }
                    return $validate->validate("captcha", "code", $languageKey);
                }
            }
        }
        return true;
    }

    public function getForms()
    {
        return $this->forms;
    }

    public function setForms($forms)
    {
        $this->forms = $forms;
        return $this;
    }

    public function isEnabledForForm($formName)
    {
        if ($this->isEnabled()) {
            $forms = $this->getForms();
            if (!array_key_exists($formName, $forms)) {
                return true;
            }
            return (bool) $forms[$formName];
        }
        return false;
    }

    public function getStoredFormSettings()
    {
        $data = \WHMCS\Config\Setting::getValue(static::SETTING_CAPTCHA_FORMS);
        if (!is_string($data) || strlen($data) == 0) {
            return [];
        }
        $data = json_decode($data, true);
        if (!is_array($data)) {
            $data = [];
        }
        return $data;
    }

    public function setStoredFormSettings($data = [])
    {
        \WHMCS\Config\Setting::setValue(static::SETTING_CAPTCHA_FORMS, json_encode($data));
        return $this;
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function __toString()
    {
        return $this->getCaptchaType();
    }

    public function getCaptchaType()
    {
        return $this->captchaType ?: "";
    }

    public function getButtonClass($formName)
    {
        $class = "";
        if ($this->isEnabledForForm($formName)) {
            if ($this->recaptcha->isEnabled()) {
                $class = " btn-recaptcha";
            }
            if ($this->recaptcha->isInvisible()) {
                $class .= " btn-recaptcha-invisible";
            }
        }
        return $class;
    }
}
