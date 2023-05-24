<?php

namespace herosphp\plugins\minio;

use Aws\Result;
use Aws\S3\S3Client;
use herosphp\core\UploadFile;
use herosphp\exception\HeroException;
use herosphp\utils\StringUtil;

/**
 * @note composer require league/flysystem-aws-s3-v3
 */
class Minio
{
    protected static array $_config = [];

    private S3Client $s3Client;

    public function __construct(array $config)
    {
        if (
            empty($config) || ! isset($config['credentials']) || ! isset($config['bucket_name'])
        ) {
            throw new HeroException('Invalid upload configs');
        }

        if (! class_exists(S3Client::class)) {
            throw new HeroException('Please run "composer install league/flysystem-aws-s3-v3:^3.0"');
        }
        //init s3 driver
        $client = new S3Client($config);
        $this->s3Client = $client;
        static::$_config = $config;
    }

    /**
     * @param  UploadFile  $uploadFile
     * @return string
     */
    public function save(UploadFile $uploadFile): string
    {
        $dstFile = date('Y/m/d/').StringUtil::genGlobalUid().'.'.$uploadFile->getUploadExtension();
        $this->s3Client->putObject([
            'Bucket' => static::$_config['bucket_name'],
            'Key' => $dstFile,
            'SourceFile' => $uploadFile->getPathname(),
            'ContentType' => MimeType::fromFilename($uploadFile->getUploadName()) ?: 'application/octet-stream',
        ]);

        return $dstFile;
    }

    /**
     * @param  string  $dstFilePath  绝对路径
     * @return string
     */
    public function saveFile(string $dstFilePath): string
    {
        $dstFile = date('Y/m/d/').StringUtil::genGlobalUid().'.'.pathinfo($dstFilePath)['extension'];
        $this->s3Client->putObject([
            'Bucket' => static::$_config['bucket_name'],
            'Key' => $dstFile,
            'SourceFile' => $dstFilePath,
            'ContentType' => MimeType::fromFilename($dstFilePath) ?: 'application/octet-stream',
        ]);

        return $dstFile;
    }

    /**
     * @param  string  $filename
     * @return Result
     */
    public function delete(string $filename): Result
    {
        $dstFile = $this->formatStorageSavePath($filename);

        return $this->s3Client->deleteObject(['Bucket' => static::$_config['bucket_name'], 'Key' => $dstFile]);
    }

    /**
     * 格式化处理, 去除前后的“/”
     *
     * @param  string  $storageSavePath
     * @return string
     */
    protected function formatStorageSavePath(string $storageSavePath): string
    {
        return trim($storageSavePath, '/');
    }

    /**
     * @note 设置策略，会覆盖原来设置的!
     *
     * @param  string  $bucket
     * @param  array  $policies
     * $policies = [
     *   'read' => ['read1', 'read2'], //只读
     *   'write' => ['write1', 'write2'],  //只写
     *   'read+write' => ['readwrite1', 'rw'] // 读+写
     *   ];
     * @return Result
     */
    protected function setBucketPolicies(string $bucket, array $policies = []): Result
    {
        $policyString = $this->getPolicyString($policies, $bucket);

        return $this->s3Client->putBucketPolicy(['Bucket' => $bucket, 'Policy' => $policyString]);
    }

