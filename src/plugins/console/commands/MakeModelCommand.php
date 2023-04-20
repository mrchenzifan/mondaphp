<?php

namespace herosphp\plugins\console\commands;

use herosphp\GF;
use herosphp\plugins\console\Util;
use herosphp\plugins\database\Db;
use Illuminate\Database\Capsule\Manager;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MakeModelCommand extends Command
{
    protected static $defaultName = 'make:model';

    protected static $defaultDescription = 'Make Model';

    // model name
    protected static string $name;

    // model table name
    protected static string $table;

    // db config
    protected static array $dbConfig;

    protected static bool $overwrite = true;

    /**
     * @return void
     */
    protected function configure(): void
    {
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        static::$dbConfig = GF::config('database', []);
        $io = new SymfonyStyle($input, $output);
        static::$name = $io->ask('请输入模型名称?', '', function ($name) {
            if (! $name) {
                throw new RuntimeException('模型名称不能为空.');
            }

            return $name;
        });
        static::$table = $io->ask('请输入表名称?', '', function ($table) {
            if (! $table) {
                throw new RuntimeException('请输入表名称.');
            }

            return $table;
        });

        if (! static::$dbConfig) {
            $output->writeln('请配置数据库！');

            return self::FAILURE;
        }
        // connect db
        $capsule = new Manager;
        foreach (static::$dbConfig as $k => $v) {
            $capsule->addConnection($v, $k);
        }
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        static::$name = Util::nameToClass(static::$name);
        $output->writeln('Make model '.static::$name);
        static::$name = ucfirst(static::$name);
        $file = APP_PATH.'entity/'.static::$name.'.php';
        $namespace = 'app\entity';
        $this->createModel($namespace, $file);

        return self::SUCCESS;
    }

    /**
     * @param $namespace
     * @param $file
     * @return void
     */
    protected function createModel($namespace, $file): void
    {
        $class = static::$name;
        $path = pathinfo($file, PATHINFO_DIRNAME);
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $table = static::$table;
        $table_val = '"'.static::$table.'"';
        $pk = 'id';
        $properties = '';
        $database = static::$dbConfig['default']['database'];
        $incrementingBool = 'true';
        $fillable_val = '[';
        try {
            foreach (Db::connection('default')->select("select COLUMN_NAME,DATA_TYPE,COLUMN_KEY,COLUMN_COMMENT from INFORMATION_SCHEMA.COLUMNS where table_name = '$table' and table_schema = '$database'") as $item) {
                $type = $this->getType($item->DATA_TYPE);
                if ($item->COLUMN_KEY === 'PRI') {
                    $pk = $item->COLUMN_NAME;
                    $item->COLUMN_COMMENT .= '(主键)';
                    if ($type == 'string') {
                        $incrementingBool = 'false';
                    }
                }
                $properties .= " * @property $type \${$item->COLUMN_NAME} {$item->COLUMN_COMMENT}\n";
                if (! in_array($item->COLUMN_NAME, ['created_at', 'updated_at'])) {
                    $fillable_val .= "'{$item->COLUMN_NAME}', ";
                }
            }
        } catch (\Throwable $e) {
        }
        $fillable_val = rtrim($fillable_val, ', ');
        $fillable_val .= ']';
        $properties = rtrim($properties) ?: ' *';
        $model_content = <<<EOF
<?php
declare(strict_types=1);

namespace $namespace;

use herosphp\plugins\database\Model;

/**
$properties
 */
class $class extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected \$table = $table_val;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected \$primaryKey = '$pk';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public \$incrementing = $incrementingBool;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public \$timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected \$fillable = $fillable_val;
}

EOF;

        file_put_contents($file, $model_content);
    }

    protected function getType(string $type): string
    {
        if (str_contains($type, 'int')) {
            return 'integer';
        }

        return match ($type) {
            'char', 'varchar', 'string', 'text', 'date', 'time', 'guid', 'datetimetz', 'datetime', 'decimal', 'enum' => 'string',
            'boolean' => 'integer',
            'float' => 'float',
            default => 'mixed',
        };
    }
}
