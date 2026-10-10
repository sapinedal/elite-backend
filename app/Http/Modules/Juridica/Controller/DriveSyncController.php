<?php

namespace App\Http\Modules\Juridica\Controller;

use App\Http\Controllers\Controller;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Throwable;

class DriveSyncController extends Controller
{
    protected GoogleDriveService $driveService;

    public function __construct(GoogleDriveService $driveService)
    {
        $this->driveService = $driveService;
    }

    /**
     * Get folder contents from Google Drive API
     */
    public function getFolderFiles(string $folderId): JsonResponse
    {
        try {
            $files = $this->driveService->listFolderFiles($folderId);

            return response()->json([
                'status' => 'success',
                'is_configured' => $this->driveService->isConfigured(),
                'folder_id' => $folderId,
                'data' => $files,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al consultar archivos en Google Drive: ' . $e->getMessage(),
            ], 500);
        }
    }
}
