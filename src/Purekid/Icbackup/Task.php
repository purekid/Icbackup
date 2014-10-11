<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 14-10-9
 * Time: 10:30
 */

namespace Purekid\Icbackup;

use Purekid\Icbackup\Thread\ThreadMulti;
use Purekid\Icbackup\Thread\ThreadSingle;

class Task
{

    public $logger = null;
    protected $threads = [];
    protected $historyName = '.history';
    protected $modifiedFiles = [];
    protected $zipSize = 0;
    protected $config = null;

    public function __construct(array $config)
    {
        $this->scp = $config['scp'];
        $this->name = $config['name'];
        $this->path = $config['dir'];
        $this->backStorage = $config['storage'];

        // 这些文件夹比对时间戳后才决定是否访问
        if(isset($config['ignoreUnmodifiedDir'])){
            $this->ignoreUnmodifiedDir = $config['ignoreUnmodifiedDir'];
        }else{
            $this->ignoreUnmodifiedDir = [];
        }

        //开启后只生成日志不执行后续的ZIP等操作
        if(isset($config['onlySaveHistory']) && $config['onlySaveHistory']){
            $this->onlySaveHistory = true;
        }else{
            $this->onlySaveHistory = false;
        }

        //是否启用多线程
        if(!isset($config['multi_thread']) || !$config['multi_thread'] || !isset($config['thread_count'])){
            $this->threadCount = 1;
        }else{
            $this->threadCount = (int) $config['thread_count'];
        }

        //需要备份的文件夹名字
        $this->rootName = basename($this->path);

    }

    public function process()
    {

        $this->logger->addInfo("Task #{$this->name} start running!");

        $this->parseHistory();

        $modified_files = $this->scanRootModified();

        $this->initThreads($modified_files);

        if(!$this->onlySaveHistory){
            $zipFilePath = $this->zip();
            if($zipFilePath){
                $this->putHistory();
                $this->pushRemote($zipFilePath);
            }

        }else{
            $this->putHistory();
        }

        $res = [
            'NAME'=>$this->name,
            'PATH'=>$this->path,
            'MODIFIED' => count($this->modifiedFiles) ,
            'ZIP_SIZE' => $this->zipSize ,
        ];

        if($this->zipSize){
            $res['ZIP_NAME'] = basename($zipFilePath);
        }

        return $res;

    }

    public function parseHistory(){

        $history = [];

        $this->historyName = $this->rootName . $this->historyName;

        $history_path = $this->historyPath = $this->backStorage . DIRECTORY_SEPARATOR . $this->historyName;

        $count_line = 0;

        if(file_exists($history_path)){
            $history_content = file_get_contents($history_path);
            $history_lines = explode("\n", $history_content);

            foreach($history_lines as $line){
                $line = trim($line);
                if ($line) {
                    $arr = explode("\t", $line);
                    $history[$arr[0]] = intval($arr[1]);
                    $count_line ++;
                }
            }

        }

        $this->history = $history;

        $this->logger->addInfo("History parsed successfully , {$count_line} lines.");

    }

    /**
     * 扫描根目录有变化的文件
     * @return array
     */
    public function scanRootModified()
    {

        $file_stack = [];
        $i = 0;

        if($handle = opendir($this->path)){
            while (false !== ($filename = readdir($handle))){

                $filepath = $this->path . DIRECTORY_SEPARATOR . $filename;
                if( $filename != '.' && $filename != '..'){

                    $threadId = $i++ % $this->threadCount;

                    $last_update_time = filemtime($filepath);

                    //此文件夹是否必须检查时间戳变化来决定访问
                    if(in_array($filename,$this->ignoreUnmodifiedDir)){
                        //仅扫描有变更的文件夹
                        if(!isset($this->history[$filename]) || $last_update_time > intval($this->history[$filename])){
                            $file_stack[$threadId][$filename] = $last_update_time;
                        }
                    }else{
                        $file_stack[$threadId][$filename] = $last_update_time;
                    }

                }
            }
            closedir($handle);
        }
        return $file_stack;
    }

    public function initThreads($file_stack){

        $modifiedFiles = [];

        if(empty($file_stack)){
            $this->logger->addInfo("No changes found in {$this->path}");
        }else{
            $this->logger->addInfo("{$this->threadCount} threads start running!");
        }

        foreach($file_stack as $threadId => $files){
            //根据当前配置决定创建多线程还是单线程执行扫描
            if($this->isEnabledMultiThread()){
                $thread = ThreadMulti::make($threadId, $files , $this->history , $this->backStorage ,$this->path);
            }else{
                $thread = ThreadSingle::make($threadId, $files , $this->history , $this->backStorage ,$this->path);
            }
            $thread->logger = $this->logger;
            $thread->ignoreUnmodifiedDir = $this->ignoreUnmodifiedDir;
            $thread->rootDir = $this->path;
            $thread->start();
            $this->threads[] = $thread;
        }

        while (count($this->threads)) {
            //遍历检查线程组运行结束
            foreach ($this->threads as $threadId => $thread) {
                if ($thread->done) {
                    //合并线程的扫描结果
                    $modifiedFiles = $modifiedFiles + $thread->modifiedFiles;
                    unset($this->threads[$threadId]);
                }
            }
            sleep(1);
        }

        $this->modifiedFiles = $modifiedFiles;

    }

