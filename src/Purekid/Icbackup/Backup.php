<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 14-10-9
 * Time: 10:30
 */

namespace Purekid\Icbackup;

use Monolog\Handler\FirePHPHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Backup
{

    protected $logger = null;

    protected $config = null;

    protected $tasks = [];

    public $configPath = null;

    public function run()
    {

        $start = microtime(true);

        $this->parseConfig();

        $this->setupLogger();

        $this->initTasks();

        $res = $this->processTasks();

        $end = microtime(true);

        $runtime = round($end-$start,2);

        echo "Backup Job DONE! SPENT:{$runtime}s. \n";

        echo "--------------\nResult ";
        print_r($res);

        echo "\n";

        $this->logger->addInfo('All tasks done,have a nice day! :P');

    }

    public function initTasks(){

        foreach($this->config['sections'] as $section){
            if(isset($section['enable']) && !$section['enable']) continue;
            $task = new Task($section);
            $task->logger = $this->logger;
            $this->tasks[] = $task;
        }

    }

    public function processTasks(){

        $res = [];

        foreach($this->tasks as $task){
            $resTask = $task->process();
            $res[$resTask['NAME']] = $resTask;
        }

        return $res;

    }

    protected function parseConfig()
    {

        $config = file_get_contents( $this->configPath );

        if($config) {
            $config = json_decode($config,true);
        }

        $this->config = $config;

    }

    protected function setupLogger()
    {
        $logger = new Logger('backup');

        $logName = basename($this->config['log']);
        $logDir = substr($this->config['log'],0,strpos($this->config['log'],$logName));

        if(!is_dir($logDir)){
            mkdir($logDir,0755,true);
        }

        $logger->pushHandler(new StreamHandler($this->config['log'], Logger::DEBUG));
        $logger->pushHandler(new FirePHPHandler());

        $this->logger = $logger;

    }

    protected function pathRoot($path)
    {
        $root_path = realpath( __DIR__.DIRECTORY_SEPARATOR. '..' .DIRECTORY_SEPARATOR. '..'.DIRECTORY_SEPARATOR. '..');

        if($path){
            return $root_path . DIRECTORY_SEPARATOR . $path;
        }

        return $root_path;
    }

} 