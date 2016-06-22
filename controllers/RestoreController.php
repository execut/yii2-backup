<?php
/**
 * Created by PhpStorm.
 * User: execut
 * Date: 17.06.16
 * Time: 14:48
 */

namespace execut\backup\controllers;

use execut\backup\Manager;
use execut\yii\bash\Command;
use yii\console\Controller;

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
            '{dbname}{moduleId}'
        ],
    ];

    public function actionIndex($version = null) {
        $manager = $this->getManager();
        $files = $manager->downloadFiles($version);
        $resultFile = $manager->getTmpDir() . '/result' . $this->module->id . '.zip';
        $command = new Command([
            'params' => 'cat {files} > ' . $resultFile,
            'values' => [
                'files' => $files,
            ]
        ]);
        $command->execute();
        $command = new Command([
            'params' => 'unzip ' . $resultFile,
            'values' => [
                'files' => $files,
            ],
        ]);
        $command->execute();
        foreach ($this->module->dbKeys as $key) {
            $file = \yii::getAlias('@runtime') . '/' . $this->module->id . '-' . $key . '.sql';
            $command = $this->extractCommandFromParams($key, $file);
            var_dump((string) $command);
            exit;
            $command->execute();

            $this->dumpFiles[] = $file;
        }

        var_dump($files);
        exit;
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