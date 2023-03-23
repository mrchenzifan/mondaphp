<?php

namespace herosphp\core;

use herosphp\utils\File;
use function pathinfo;

/**
 * Class UploadFile
 */
class UploadFile extends File
{
    /**
     * @var string
     */
    protected string $uploadName;

    /**
     * @var string
     */
    protected string $uploadMimeType;

    /**
     * @var int
     */
    protected int $uploadErrorCode;

    /**
     * UploadFile constructor.
     *
     * @param  string  $fileName
     * @param  string  $uploadName
     * @param  string  $uploadMimeType
     * @param  int  $uploadErrorCode
     */
    public function __construct(string $fileName, string $uploadName, string $uploadMimeType, int $uploadErrorCode)
    {
        $this->uploadName = $uploadName;
        $this->uploadMimeType = $uploadMimeType;
        $this->uploadErrorCode = $uploadErrorCode;
        parent::__construct($fileName);
    }

    /**
     * GetUploadName
     *
     * @return string
     */
    public function getUploadName(): string
    {
        return $this->uploadName;
    }

    /**
     * GetUploadMimeType
     *
     * @return string
     */
    public function getUploadMimeType(): string
    {
        return $this->uploadMimeType;
    }

    /**
     * GetUploadExtension
     *
     * @return string
     */
    public function getUploadExtension(): string
    {
        return pathinfo($this->uploadName, PATHINFO_EXTENSION);
    }

    /**
     * GetUploadErrorCode
     *
     * @return int
     */
    public function getUploadErrorCode(): int
    {
        return $this->uploadErrorCode;
    }

    /**
     * IsValid
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->uploadErrorCode === UPLOAD_ERR_OK;
    }
}
