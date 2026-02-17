<?php
namespace Vendor\VersionChecker\Controller\Adminhtml\Check;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;

class Index extends Action
{
    protected $directoryList;

    public function __construct(Context $context, DirectoryList $directoryList) {
        parent::__construct($context);
        $this->directoryList = $directoryList;
    }

    public function execute() {
        $targetPath = $this->directoryList->getPath('app') . '/code/GlobalPayments/PaymentGateway';
        $composerFile = $targetPath . '/composer.json';
        $currentVersion = '0.0.0';

        if (file_exists($composerFile)) {
            $composerData = json_decode(file_get_contents($composerFile), true);
            $currentVersion = $composerData['version'] ?? '0.0.0';
        }

        $latestVersion = $currentVersion;
        $repoUrl = "https://github.com/globalpayments/magento2-2.0-plugin";
        
        // Pobieranie wersji przez GIT
        $gitCmd = "git ls-remote --tags " . escapeshellarg($repoUrl) . " | grep -oP 'refs/tags/v?\\K[0-9]+\\.[0-9]+\\.[0-9]+' | sort -V | tail -n 1";
        $gitOutput = shell_exec($gitCmd);
        
        if ($gitOutput) {
            $latestVersion = trim($gitOutput);
        }

        $updateTo = $this->getRequest()->getParam('update_to');
        if ($updateTo) { 
            return $this->runUpdate($updateTo, $targetPath, $repoUrl); 
        }

        $currentVersion = trim($currentVersion);
        $latestVersion = trim($latestVersion);

        if (version_compare($currentVersion, $latestVersion, '<')) {
            $installUrl = $this->getUrl('*/*/*', ['update_to' => $latestVersion]);
            $this->messageManager->addWarning(__("WYKRYTO AKTUALIZACJĘ! Nowa wersja: <b>%1</b> (Twoja: %2).<br/><a href='%3' style='background:#eb5202; color:#fff; padding:8px 15px; text-decoration:none; border-radius:3px; display:inline-block; margin-top:10px; font-weight:bold;'>INSTALUJ AKTUALIZACJĘ I URUCHOM SETUP:UPGRADE</a>", $latestVersion, $currentVersion, $installUrl));
        } else {
            $this->messageManager->addSuccess(__("Wtyczka Global Payments (Wersja %1) jest aktualna.", $currentVersion));
        }

        return $this->resultRedirectFactory->create()->setPath('admin/dashboard/index');
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
}
