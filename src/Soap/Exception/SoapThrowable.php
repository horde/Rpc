<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Soap\Exception;

use Horde\Exception\HordeThrowable;

/**
 * Marker interface for all SOAP-related exceptions.
 *
 * Implemented by every exception raised by the SOAP server, client, and
 * transport layers, so that callers can catch SOAP errors uniformly
 * without depending on the native \SoapFault from ext-soap.
 *
 * Mirrors the role of {@see \Horde\Rpc\JsonRpc\Exception\JsonRpcThrowable}
 * in the JSON-RPC namespace.
 */
interface SoapThrowable extends HordeThrowable
{
    /**
     * Return the SOAP fault code (e.g. "Client", "Server", "VersionMismatch").
     *
     * Maps to the SOAP 1.1 <faultcode> element and the SOAP 1.2
     * <Code><Value> element.
     */
    public function getFaultCode(): string;

    /**
     * Return the SOAP fault actor / role, if any.
     *
     * Maps to the SOAP 1.1 <faultactor> element and the SOAP 1.2
     * <Role> element. Optional in both versions.
     */
    public function getFaultActor(): ?string;

    /**
     * Return the structured fault detail payload, if any.
     *
     * Maps to the SOAP 1.1 <detail> element and the SOAP 1.2 <Detail>
     * element. The shape is application-defined; ext-soap typically
     * exposes it as a stdClass or array.
     */
    public function getDetail(): mixed;
}
