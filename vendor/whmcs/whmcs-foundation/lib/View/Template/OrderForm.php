<?php

namespace WHMCS\View\Template;

class OrderForm extends AbstractTemplateSet
{
    use TemplateSetParentTrait;

    public static function factory($requestedTemplateFile, $systemTemplateName = NULL, $sessionTemplateName = NULL, $requestTemplateName = NULL)
    {
        $template = parent::factory($systemTemplateName, $sessionTemplateName, $requestTemplateName);
        if (is_null($requestedTemplateFile)) {
            return $template;
        }
        while (!is_null($template)) {
            if (file_exists(ROOTDIR . DIRECTORY_SEPARATOR . static::templateDirectory() . DIRECTORY_SEPARATOR . $template->getName() . DIRECTORY_SEPARATOR . $requestedTemplateFile)) {
                return $template;
            }
            $template = $template->getParent();
        }
        throw new \WHMCS\Exception\View\TemplateNotFound();
    }

    public static function factoryPreference($preference = NULL)
    {
        return self::factory(NULL, NULL, NULL, $preference);
    }

    public static function type()
    {
        return "orderform";
    }

    public static function templateDirectory()
    {
        return "templates/orderforms";
    }

    public static function defaultName()
    {
        return "standard_cart";
    }

    public static function defaultSettingKey()
    {
        return "OrderFormTemplate";
    }

    public function productGroups()
    {
        return \WHMCS\Product\Group::orderBy("order")->where("orderfrmtpl", $this->getName())->get();
    }

    public function getTemplateConfigValues($AbstractConfigValues)
    {
        return new OrderFormValues($this);
    }

    public static function getIncompatibleWithTheme($theme = NULL, \Illuminate\Support\Collection $orderforms)
    {
        if (!$orderforms) {
            $orderforms = static::all();
        }
        $incompatible = [];
        $compatUtil = new CompatUtil();
        foreach ($orderforms as $orderform) {
            if ($compatUtil->isCompat($orderform, $theme) === false) {
                $incompatible[] = $orderform;
            }
        }
        return $incompatible;
    }

    public static function getBestCompatibleWithTheme($OrderForm, $theme = NULL, \Illuminate\Support\Collection $orderforms)
    {
        if (!$orderforms) {
            $orderforms = static::all();
        }
        $compatible = [];
        $compatUtil = new CompatUtil();
        foreach ($orderforms as $orderform) {
            if ($compatUtil->isCompat($orderform, $theme)) {
                $compatible[$orderform->getName()] = $orderform;
            }
        }
        $currentDefault = OrderForm::getDefault();
        if ($currentDefault && array_key_exists($currentDefault->getName(), $compatible)) {
            $best = $compatible[$currentDefault->getName()];
        } else {
            if (array_key_exists(OrderForm::defaultName(), $compatible)) {
                $best = $compatible[OrderForm::defaultName()];
            } else {
                $best = array_shift($compatible);
            }
        }
        if (!$best) {
            $best = OrderForm::find(OrderForm::defaultName());
        }
        return $best;
    }

    protected function buildParent()
    {
        parent::buildParent();
        if (is_null($this->parent) && $this->name != static::defaultName()) {
            $this->parent = static::find(static::defaultName());
        }
        return $this;
    }
}
