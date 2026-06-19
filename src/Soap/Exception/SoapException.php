<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Soap\Exception;

use Horde\Exception\HordeRuntimeException;
use SoapFault;
use Throwable;

/**
 * SOAP exception with fault-code, fault-actor, and detail accessors.
 *
 * Used by both the SOAP server and the SOAP client. Transports that do
 * not use ext-soap can construct this directly; transports that do can
 * convert a native \SoapFault via {@see self::fromSoapFault()}.
 *
 * The accessor surface is the union of the SOAP 1.1 fault model
 * (faultcode, faultactor, detail) and the SOAP 1.2 fault model
 * (Code, Role, Detail). Both versions map onto the same three fields.
 */
class SoapException extends HordeRuntimeException implements SoapThrowable
{
    /**
     * @param string $message Human-readable fault description (SOAP <faultstring> / <Reason>).
     * @param string $faultCode Fault code, defaults to "Server" for caller-side faults of unknown origin.
     * @param string|null $faultActor Fault actor / role, if any.
     * @param mixed $detail Application-defined fault detail payload, if any.
     * @param Throwable|null $previous Original exception (commonly the wrapped \SoapFault).
     */
    public function __construct(
        string $message,
        private readonly string $faultCode = 'Server',
        private readonly ?string $faultActor = null,
        private readonly mixed $detail = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getFaultCode(): string
    {
        return $this->faultCode;
    }

    public function getFaultActor(): ?string
    {
        return $this->faultActor;
    }

    public function getDetail(): mixed
    {
        return $this->detail;
    }

    /**
     * Build a SoapException from a native ext-soap \SoapFault.
     *
     * Preserves the original fault as the previous exception so callers
     * who need the raw faultcode_ns, headerfault, etc. can still reach
     * them via {@see Throwable::getPrevious()}.
     *
     * The native \SoapFault exposes properties (not getters):
     *  - faultcode    : fault code string (SOAP 1.1) or local part (1.2)
     *  - faultstring  : human-readable message
     *  - faultactor   : actor URI, optional
     *  - detail       : application-specific detail, optional
     */
    public static function fromSoapFault(SoapFault $fault): self
    {
        $faultCode = isset($fault->faultcode) && is_string($fault->faultcode) && $fault->faultcode !== ''
            ? $fault->faultcode
            : 'Server';

        $faultActor = isset($fault->faultactor) && is_string($fault->faultactor) && $fault->faultactor !== ''
            ? $fault->faultactor
            : null;

        $detail = $fault->detail ?? null;

        $message = $fault->getMessage();
        if ($message === '' && isset($fault->faultstring) && is_string($fault->faultstring)) {
            $message = $fault->faultstring;
        }

        return new self(
            message: $message,
            faultCode: $faultCode,
            faultActor: $faultActor,
            detail: $detail,
            previous: $fault,
        );
    }
}