    public function isEnabledMultiThread(){

        return $this->threadCount > 0 && class_exists(('Thread'));

    }

    public function zip(){

        $zip = new \ZipArchive();
        $zip_dir_name = 'files';
        $zip_dir_path = $this->backStorage . DIRECTORY_SEPARATOR . $zip_dir_name  ;

        if(!is_dir($zip_dir_path)){
            mkdir($zip_dir_path,0755,true) ;
        }

        $zipFilename = $zip_dir_path . DIRECTORY_SEPARATOR . $this->name . '-' .date("Y-m-d-G-i-s").'.zip';

        if(!count($this->modifiedFiles)){
            return false;
        }

        $this->logger->addInfo("Start zipping to {$zipFilename}...");

        if ($zip->open($zipFilename, \ZIPARCHIVE::CREATE)) {

            foreach($this->modifiedFiles as $realPath => $mtime){

 		        //检测是否存在文件夹修改标志位，存在则证明是文件夹子元素修改，不直接打包文件夹
                if($mtime[0] == 'e') continue;

                if(is_dir($this->path . DIRECTORY_SEPARATOR . $realPath)){
                    $zip->addEmptyDir( $this->rootName . DIRECTORY_SEPARATOR . $realPath);
                }else{
                    $zip->addFile($this->path . DIRECTORY_SEPARATOR . $realPath , $this->rootName . DIRECTORY_SEPARATOR . $realPath);
                }

            }

            $zip->close();

        }

        $filesize = filesize($zipFilename);
        if($filesize > 1024 * 1024){
            $size = round($filesize / 1024 / 1024,2) . "MB" ;
        }else{
            $size = round($filesize / 1024 ,2) . "KB" ;
        }

        $this->zipSize = $size;
        $this->logger->addInfo("Done zipping to {$zipFilename} Size:{$size}");

        if($filesize) return $zipFilename;

        return false;

    }

    public function putHistory(){

        $history_handler = fopen($this->historyPath, 'a');

        foreach($this->modifiedFiles as $realPath => $mtime){
            $addToZip = 1;
            if($mtime[0] == 'e'){
                $addToZip = 0;
                $mtime = substr($mtime,1);
            }
            fwrite($history_handler, $realPath."\t".$mtime."\n");
        }

        fclose($history_handler);

    }

    protected function pushRemote($zipFilePath){

        foreach($this->scp as $scp) {

            $retryTimes = 0;
            $retryInterval = 1;

            $scpHost = $scp['host'];
            $scpPort = $scp['port'];
            $scpPath = $scp['path'];
            $scpUser = $scp['user'];
            $scpPassword = $scp['password'];

            $startTime = microtime(true);

            $sshConn = ssh2_connect($scpHost, $scpPort);
            while (!$sshConn) {
                if ($retryTimes < 10) {
                    $this->logger->addInfo("[" . date('Y-m-d H:i:s') . "] CONNECTING TO " . $scpHost . ":" . $scpPort . "  " . $this->name . " ... ");
                }
                $this->logger->addInfo("[CONNECTION FAILED] ... try in " . $retryInterval . " seconds\n");
                sleep($retryInterval);

                $sshConn = ssh2_connect($scpHost, $scpPort);
                $retryInterval *= 2;
                $retryTimes--;
                if ($retryTimes === 0) {
                    break;
                }
            }
            if ($sshConn) {
                if ($retryTimes < 10) {
                    $this->logger->addInfo("[" . date('Y-m-d H:i:s') . "] TRANSFERING TO " . $scpHost . ":" . $scpPort . "  " . $this->name . " ... ");
                }
                if (ssh2_auth_password($sshConn, $scpUser, $scpPassword)) {

                    $zipName = basename($zipFilePath);

                    if (ssh2_scp_send($sshConn, $zipFilePath, $scpPath . DIRECTORY_SEPARATOR . $zipName, 0640)) {
                        $endTime = microtime(true);
                        $spent = round($endTime - $startTime,2);
                        $this->logger->addInfo("[" . date('Y-m-d H:i:s') . "] TRANSFER SUCCESS,Spent {$spent} seconds. ");
                        ssh2_exec($sshConn, 'exit');    // exit to flush data
                    } else {
                        $this->logger->addInfo("[" . date('Y-m-d H:i:s') . "] {$zipName} TRANSFER FAILED.");
                    }

                } else {
                    $this->logger->addInfo("WRONG PASSWORD");
                }
            } else {
                $this->logger->addError("[" . date('Y-m-d H:i:s') . "] FAILED TO CONNECT TO " . $scpHost . ": " . $this->name);
            }
        }

    }

}
