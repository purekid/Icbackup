<?php

/**
 * Created by PhpStorm.
 * User: michael
 * Date: 10/10/14
 * Time: 2:39 PM
 */

namespace Purekid\Icbackup\Thread;

trait ThreadTrait {

    protected $threadId = 0;
    public $modifiedFiles = [];
    public $ignoreUnmodifiedDir = [];
    public $multiMode = false;

    public static function make($threadId, $files, $history, $storagePath , $sourceBackupDirPath){

        $thread = new self();
        $thread->threadId = $threadId;
        $thread->files = $files;
        $thread->running = true;
        $thread->done = false;
        $thread->history = $history;
        $thread->storagePath = $storagePath;
        $thread->sourceBackupDirPath = $sourceBackupDirPath;
        $thread->modifiedFiles = [];
        $thread->scanedCount = 0;

        return $thread;

    }

    public function historyPath(){

        $path = $this->storagePath . DIRECTORY_SEPARATOR . $this->threadId . ".thread.history";
        return $path;

    }

    public function run(){

        $modifiedFiles = [];

        $specifyFiles = [];

        if($this->multiMode){
            $specifyFiles = $this->files;
        }

        $this->scanFiles($this->rootDir, $modifiedFiles, $specifyFiles );

        $this->running = false;
        $this->done = true;
        $this->modifiedFiles = $modifiedFiles;
        $modifiedCount = count($modifiedFiles);

        $this->logger->addInfo("Thread {$this->threadId} done , found {$modifiedCount} modified files.");

    }

    protected function scanFiles($path, &$modifiedFiles, $specifyFiles = [] ) {

        $children_empty_dir_modified = false;

        if($this->scanedCount && $this->scanedCount % 1000 == 0 ){
            $this->logger->addInfo("Thread {$this->threadId} : Scaned {$this->scanedCount} files... ");
        }

        if($handle = opendir($path)){

            while (false !== ($filename = readdir($handle))){

                if( $filename != '.' && $filename != '..') {

                    $this->scanedCount ++;

                    if(empty($specifyFiles) || isset($specifyFiles[$filename])) {
                        $filepath = $path . DIRECTORY_SEPARATOR . $filename;
                        $relpath =  substr($filepath , strlen($this->sourceBackupDirPath) + 1);

                        $last_update_time = filemtime($filepath);

                        $creating = !isset($this->history[$relpath]);
                        $modified = $creating || $last_update_time > intval($this->history[$relpath]);

                        if($modified){
                            if(is_dir($filepath)){
                                $children_empty_dir_modified_tag = $this->scanFiles($filepath,$modifiedFiles);

                                //文件夹中有子项发生变化，无须加入ZIP
                                if( $children_empty_dir_modified_tag ){
                                    $modifiedFiles[$relpath] = ['e'.$last_update_time,0];

                                //空文件夹被创建，需要加入ZIP
                                }else{
                                    $children_empty_dir_modified = true;
                                    $modifiedFiles[$relpath] = [$last_update_time,0];
                                }

                            }else{
                                $modifiedFiles[$relpath] = [$last_update_time,1];
                                $children_empty_dir_modified = true;
                            }
                        }else{
                            if(is_dir($filepath) && !isset($this->ignoreUnmodifiedDir[$filename])) {
                                $this->scanFiles($filepath,$modifiedFiles);
                            }
                        }
                    }
                }
            }
            closedir($handle);
        }

        return $children_empty_dir_modified;
    }

}