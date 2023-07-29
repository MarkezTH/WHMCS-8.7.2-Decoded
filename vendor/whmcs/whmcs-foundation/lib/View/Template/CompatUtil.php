<?php

namespace WHMCS\View\Template;

class CompatUtil
{
    private $cache = NULL;
    const SETTING_COMPAT_DISABLED = "TemplateCompatDisabled";
    const COMPAT_YES = "compatible";
    const COMPAT_NO = "incompatible";
    const COMPAT_UNKNOWN = "unknown";

    public function __construct(CompatCache $cache = NULL)
    {
        if (is_null($cache)) {
            $cache = new CompatCache($this);
        }
        $this->cache = $cache;
    }

    public function getCache($CompatCache)
    {
        return $this->cache;
    }

    public function isCompatDisabled()
    {
        return (bool) (int) \WHMCS\Config\Setting::getValue(static::SETTING_COMPAT_DISABLED);
    }

    public function getDefaultProvider($TemplateSetInterface)
    {
        return Theme::factory(\WHMCS\Config\Setting::getValue(Theme::defaultSettingKey()), \WHMCS\Session::get(Theme::defaultSettingKey()));
    }

    public function detailsCompatible()
    {
        return ["status" => "compatible", "reason" => "ok"];
    }

    private function getCompatDetails(TemplateSetInterface $subject, TemplateSetInterface $provider = NULL)
    {
        if ($this->isCompatDisabled()) {
            return $this->detailsCompatible();
        }
        if (is_null($provider)) {
            $provider = $this->getDefaultProvider();
        }
        return $this->getCache()->getCompatibility($subject->getName(), $provider->getName());
    }

    public function isCompat(TemplateSetInterface $subject, TemplateSetInterface $provider = NULL)
    {
        $details = $this->getCompatDetails($subject, $provider);
        if (!is_null($details) && $details["status"] === static::COMPAT_NO) {
            return false;
        }
        return true;
    }

    public function incompatibilityConcerns(TemplateSetInterface $subject, TemplateSetInterface $provider = NULL)
    {
        $details = $this->getCompatDetails($subject, $provider);
        if (!is_null($details) && $details["status"] !== static::COMPAT_YES) {
            return $details["reason"];
        }
        return "";
    }

    public function assertProviderCompatibility(TemplateSetInterface $subject, TemplateSetInterface $provider)
    {
        $provideList = $provider->getProvides();
        $parent = $subject->getParent();
        $templates = [];
        while ($parent instanceof TemplateSetInterface) {
            array_unshift($templates, $parent);
            $parent = $parent->getParent();
        }
        $templates[] = $subject;
        $dependencyList = [];
        foreach ($templates as $template) {
            $dependencyList = array_merge($dependencyList, $template->getDependencies());
            $provideList = array_merge($provideList, $template->getProvides());
        }
        $lowerPriorities = [];
        try {
            $this->assertCompatibility($provideList, $dependencyList);
        } catch (\WHMCS\Exception\View\TemplateCompatUnknown $e) {
            $lowerPriorities[] = $e;
        }
        if ($lowerPriorities) {
            throw array_shift($lowerPriorities);
        }
    }

    protected function assertCompatibility($provideList, $dependencyList)
    {
        foreach ($dependencyList as $lib => $expression) {
            if (!array_key_exists($lib, $provideList)) {
                throw new \WHMCS\Exception\View\TemplateCompatUnknown(sprintf("Unknown Compatibility. Requires %s %s but the current theme does not itemize that library.", $lib, $expression));
            }
            $requirement = new \WHMCS\Version\CompoundExpression($expression);
            if (!$requirement->matches($provideList[$lib])) {
                throw new \WHMCS\Exception\View\TemplateUnfilledDependency(sprintf("Incompatible. Requires %s %s but the current theme only provides version %s.", $lib, $expression, $provideList[$lib]));
            }
        }
        return true;
    }

    public static function initializeRuntimeOrderForm(TemplateSetInterface $theme, $preference = NULL)
    {
        try {
            $self = new static();
            $needsBestFallback = false;
            if (empty($preference)) {
                if ($preference === "") {
                    \WHMCS\Session::delete(OrderForm::defaultSettingKey());
                }
                $needsBestFallback = true;
            } else {
                $orderForm = OrderForm::find($preference);
                if (!$orderForm) {
                    $needsBestFallback = true;
                } else {
                    $name = $orderForm->getName();
                    if ($self->isCompatDisabled()) {
                        \WHMCS\Config\Setting::updateRuntimeConfigCache(OrderForm::defaultSettingKey(), $name);
                    } else {
                        if ($self->isCompat($orderForm, $theme) === true) {
                            \WHMCS\Session::set(OrderForm::defaultSettingKey(), $name);
                            \WHMCS\Config\Setting::updateRuntimeConfigCache(OrderForm::defaultSettingKey(), $name);
                        } else {
                            if (\WHMCS\Session::get(OrderForm::defaultSettingKey()) == $name) {
                                \WHMCS\Session::delete(OrderForm::defaultSettingKey());
                                $needsBestFallback = true;
                            }
                        }
                    }
                }
            }
            if ($needsBestFallback) {
                $sessVal = "";
                if (\WHMCS\Session::exists(OrderForm::defaultSettingKey())) {
                    $sessVal = \WHMCS\Session::get(OrderForm::defaultSettingKey());
                }
                $orderForms = [$sessVal, (string) \WHMCS\Config\Setting::getValue(OrderForm::defaultSettingKey()), OrderForm::defaultName()];
                foreach ($orderForms as $name) {
                    $name = preg_replace("/[^0-9a-z\\-_]/i", "", $name);
                    if ($name != "" && is_dir(ROOTDIR . DIRECTORY_SEPARATOR . OrderForm::templateDirectory() . DIRECTORY_SEPARATOR . $name . "/")) {
                        \WHMCS\Config\Setting::updateRuntimeConfigCache(OrderForm::defaultSettingKey(), $name);
                    }
                }
            }
        } catch (\Throwable $e) {
            logActivity("Failure to initialize orderform: " . $e->getMessage());
        }
    }
}
