<?php

// TBD - Use composer
// require dirname(__DIR__, 2). '/vendor/autoload.php';

// The working directory for CLI mode can change. Use __DIR__ relative paths.
require dirname(__DIR__, 2).'/vendor/openkm/sdk4php/src/openkm/OpenKM.php';

// ini_set('display_errors', true);
// error_reporting(E_ALL);

use openkm\OKMWebservices;
use openkm\OKMWebServicesFactory;
use openkm\OpenKM;
use openkm\bean\Folder;

class ExampleFolder {
    
    const HOST = "http://localhost:8080/OpenKM/";
    const USER = "okmAdmin";
    const PASSWORD = "admin";
    
    private $ws;
    
    public function __construct() {
        $this->ws = OKMWebServicesFactory::build(self::HOST, self::USER, self::PASSWORD);
    }
    
    public function testCreateFolder() {
        try {
            $fld = new Folder();
            $fld->setPath("/okm:root/SDK4PHP/test");
            $folder = $this->ws->createFolder($fld);
            var_dump($folder);
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testCreateFolderSimple() {
        try {
            $folder = $this->ws->createFolderSimple("/okm:root/SDK4PHP/test");
            var_dump($folder);
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testGetFolderProperties() {
        try {
            $folder = $this->ws->getFolderProperties("/okm:root/SDK4PHP/test");
            var_dump($folder);
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testDeleteFolder() {
        try {
            $this->ws->deleteFolder("/okm:root/SDK4PHP/test");
            echo 'delete folder';
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testRenameFolder() {
        try {
            // Exists folder /okm:root/SDK4PHP/test
            $this->ws->renameFolder("/okm:root/SDK4PHP/test", "renamedFolder");
            // Folder has renamed to /okm:root/SDK4PHP/renamedFolder
            echo 'rename Folder';
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testMoveFolder() {
        try {
            // Exists folder /okm:root/SDK4PHP/test
            $this->ws->moveFolder("/okm:root/SDK4PHP/test", "/okm:root/SDK4PHP/tmp");
            // Folder has moved to /okm:root/SDK4PHP/tmp/test
            echo 'move Folder';
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testGetFolderChildren() {
        try {
            $folders = $this->ws->getFolderChildren("/okm:root/SDK4PHP");
            foreach ($folders as $folder) {
                var_dump($folder);
            }
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testIsValidFolder() {
        try {
            // Return false
            var_dump($this->ws->isValidFolder("/okm:root/SDK4PHP/logo.png"));
            // Return true
            var_dump($this->ws->isValidFolder("/okm:root/SDK4PHP"));
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function getFolderPathByUUID($folderUUID) {
        try {
            return ($this->ws->getFolderPath($folderUUID));
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
}

class ExampleDocument {
    
    const HOST = "http://localhost:8080/OpenKM/";
    const USER = "okmAdmin";
    const PASSWORD = "admin";
    
    private $ws;
    
    public function __construct() {
        $this->ws = OKMWebServicesFactory::build(self::HOST, self::USER, self::PASSWORD);
    }
    
    public function testCreateDocumentSimple() {
        try {
            $fileName = dirname(__FILE__) . '/files/logo.png';
            $docPath = '/okm:root/SDK4PHP/logo.png';
            $document = $this->ws->createDocumentSimple($docPath, file_get_contents($fileName));
            var_dump($document);
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testDeleteDocument() {
        try {
            $this->ws->deleteDocument('/okm:root/SDK4PHP/logo.png');
            echo 'deleted';
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testGetDocumentProperties() {
        try {
            $document = $this->ws->getDocumentProperties('/okm:root/SDK4PHP/logo.png');
            var_dump($document);
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testGetContent($method) {
        $content = $this->ws->getContent('/okm:root/SDK4PHP/logo.png');
        switch ($method) {
            case 1:
                $file = fopen(dirname(__FILE__) . '/files/logo_download.png', 'w+');
                fwrite($file, $content);
                fclose($file);
                echo 'download correct';
                break;
            case 2:
                $document = $this->ws->getDocumentProperties('/okm:root/SDK4PHP/logo.png');
                header('Expires', 'Sat, 6 May 1971 12:00:00 GMT');
                header('Cache-Control', 'max-age=0, must-revalidate');
                header('Cache-Control', 'post-check=0, pre-check=0');
                header('Pragma', 'no-cache');
                header('Content-Type: ' . $document->getMimeType());
                header('Content-Disposition: attachment; filename="' . substr($document->getPath(), strrpos($document->getPath(), '/') + 1) . '"');
                echo $content;
                break;
        }
    }
    
    public function testGetContentByVersion($method) {
        $content = $this->ws->getContentByVersion('/okm:root/SDK4PHP/logo.png', 1.1);
        switch ($method) {
            case 1:
                $file = fopen(dirname(__FILE__) . '/files/logo_download_version.png', 'w+');
                fwrite($file, $content);
                fclose($file);
                echo 'download correct';
                break;
            case 2:
                $document = $this->ws->getDocumentProperties('/okm:root/SDK4PHP/logo.png');
                header('Expires', 'Sat, 6 May 1971 12:00:00 GMT');
                header('Cache-Control', 'max-age=0, must-revalidate');
                header('Cache-Control', 'post-check=0, pre-check=0');
                header('Pragma', 'no-cache');
                header('Content-Type: ' . $document->getMimeType());
                header('Content-Disposition: attachment; filename="' . substr($document->getPath(), strrpos($document->getPath(), '/') + 1) . '"');
                echo $content;
                break;
        }
    }
    
    public function testGetDocumentChildren() {
        try {
            $documents = $this->ws->getDocumentChildren('/okm:root');
            foreach ($documents as $document) {
                var_dump($document);
            }
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testRenameDocument() {
        try {
            $document = $this->ws->renameDocument('/okm:root/SDK4PHP/logo.png', 'logo_rename.png');
            var_dump($document);
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testSetProperties() {
        try {
            $document = $this->ws->getDocumentProperties('95bb0265-f303-42d5-b485-6dfb7cc18ced');
            $document->setTitle('Logo');
            $document->setDescription('some description');
            $document->setLanguage('es');
            //Keywords
            $keywords = [];
            $keywords[] = 'test';
            $document->setKeywords($keywords);
            //Categories
            $categories = [];
            $category = $this->ws->getFolderProperties('/okm:categories/test');
            $categories[] = $category;
            $document->setCategories($categories);
            
            $this->ws->setProperties($document);
            echo 'updated';
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testCheckout() {
        try {
            $this->ws->checkout('/okm:root/SDK4PHP/logo.png');
            // At this point the document is locked for other users except for the user who executed the action
            echo 'correct';
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testCancelCheckout() {
        try {
            // At this point the document is locked for other users except for the user who executed the action
            $this->ws->cancelCheckout('/okm:root/SDK4PHP/logo.png');
            // At this point other users are allowed to execute a checkout and modify the document
            echo 'correct';
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testForceCancelCheckout() {
        try {
            // At this point the document is locked for other users except for the user who executed the action
            $this->ws->forceCancelCheckout('/okm:root/SDK4PHP/logo.png');
            // At this point other users are allowed to execute a checkout and modify the document
            echo 'correct';
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testIsCheckedOut() {
        try {
            echo "Is the document checkout:" . $this->ws->isCheckedOut('/okm:root/SDK4PHP/logo.png');
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testCheckin() {
        try {
            $fileName = dirname(__FILE__) . '/files/logo.png';
            $version = $this->ws->checkin('/okm:root/SDK4PHP/logo.png', file_get_contents($fileName), "optional some comment");
            var_dump($version);
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testGetVersionHistory() {
        try {
            $versions = $this->ws->getVersionHistory('/okm:root/SDK4PHP/logo.png');
            foreach ($versions as $version) {
                var_dump($version);
            }
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testLock() {
        try {
            $lockInfo = $this->ws->lock('/okm:root/SDK4PHP/logo.png');
            var_dump($lockInfo);
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testUnlock() {
        try {
            $this->ws->unlock('/okm:root/SDK4PHP/logo.png');
            echo 'unlock';
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testForceUnlock() {
        try {
            $this->ws->forceUnlock('/okm:root/SDK4PHP/logo.png');
            echo 'forceUnlock';
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testIsLocked() {
        try {
            echo "Is document locked:" . $this->ws->isLocked('/okm:root/SDK4PHP/logo.png');
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testGetLockInfo() {
        try {
            var_dump($this->ws->getLockInfo('/okm:root/SDK4PHP/logo.png'));
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testPurgeDocument() {
        try {
            $this->ws->purgeDocument('/okm:root/SDK4PHP/logo.png');
            echo 'purgeDocument';
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testMoveDocument() {
        try {
            $this->ws->moveDocument('/okm:root/SDK4PHP/logo.png', '/okm:root/SDK4PHP/tmp');
            echo 'moveDocument';
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testCopyDocument() {
        try {
            $this->ws->copyDocument('/okm:root/SDK4PHP/logo.png', '/okm:root/SDK4PHP/tmp');
            echo 'copyDocument';
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testRestoreVersion() {
        try {
            $this->ws->restoreVersion('/okm:root/SDK4PHP/logo.png', '1.1');
            echo 'restoreVersion';
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testPurgeVersionHistory() {
        try {
            // Version history has version 1.3,1.2,1.1 and 1.0
            $this->ws->purgeVersionHistory('/okm:root/SDK4PHP/logo.png');
            // Version history has only version 1.3
            echo 'purgeVersionHistory';
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testGetVersionHistorySize() {
        try {
            $units = array("B", "KB", "MB", "GB", "TB", "PB", "EB");
            
            $bytes = $this->ws->getVersionHistorySize('/okm:root/SDK4PHP/logo.png');
            $value = "";
            
            for ($i = 6; $i > 0; $i--) {
                $step = pow(1024, $i);
                if ($bytes > $step) {
                    $value = number_format($bytes / $step, 2) . ' ' . $units[$i];
                }
                if (empty($value)) {
                    $value = $bytes . ' ' . $units[0];
                }
            }
            echo $value;
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testIsValidDocument() {
        try {
            // Return true
            var_dump($this->ws->isValidDocument("/okm:root/SDK4PHP/logo.png"));
            // Return false
            var_dump($this->ws->isValidDocument('/okm:root'));
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function testGetDocumentPath() {
        try {
            var_dump($this->ws->getDocumentPath("8b559709-3c90-4c26-b181-5192a17362b2"));
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function getDocumentVersions($uuid) {
        try {
            return $this->ws->getVersionHistory($uuid);
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    
    public function checkFindByName() {
        try {
            $queryResults = $this->ws->findByName('test');
            foreach ($queryResults as $queryResult) {
                var_dump($queryResult);
            }
        } catch (Exception $e) {
            var_dump($e);
        }
    }
}

class clsLoader {
    
    private $host;
    private $pathIn;
    private $dmsFolder;
    
    const HOST = "http://localhost:8080/OpenKM/";
    const USER = "okmAdmin";
    const PASSWORD = "admin";
    
    private $ws;
    
    public function __construct($aaParams) {
        foreach (get_class_vars(__CLASS__) as $prop => $default) {
            if (isset($aaParams[$prop])) {
                $this->$prop = $aaParams[$prop];
            }
        }
        $this->ws = OKMWebServicesFactory::build(self::HOST, self::USER, self::PASSWORD);
    }
    
    public function loadFiles() {
        try {
            $metaDate = date('d-M-Y');
            $sel = $this->pathIn."/*";
            printf('%s - Uploading %s%s files to %s', $metaDate, $sel, $this->dmsFolder, PHP_EOL);
            foreach (glob($sel) as $filename) {
                $docPath = sprintf('/okm:root/%s/%s',$this->dmsFolder,basename($filename));
                $objNewDoc = $this->ws->createDocumentSimple($docPath, file_get_contents($filename));
                // Add meta 'src' to new document
                $this->ws->setPropertyGroupPropertiesSimple(
                    $objNewDoc->getUuid(),
                    'okg:src',
                    [
                        'okp:src.date' => $metaDate,
                        'okp:src.host' => $this->host,
                        'okp:src.path' => $this->pathIn,
                    ]
                    );
            }
        } catch (Exception $e) {
            var_dump($e);
        }
    }
}

$openkm = new OpenKM(); //autoload

// TBD : Convert to parameters
$objLoader = new clsLoader([
    'host' => 'us1121',
    'pathIn' => '/data/ss010/0_scanIn',
    'dmsFolder' => 'Staged',
]);
$objLoader->loadFiles();
exit;

$exampleFolder = new ExampleFolder();
$folderPath = $exampleFolder->getFolderPathByUUID('849882fd-796a-48ab-9bc2-5f0ba3a022dd');
echo $folderPath.PHP_EOL;

$exampleDocument = new ExampleDocument();
$docVersions = $exampleDocument->getDocumentVersions('5e5132aa-8f64-4672-ac3f-a0da20eb6a0c');
var_dump($docVersions);
