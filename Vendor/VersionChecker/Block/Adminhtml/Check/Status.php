<?php
namespace Vendor\VersionChecker\Block\Adminhtml\Check;

use Magento\Backend\Block\Template;
use Vendor\VersionChecker\Model\Service\VersionService;

class Status extends Template
{
    private $versionService;

    public function __construct(
        Template\Context $context,
        VersionService $versionService,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->versionService = $versionService;
    }

    public function getCurrentVersion()
    {
        return $this->versionService->getCurrentVersion();
    }

    public function getLatestVersion()
    {
        return $this->versionService->getCachedLatestVersion();
    }

    public function getCheckerCurrentVersion()
    {
        return $this->versionService->getSelfCurrentVersion();
    }

    public function getCheckerLatestVersion()
    {
        return $this->versionService->getSelfCachedLatestVersion();
    }

    public function isEnabled()
    {
        return $this->versionService->isEnabled();
    }

    public function getUpdateUrl($version)
    {
        return $this->getUrl('*/check/index', ['update_to' => $version]);
    }
}
