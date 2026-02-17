<?php
namespace Vendor\VersionChecker\Controller\Adminhtml\Check;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\View\Result\PageFactory;
use Vendor\VersionChecker\Model\Service\VersionService;

class Index extends Action
{
    protected $directoryList;
    protected $pageFactory;
    protected $versionService;

    public function __construct(Context $context, DirectoryList $directoryList, PageFactory $pageFactory, VersionService $versionService) {
        parent::__construct($context);
        $this->directoryList = $directoryList;
        $this->pageFactory = $pageFactory;
        $this->versionService = $versionService;
    }

    public function execute() {
        $targetPath = $this->directoryList->getPath('app') . '/code/GlobalPayments/PaymentGateway';
        $repoUrl = "https://github.com/" . $this->versionService->getRepoOwner() . "/" . $this->versionService->getRepoName();

        $updateTo = $this->getRequest()->getParam('update_to');
        if ($updateTo) { 
            return $this->runUpdate($updateTo, $targetPath, $repoUrl); 
        }

        $selfUpdateTo = $this->getRequest()->getParam('update_self_to');
        if ($selfUpdateTo) {
            $selfTargetPath = $this->directoryList->getPath('app') . '/code/Vendor/VersionChecker';
            $selfRepoUrl = "https://github.com/" . \Vendor\VersionChecker\Model\Service\VersionService::SELF_REPO_OWNER . "/" . \Vendor\VersionChecker\Model\Service\VersionService::SELF_REPO_NAME;
            return $this->runSelfUpdate($selfUpdateTo, $selfTargetPath, $selfRepoUrl);
        }

        $resultPage = $this->pageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Global Payments Module Status'));
        return $resultPage;
    }

    private function runUpdate($version, $targetPath, $repoUrl) {
        $tmpDir = $this->directoryList->getPath('var') . '/gp_clone_tmp';
        $magentoRoot = $this->directoryList->getRoot();
        
        try {
            if (is_dir($tmpDir)) shell_exec("rm -rf " . escapeshellarg($tmpDir));
            
            // 1. Klonowanie plików
            $cloneCmd = "git clone --branch v" . escapeshellarg($version) . " --depth 1 " . escapeshellarg($repoUrl) . " " . escapeshellarg($tmpDir) . " 2>&1";
            shell_exec($cloneCmd);
            
            if (!file_exists($tmpDir . '/composer.json')) {
                throw new \Exception("Błąd klonowania GIT. Sprawdź połączenie.");
            }
            
            // 2. Podmiana plików
            shell_exec("rm -rf " . escapeshellarg($tmpDir . '/.git'));
            shell_exec("cp -R " . escapeshellarg($tmpDir) . "/* " . escapeshellarg($targetPath) . "/");
            shell_exec("rm -rf " . escapeshellarg($tmpDir));
            
            // 3. AUTOMATYCZNE SETUP:UPGRADE
            // Używamy pełnej ścieżki do PHP i bin/magento
            $phpPath = PHP_BINARY;
            $upgradeCmd = "cd " . escapeshellarg($magentoRoot) . " && $phpPath bin/magento setup:upgrade 2>&1";
            $upgradeOutput = shell_exec($upgradeCmd);
            
            // 4. CZYSZCZENIE CACHE
            shell_exec("cd " . escapeshellarg($magentoRoot) . " && $phpPath bin/magento cache:clean 2>&1");
            
            $this->messageManager->addSuccess(__("SUKCES! Wersja %1 wgrana i aktywowana. <br/><b>Log systemowy:</b><pre>%2</pre>", $version, $upgradeOutput));
            
        } catch (\Exception $e) { 
            $this->messageManager->addError(nl2br($e->getMessage())); 
        }
        
        return $this->resultRedirectFactory->create()->setPath('admin/dashboard/index');
    }

    private function runSelfUpdate($version, $targetPath, $repoUrl) {
        $tmpDir = $this->directoryList->getPath('var') . '/vendor_versionchecker_tmp';
        $magentoRoot = $this->directoryList->getRoot();
        
        try {
            if (is_dir($tmpDir)) shell_exec("rm -rf " . escapeshellarg($tmpDir));
            $cloneCmd = "git clone --depth 1 " . escapeshellarg($repoUrl) . " " . escapeshellarg($tmpDir) . " 2>&1";
            shell_exec($cloneCmd);
            $candidatePaths = [
                $tmpDir . '/Vendor/VersionChecker',
                $tmpDir . '/VersionChecker',
                $tmpDir
            ];
            $sourceModulePath = null;
            foreach ($candidatePaths as $candidate) {
                $composer = $candidate . '/composer.json';
                if (!file_exists($composer)) {
                    continue;
                }
                $json = json_decode(@file_get_contents($composer), true);
                if (!is_array($json)) {
                    continue;
                }
                $isM2Module = isset($json['type']) && $json['type'] === 'magento2-module';
                $psr4 = isset($json['autoload']['psr-4']) ? $json['autoload']['psr-4'] : [];
                $hasNamespace = is_array($psr4) && (isset($psr4['Vendor\\VersionChecker\\']) || isset($psr4['Vendor\\\\VersionChecker\\\\']));
                $isExpectedPackage = isset($json['name']) && stripos($json['name'], 'vendor-versionchecker') !== false;
                if ($isM2Module && ($hasNamespace || $isExpectedPackage)) {
                    $sourceModulePath = $candidate;
                    break;
                }
            }
            if ($sourceModulePath === null) {
                if (is_dir($tmpDir . '/Vendor/VersionChecker')) {
                    $sourceModulePath = $tmpDir . '/Vendor/VersionChecker';
                }
            }
            if ($sourceModulePath === null) {
                throw new \Exception("Nie znaleziono poprawnej struktury modułu Version Checker w repozytorium.");
            }
            shell_exec("rm -rf " . escapeshellarg($tmpDir . '/.git'));
            if (!is_dir($targetPath)) {
                shell_exec("mkdir -p " . escapeshellarg($targetPath));
            }
            shell_exec("cp -R " . escapeshellarg($sourceModulePath) . "/* " . escapeshellarg($targetPath) . "/");
            shell_exec("rm -rf " . escapeshellarg($tmpDir));
            $phpPath = PHP_BINARY;
            $upgradeCmd = "cd " . escapeshellarg($magentoRoot) . " && $phpPath bin/magento setup:upgrade 2>&1";
            $upgradeOutput = shell_exec($upgradeCmd);
            shell_exec("cd " . escapeshellarg($magentoRoot) . " && $phpPath bin/magento cache:clean 2>&1");
            $this->messageManager->addSuccess(__("SUKCES! Moduł Version Checker w wersji %1 został wgrany i aktywowany. <br/><b>Log systemowy:</b><pre>%2</pre>", $version, $upgradeOutput));
        } catch (\Exception $e) { 
            $this->messageManager->addError(nl2br($e->getMessage())); 
        }
        
        return $this->resultRedirectFactory->create()->setPath('admin/dashboard/index');
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Vendor_VersionChecker::check');
    }
}
