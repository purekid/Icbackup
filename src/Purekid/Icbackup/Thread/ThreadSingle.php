<?php
/**
 * Created by PhpStorm.
 * User: michael
 * Date: 14-10-9
 * Time: 10:30
 */

namespace Purekid\Icbackup\Thread;

class ThreadSingle
{
    use ThreadTrait;

    public function start(){
        $this->run();
    }

}