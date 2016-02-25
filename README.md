# yii2-backup
Command for reserving yii2 project databases and gitignored files. After starting command databases dumps and project
folders specified in the settings is compressed, it split in pieces and fill them to the specified ftp.

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

### Install

Either run

```
$ php composer.phar require execut/yii2-backup "dev-master"

### Configuration
For usage add in console this config and modify it:

<?php
...
    'controllerMap' => [
        'backup' => [
            'class' => 'execut\backup\controllers\BackupController',
            'ftpDir' => 'backups',
            'ftpHost' => 'localhost',
            'ftpLogin' => 'login',
            'ftpPassword' => 'password',
            'folderPrefix' => 'backups-production',
            'dbKeys' => [
                'db',
                'dbOther',
            ],
            'adminMail' => 'root@localhost.com',
            'filePartSize' => '300MiB', // Split unix command part size
        ],
...


After configuration, simple add task to cron:
```
0 6 * * *	root	cd /projectFoder && ./yii backup
```