<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ApiCredentialRequest;
use App\Http\Resources\ApiCredentialResource;
use App\Models\ApiCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApiCredentialController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('api-keys.view'), Response::HTTP_FORBIDDEN);

        $perPage = (int) $request->query('per_page', 10);
        $search = $request->query('search', '');
        $status = $request->query('status', '');

        $query = ApiCredential::query()
            ->withCount('requestLogs');

        if (! empty($search)) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%")
                    ->orWhere('api_key', 'LIKE', "%{$search}%");
            });
        }

        if (! empty($status) && in_array($status, ['active', 'inactive', 'expired', 'revoked'])) {
            $query->where('status', $status);
        }

        $credentials = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->sendResponse($credentials, 'Credenciales API consultadas correctamente');
    }

    public function store(ApiCredentialRequest $request): JsonResponse
    {
        $data = $request->validated();

        DB::beginTransaction();
        try {
            $apiKey = $data['api_key'] ?? ApiCredential::generateApiKey();
            $plainSecret = $data['api_secret'] ?? ApiCredential::generateSecret();

            $credential = ApiCredential::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'api_key' => $apiKey,
                'api_secret' => $plainSecret,
                'expires_at' => $data['expires_at'] ?? null,
                'allowed_ips' => $data['allowed_ips'] ?? null,
                'abilities' => $data['abilities'] ?? ['*'],
                'rate_limit' => $data['rate_limit'] ?? 1000,
                'metadata' => $data['metadata'] ?? null,
                'status' => 'active',
                'created_by' => $request->user()->id,
            ]);

            DB::commit();

            $response = (new ApiCredentialResource($credential))->toArray($request);
            $response['api_secret_plain'] = $plainSecret;
            $response['warning'] = 'Guarda el API Secret ahora. No podrás verlo nuevamente.';

            return $this->sendResponse($response, 'Credencial API creada exitosamente', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear credencial API: '.$e->getMessage());

            return $this->sendError('Error al crear la credencial API', [], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->hasPermission('api-keys.update'), Response::HTTP_FORBIDDEN);

        $credential = ApiCredential::find($id);

        if (! $credential) {
            return $this->sendError('Credencial API no encontrada', [], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date'],
            'allowed_ips' => ['nullable', 'array'],
            'allowed_ips.*' => ['ip'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string'],
            'rate_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'metadata' => ['nullable', 'array'],
        ]);

        DB::beginTransaction();
        try {
            $credential->update([
                ...$validated,
                'updated_by' => $request->user()->id,
            ]);

            DB::commit();

            return $this->sendResponse(new ApiCredentialResource($credential), 'Credencial API actualizada exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar credencial API: '.$e->getMessage());

            return $this->sendError('Error al actualizar la credencial API', [], 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->hasPermission('api-keys.delete'), Response::HTTP_FORBIDDEN);

        $credential = ApiCredential::find($id);

        if (! $credential) {
            return $this->sendError('Credencial API no encontrada', [], 404);
        }

        $credential->delete();

        return $this->sendResponse(null, 'Credencial API eliminada exitosamente');
    }

    public function regenerate(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->hasPermission('api-keys.update'), Response::HTTP_FORBIDDEN);

        $credential = ApiCredential::find($id);

        if (! $credential) {
            return $this->sendError('Credencial API no encontrada', [], 404);
        }

        $newApiKey = ApiCredential::generateApiKey();
        $newSecret = ApiCredential::generateSecret();

        $credential->update([
            'api_key' => $newApiKey,
            'api_secret' => $newSecret,
            'updated_by' => $request->user()->id,
        ]);

        $response = (new ApiCredentialResource($credential))->toArray($request);
        $response['api_secret_plain'] = $newSecret;
        $response['warning'] = 'Guarda el nuevo API Secret ahora. No podrás verlo nuevamente.';

        return $this->sendResponse($response, 'Credenciales regeneradas exitosamente');
    }

    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->hasPermission('api-keys.update'), Response::HTTP_FORBIDDEN);

        $credential = ApiCredential::find($id);

        if (! $credential) {
            return $this->sendError('Credencial API no encontrada', [], 404);
        }

        $newStatus = $credential->status === 'active' ? 'inactive' : 'active';

        $credential->update([
            'status' => $newStatus,
            'updated_by' => $request->user()->id,
        ]);

        $message = $newStatus === 'active' ? 'Credencial API activada' : 'Credencial API desactivada';

        return $this->sendResponse(new ApiCredentialResource($credential), $message);
    }

    public function revoke(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->hasPermission('api-keys.update'), Response::HTTP_FORBIDDEN);

        $credential = ApiCredential::find($id);

        if (! $credential) {
            return $this->sendError('Credencial API no encontrada', [], 404);
        }

        $credential->revoke();

        return $this->sendResponse(new ApiCredentialResource($credential), 'Credencial API revocada exitosamente');
    }

    public function logs(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->hasPermission('api-keys.view'), Response::HTTP_FORBIDDEN);

        $credential = ApiCredential::find($id);

        if (! $credential) {
            return $this->sendError('Credencial API no encontrada', [], 404);
        }

        $perPage = (int) $request->query('per_page', 50);
        $logs = $credential->requestLogs()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->sendResponse($logs, 'Logs consultados correctamente');
    }

    public function stats(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()->hasPermission('api-keys.view'), Response::HTTP_FORBIDDEN);

        $credential = ApiCredential::find($id);

        if (! $credential) {
            return $this->sendError('Credencial API no encontrada', [], 404);
        }

        $stats = [
            'total_requests' => $credential->requestLogs()->count(),
            'requests_today' => $credential->requestLogs()->today()->count(),
            'requests_last_24h' => $credential->requestLogs()->lastHours(24)->count(),
            'successful_requests' => $credential->requestLogs()->success()->count(),
            'failed_requests' => $credential->requestLogs()->errors()->count(),
            'avg_response_time' => $credential->requestLogs()->avg('response_time'),
            'last_request_at' => $credential->last_used_at?->toISOString(),
            'most_used_endpoints' => $credential->requestLogs()
                ->select('endpoint', DB::raw('count(*) as total'))
                ->groupBy('endpoint')
                ->orderBy('total', 'desc')
                ->limit(5)
                ->get(),
        ];

        return $this->sendResponse($stats, 'Estadísticas consultadas correctamente');
    }
}
