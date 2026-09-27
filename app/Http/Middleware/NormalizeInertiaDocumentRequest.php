<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\Response;

class NormalizeInertiaDocumentRequest
{
    /**
     * A document navigation must always receive HTML. Inertia headers belong
     * exclusively to the asynchronous visits performed by the Vue client.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isDocumentNavigation = $request->header('Sec-Fetch-Mode') === 'navigate'
            && $request->header('Sec-Fetch-Dest') === 'document';

        if (! $isDocumentNavigation || ! $request->header(Header::INERTIA)) {
            return $next($request);
        }

        foreach ([
            Header::INERTIA,
            Header::VERSION,
            Header::PARTIAL_COMPONENT,
            Header::PARTIAL_ONLY,
            Header::PARTIAL_EXCEPT,
            Header::ERROR_BAG,
            Header::RESET,
            Header::INFINITE_SCROLL_MERGE_INTENT,
            Header::EXCEPT_ONCE_PROPS,
            'X-Requested-With',
        ] as $header) {
            $request->headers->remove($header);
        }

        return $next($request);
    }
}
