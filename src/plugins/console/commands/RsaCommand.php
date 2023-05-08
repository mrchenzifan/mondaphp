<?php

namespace herosphp\plugins\console\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RsaCommand extends Command
{
    protected static $defaultName = 'rsa';

    protected static $defaultDescription = 'generate RSA configuration file';

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        //配置信息
        $config = [
            'digest_alg' => 'sha512',
            'private_key_bits' => 1024, //指定多少位来生成私钥
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $res = openssl_pkey_new($config);
        //获取私钥
        openssl_pkey_export($res, $privateKey, null, $config);
        //获取公钥
        $details = openssl_pkey_get_details($res);
        $publicKey = $details['key'];
        $this->createFile($publicKey, $privateKey);

        return self::SUCCESS;
    }

    /**
     * @param  string  $publicKey
     * @param  string  $privateKey
     * @return void
     */
    protected function createFile(string $publicKey, string $privateKey): void
    {
        $content = <<<EOF
<?php

declare(strict_types=1);

return [
    'private_key' => '{$privateKey}',
    'public_key' => '{$publicKey}',
];
EOF;

        $file = CONFIG_PATH.'rsa.config.php';
        file_put_contents($file, $content);
    }
}
