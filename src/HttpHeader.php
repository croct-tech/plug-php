<?php

declare(strict_types=1);

namespace Croct\Plug;

/**
 * HTTP headers used to authenticate and contextualize Croct API requests.
 */
enum HttpHeader: string
{
    /**
     * Authenticates the request with the API key identifier.
     */
    case API_KEY = 'X-Api-Key';

    /**
     * Identifies the visitor making the request.
     */
    case CLIENT_ID = 'X-Client-Id';

    /**
     * Carries the visitor's IP address.
     */
    case CLIENT_IP = 'X-Client-Ip';

    /**
     * Carries the visitor's user token.
     */
    case TOKEN = 'X-Token';

    /**
     * Carries the visitor's user agent.
     */
    case CLIENT_AGENT = 'X-Client-Agent';

    /**
     * Identifies the SDK that issued the request.
     */
    case CLIENT_LIBRARY = 'X-Client-Library';
}
