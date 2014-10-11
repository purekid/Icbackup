<?php

if ( ! file_exists($file = __DIR__.'/../vendor/autoload.php')) {
    echo "You must install the dev dependencies using:\n";
    echo "    composer install --dev\n";
    exit(1);
}

$loader = require_once $file;

$map = array(

    );
$loader->addClassMap($map);

$loader->add('Purekid\Icbackup\Test', __DIR__);
