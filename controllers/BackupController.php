<?php
/**
 * Command for backup yii2 project databases and gitignored files and upload it to ftp.
 */
namespace execut\backup\controllers;

use execut\yii\helpers\ArrayHelper;
use yii\baseException;
use yii\console\Controller;
use yii\db\Connection;
use yii\helpers\Console;
use yii\helpers\FileHelper;

class BackupController extends Controller {
    /**
     * Redefine default dump commands.
     *
     * Value in commands is used by defined order. Params is setted by associative values. For example param"-u user"
     * setted by define pair "u" => "user".
     *
     * @var array
     */
    public $dumpCommands = [];

    /**
     * Forders for adding in backup
     *
     * @var string
     */
    public $folders = [
        'frontend/web/files',
        'frontend/web/i',
    ];

    /**
     * Db components keys for dumps
     *
     * @var array
     */
    public $dbKeys = [
        'db',
    ];

    /**
     * Host for ftp
     *
     * @var string
     */
    public $ftpHost = 'localhost';

    /**
     * User name for ftp
     *
     * @var string
     */
    public $ftpLogin = 'root';

    /**
     * Password for ftp
     *
     * @var string
     */
    public $ftpPassword = 'anonymous';

    /**
     * @var int
     */
    public $ftpPort = 21;

    /**
     * @var int
     */
    public $ftpTimeout = 60;

    /**
     * @var bool
     */
    public $ftpSsl = false;

    /**
     * Main backups folder on ftp
     *
     * @var string
     */
    public $ftpDir = 'backup';

    /**
     * Subfolder prefix name for ftp. Following a prefix is put date. For example: "backup_Y-m-d".
     *
     * @var string
     */
    public $folderPrefix = 'backup';

    /**
     * Admin email for errors reporting.
     *
     * @var string
     */
    public $adminMail = 'root@localhost.localdomain';

    /**
     * Backup arhive is splitted by parts for reliability copy process.
     *
     * @var string
     */
    public $filePartSize = '300MiB';

    /**
     * Cache dir name.
     *
     * @var string
     */    
    public $cacheDir = 'backups';

    protected $dumpFiles = [];

    /**
     * Default dump commands definitions. Keys of array this name of database driver.
     *
     * @var array
     */
    protected $defaultDumpCommands = [
        'mysql' => [
            'mysqldump',
            'u ' => '{user}',
            'p' => '{password}',
            'h ' => '{host}',
            'P ' => '{port}',
            '{dbname} > {file}',
        ],
        'pgsql' => [
            'PGPASSWORD="{password}" pg_dump',
            'U ' => '{user}',
            'h ' => '{host}',
            'p ' => '{port}',
            '{dbname} > {file}'
        ],
    ];

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), [
            'dumpCommands', 'folders', 'dbKeys', 'ftpHost', 'ftpLogin', 'ftpPassword', 'ftpPort', 'ftpTimeout',
        ]);
    }

    /**
     * Index action
     */
    public function actionIndex() {
        try {
            $this->makeDbDumps();

            $uploadedFiles = $this->zipFiles();
            $this->getManager()->uploadFiles($uploadedFiles);
        } catch (Exception $e) {
            $this->sendError($e->getMessage());
        }

        $this->clearFiles();
    }

    protected $manager = null;
    protected function getManager() {
        if ($this->manager !== null) {
            return $this->manager;
        }

        $manager = $this->module->manager;
        $params = array_filter([
            'dir' => $this->ftpDir,
            'host' => $this->ftpHost,
            'login' => $this->ftpLogin,
            'password' => $this->ftpPassword,
            'port' => $this->ftpPort,
            'ssl' => $this->ftpSsl,
            'timeout' => $this->ftpTimeout,
        ]);
        \yii::configure($manager, $params);

        return $this->manager = $manager;
    }

    /**
     * Create sql databases dump files
     *
     * @throws Exception
     */
    public function makeDbDumps() {
        foreach ($this->dbKeys as $key) {
            $file = \yii::getAlias('@runtime') . '/dump-' . $key . '.sql';
            $command = $this->extractCommandFromParams($key, $file);

            exec($command);

            $this->dumpFiles[] = $file;
            $this->folders[] = $file;
        }
    }

    /**
     * Zipping list of files
     *
     * @param $out
     * @return array
     */
    protected function zipFiles()
    {
        $cacheDir = $this->getCacheDir();
        $zipFile = $cacheDir.'/'.date('Ymd-His');
        $zipCommand = 'zip - ' . implode(' ', $this->folders) . ' | split -b ' . $this->filePartSize . ' - ' . $zipFile;
        exec($zipCommand, $out);

        $files = FileHelper::findFiles( $cacheDir.'/');
        $uploadedFiles = [];

        foreach($files as $file) {
            $this->dumpFiles[] = $uploadedFiles[] = $file;
        }
 
        return $uploadedFiles;
    }
    
    /**
     * Get cache dir
     */
    public function getCacheDir() {
        
        $cacheDir = \yii::getAlias('@runtime').'/'.$this->cacheDir;
        if (!file_exists($cacheDir)) {
            mkdir($cacheDir);
        }
        
        return $cacheDir;
        
    }

    /**
     * Clear temporary files
     */
    protected function clearFiles()
    {
        foreach ($this->dumpFiles as $dumpFile) {
            unlink($dumpFile);
        }
    }

    /**
     * Sending dump error report
     *
     * @param $error
     */
    public function sendError($error)
    {
        mail($this->adminMail, 'Backup errors ' . date('Y-m-d H:i:s'), $error);
        $this->stderr($error . "\n");
    }

    /**
     * @param $key
     * @param $file
     * @return string
     * @throws Exception
     */
    protected function extractCommandFromParams($key, $file)
    {
        /**
         * @var Connection $db
         */
        $db = \yii::$app->$key;
        $params = [
            '{user}' => $db->username,
            '{password}' => $db->password,
            '{file}' => $file,
            '{host}' => '',
            '{port}' => '',
        ];

        $driverName = $db->driverName;
        $dsn = $db->dsn;
        $dsnParts = explode(';', str_replace($driverName . ':', '', $dsn));
        if (count($dsnParts) < 2) {
            throw new Exception('Wrong dsn "' . $dsn . '" in "' . $key . '" db component');
        }

        foreach ($dsnParts as $part) {
            $parts = explode('=', $part);
            $paramKey = $parts[0];
            $paramValue = $parts[1];
            $paramKey = '{' . $paramKey . '}';
            $params[$paramKey] = $paramValue;
        }

        $commandsParams = array_merge($this->defaultDumpCommands, $this->dumpCommands);
        if (!array_key_exists($driverName, $commandsParams)) {
            throw new Exception('Driver by name "' . $driverName . '" is not supported, set it in "dumpCommands" param');
        }

        $commandParams = $commandsParams[$driverName];
        $command = '';
        foreach ($commandParams as $paramKey => $paramValue) {
            if (is_int($paramKey)) {
                $paramKey = '';
            } else {
                $paramKey = '-' . $paramKey;
                if (empty($params[$paramValue]) ) {
                    continue;
                }
            }

            $command .= ' ' . $paramKey . $paramValue;
        }

        $command = strtr($command, $params);

        return $command;
    }
}