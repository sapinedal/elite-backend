<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GoogleDriveService
{
    protected string $credentialsPath;

    public function __construct()
    {
        $this->credentialsPath = storage_path('app/google-drive-key.json');
    }

    /**
     * Check if Google Drive Service Account credentials exist
     */
    public function isConfigured(): bool
    {
        return file_exists($this->credentialsPath);
    }

    /**
     * List files in a Google Drive Folder by Folder ID
     */
    public function listFolderFiles(string $folderId): array
    {
        if (!$this->isConfigured()) {
            Log::info("Google Drive Service Account key not found at {$this->credentialsPath}. Returning fallback simulation mode.");
            return $this->getSimulatedFolderFiles($folderId);
        }

        try {
            if (class_exists('\Google\Client')) {
                $client = new \Google\Client();
                $client->setAuthConfig($this->credentialsPath);
                $client->addScope(\Google\Service\Drive::DRIVE_READONLY);
                $service = new \Google\Service\Drive($client);

                $results = $service->files->listFiles([
                    'q' => "'{$folderId}' in parents and trashed = false",
                    'fields' => 'files(id, name, mimeType, webViewLink, webContentLink, thumbnailLink)',
                ]);

                $files = [];
                foreach ($results->getFiles() as $file) {
                    $files[] = [
                        'id' => $file->getId(),
                        'name' => $file->getName(),
                        'mimeType' => $file->getMimeType(),
                        'webViewLink' => $file->getWebViewLink(),
                        'thumbnailLink' => $file->getThumbnailLink(),
                    ];
                }
                return $files;
            }
        } catch (\Exception $e) {
            Log::error("Error syncing Google Drive folder {$folderId}: " . $e->getMessage());
        }

        return $this->getSimulatedFolderFiles($folderId);
    }

    /**
     * Simulation data for development and testing
     */
    private function getSimulatedFolderFiles(string $folderId): array
    {
        return [
            [
                'id' => 'file_contract_pdf_01',
                'name' => 'Contrato_Oficial_Firmado.pdf',
                'mimeType' => 'application/pdf',
                'webViewLink' => "https://drive.google.com/drive/folders/{$folderId}",
                'thumbnailLink' => null,
            ],
            [
                'id' => 'file_policy_pdf_02',
                'name' => 'Poliza_Cumplimiento_Aseguradora.pdf',
                'mimeType' => 'application/pdf',
                'webViewLink' => "https://drive.google.com/drive/folders/{$folderId}",
                'thumbnailLink' => null,
            ],
            [
                'id' => 'file_acta_recibo_03',
                'name' => 'Acta_Recibo_Satisfaccion_FTRA.pdf',
                'mimeType' => 'application/pdf',
                'webViewLink' => "https://drive.google.com/drive/folders/{$folderId}",
                'thumbnailLink' => null,
            ]
        ];
    }
}
