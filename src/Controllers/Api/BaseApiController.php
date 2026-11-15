<?php

declare(strict_types=1);

namespace Ephraitech\Platform\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Model;
use Ephraitech\Platform\Config\PlatformConfig;
use Psr\Log\LoggerInterface;

/**
 * Parent of every module's API controller.
 *
 * Success:  {"data": ..., "meta": {...}}
 * Failure:  {"status": 422, "error": "validation_failed", "message": "...", "details": {...}}
 *           (the same shape as ci4-auth's own API errors)
 *
 * Routes put the guard and permission in one filter, e.g.
 *     $routes->get('clients', 'Api\ClientsController::index', ['filter' => 'can:token,crm.view']);
 * The platform filter has already chosen the tenant (X-Tenant-ID or the
 * token's tenant) and refused switched-off modules before this runs, so
 * TenantScoped models and can() work as in web controllers.
 */
abstract class BaseApiController extends Controller
{
    /**
     * @var list<string>
     */
    protected $helpers = ['platform', 'url'];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->response->setHeader('Cache-Control', 'no-store');
    }

    /**
     * @param array<string, mixed> $meta
     */
    protected function respondData(mixed $data, array $meta = [], int $status = 200): ResponseInterface
    {
        $body = ['data' => $data];

        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return $this->response->setStatusCode($status)->setJSON($body);
    }

    protected function respondCreated(mixed $data, ?string $location = null): ResponseInterface
    {
        if ($location !== null) {
            $this->response->setHeader('Location', $location);
        }

        return $this->respondData($data, [], 201);
    }

    protected function respondNoContent(): ResponseInterface
    {
        return $this->response->setStatusCode(204);
    }

    /**
     * A page of rows from a model or query builder, with ?page= and
     * ?per_page= (capped by Config\Platform::$apiMaxPerPage).
     *
     * @param (callable(array<string, mixed>|object): mixed)|null $transform
     */
    protected function respondPage(Model|BaseBuilder $source, ?callable $transform = null, int $defaultPerPage = 25): ResponseInterface
    {
        $perPage = (int) ($this->request->getGet('per_page') ?? $defaultPerPage);
        $perPage = max(1, min($perPage, PlatformConfig::get()->apiMaxPerPage));
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));

        // Count without resetting, then fetch the page with the same conditions.
        $total = $source->countAllResults(false);

        $rows = $source instanceof Model
            ? $source->findAll($perPage, ($page - 1) * $perPage)
            : $source->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

        $data = $transform === null ? $rows : array_map($transform, $rows);

        return $this->respondData(array_values($data), [
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
            'pages'    => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * @param array<string, mixed> $details
     */
    protected function fail(int $status, string $error, string $message, array $details = []): ResponseInterface
    {
        $body = ['status' => $status, 'error' => $error, 'message' => $message];

        if ($details !== []) {
            $body['details'] = $details;
        }

        return $this->response->setStatusCode($status)->setJSON($body);
    }

    /**
     * @param array<string, string> $errors field => message
     */
    protected function failValidation(array $errors, string $message = 'Some fields need attention.'): ResponseInterface
    {
        return $this->fail(422, 'validation_failed', $message, $errors);
    }

    protected function failNotFound(string $message = 'Not found.'): ResponseInterface
    {
        return $this->fail(404, 'not_found', $message);
    }

    protected function failForbidden(string $message = 'You may not do this.'): ResponseInterface
    {
        return $this->fail(403, 'forbidden', $message);
    }

    /**
     * Request body as an array: JSON when sent as JSON, else form fields.
     *
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        $type = strtolower($this->request->getHeaderLine('Content-Type'));

        if (str_contains($type, 'application/json')) {
            $json = $this->request->getJSON(true);

            return is_array($json) ? $json : [];
        }

        return (array) $this->request->getPost() + (array) $this->request->getRawInput();
    }
}
