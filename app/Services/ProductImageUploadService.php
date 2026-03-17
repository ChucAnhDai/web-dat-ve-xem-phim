<?php

namespace App\Services;

use App\Core\Logger;
use App\Support\Slugger;

class ProductImageUploadService
{
    private Logger $logger;
    private string $projectRoot;
    private string $publicDirectory;
    private int $maxFileSizeBytes;
    private array $allowedMimeTypes;

    public function __construct(?array $config = null, ?Logger $logger = null, ?string $projectRoot = null)
    {
        $shopConfig = $config ?? require dirname(__DIR__, 2) . '/config/shop.php';
        $uploadConfig = $shopConfig['products']['uploads'] ?? [];

        $this->logger = $logger ?? new Logger();
        $this->projectRoot = rtrim($projectRoot ?? dirname(__DIR__, 2), DIRECTORY_SEPARATOR);
        $this->publicDirectory = trim((string) ($uploadConfig['public_directory'] ?? 'public/uploads/products'), '/');
        $this->maxFileSizeBytes = max(1, (int) ($uploadConfig['max_file_size_bytes'] ?? (5 * 1024 * 1024)));
        $this->allowedMimeTypes = (array) ($uploadConfig['allowed_mime_types'] ?? []);
    }

    public function store($file, array $context = []): array
    {
        $field = (string) ($context['error_field'] ?? 'image_file');
        $normalizedFile = is_array($file) ? $file : null;

        if ($normalizedFile === null || empty($normalizedFile['tmp_name'])) {
            throw $this->validationException($field, 'Image upload file is required.');
        }

        $errorCode = (int) ($normalizedFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw $this->validationException($field, $this->uploadErrorMessage($errorCode));
        }

        $size = (int) ($normalizedFile['size'] ?? 0);
        if ($size <= 0) {
            throw $this->validationException($field, 'Uploaded image cannot be empty.');
        }
        if ($size > $this->maxFileSizeBytes) {
            throw $this->validationException($field, 'Uploaded image exceeds the maximum allowed size.');
        }

        $tmpName = (string) ($normalizedFile['tmp_name'] ?? '');
        $isTestUpload = !empty($normalizedFile['test']);
        if (!$isTestUpload && !is_uploaded_file($tmpName)) {
            throw $this->validationException($field, 'Uploaded image payload is invalid.');
        }

        $mimeType = $this->detectMimeType($tmpName);
        $extension = $this->allowedMimeTypes[$mimeType] ?? null;
        if ($extension === null) {
            throw $this->validationException($field, 'Uploaded image type is not supported.');
        }

        $relativeDirectory = $this->publicDirectory . '/' . date('Y/m');
        $absoluteDirectory = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            throw new ProductImageUploadException([
                $field => ['Failed to prepare product image storage directory.'],
            ], 'Product image storage directory is unavailable.');
        }

        $originalName = (string) ($normalizedFile['name'] ?? 'product-image');
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBaseName = Slugger::slugify($baseName);
        if ($safeBaseName === '') {
            $safeBaseName = 'product-image';
        }

        $fileName = $safeBaseName . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $relativePath = $relativeDirectory . '/' . $fileName;
        $absolutePath = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        $moveSucceeded = false;
        if ($isTestUpload) {
            $moveSucceeded = @rename($tmpName, $absolutePath);
        } else {
            $moveSucceeded = @move_uploaded_file($tmpName, $absolutePath);
        }

        if (!$moveSucceeded) {
            throw new ProductImageUploadException([
                $field => ['Failed to store uploaded product image.'],
            ], 'Product image upload could not be stored.');
        }

        return [
            'stored_path' => $relativePath,
            'size' => $size,
            'mime_type' => $mimeType,
            'original_name' => $originalName,
        ];
    }

    public function deleteStoredFile(?string $storedPath): void
    {
        $normalized = trim(str_replace('\\', '/', (string) ($storedPath ?? '')), '/');
        if (!$this->isManagedStoredPath($normalized)) {
            return;
        }

        $absolutePath = $this->projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        if (!is_file($absolutePath)) {
            return;
        }

        if (!@unlink($absolutePath)) {
            $this->logger->info('Uploaded product image cleanup failed', [
                'stored_path' => $normalized,
            ]);
        }
    }

    public function isManagedStoredPath(?string $storedPath): bool
    {
        $normalized = trim(str_replace('\\', '/', (string) ($storedPath ?? '')), '/');
        if ($normalized === '') {
            return false;
        }

        return $normalized === $this->publicDirectory || str_starts_with($normalized, $this->publicDirectory . '/');
    }

    private function detectMimeType(string $tmpName): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return '';
        }

        $mimeType = (string) finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        return $mimeType;
    }

    private function uploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Uploaded image exceeds the maximum allowed size.';
            case UPLOAD_ERR_PARTIAL:
                return 'Uploaded image was only partially received.';
            case UPLOAD_ERR_NO_FILE:
                return 'Image upload file is required.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Server upload temp directory is missing.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Server could not write the uploaded image.';
            case UPLOAD_ERR_EXTENSION:
                return 'A server extension blocked the uploaded image.';
            default:
                return 'Uploaded image could not be processed.';
        }
    }

    private function validationException(string $field, string $message): ProductImageUploadException
    {
        return new ProductImageUploadException([
            $field => [$message],
        ], $message);
    }
}
