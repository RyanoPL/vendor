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

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Vendor_VersionChecker::check');
    }
}
