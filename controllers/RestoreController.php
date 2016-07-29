<?php
/**
 * Created by PhpStorm.
 * User: execut
 * Date: 17.06.16
 * Time: 14:48
 */

namespace execut\backup\controllers;

use execut\backup\Manager;
use execut\backup\Module;
use execut\yii\bash\Command;
use yii\console\Controller;

/**
 * Class RestoreController
 * @package execut\backup\controllers
 * @property Module $module
 */
class RestoreController extends Controller
{

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

    ///usr/lib/postgresql/9.4/bin/bdr_dump -Fp -U postgres detalika_3 -f /tmp/data.sql --data-only
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
            'cat {file} | ',
            'PGPASSWORD="{password}" psql',
            'U ' => '{user}',
            'h ' => '{host}',
            'p ' => '{port}',
            '{dbname}'
        ],
    ];

    public $createDbCommands = [];
    protected $defaultCreateDbCommand = [
        'pgsql' => [
            'PGPASSWORD="{password}" createdb',
            'U ' => '{user}',
            'h ' => '{host}',
            'p ' => '{port}',
            '{dbname}'
        ]
    ];

    public function actionIndex($version = null) {
//        $manager = $this->getManager();
//        $files = $manager->downloadFiles($version);
//        $this->implodeArhive($files);
//        $this->extractArhive();
        $this->applyDumps();
//        $this->moveFolders();
    }

    protected function moveFolders() {
        $folders = $this->module->folders;
        foreach ($folders as $folder) {
            $fromDir = $manager->getTmpDir() . '/' . $folder;
            $toDir = $folder;
            $rmCommand = new Command([
                'params' => 'rm -Rf {toDir}/*'
            ]);
            $rmCommand->execute();

            $moveCommand = new Command([
                'params' => 'mv -Rf {fromDir} {toDir}',
                'values' => [
                    'fromDir' => $fromDir,
                    'toDir' => $toDir,
                ],
            ]);
            $moveCommand->execute();
        }
    }

    /**
     * @return Manager
     */
    protected function getManager()
    {
        $manager = $this->module->manager;
        return $manager;
    }

    /**
     * @param $key
     * @param $file
     * @return string
     * @throws Exception
     */
    protected function extractCommandFromParams($key, $file, $commandsParams = null)
    {
        if ($commandsParams === null) {
            $commandsParams = array_merge($this->defaultDumpCommands, $this->dumpCommands);
        }

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
            'moduleId' => $this->module->id
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

    /**
     * @param $resultFile
     * @param $files
     * @return Command
     */
    protected function implodeArhive($files)
    {
        $resultFile = $this->getResultFile();
        $command = new Command([
            'params' => 'cat {files} > ' . $resultFile,
            'values' => [
                'files' => $files,
            ]
        ]);
        return $command->execute();
    }

    /**
     * @param $resultFile
     * @param $files
     * @return Command
     */
    protected function extractArchive()
    {
        $resultFile = $this->getResultFile();
        $command = new Command([
            'params' => 'unzip ' . $resultFile,
        ]);
        return $command->execute();
    }

    protected function applyDumps()
    {
        foreach ($this->module->dbKeys as $key) {
            $file = \yii::getAlias('@runtime') . '/' . $this->module->id . '-' . $key . '.sql';
            $createDbCommand = $this->extractCommandFromParams($key, $file, array_merge($this->defaultCreateDbCommand, $this->createDbCommands));
            $createDbCommand->execute();

            $applyDbCommand = $this->extractCommandFromParams($key, $file);
            $applyDbCommand->execute();
        }
    }

    /**
     * @param $manager
     * @return string
     */
    protected function getResultFile()
    {
        $resultFile = $manager->getTmpDir() . '/result' . $this->module->id . '.zip';
        return $resultFile;
    }
}