    /**
     * 生成policy设置字符串
     *
     * @param $policies
     * @param $bucket
     * @return string
     */
    protected function getPolicyString($policies, $bucket): string
    {
        $policy_types = array_keys($policies);
        sort($policy_types);
        $policy_types = implode('&', $policy_types);
        switch ($policy_types) {
            case 'read':
                $paths = $policies['read'];
                $prefix_string = '"'.implode('","', $paths).'"';
                $resource = '"'
                    .implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $paths)
                    )
                    .'"';
                $str = <<<STR
{
	"Version": "2012-10-17",
	"Statement": [{
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetBucketLocation"],
		"Resource": ["arn:aws:s3:::$bucket"]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:ListBucket"],
		"Resource": ["arn:aws:s3:::$bucket"],
		"Condition": {
			"StringEquals": {
				"s3:prefix": [$prefix_string]
			}
		}
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetObject"],
		"Resource": [$resource]
	}]
}
STR;
                break;
            case 'write':
                $paths = $policies['write'];
                $resource = '"'
                    .implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $paths)
                    )
                    .'"';
                $str = <<<STR
{
	"Version": "2012-10-17",
	"Statement": [{
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetBucketLocation", "s3:ListBucketMultipartUploads"],
		"Resource": ["arn:aws:s3:::$bucket"]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:AbortMultipartUpload", "s3:DeleteObject", "s3:ListMultipartUploadParts", "s3:PutObject"],
		"Resource": [$resource]
	}]
}
STR;

                break;
            case 'read+write':
                $paths = $policies['read+write'];
                $prefix_string = '"'.implode('","', $paths).'"';
                $resource = '"'
                    .implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $paths)
                    )
                    .'"';
                $str = <<<STR
{
	"Version": "2012-10-17",
	"Statement": [{
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetBucketLocation", "s3:ListBucketMultipartUploads"],
		"Resource": ["arn:aws:s3:::$bucket"]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:ListBucket"],
		"Resource": ["arn:aws:s3:::$bucket"],
		"Condition": {
			"StringEquals": {
				"s3:prefix": [$prefix_string]
			}
		}
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetObject", "s3:ListMultipartUploadParts", "s3:PutObject", "s3:AbortMultipartUpload", "s3:DeleteObject"],
		"Resource": [$resource]
	}]
}
STR;
                break;
            case 'read&read+write':
                $prefix_string = '"'.implode('","', array_merge($policies['read'], $policies['read+write'])).'"';
                $all_resource = '"'
                    .implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['read+write'])
                    )
                    .'"';
                $read_resource = '"'
                    .implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['read'])
                    )
                    .'"';
                $str = <<<STR
{
	"Version": "2012-10-17",
	"Statement": [{
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetBucketLocation", "s3:ListBucketMultipartUploads"],
		"Resource": ["arn:aws:s3:::$bucket"]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:ListBucket"],
		"Resource": ["arn:aws:s3:::$bucket"],
		"Condition": {
			"StringEquals": {
				"s3:prefix": [$prefix_string]
			}
		}
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:AbortMultipartUpload", "s3:DeleteObject", "s3:GetObject", "s3:ListMultipartUploadParts", "s3:PutObject"],
		"Resource": [$all_resource]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetObject"],
		"Resource": [$read_resource]
	}]
}
STR;

                break;
            case 'read+write&write':
                $prefix_string = '"'.implode('","', array_merge($policies['read'], $policies['read+write'])).'"';
                $all_resource = '"'
                    .implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['read+write'])
                    )
                    .'"';
                $read_resource = '"'
                    .implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['read'])
                    )
                    .'"';

                $str = <<<STR
{
	"Version": "2012-10-17",
	"Statement": [{
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetBucketLocation", "s3:ListBucket", "s3:ListBucketMultipartUploads"],
		"Resource": ["arn:aws:s3:::$bucket"]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:ListBucket"],
		"Resource": ["arn:aws:s3:::$bucket"],
		"Condition": {
			"StringEquals": {
				"s3:prefix": [$prefix_string]
			}
		}
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetObject"],
		"Resource": [$read_resource]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:AbortMultipartUpload", "s3:DeleteObject", "s3:ListMultipartUploadParts", "s3:PutObject"],
		"Resource": [$all_resource]
	}]
}
STR;

                break;
            case 'read&write':
                $prefix_string = '"'.implode('","', $policies['read']).'"';

                $read_resource = '"'
                    .implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['read'])
                    )
                    .'"';
                $write_resource = '"'
                    .implode(
                        '","',
                        array_map(static function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['write'])
                    )
                    .'"';
                $str = <<<STR
{
	"Version": "2012-10-17",
	"Statement": [{
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:ListBucketMultipartUploads", "s3:GetBucketLocation", "s3:ListBucket"],
		"Resource": ["arn:aws:s3:::$bucket"]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:ListBucket"],
		"Resource": ["arn:aws:s3:::$bucket"],
		"Condition": {
			"StringEquals": {
				"s3:prefix": [$prefix_string]
			}
		}
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetObject"],
		"Resource": [$read_resource]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:PutObject", "s3:AbortMultipartUpload", "s3:DeleteObject", "s3:ListMultipartUploadParts"],
		"Resource": [$write_resource]
	}]
}
STR;
                break;
            case 'read&read+write&write':
                $prefix_string = '"'.implode('","', array_merge($policies['read'], $policies['read+write'])).'"';
                $all_resource = '"'
                    .implode(
                        '","',
                        array_map(function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['read+write'])
                    )
                    .'"';
                $read_resource = '"'
                    .implode(
                        '","',
                        array_map(function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['read'])
                    )
                    .'"';
                $write_resource = '"'
                    .implode(
                        '","',
                        array_map(function ($path) use ($bucket) {
                            return "arn:aws:s3:::$bucket/$path*";
                        }, $policies['write'])
                    )
                    .'"';
                $str = <<<STR
{
	"Version": "2012-10-17",
	"Statement": [{
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetBucketLocation", "s3:ListBucket", "s3:ListBucketMultipartUploads"],
		"Resource": ["arn:aws:s3:::$bucket"]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:ListBucket"],
		"Resource": ["arn:aws:s3:::$bucket"],
		"Condition": {
			"StringEquals": {
				"s3:prefix": [$prefix_string]
			}
		}
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:DeleteObject", "s3:GetObject", "s3:ListMultipartUploadParts", "s3:PutObject", "s3:AbortMultipartUpload"],
		"Resource": [$all_resource]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:GetObject"],
		"Resource": [$read_resource]
	}, {
		"Effect": "Allow",
		"Principal": {
			"AWS": ["*"]
		},
		"Action": ["s3:AbortMultipartUpload", "s3:DeleteObject", "s3:ListMultipartUploadParts", "s3:PutObject"],
		"Resource": [$write_resource]
	}]
}
STR;
                break;
            default:
                $str = '';
                break;
        }

        return $str;
    }
}
