<?php

namespace Transip\Api\Library;

class TransipAPI
{
    private $httpClient = NULL;
    const TRANSIP_API_ENDPOINT = "https://api.transip.nl/v6";
    const TRANSIP_API_LIBRARY_VERSION = "6.12.0";
    const TRANSIP_API_DEMO_TOKEN = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImN3MiFSbDU2eDNoUnkjelM4YmdOIn0.eyJpc3MiOiJhcGkudHJhbnNpcC5ubCIsImF1ZCI6ImFwaS50cmFuc2lwLm5sIiwianRpIjoiY3cyIVJsNTZ4M2hSeSN6UzhiZ04iLCJpYXQiOjE1ODIyMDE1NTAsIm5iZiI6MTU4MjIwMTU1MCwiZXhwIjoyMTE4NzQ1NTUwLCJjaWQiOiI2MDQ0OSIsInJvIjpmYWxzZSwiZ2siOmZhbHNlLCJrdiI6dHJ1ZX0.fYBWV4O5WPXxGuWG-vcrFWqmRHBm9yp0PHiYh_oAWxWxCaZX2Rf6WJfc13AxEeZ67-lY0TA2kSaOCp0PggBb_MGj73t4cH8gdwDJzANVxkiPL1Saqiw2NgZ3IHASJnisUWNnZp8HnrhLLe5ficvb1D9WOUOItmFC2ZgfGObNhlL2y-AMNLT4X7oNgrNTGm-mespo0jD_qH9dK5_evSzS3K8o03gu6p19jxfsnIh8TIVRvNdluYC2wo4qDl5EW5BEZ8OSuJ121ncOT1oRpzXB0cVZ9e5_UVAEr9X3f26_Eomg52-PjrgcRJ_jPIUYbrlo06KjjX2h0fzMr21ZE023Gw";

    public function __construct($customerLoginName, $privateKey, $generateWhitelistOnlyTokens = true, $token = "", $endpointUrl = "", \Symfony\Component\Cache\Adapter\AdapterInterface $cache = NULL, HttpClient\HttpClient $httpClient = NULL)
    {
        $endpoint = self::TRANSIP_API_ENDPOINT;
        if ($endpointUrl != "") {
            $endpoint = $endpointUrl;
        }
        $this->httpClient = $httpClient ?? new HttpClient\GuzzleClient($endpoint);
        if ($customerLoginName != "") {
            $this->httpClient->setLogin($customerLoginName);
        }
        if ($privateKey != "") {
            $this->httpClient->setPrivateKey($privateKey);
        }
        if ($cache === NULL) {
            $cache = new \Symfony\Component\Cache\Adapter\FilesystemAdapter();
        }
        $this->httpClient->setCache($cache);
        $this->httpClient->setGenerateWhitelistOnlyTokens($generateWhitelistOnlyTokens);
        if ($token != "") {
            $this->httpClient->setToken($token);
        } else {
            $this->httpClient->getTokenFromCache();
        }
    }

    public function availabilityZone($AvailabilityZoneRepository)
    {
        return new Repository\AvailabilityZoneRepository($this->httpClient);
    }

    public function products($ProductRepository)
    {
        return new Repository\ProductRepository($this->httpClient);
    }

    public function productElements($ElementRepository)
    {
        return new Repository\Product\ElementRepository($this->httpClient);
    }

    public function sshKey($SshKeyRepository)
    {
        return new Repository\SshKeyRepository($this->httpClient);
    }

    public function invoice($InvoiceRepository)
    {
        return new Repository\InvoiceRepository($this->httpClient);
    }

    public function invoicePdf($PdfRepository)
    {
        return new Repository\Invoice\PdfRepository($this->httpClient);
    }

    public function invoiceItem($ItemRepository)
    {
        return new Repository\Invoice\ItemRepository($this->httpClient);
    }

    public function domains($DomainRepository)
    {
        return new Repository\DomainRepository($this->httpClient);
    }

    public function domainAction($ActionRepository)
    {
        return new Repository\Domain\ActionRepository($this->httpClient);
    }

    public function domainBranding($BrandingRepository)
    {
        return new Repository\Domain\BrandingRepository($this->httpClient);
    }

    public function domainContact($ContactRepository)
    {
        return new Repository\Domain\ContactRepository($this->httpClient);
    }

    public function domainDns($DnsRepository)
    {
        return new Repository\Domain\DnsRepository($this->httpClient);
    }

    public function domainDnsSec($DnsSecRepository)
    {
        return new Repository\Domain\DnsSecRepository($this->httpClient);
    }

    public function domainNameserver($NameserverRepository)
    {
        return new Repository\Domain\NameserverRepository($this->httpClient);
    }

    public function domainSsl($SslRepository)
    {
        return new Repository\Domain\SslRepository($this->httpClient);
    }

    public function domainWhois($WhoisRepository)
    {
        return new Repository\Domain\WhoisRepository($this->httpClient);
    }

    public function domainAvailability($DomainAvailabilityRepository)
    {
        return new Repository\DomainAvailabilityRepository($this->httpClient);
    }

    public function domainTlds($DomainTldRepository)
    {
        return new Repository\DomainTldRepository($this->httpClient);
    }

    public function domainWhitelabel($DomainWhitelabelRepository)
    {
        return new Repository\DomainWhitelabelRepository($this->httpClient);
    }

    public function traffic($TrafficRepository)
    {
        return new Repository\TrafficRepository($this->httpClient);
    }

    public function trafficPool($TrafficPoolRepository)
    {
        return new Repository\TrafficPoolRepository($this->httpClient);
    }

    public function vps($VpsRepository)
    {
        return new Repository\VpsRepository($this->httpClient);
    }

    public function vpsAddons($AddonRepository)
    {
        return new Repository\Vps\AddonRepository($this->httpClient);
    }

