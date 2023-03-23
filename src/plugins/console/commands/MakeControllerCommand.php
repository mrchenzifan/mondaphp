<?php

namespace herosphp\plugins\console\commands;

use herosphp\plugins\console\Util;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeControllerCommand extends Command
{
    protected static $defaultName = 'make:controller';

    protected static $defaultDescription = 'Make Controller';

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Controller name');
    }

    /**
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $output->writeln("Make controller $name");

        $name = str_replace('\\', '/', $name);
        if (! ($pos = strrpos($name, '/'))) {
            $name = ucfirst($name);
            $controller_str = Util::guessPath(APP_PATH, 'controller') ?: 'controller';
            $file = APP_PATH."/$controller_str/$name.php";
            $namespace = $controller_str === 'Controller' ? 'App\Controller' : 'app\controller';
        } else {
            $name_str = substr($name, 0, $pos);
            if ($real_name_str = Util::guessPath(APP_PATH, $name_str)) {
                $name_str = $real_name_str;
            } else {
                if ($real_section_name = Util::guessPath(APP_PATH, strstr($name_str, '/', true))) {
                    $upper = strtolower($real_section_name[0]) !== $real_section_name[0];
                } else {
                    if ($real_base_controller = Util::guessPath(APP_PATH, 'controller')) {
                        $upper = strtolower($real_base_controller[0]) !== $real_base_controller[0];
                    }
                }
            }
            $upper = $upper ?? strtolower($name_str[0]) !== $name_str[0];
            if ($upper && ! $real_name_str) {
                $name_str = preg_replace_callback('/\/([a-z])/', function ($matches) {
                    return '/'.strtoupper($matches[1]);
                }, ucfirst($name_str));
            }
            $path = "$name_str/".($upper ? 'Controller' : 'controller');
            $name = ucfirst(substr($name, $pos + 1));
            $file = APP_PATH."/$path/$name.php";
            $namespace = str_replace('/', '\\', ($upper ? 'App/' : 'app/').$path);
        }
        $this->createController($name, $namespace, $file);

        return self::SUCCESS;
    }

    /**
     * @param $name
     * @param $namespace
     * @param $file
     * @return void
     */
    protected function createController($name, $namespace, $file): void
    {
        $path = pathinfo($file, PATHINFO_DIRNAME);
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $controller_content = <<<EOF
<?php
declare(strict_types=1);
namespace $namespace;

use herosphp\annotation\Get;
use herosphp\annotation\Post;
use herosphp\core\HttpRequest;
use herosphp\annotation\Controller;
use herosphp\core\BaseController;
use herosphp\core\HttpResponse;

#[Controller($name::class)]
class $name extends BaseController
{
    #[Get(uri: '')]
    public function index(HttpRequest \$request):HttpResponse
    {
    }

    #[Get(uri: '')]
    public function detail(HttpRequest \$request):HttpResponse
    {
    }

    #[Post(uri: '/')]
    public function create(HttpRequest \$request):HttpResponse
    {
    }

    #[Post(uri: '/')]
    public function update(HttpRequest \$request):HttpResponse
    {
    }

    #[Post(uri: '/')]
    public function delete(HttpRequest \$request):HttpResponse
    {
    }
}

EOF;
        file_put_contents($file, $controller_content);
    }
}
