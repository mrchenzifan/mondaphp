<?php

namespace herosphp\plugins\console\commands;

use herosphp\plugins\console\Util;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeMiddlewareCommand extends Command
{
    protected static $defaultName = 'make:middleware';

    protected static $defaultDescription = 'Make Middleware';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Middleware name');
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $output->writeln("Make middleware $name");

        $name = str_replace('\\', '/', $name);
        if (! $middleware_str = Util::guessPath(APP_PATH, 'middleware')) {
            $middleware_str = Util::guessPath(APP_PATH, 'controller') === 'Controller' ? 'Middleware' : 'middleware';
        }
        $upper = $middleware_str === 'Middleware';
        if (! ($pos = strrpos($name, '/'))) {
            $name = ucfirst($name);
            $file = APP_PATH."$middleware_str/$name.php";
            $namespace = $upper ? 'App\Middleware' : 'app\middleware';
        } else {
            if ($real_name = Util::guessPath(APP_PATH, $name)) {
                $name = $real_name;
            }
            if ($upper && ! $real_name) {
                $name = preg_replace_callback('/\/([a-z])/', function ($matches) {
                    return '/'.strtoupper($matches[1]);
                }, ucfirst($name));
            }
            $path = "$middleware_str/".substr($upper ? ucfirst($name) : $name, 0, $pos);
            $name = ucfirst(substr($name, $pos + 1));
            $file = APP_PATH."$path/$name.php";
            $namespace = str_replace('/', '\\', ($upper ? 'App/' : 'app/').$path);
        }

        $this->createMiddleware($name, $namespace, $file);

        return self::SUCCESS;
    }

    /**
     * @param $name
     * @param $namespace
     * @param $file
     * @return void
     */
    protected function createMiddleware($name, $namespace, $file): void
    {
        $path = pathinfo($file, PATHINFO_DIRNAME);
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $middleware_content = <<<EOF
<?php

declare(strict_types=1);

namespace $namespace;

use herosphp\core\HttpRequest;
use herosphp\core\MiddlewareInterface;

class $name implements MiddlewareInterface
{
    public function process(HttpRequest \$request, callable \$handler): mixed
    {
        return \$handler(\$request);
    }
}


EOF;
        file_put_contents($file, $middleware_content);
    }
}
