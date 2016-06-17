<?php
/**
 * Created by PhpStorm.
 * User: execut
 * Date: 17.06.16
 * Time: 15:43
 */

namespace execut\backup;


class Manager
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

    protected $connection = null;
    protected $currentFileNbr = null;
    protected $currentFileName = null;
    public function uploadFiles($files) {
        $this->currentFileNbr = 0;
        $ftp = $this->getConnection();
        $folder = $this->folderPrefix . '_' . date('Y-m-d');
        if (!$ftp->isDir($folder)) {
            $ftp->mkdir($folder);
        }

        $ftp->chdir($folder);
        foreach ($uploadedFiles as $key => $uploadedFile) {
            $this->uploadFile($key, $uploadedFile);
        }
    }


    /**
     * @param $ftp
     */
    protected function goToBackupDir($ftp)
    {
        if (!$ftp->isDir($this->ftpDir)) {
            $ftp->mkdir($this->ftpDir);
        }

        $ftp->chdir($this->ftpDir);
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
        $ftp->connect($this->ftpHost, $this->ftpSsl, $this->ftpPort, $this->ftpTimeout);
        $ftp->pasv(true);
        $ftp->login($this->ftpLogin, $this->ftpPassword);
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
        $file = $this->getUploadedFileName();
        $ftp = $this->getConnection();
        $ftpName = $file . '_' . $key . '.zip';
        $hasError = true;
        $tryCount = 0;
        while ($hasError && $tryCount != 9) {
            try {
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