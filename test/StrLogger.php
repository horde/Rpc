<?php

namespace Horde\Rpc\Test;

class StrLogger
{
    public array $logs = [];

    public function log($msg, $level)
    {
        $this->logs[] = [
            'msg' => $msg,
            'level' => $level,
        ];
    }

    public function err($msg)
    {
        $this->log($msg, 'ERROR');
    }

    public function debug($msg)
    {
        $this->log($msg, 'DEBUG');
    }

    public function notice($msg)
    {
        $this->log($msg, 'NOTICE');
    }
}
