<?php

declare(strict_types=1);

namespace App\Strategy;

use Bamise\Contract\CrudHandlerInterface;
use Bamise\Contract\Enum\OperationType;
use Bamise\Contract\MiddlewareInterface;
use Bamise\Contract\ValueObject\CrudContext;
use Bamise\Contract\ValueObject\CrudResult;

/**
 * Intercepts Create operations on the 'uploads' resource, moves the uploaded
 * file to the uploads directory, and injects metadata into inputData before
 * the strategy runs.
 */
final class FileUploadMiddleware implements MiddlewareInterface
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    private const MAX_SIZE_BYTES     = 5 * 1024 * 1024; // 5 MB

    public function __construct(private readonly string $uploadDir) {}

    public function process(CrudContext $context, CrudHandlerInterface $next): CrudResult
    {
        if ($context->operation !== OperationType::Create || $context->resourceName !== 'uploads') {
            return $next->handle($context);
        }

        $fileInfo = $context->inputData['_uploaded_file'] ?? null;

        if (! is_array($fileInfo) || ($fileInfo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return new CrudResult(
                success: false,
                errors: ['message' => 'No valid file uploaded.'],
                meta: ['operation' => $context->operation->value],
            );
        }

        $originalName = (string) ($fileInfo['name'] ?? '');
        $mimeType     = (string) ($fileInfo['type'] ?? '');
        $size         = (int) ($fileInfo['size'] ?? 0);
        $tmpName      = (string) ($fileInfo['tmp_name'] ?? '');

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return new CrudResult(
                success: false,
                errors: ['message' => 'File type not allowed.'],
                meta: ['operation' => $context->operation->value],
            );
        }

        if ($size > self::MAX_SIZE_BYTES) {
            return new CrudResult(
                success: false,
                errors: ['message' => 'File exceeds maximum size of 5 MB.'],
                meta: ['operation' => $context->operation->value],
            );
        }

        $extension      = pathinfo($originalName, PATHINFO_EXTENSION);
        $storedFilename = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
        $destination    = $this->uploadDir . '/' . $storedFilename;

        if (! is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        if (! move_uploaded_file($tmpName, $destination)) {
            return new CrudResult(
                success: false,
                errors: ['message' => 'Failed to save uploaded file.'],
                meta: ['operation' => $context->operation->value],
            );
        }

        // Replace inputData with file metadata; remove the raw file info
        $newInput = [
            'original_name'   => $originalName,
            'stored_filename' => $storedFilename,
            'size'            => $size,
            'mime_type'       => $mimeType,
            'uploaded_at'     => date('Y-m-d H:i:s'),
        ];

        $modified = new CrudContext(
            request:      $context->request,
            resourceName: $context->resourceName,
            operation:    $context->operation,
            inputData:    $newInput,
            subject:      $context->subject,
        );

        return $next->handle($modified);
    }
}
