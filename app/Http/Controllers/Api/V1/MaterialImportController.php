<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MaterialImportRequest;
use App\Services\MaterialImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use UnexpectedValueException;

class MaterialImportController extends Controller
{
    public function store(MaterialImportRequest $request, MaterialImportService $service): JsonResponse
    {
        try {
            $result = $service->generate($request->file('file'));
        } catch (UnexpectedValueException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (RuntimeException $exception) {
            Log::error('Material AI import failed.', [
                'user_id' => $request->user('api')?->getAuthIdentifier(),
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to generate the material seeder. Check the AI provider configuration and try again.',
            ], 502);
        }

        return response()->json([
            'message' => 'Material seeder generated successfully.',
            'items' => $result['items'],
            'php' => $result['php'],
        ]);
    }
}
