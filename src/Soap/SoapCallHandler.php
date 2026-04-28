<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Soap;

use Horde\Rpc\Dispatch\ApiProvider;
use Horde\Rpc\Dispatch\MethodInvoker;
use SoapFault;

/**
 * Registered with SoapServer to handle incoming SOAP method calls.
 *
 * Translates SOAP method invocations to the shared dispatch layer.
 * Method names use dot notation (e.g. "calendar.list") matching the
 * ApiProvider convention.
 *
 * @internal
 */
final class SoapCallHandler
{
    public function __construct(
        private readonly ApiProvider $provider,
        private readonly MethodInvoker $invoker,
    ) {}

    /**
     * @throws SoapFault
     */
    public function __call(string $method, array $params): mixed
    {
        // SOAP methods may arrive with dot notation already
        if (!$this->provider->hasMethod($method)) {
            throw new SoapFault('Server', sprintf('Method "%s" is not defined', $method));
        }

        $result = $this->invoker->invoke($method, $params);

        return $result->value;
    }
}
