<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Http;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Ephraitech\Platform\Config\PlatformConfig;

/**
 * Error answers that suit the caller: JSON in the same shape as
 * ci4-auth's API errors ({"status", "error", "message"}) for API and AJAX
 * requests, pages for browsers.
 */
final class Respond
{
    public static function wantsJson(RequestInterface $request): bool
    {
        if (! $request instanceof IncomingRequest) {
            return false;
        }

        // The route path, relative to baseURL and without index.php.
        $path   = trim($request->getPath(), '/');
        $prefix = trim(PlatformConfig::get()->apiPrefix, '/');

        return ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix . '/')))
            || str_starts_with($path, 'api/')
            || $request->isAJAX()
            || str_contains(strtolower($request->getHeaderLine('Accept')), 'application/json');
    }

    /**
     * @param array<string, mixed> $extra
     */
    public static function json(int $status, string $error, string $message, array $extra = []): ResponseInterface
    {
        return service('response')
            ->setStatusCode($status)
            ->setJSON(['status' => $status, 'error' => $error, 'message' => $message] + $extra);
    }

    /**
     * 404 for a switched-off module or an unknown tenant. Browsers get the
     * app's normal "not found" page, so nothing reveals that the area exists.
     */
    public static function notFound(RequestInterface $request, string $message = 'Not found.'): ResponseInterface
    {
        if (self::wantsJson($request)) {
            return self::json(404, 'not_found', $message);
        }

        throw PageNotFoundException::forPageNotFound($message);
    }

    /**
     * A page explaining why the workspace cannot be used (or 403 JSON).
     *
     * @param array<string, mixed> $data extra view data: tenant, actions
     */
    public static function blocked(RequestInterface $request, int $status, string $error, string $title, string $message, array $data = []): ResponseInterface
    {
        if (self::wantsJson($request)) {
            return self::json($status, $error, $message);
        }

        return service('response')
            ->setStatusCode($status)
            ->setBody(view('Ephraitech\Platform\Views\errors\workspace', ['pageTitle' => $title, 'title' => $title, 'message' => $message] + $data));
    }
}
