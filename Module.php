<?php
/**
 * Created by PhpStorm.
 * User: execut
 * Date: 21.06.16
 * Time: 13:15
 */

namespace execut\backup;


class Module extends \yii\base\Module
{
    /**
     * The namespace that controller classes are in.
     *
     * @var string
     * @access public
     */
    public $controllerNamespace = 'execut\backup\controllers';

    public $filePartSize = '300MiB';

    public $folders = [];

    public $adminMail = 'root@localhost';
    public $dbKeys = [
        'db'
    ];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->manager->tmpDir === null) {
            $this->manager->tmpDir = $this->id;
        }

        // custom initialization code goes here
    }
}