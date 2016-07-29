<?php
/**
 * Command for backup yii2 project databases and gitignored files and upload it to ftp.
 */
namespace execut\backup\controllers;

use execut\backup\Manager;
use execut\yii\bash\Command;
use execut\yii\helpers\ArrayHelper;
use yii\baseException;
use yii\console\Controller;
use yii\db\Connection;
use yii\helpers\Console;
use yii\helpers\FileHelper;

class CreateController extends Controller {

    /**
     * Redefine default dump commands.
     *
     * Value in commands is used by defined order. Params is setted by associative values. For example param"-u user"
     * setted by define pair "u" => "user".
     *
     * @var array
     */
    public $dumpCommands = [];

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
     * Index action
     */
    public function actionIndex() {
        $this->dumpFiles = $this->module->folders;
        try {
            $this->makeDbDumps();

            $uploadedFiles = $this->zipFiles();
            $this->getManager()->uploadFiles($uploadedFiles);
        } catch (Exception $e) {
            $this->sendError($e->getMessage());
        }

        $this->clearFiles();
    }

    /**
     * @return Manager
     */
    protected function getManager() {
        return $this->module->manager;
    }

    /**
     * Create sql databases dump files
     *
     * @throws Exception
     */
    public function makeDbDumps() {
        foreach ($this->module->dbKeys as $key) {
            $file = \yii::getAlias('@runtime') . '/' . $this->module->id . '-' . $key . '.sql';
            $command = $this->extractCommandFromParams($key, $file);
            $command->execute();

            $this->dumpFiles[] = $file;
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
        $zipFile = $cacheDir.'/' . $this->module->key . '.zip';
        $zipCommand = \yii::createObject(Command::class, [
            'params' => 'zip - {folders} | split -d -b {filePartSize} - {zipFile}',
            'values' => [
                'folders' => $this->dumpFiles,
                'filePartSize' => $this->module->filePartSize,
                'zipFile' => $zipFile
            ],
        ]);

        $zipCommand->execute();

        $files = FileHelper::findFiles($cacheDir.'/');
        $uploadedFiles = [];

        foreach($files as $file) {
            $this->dumpFiles[] = $uploadedFiles[] = $file;
        }

        return $uploadedFiles;
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
        mail($this->module->adminMail, 'Backup errors ' . date('Y-m-d H:i:s'), $error);
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
            'user' => $db->username,
            'password' => $db->password,
            'file' => $file,
            'host' => '',
            'port' => '',
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
            $params[$paramKey] = $paramValue;
        }

        $commandsParams = array_merge($this->defaultDumpCommands, $this->dumpCommands);
        if (!array_key_exists($driverName, $commandsParams)) {
            throw new Exception('Driver by name "' . $driverName . '" is not supported, set it in "dumpCommands" param');
        }

        $commandParams = $commandsParams[$driverName];
        $command = new Command([
            'params' => $commandParams,
            'values' => $params,
        ]);

        return $command;
    }
}