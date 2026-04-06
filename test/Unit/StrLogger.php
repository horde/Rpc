<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Test\Unit;

class StrLogger
{
    public array $logs = [];

    public function log(string $msg, string $level): void
    {
        $this->logs[] = [
            'msg' => $msg,
            'level' => $level,
        ];
    }

    public function err(string $msg): void
    {
        $this->log($msg, 'ERROR');
    }

    public function debug(string $msg): void
    {
        $this->log($msg, 'DEBUG');
    }

    public function notice(string $msg): void
    {
        $this->log($msg, 'NOTICE');
    }
}
