# yii2-backup
Command for backup yii2 project databases and gitignored files.

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

### Install

Either run

```
$ php composer.phar require execut/yii2-backup "dev-master"

### Configuration
For usage add in console this config and modify it:
```php
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
        ],
...
```
