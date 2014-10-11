<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 10/10/14
 * Time: 4:23 PM
 */

if ( ! file_exists($file = __DIR__.'/../vendor/autoload.php')) {
    echo "You must install the dependencies using:\n";
    echo "    composer install --dev\n";
    exit(1);
}

$configParsed = false;

if(!isset($argv[1]) || !is_file($argv[1])){
    echo "Error!No config specified\nUsage: $ php Backup.php sample.json\n";
    exit(0);
}

$configPath = $argv[1];

$loader = require_once $file;

$backup = new \Purekid\Icbackup\Backup();

$backup->configPath = $configPath;

$backup->run();


