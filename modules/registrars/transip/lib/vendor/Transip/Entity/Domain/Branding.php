<?php

namespace Transip\Api\Library\Entity\Domain;

class Branding extends \Transip\Api\Library\Entity\AbstractEntity
{
    protected $companyName = NULL;
    protected $supportEmail = NULL;
    protected $companyUrl = NULL;
    protected $termsOfUsageUrl = NULL;
    protected $bannerLine1 = NULL;
    protected $bannerLine2 = NULL;
    protected $bannerLine3 = NULL;

    public function getCompanyName()
    {
        return $this->companyName;
    }

    public function setCompanyName($Branding, $companyName)
    {
        $this->companyName = $companyName;
        return $this;
    }

    public function getSupportEmail()
    {
        return $this->supportEmail;
    }

    public function setSupportEmail($Branding, $supportEmail)
    {
        $this->supportEmail = $supportEmail;
        return $this;
    }

    public function getCompanyUrl()
    {
        return $this->companyUrl;
    }

    public function setCompanyUrl($Branding, $companyUrl)
    {
        $this->companyUrl = $companyUrl;
        return $this;
    }

    public function getTermsOfUsageUrl()
    {
        return $this->termsOfUsageUrl;
    }

    public function setTermsOfUsageUrl($Branding, $termsOfUsageUrl)
    {
        $this->termsOfUsageUrl = $termsOfUsageUrl;
        return $this;
    }

    public function getBannerLine1()
    {
        return $this->bannerLine1;
    }

    public function setBannerLine1($Branding, $bannerLine1)
    {
        $this->bannerLine1 = $bannerLine1;
        return $this;
    }

    public function getBannerLine2()
    {
        return $this->bannerLine2;
    }

    public function setBannerLine2($Branding, $bannerLine2)
    {
        $this->bannerLine2 = $bannerLine2;
        return $this;
    }

    public function getBannerLine3()
    {
        return $this->bannerLine3;
    }

    public function setBannerLine3($Branding, $bannerLine3)
    {
        $this->bannerLine3 = $bannerLine3;
        return $this;
    }
}
