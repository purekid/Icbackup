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

        if(isset($this->config['timezone'])){
            date_default_timezone_set($this->config['timezone']);
        }

        $this->setupLogger();

        $this->initTasks();

        $res = $this->processTasks();

        $end = microtime(true);

        $runtime = round($end-$start,2);

        $output = "Backup Job DONE! SPENT:{$runtime}s. \n";

        $output .= "--------------\nResult ";

        $output .= print_r($res,true);

        $output .= "\n";

        echo $output;

        $this->logger->addInfo($output);

    }

    public function initTasks(){

        foreach($this->config['tasks'] as $task_config){
            if(isset($task_config['enable']) && !$task_config['enable']) continue;
            $task = new Task($task_config);
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