    public function vpsBackups($BackupRepository)
    {
        return new Repository\Vps\BackupRepository($this->httpClient);
    }

    public function vpsFirewall($FirewallRepository)
    {
        return new Repository\Vps\FirewallRepository($this->httpClient);
    }

    public function vpsIpAddresses($IpAddressRepository)
    {
        return new Repository\Vps\IpAddressRepository($this->httpClient);
    }

    public function vpsOperatingSystems($OperatingSystemRepository)
    {
        return new Repository\Vps\OperatingSystemRepository($this->httpClient);
    }

    public function vpsSnapshots($SnapshotRepository)
    {
        return new Repository\Vps\SnapshotRepository($this->httpClient);
    }

    public function vpsUpgrades($UpgradeRepository)
    {
        return new Repository\Vps\UpgradeRepository($this->httpClient);
    }

    public function vpsUsage($UsageRepository)
    {
        return new Repository\Vps\UsageRepository($this->httpClient);
    }

    public function vpsVncData($VncDataRepository)
    {
        return new Repository\Vps\VncDataRepository($this->httpClient);
    }

    public function vpsTCPMonitor($TCPMonitorRepository)
    {
        return new Repository\Vps\TCPMonitorRepository($this->httpClient);
    }

    public function vpsTCPMonitorContact($MonitoringContactRepository)
    {
        return new Repository\Vps\MonitoringContactRepository($this->httpClient);
    }

    public function vpsLicenses($LicenseRepository)
    {
        return new Repository\Vps\LicenseRepository($this->httpClient);
    }

    public function privateNetworks($PrivateNetworkRepository)
    {
        return new Repository\PrivateNetworkRepository($this->httpClient);
    }

    public function bigStorages($BigStorageRepository)
    {
        return new Repository\BigStorageRepository($this->httpClient);
    }

    public function bigStorageBackups($BackupRepository)
    {
        return new Repository\BigStorage\BackupRepository($this->httpClient);
    }

    public function bigStorageUsage($UsageRepository)
    {
        return new Repository\BigStorage\UsageRepository($this->httpClient);
    }

    public function mailService($MailServiceRepository)
    {
        return new Repository\MailServiceRepository($this->httpClient);
    }

    public function haip($HaipRepository)
    {
        return new Repository\HaipRepository($this->httpClient);
    }

    public function haipIpAddresses($IpAddressRepository)
    {
        return new Repository\Haip\IpAddressRepository($this->httpClient);
    }

    public function haipPortConfigurations($PortConfigurationRepository)
    {
        return new Repository\Haip\PortConfigurationRepository($this->httpClient);
    }

    public function haipCertificates($CertificateRepository)
    {
        return new Repository\Haip\CertificateRepository($this->httpClient);
    }

    public function haipStatusReports($StatusReportRepository)
    {
        return new Repository\Haip\StatusReportRepository($this->httpClient);
    }

    public function colocation($ColocationRepository)
    {
        return new Repository\ColocationRepository($this->httpClient);
    }

    public function colocationIpAddress($IpAddressRepository)
    {
        return new Repository\Colocation\IpAddressRepository($this->httpClient);
    }

    public function colocationRemoteHands($RemoteHandsRepository)
    {
        return new Repository\Colocation\RemoteHandsRepository($this->httpClient);
    }

    public function openStackProjects($ProjectRepository)
    {
        return new Repository\OpenStack\ProjectRepository($this->httpClient);
    }

    public function openStackProjectUsers($UserRepository)
    {
        return new Repository\OpenStack\Project\UserRepository($this->httpClient);
    }

    public function openStackUsers($UserRepository)
    {
        return new Repository\OpenStack\UserRepository($this->httpClient);
    }

    public function test($ApiTestRepository)
    {
        return new Repository\ApiTestRepository($this->httpClient);
    }

    public function auth($AuthRepository)
    {
        return new Repository\AuthRepository($this->httpClient);
    }

    public function setHttpClient($httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function setToken($token)
    {
        $this->httpClient->setToken($token);
    }

    public function setEndpointUrl($endpointUrl)
    {
        $this->httpClient->setEndpoint($endpointUrl);
    }

    public function setTokenLabelPrefix($endpointUrl)
    {
        $this->httpClient->setTokenLabelPrefix($endpointUrl);
    }

    public function getLogin()
    {
        return $this->httpClient->getLogin();
    }

    public function getEndpointUrl()
    {
        return $this->httpClient->getEndpoint();
    }

    public function getGenerateWhitelistOnlyTokens()
    {
        return $this->httpClient->getGenerateWhitelistOnlyTokens();
    }

    public function clearCache()
    {
        $this->httpClient->clearCache();
    }

    public function setReadOnlyMode($mode)
    {
        $this->httpClient->setReadOnlyMode($mode);
    }

    public function getReadOnlyMode()
    {
        return $this->httpClient->getReadOnlyMode();
    }

    public function useDemoToken()
    {
        $this->setToken(self::TRANSIP_API_DEMO_TOKEN);
    }

    public function setTestMode($testMode)
    {
        $this->httpClient->setTestMode($testMode);
    }

    public function getTestMode()
    {
        return $this->httpClient->getTestMode();
    }

    public function getRateLimitLimit()
    {
        return $this->httpClient->getRateLimitLimit();
    }

    public function getRateLimitRemaining()
    {
        return $this->httpClient->getRateLimitRemaining();
    }

    public function getRateLimitReset()
    {
        return $this->httpClient->getRateLimitReset();
    }

    public function getTokenExpiryTime()
    {
        return $this->httpClient->getChosenTokenExpiry();
    }

    public function setTokenExpiryTime($expiryTime)
    {
        $this->httpClient->setChosenTokenExpiry($expiryTime);
    }
}
