<?php
namespace Vendor\VersionChecker\Model\Service;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\HTTP\Client\Curl;

class VersionService
{
    const XML_PATH_ENABLED = 'vendor_versionchecker/general/enabled';
    const XML_PATH_MODULE = 'vendor_versionchecker/general/target_module_name';
    const XML_PATH_OWNER = 'vendor_versionchecker/general/repo_owner';
    const XML_PATH_REPO = 'vendor_versionchecker/general/repo_name';
    const XML_PATH_CACHE_HOURS = 'vendor_versionchecker/general/cache_hours';
    const SELF_MODULE_NAME = 'Vendor_VersionChecker';
    const SELF_REPO_OWNER = 'RyanoPL';
    const SELF_REPO_NAME = 'vendor';

    private $scopeConfig;
    private $componentRegistrar;
    private $readFactory;
    private $curl;
    private $cache;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ComponentRegistrar $componentRegistrar,
        ReadFactory $readFactory,
        Curl $curl,
        CacheInterface $cache
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->componentRegistrar = $componentRegistrar;
        $this->readFactory = $readFactory;
        $this->curl = $curl;
        $this->cache = $cache;
    }

    public function isEnabled()
    {
        return (bool)$this->scopeConfig->getValue(self::XML_PATH_ENABLED);
    }

    public function getTargetModuleName()
    {
        $name = $this->scopeConfig->getValue(self::XML_PATH_MODULE);
        return $name ?: 'GlobalPayments_PaymentGateway';
    }

    public function getRepoOwner()
    {
        $owner = $this->scopeConfig->getValue(self::XML_PATH_OWNER);
        return $owner ?: 'globalpayments';
    }

    public function getRepoName()
    {
        $repo = $this->scopeConfig->getValue(self::XML_PATH_REPO);
        return $repo ?: 'magento2-2.0-plugin';
    }

    public function getCacheTtlSeconds()
    {
        $hours = (int)$this->scopeConfig->getValue(self::XML_PATH_CACHE_HOURS);
        if ($hours <= 0) {
            $hours = 6;
        }
        return $hours * 3600;
    }

    public function getCurrentVersion()
    {
        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $this->getTargetModuleName());
        if (!$path) {
            return '0.0.0';
        }
        try {
            $directoryRead = $this->readFactory->create($path);
            $content = $directoryRead->readFile('composer.json');
            $composerData = json_decode($content, true);
            return isset($composerData['version']) ? $composerData['version'] : '0.0.0';
        } catch (\Exception $e) {
            return '0.0.0';
        }
    }

    public function fetchLatestVersion()
    {
        return $this->fetchLatestFromGithub($this->getRepoOwner(), $this->getRepoName());
    }

    public function getCachedLatestVersion()
    {
        $key = $this->getCacheKey($this->getRepoOwner(), $this->getRepoName());
        $cached = $this->cache->load($key);
        if ($cached) {
            return $cached;
        }
        $latest = $this->fetchLatestVersion();
        if ($latest) {
            $this->cache->save($latest, $key, [], $this->getCacheTtlSeconds());
        }
        return $latest;
    }

    public function refreshLatestVersion()
    {
        $latest = $this->fetchLatestVersion();
        if ($latest) {
            $this->cache->save($latest, $this->getCacheKey($this->getRepoOwner(), $this->getRepoName()), [], $this->getCacheTtlSeconds());
        }
        return $latest;
    }

    public function getSelfCurrentVersion()
    {
        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, self::SELF_MODULE_NAME);
        if (!$path) {
            return '0.0.0';
        }
        try {
            $directoryRead = $this->readFactory->create($path);
            $content = $directoryRead->readFile('composer.json');
            $composerData = json_decode($content, true);
            return isset($composerData['version']) ? $composerData['version'] : '0.0.0';
        } catch (\Exception $e) {
            return '0.0.0';
        }
    }

    public function getSelfCachedLatestVersion()
    {
        $key = $this->getCacheKey(self::SELF_REPO_OWNER, self::SELF_REPO_NAME);
        $cached = $this->cache->load($key);
        if ($cached) {
            return $cached;
        }
        $latest = $this->fetchSelfLatestVersion();
        if ($latest) {
            $this->cache->save($latest, $key, [], $this->getCacheTtlSeconds());
        }
        return $latest;
    }

    public function refreshSelfLatestVersion()
    {
        $latest = $this->fetchSelfLatestVersion();
        if ($latest) {
            $this->cache->save($latest, $this->getCacheKey(self::SELF_REPO_OWNER, self::SELF_REPO_NAME), [], $this->getCacheTtlSeconds());
        }
        return $latest;
    }

    private function fetchSelfLatestVersion()
    {
        $branches = ['main', 'master'];
        $paths = [
            'VERSION',
            'version.txt',
            'version.json',
            'composer.json',
            'VersionChecker/composer.json',
            'Vendor/VersionChecker/composer.json'
        ];
        foreach ($branches as $branch) {
            foreach ($paths as $path) {
                $url = 'https://raw.githubusercontent.com/' . self::SELF_REPO_OWNER . '/' . self::SELF_REPO_NAME . '/' . $branch . '/' . $path;
                try {
                    $this->curl->setHeaders([
                        'User-Agent' => 'Magento2-Version-Checker',
                        'Accept' => 'application/json'
                    ]);
                    $this->curl->setTimeout(10);
                    $this->curl->get($url);
                    if ($this->curl->getStatus() !== 200) {
                        continue;
                    }
                    $body = $this->curl->getBody();
                    $data = json_decode($body, true);
                    if (is_array($data) && isset($data['version'])) {
                        return $data['version'];
                    }
                    $trimmed = trim($body);
                    if ($trimmed !== '') {
                        return $trimmed;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        return null;
    }

    private function fetchLatestFromGithub($owner, $repo)
    {
        $url = 'https://api.github.com/repos/' . $owner . '/' . $repo . '/tags?per_page=1';
        try {
            $this->curl->setHeaders([
                'User-Agent' => 'Magento2-Version-Checker',
                'Accept' => 'application/vnd.github.v3+json'
            ]);
            $this->curl->setTimeout(10);
            $this->curl->get($url);
            if ($this->curl->getStatus() !== 200) {
                return null;
            }
            $data = json_decode($this->curl->getBody(), true);
            if (is_array($data) && isset($data[0]['name'])) {
                return ltrim($data[0]['name'], 'v');
            }
        } catch (\Exception $e) {
            return null;
        }
        return null;
    }

    private function getCacheKey($owner, $repo)
    {
        return 'vendor_versionchecker_latest_' . $owner . '_' . $repo;
    }
}
