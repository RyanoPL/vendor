<?php
/**
 * Global Payments Version Checker for Magento 2
 */
namespace Vendor\VersionChecker\Model\System\Message;

use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\HTTP\Client\Curl;
use Vendor\VersionChecker\Model\Service\VersionService;

class UpdateNotification implements MessageInterface
{
    private const GITHUB_API_URL = 'https://api.github.com';
    private const TARGET_MODULE_NAME = 'GlobalPayments_PaymentGateway';

    protected $componentRegistrar;
    protected $readFactory;
    protected $curl;
    protected $versionService;

    public function __construct(
        ComponentRegistrar $componentRegistrar,
        ReadFactory $readFactory,
        Curl $curl,
        VersionService $versionService
    ) {
        $this->componentRegistrar = $componentRegistrar;
        $this->readFactory = $readFactory;
        $this->curl = $curl;
        $this->versionService = $versionService;
    }

    public function getIdentity()
    {
        return 'gp_update_notification';
    }

    public function isDisplayed()
    {
        if (!$this->versionService->isEnabled()) {
            return false;
        }
        $current = $this->getCurrentVersion();
        $latest = $this->getLatestVersion();
        $selfCurrent = $this->versionService->getSelfCurrentVersion();
        $selfLatest = $this->versionService->getSelfCachedLatestVersion();
        
        $gpUpdate = $latest && $current !== '0.0.0' && version_compare($current, $latest, '<');
        $selfUpdate = $selfLatest && $selfCurrent !== '0.0.0' && version_compare($selfCurrent, $selfLatest, '<');

        return $gpUpdate || $selfUpdate;
    }

    public function getText()
    {
        $current = $this->getCurrentVersion();
        $latest = $this->getLatestVersion();
        $selfCurrent = $this->versionService->getSelfCurrentVersion();
        $selfLatest = $this->versionService->getSelfCachedLatestVersion();

        $parts = [];

        if ($latest && $current !== '0.0.0' && version_compare($current, $latest, '<')) {
            $parts[] = __(
                "<b>Global Payments:</b> A new plugin version is available (%1). Your version: %2.",
                $latest,
                $current
            );
        }

        if ($selfLatest && $selfCurrent !== '0.0.0' && version_compare($selfCurrent, $selfLatest, '<')) {
            $parts[] = __(
                "<b>Global Payments Module Status:</b> A new Version Checker module version is available (%1). Your version: %2.",
                $selfLatest,
                $selfCurrent
            );
        } else {
            $parts[] = __(
                "<b>Global Payments Module Status:</b> The Version Checker module is up to date (version %1).",
                $selfCurrent
            );
        }

        $parts[] = __(
            "To update the modules, go to: <b>System → T-ZQA eCom → Global Payments Module Status</b> and use the available update buttons there."
        );

        return implode('<br/>', $parts);
    }

    public function getSeverity()
    {
        return self::SEVERITY_CRITICAL;
    }

    public function getCurrentVersion() // zmienione z private na public
    {
        return $this->versionService->getCurrentVersion();
    }

    public function getLatestVersion()
    {
        return $this->versionService->getCachedLatestVersion();
    }

}
?>
