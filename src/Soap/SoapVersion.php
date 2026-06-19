<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Rpc\Soap;

/**
 * SOAP protocol version.
 *
 * Maps to the SOAP_1_1 / SOAP_1_2 constants defined by ext-soap, which
 * are exposed through {@see self::toExtSoapConstant()} for callers that
 * need to interoperate with native \SoapClient option arrays.
 */
enum SoapVersion: string
{
    case V1_1 = '1.1';
    case V1_2 = '1.2';

    /**
     * Return the ext-soap integer constant for this version.
     *
     * SOAP_1_1 = 1, SOAP_1_2 = 2 per the PHP soap extension. We resolve
     * them at call time rather than as enum case backing values so the
     * enum stays usable on systems where ext-soap is not loaded.
     */
    public function toExtSoapConstant(): int
    {
        return match ($this) {
            self::V1_1 => SOAP_1_1,
            self::V1_2 => SOAP_1_2,
        };
    }
}
