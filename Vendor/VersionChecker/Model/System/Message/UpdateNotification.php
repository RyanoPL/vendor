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
        
        if (!$latest || $current === '0.0.0') {
            return false;
        }

        return version_compare($current, $latest, '<');
    }

    public function getText()
    {
        $latest = $this->getLatestVersion();
        return __(
            "<b>Global Payments:</b> Dostępna jest nowa wersja wtyczki (%1). " .
            "Aby ją zaktualizować, przejdź do: <b>System → T-ZQA eCom → Global Payments Module Status</b> " .
            "i użyj dostępnego tam przycisku aktualizacji.",
            $latest
        );
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
