<?php

namespace App\Http\Middleware;

use App\Support\DeploymentCheck;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses to serve a production request while the configuration still
 * carries a development default that breaks the service (#59). The
 * exception lands in the log with every problem named; guests get the
 * error page, and the /up health check fails so a deploy is not marked
 * healthy.
 */
class EnsureDeploymentIsSafe
{
    public function __construct(private readonly DeploymentCheck $check) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->check->isEnforced()) {
            $problems = $this->check->problems();

            if ($problems !== []) {
                throw new RuntimeException(
                    "QResto will not serve production with this configuration:\n- "
                    .implode("\n- ", $problems)
                    ."\nRun `php artisan qresto:check-deploy` after fixing .env.",
                );
            }
        }

        return $next($request);
    }
}
