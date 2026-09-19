<?php

namespace App\Http\Middleware;

use App\Repositories\IdempotencyRepository;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    public function __construct(private readonly IdempotencyRepository $repository) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            abort(400, 'This endpoint requires an Idempotency-Key header to safely allow retries.');
        }

        $scopedKey = $request->method() . ':' . $request->path() . ':' . $key;

        // 1. Check Cache
        if ($cached = $this->repository->find($scopedKey)) {
            dump("CACHE HIT SUCCESSFUL! Returning early."); // <-- DEBUG 1
            return response()->json($cached['body'], $cached['status']);
        }

        // 2. Process Request
        $response = $next($request);

        // 3. Save to Cache (Robust method)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $body = json_decode($response->getContent(), true) ?? [];
            $this->repository->store($scopedKey, $body, $response->getStatusCode());
            dump("SAVED TO CACHE KEY: " . $scopedKey); // <-- DEBUG 2
        }

        return $response;
    }
}
