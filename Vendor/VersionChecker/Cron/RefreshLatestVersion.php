<?php
namespace Vendor\VersionChecker\Cron;

use Vendor\VersionChecker\Model\Service\VersionService;

class RefreshLatestVersion
{
    private $versionService;

    public function __construct(VersionService $versionService)
    {
        $this->versionService = $versionService;
    }

    public function execute()
    {
        if (!$this->versionService->isEnabled()) {
            return;
        }
        $this->versionService->refreshLatestVersion();
        $this->versionService->refreshSelfLatestVersion();
    }
}
