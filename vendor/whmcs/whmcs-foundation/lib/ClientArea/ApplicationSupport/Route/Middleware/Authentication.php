<?php

namespace WHMCS\ClientArea\ApplicationSupport\Route\Middleware;

class Authentication implements \WHMCS\Route\Contracts\Middleware\StrategyInterface
{
    use \WHMCS\Route\Middleware\Strategy\DelegatingMiddlewareTrait;

    public function _process(\WHMCS\Http\Message\ServerRequest $request, \Interop\Http\ServerMiddleware\DelegateInterface $delegate)
    {
        \Auth::requireLogin(true);
        $this->prepareUserLanguage();
        return $request->withAttribute("authenticatedUser", \Auth::user());
    }

    protected function prepareUserLanguage()
    {
        $user = \Auth::user();
        if (\WHMCS\Session::get("language")) {
            $language = \WHMCS\Session::get("language");
        } else {
            $language = $user->language;
        }
        try {
            if (\Lang::getName() != $language) {
                \DI::forgetInstance("lang");
                $lang = \DI::make("lang", [$language]);
                \Lang::swap($lang);
            } else {
                \DI::make("lang");
            }
            $locales = \Lang::getLocales();
            $activeLocale = NULL;
            foreach ($locales as $locale) {
                if ($locale["language"] == \Lang::getName()) {
                    $activeLocale = $locale;
                    if (is_array($activeLocale)) {
                        $carbonObject = new \WHMCS\Carbon();
                        $carbonObject->setLocale($activeLocale["languageCode"]);
                    }
                }
            }
        } catch (\Exception $e) {
            throw new \WHMCS\Exception\Fatal(\WHMCS\View\Helper::applicationError("Error Preparing ClientArea Language", $e->getMessage(), $e));
        }
    }
}
