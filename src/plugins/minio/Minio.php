<?php

namespace herosphp\plugins\minio;

use Aws\Result;
use Aws\S3\S3Client;
use herosphp\core\UploadFile;
use herosphp\exception\HeroException;
use herosphp\utils\StringUtil;

/**
 * @note composer require aws/aws-sdk-php
 */
class Minio
{
    protected static array $_config = [];

    protected ?S3Client $s3Client = null;

    private function __construct(array $config = [])
    {
        static::$_config = array_merge(config('minio', []), $config);

        if (! static::$_config || ! class_exists(S3Client::class)) {
            throw new HeroException('Please run "composer install aws/aws-sdk-php" or configure minio file requirement');
        }
        //init s3 driver
        $client = new S3Client(static::$_config);
        $this->s3Client = $client;
    }

    /**
     * 初始化实例
     *
     * @param  array  $config
     * @return self
     */
    public static function newInstance(array $config = []): self
    {
        return new self($config);
    }

    /**
     * 上传
     *
     * @param  UploadFile  $uploadFile
     * @return string
     */
    public function save(UploadFile $uploadFile): string
    {
        return $this->putObject($uploadFile->getUploadName(), $uploadFile->getPathname());
    }

    /**
     * @param  string  $dstFilePath
     * @return string
     */
    public function saveFile(string $dstFilePath): string
    {
        return $this->putObject(pathinfo($dstFilePath)['basename'], $dstFilePath);
    }

    public function delete(string $key): Result
    {
        $key = $this->formatStorageSavePath($key);

        return $this->s3Client->deleteObject(['Bucket' => static::$_config['bucket_name'], 'Key' => $key]);
    }

    public function getPlainUrl(string $key): string
    {
        return $this->s3Client->getObjectUrl(static::$_config['bucket_name'], $key);
    }

    public function getPreSigned(string $key, int $minutes = 10): string
    {
        // Get a command object from the client
        $command = $this->s3Client->getCommand('GetObject', [
            'Bucket' => static::$_config['bucket_name'],
            'Key' => $key,
        ]);
        // Create a pre-signed URL for a request with duration of 10 minutes
        $preSignedRequest = $this->s3Client->createPresignedRequest($command, "+{$minutes} minutes");
        // Get the actual preSigned-url
        return (string) $preSignedRequest->getUri();
    }

    /**
     * @return Result
     */
    public function createBucket(): Result
    {
        if (empty(static::$_config['bucket_name'])) {
            throw new \RuntimeException('config bucket name is empty');
        }

        return $this->s3Client->createBucket(['bucket_name' => static::$_config['bucket_name']]);
    }

    private function putObject(string $fileName, string $sourceFile): string
    {
        if (! file_exists($sourceFile)) {
            throw new \RuntimeException('File does not exist');
        }
        $key = date('Y/m/d/').StringUtil::genGlobalUid().'.'.pathinfo($fileName)['extension'];
        $this->s3Client->putObject([
            'Bucket' => static::$_config['bucket_name'],
            'Key' => $key,
            'SourceFile' => $sourceFile,
            'ContentType' => MimeType::fromFilename($fileName) ?: 'application/octet-stream',
        ]);

        return $key;
    }

    /**
     * Get the policy of a specific bucket
     *
     * @return string
     */
    public function getBucketPolicy(): string
    {
        $resp = $this->s3Client->getBucketPolicy([
            'Bucket' => static::$_config['bucket_name'],
        ]);

        return (string) $resp->get('Policy');
    }

    /**
     * Deletes the policy from the bucket
     *
     * @return void
     */
    public function deleteBucketPolicy(): void
    {
        $this->s3Client->deleteBucketPolicy([
            'Bucket' => static::$_config['bucket_name'],
        ]);
    }

    /**
     * replace a policy on the bucket
     *
     * @param  string  $policy
     * @return void
     */
    public function putBucketPolicy(string $policy): void
    {
        $this->s3Client->putBucketPolicy([
            'Bucket' => static::$_config['bucket_name'],
            'Policy' => $policy,
        ]);
    }

    /**
     * 格式化处理, 去除前后的“/”
     *
     * @param  string  $storageSavePath
     * @return string
     */
    private function formatStorageSavePath(string $storageSavePath): string
    {
        return trim($storageSavePath, '/');
    }

    /**
     * all readers and writers
     *
     * @return string
     */
    public function getReadAndWriteForAll(): string
    {
        return '{"Version":"2012-10-17","Statement":[{"Effect":"Allow","Principal":{"AWS":["*"]},"Action":["s3:GetBucketLocation","s3:ListBucket","s3:ListBucketMultipartUploads"],"Resource":["arn:aws:s3:::'.static::$_config['bucket_name'].'"]},{"Effect":"Allow","Principal":{"AWS":["*"]},"Action":["s3:AbortMultipartUpload","s3:DeleteObject","s3:GetObject","s3:ListMultipartUploadParts","s3:PutObject"],"Resource":["arn:aws:s3:::'.static::$_config['bucket_name'].'/*"]}]}';
    }
}
