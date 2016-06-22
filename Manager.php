<?php
/**
 * Created by PhpStorm.
 * User: execut
 * Date: 17.06.16
 * Time: 15:43
 */

namespace execut\backup;


use yii\base\Component;

class Manager extends Component
{

    /**
     * Host for ftp
     *
     * @var string
     */
    public $host = 'localhost';

    /**
     * User name for ftp
     *
     * @var string
     */
    public $login = 'root';

    /**
     * Password for ftp
     *
     * @var string
     */
    public $password = 'anonymous';

    /**
     * @var int
     */
    public $port = 21;

    /**
     * @var int
     */
    public $timeout = 60;

    /**
     * @var bool
     */
    public $ssl = false;

    /**
     * Main backups folder on ftp
     *
     * @var string
     */
    public $dir = 'backup';

    /**
     * Subfolder prefix name for ftp. Following a prefix is put date. For example: "backup_Y-m-d".
     *
     * @var string
     */
    public $folderPrefix = 'backup';

    /**
     * Subfolder prefix name for ftp. Following a prefix is put date. For example: "backup_Y-m-d".
     *
     * @var string
     */
    public $prefix = 'backup';
    public $tmpDir = null;

    protected $cacheDir = null;
    protected $connection = null;
    protected $currentFileNbr = null;
    protected $currentFileName = null;
    public function uploadFiles($files) {
        $this->currentFileNbr = 0;
        $ftp = $this->getConnection();
        $folder = $this->folderPrefix . '_' . date('Y-m-d H:i:s');
        if (!$ftp->isDir($folder)) {
            $ftp->mkdir($folder);
        }

        $ftp->chdir($folder);
        foreach ($uploadedFiles as $uploadedFile) {
            $this->uploadFile($uploadedFile);
        }
    }
    
    public function downloadFiles($version = null) {
        $ftp = $this->getConnection();
        if ($version === null) {
            $folders = $ftp->nlist('.', false, function (&$files) {
                foreach ($files as $key => $file) {
                    if (strpos($file, $this->folderPrefix) !== 2) {
                        unset($files[$key]);
                    }
                }
            });

            if (empty($folders)) {
                throw new Exception('Not found backups directory ' . $this->dir . '/' . $this->folderPrefix . '_Y-m-d H:i:s');
            }

            $folder = array_pop($folders);
            $ftp->chdir($folder);
        } else {
            $folder = $this->folderPrefix . '_' . $version;
            if (!$ftp->chdir($folder)) {
                throw new Exception('Folder with version ' . $version . ' ' . $folder . ' not found');
            }
        }

        $downlodedFiles = $ftp->nlist();
        $result = [];
        foreach ($downlodedFiles as $file) {
            $filePath = $this->getTmpDir() . '/' . $file;
            $handle = fopen($filePath, 'w');
            $ftp->fget($handle, $file, FTP_BINARY);
            $result[] = $filePath;
        }

        return $result;
    }

    /**
     * Get cache dir
     */
    public function getTmpDir() {

        $cacheDir = \yii::getAlias('@runtime').'/'.$this->tmpDir;
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir);
        }

        return $cacheDir;
    }

    /**
     * @param $ftp
     */
    protected function goToBackupDir($ftp)
    {
        if (!$ftp->isDir($this->dir)) {
            $ftp->mkdir($this->dir);
        }

        $ftp->chdir($this->dir);
    }

    /**
     * @return \yii2mod\ftp\FtpClient
     * @throws \yii2mod\ftp\FtpException
     */
    protected function getConnection()
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $ftp = new \yii2mod\ftp\FtpClient();
        $ftp->connect($this->host, $this->ssl, $this->port, $this->timeout);
        $ftp->pasv(true);
        $ftp->login($this->login, $this->password);
        $this->goToBackupDir($ftp);

        return $this->connection = $ftp;
    }

    /**
     * @param $file
     * @param $key
     * @param $ftp
     * @param $uploadedFile
     */
    protected function uploadFile($uploadedFile)
    {
        $ftp = $this->getConnection();
        $hasError = true;
        $tryCount = 0;
        while ($hasError && $tryCount != 9) {
            try {
                $ftpName = basename($uploadedFile);
                $ftp->fput($ftpName, fopen($uploadedFile, 'r'), FTP_BINARY);
                $ftpFileSize = $ftp->size($ftpName);
                $realFileSize = filesize($uploadedFile);
                if ($ftpFileSize !== $realFileSize) {
                    $lastError = 'Size of file ' . $ftpName . ' is ' . $ftpFileSize . ' but real is ' . $realFileSize;
                    $tryCount++;
                } else {
                    $hasError = false;
                }
            } catch (Exception $e) {
                $tryCount++;
                $lastError = mb_convert_encoding($e->getMessage(), 'utf8', 'cp1251');
            }
        }

        if ($hasError) {
            throw new Exception($lastError);
        }
    }

    /**
     * @return bool|string
     */
    protected function getUploadedFileName()
    {
        if ($this->currentFileName === null) {
            $this->currentFileName = date('H_i_s');
        }

        return $this->currentFileName;
    }
}