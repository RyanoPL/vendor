<?php
/**
 * Global Payments Version Checker for Magento 2
 */
namespace Vendor\VersionChecker\Model\System\Message;

use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\HTTP\Client\Curl;

class UpdateNotification implements MessageInterface
{
    private const GITHUB_API_URL = 'https://api.github.com';
    private const TARGET_MODULE_NAME = 'GlobalPayments_PaymentGateway';

    protected $componentRegistrar;
    protected $readFactory;
    protected $curl;

    public function __construct(
        ComponentRegistrar $componentRegistrar,
        ReadFactory $readFactory,
        Curl $curl
    ) {
        $this->componentRegistrar = $componentRegistrar;
        $this->readFactory = $readFactory;
        $this->curl = $curl;
    }

    public function getIdentity()
    {
        return 'gp_update_notification';
    }

    public function isDisplayed()
    {
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
        return __("<b>Global Payments:</b> Dostępna jest nowa wersja wtyczki (%1). Zaktualizuj ją: <code>composer update globalpayments/magento2-2.0-plugin</code>", $latest);
    }

    public function getSeverity()
    {
        return self::SEVERITY_CRITICAL;
    }

    public function getCurrentVersion() // zmienione z private na public
    {
        $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, self::TARGET_MODULE_NAME);
        if (!$path) {
            return '0.0.0';
        }
        
        try {
            $directoryRead = $this->readFactory->create($path);
            $content = $directoryRead->readFile('composer.json');
            $composerData = json_decode($content, true);
            return $composerData['version'] ?? '0.0.0';
        } catch (\Exception $e) {
            return '0.0.0';
        }
    }

    public function getLatestVersion()
	{
		$url = 'https://api.github.com';
		
		$options = [
			'http' => [
				'method' => 'GET',
				'header' => [
					'User-Agent: Magento2-Version-Checker',
					'Accept: application/vnd.github.v3+json'
				],
				'timeout' => 10,
				'ignore_errors' => true
			],
			'ssl' => [
				'verify_peer' => false,
				'verify_peer_name' => false
			]
		];

		try {
			$context = stream_context_create($options);
			$response = file_get_contents($url, false, $context);
			
			if ($response === false) {
				return null;
			}

			$data = json_decode($response, true);
			
			// Wyciągamy pierwszy tag z listy [0]['name']
			if (is_array($data) && isset($data[0]['name'])) {
				return ltrim($data[0]['name'], 'v');
			}
		} catch (\Exception $e) {
			return null;
		}
		return null;
	}

}
?>