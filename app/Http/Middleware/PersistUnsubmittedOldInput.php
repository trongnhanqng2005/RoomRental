<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PersistUnsubmittedOldInput
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $currentPath = trim($request->path(), '/');

        // Allow user to explicitly clear unsubmitted draft with ?clear_draft=1
        if ($request->isMethod('GET') && $request->has('clear_draft')) {
            $session->forget('_persistent_old_input.'.$currentPath);
            $session->forget('_old_input');

            return redirect()->to($request->url());
        }

        // On GET requests: if no fresh _old_input exists, restore from persistent draft if available
        if ($request->isMethod('GET')) {
            $persistentDraft = $session->get('_persistent_old_input.'.$currentPath);

            if (is_array($persistentDraft) && $persistentDraft !== []) {
                $currentOldInput = (array) $session->get('_old_input', []);
                // Merge persistent draft with any current old input
                $merged = array_merge($persistentDraft, $currentOldInput);
                $session->put('_old_input', $merged);
            }
        }

        $response = $next($request);

        // After request execution:
        // Case 1: Form submission failed validation and redirected back with errors & old input
        if ($response->isRedirection() && $session->has('errors') && $session->has('_old_input')) {
            $targetUrl = (string) $response->headers->get('Location', '');
            $targetPath = trim((string) parse_url($targetUrl, PHP_URL_PATH), '/');

            if ($targetPath !== '') {
                $oldInput = (array) $session->get('_old_input', []);
                // Exclude sensitive or non-persistable fields
                unset($oldInput['_token'], $oldInput['password'], $oldInput['password_confirmation']);
                $session->put('_persistent_old_input.'.$targetPath, $oldInput);
            }
        }

        // Case 2: Form submission succeeded (non-GET, successful status or redirect without validation errors)
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            $hasValidationErrors = $session->has('errors');

            // If the submission finished without validation errors, clear the persistent draft
            if (! $hasValidationErrors && ($response->isSuccessful() || $response->isRedirection())) {
                $referer = (string) $request->headers->get('referer', '');
                $refererPath = trim((string) parse_url($referer, PHP_URL_PATH), '/');

                if ($refererPath !== '') {
                    $session->forget('_persistent_old_input.'.$refererPath);
                }

                // Also clear for the request path itself if applicable
                $session->forget('_persistent_old_input.'.$currentPath);
            }
        }

        return $response;
    }
}
