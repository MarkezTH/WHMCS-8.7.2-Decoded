<?php

namespace WHMCS\MarketConnect;

class CodeGuardController extends AbstractController
{
    protected $serviceName = MarketConnect::SERVICE_CODEGUARD;
    protected $langPrefix = "codeGuard";

    public function index(\WHMCS\Http\Message\ServerRequest $request)
    {
        $ca = parent::index($request);
        if ($ca instanceof \Laminas\Diactoros\Response\RedirectResponse) {
            return $ca;
        }
        $currency = $ca->retrieve("activeCurrency");
        $products = $ca->retrieve("plans");
        foreach ($products as $key => $product) {
            $pricing = $product->pricing($currency);
            if (!$pricing->best()) {
                unset($products[$key]);
            } else {
                $products[$key]->diskSpace = Promotion\Service\CodeGuard::getDiskSpaceFromName($product->name);
            }
        }
        $ca->assign("products", $products);
        $ca->assign("codeGuardFaqs", $this->getFaqs());
        return $ca;
    }

    protected function getFaqs()
    {
        $faqs = [];
        for ($i = 1; $i <= 9; $i++) {
            $faqs[] = ["question" => \Lang::trans("store.codeGuard.faq.q" . $i), "answer" => \Lang::trans("store.codeGuard.faq.a" . $i)];
        }
        return $faqs;
    }
}
