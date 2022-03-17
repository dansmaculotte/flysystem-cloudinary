<?php

namespace DansMaCulotte\Flysystem\Cloudinary;

use Cloudinary\Api\Admin\AdminApi;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Asset\Media;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Exception;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperationFailed;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToRetrieveMetadata;
use Throwable;

/**
 *
 */
class CloudinaryAdapter implements FilesystemAdapter
{
    protected UploadApi $uploadApi;
    protected AdminApi $adminApi;

    private const EXTRA_METADATA_FIELDS = [
        'version',
        'width',
        'height',
        'url',
        'secure_url',
        'next_cursor',
        'public_id'
    ];

    /**
     * Cloudinary does not suppory visibility - all is public
     */

    public function __construct(array $options)
    {
        Configuration::instance($options);
        $this->uploadApi = new UploadApi();
        $this->adminApi = new AdminApi();
    }

    public function write($path, $contents, Config $options): void
    {
        $tempFile = tmpfile();
        fwrite($tempFile, $contents);
        $this->writeStream($path, $tempFile, $options);
    }

    public function writeStream($path, $resource, Config $options): void
    {
        $public_id = $options->get('public_id', $path);
        $resource_type = $options->get('resource_type', 'auto');
        $resourceMetadata = stream_get_meta_data($resource);
        $uploadedMetadata = $this->uploadApi->upload(
            $resourceMetadata['uri'],
            [
                'public_id' => $public_id,
                'resource_type' => $resource_type,
            ]
        );
    }


    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $url = Media::fromParams($source);
            $this->uploadApi->upload($url, ['public_id' => $destination]);
        } catch (Throwable $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (FilesystemOperationFailed $exception) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $exception);
        }
    }

    public function delete($path): void
    {
        try {
            $result = $this->uploadApi->destroy($path, ['invalidate' => true])['result'];
            if ($result != 'ok') {
                throw new UnableToDeleteFile('file not found');
            }
        } catch (Throwable $exception) {
            throw UnableToDeleteFile::atLocation($path, '', $exception);
        }
    }
    
    public function deleteDirectory($dirname): void
    {
        $this->adminApi->deleteFolder($dirname);
    }

    public function createDirectory($dirname, Config $options): void
    {
        $this->adminApi->createFolder($dirname, (array) $options);
    }

    public function fileExists($path): bool
    {
        try {
            $this->adminApi->asset($path);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    public function directoryExists(string $path): bool
    {
        throw new Exception('Not implemented');
    }


    public function read($path): string
    {
        $contents = file_get_contents(Media::fromParams($path));
        return (string) $contents;
    }

    public function readStream($path)
    {
        return fopen(Media::fromParams($path), 'r');
    }

    public function listContents(string $directory, bool $hasRecursive): iterable
    {
        $resources = [];

        // get resources array
        $response = null;
        do {
            $response = (array) $this->adminApi->assets([
                'type' => 'upload',
                'prefix' => $directory,
                'max_results' => 500,
                'next_cursor' => isset($response['next_cursor']) ? $response['next_cursor'] : null,
            ]);
            $resources = array_merge($resources, $response['resources']);
        } while (array_key_exists('next_cursor', $response));

        // parse resourses
        foreach ($resources as $i => $resource) {
            //$resources[$i] = $this->prepareResourceMetadata($resource);
            yield  $this->mapToObject($resource);
            //
        }
        return $resources;
    }

    public function getResource($path)
    {
        return (array) $this->adminApi->asset($path);
    }

    public function fileSize($path): FileAttributes
    {
        return $this->fetchFileMetadata($path, FileAttributes::ATTRIBUTE_FILE_SIZE);
    }

    public function setVisibility(string $path, $visibility): void
    {
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->fetchFileMetadata($path, FileAttributes::ATTRIBUTE_VISIBILITY);
    }

    public function mimetype($path): FileAttributes
    {
        return $this->fetchFileMetadata($path, FileAttributes::ATTRIBUTE_MIME_TYPE);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->fetchFileMetadata($path, FileAttributes::ATTRIBUTE_LAST_MODIFIED);
    }
    
    private function fetchFileMetadata(string $path, string $type): FileAttributes
    {
        try {
            $result = $this->getResource($path);
        } catch (Throwable $exception) {
            throw UnableToRetrieveMetadata::create($path, $type, '', $exception);
        }
        $attributes = $this->mapToObject($result, $path);

        if (!$attributes instanceof FileAttributes) {
            throw UnableToRetrieveMetadata::create($path, $type, '');
        }
        return $attributes;
    }

    private function mapToObject($resource): FileAttributes
    {
        return new FileAttributes(
            $resource['public_id'],
            (int) $resource['bytes'],
            'public',
            (int) strtotime($resource['created_at']),
            (string) sprintf('%s/%s', $resource['resource_type'], $resource['format']),
            $this->extractExtraMetadata((array) $resource)
        );
    }
    
    private function extractExtraMetadata(array $metadata): array
    {
        $extracted = [];

        foreach (self::EXTRA_METADATA_FIELDS as $field) {
            if (isset($metadata[$field]) && $metadata[$field] !== '') {
                $extracted[$field] = $metadata[$field];
            }
        }

        return $extracted;
    }

    public function getMetadata(string $path): FileAttributes
    {
        return $this->mapToObject(
            $this->getResource($path)
        );
    }
}
