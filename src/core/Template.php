<?php

// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// * Copyright 2014 The Herosphp Authors. All rights reserved.
// * Use of this source code is governed by a MIT-style license
// * that can be found in the LICENSE file.
// * +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

declare(strict_types=1);

namespace herosphp\core;

use herosphp\exception\TemplateException;
use herosphp\GF;
use herosphp\utils\FileUtil;

/**
 * 模板编译工具类,将数据模型导入到模板并输出。
 * ---------------------------------------------------------------------
 *
 * @author RockYang<yangjian102621@gmail.com>
 */
class Template
{
    // template root dir
    private static string $_temp_dir = '';

    // compile root dir
    private static string $_compile_dir = '';

    // template vars
    private array $_temp_vars = [];

    // switch for template cache
    private static bool $_cache = true;

    // template file suffix
    private static string $_temp_suffix = '.html';

    // template compile rules
    private static array $_temp_rules = [

        // {$var}, {$array['key']}
        '/{\$([^\}|\.]{1,})}/i' => '<?php echo \$${1}?>',

        // array: {$array.key}
        '/{\$([0-9a-z_]{1,})\.([0-9a-z_]{1,})}/i' => '<?php echo \$${1}[\'${2}\']?>',

        // two-demensional array
        '/{\$([0-9a-z_]{1,})\.([0-9a-z_]{1,})\.([0-9a-z_]{1,})}/i' => '<?php echo \$${1}[\'${2}\'][\'${3}\']?>',

        // for loop
        '/{for ([^\}]+)}/i' => '<?php for ${1} {?>',
        '/{\/for}/i' => '<?php } ?>',

        // foreach ( $array as $key => $value )
        '/{loop\s+\$([^\}]{1,})\s+\$([^\}]{1,})\s+\$([^\}]{1,})\s*}/i' => '<?php foreach ( \$${1} as \$${2} => \$${3} ) { ?>',
        '/{\/loop}/i' => '<?php } ?>',

        // foreach ( $array as $value )
        '/{loop\s+\$(.*?)\s+\$([0-9a-z_]{1,})\s*}/i' => '<?php foreach ( \$${1} as \$${2} ) { ?>',

        // expr: excute the php expression
        // echo: print the php expression
        '/{expr\s+(.*?)}/i' => '<?php ${1} ?>',
        '/{echo\s+(.*?)}/i' => '<?php echo ${1} ?>',

        // if else tag
        '/{if\s+(.*?)}/i' => '<?php if ( ${1} ) { ?>',
        '/{else}/i' => '<?php } else { ?>',
        '/{elseif\s+(.*?)}/i' => '<?php } elseif ( ${1} ) { ?>',
        '/{\/if}/i' => '<?php } ?>',

        // require|include tag
        '/{(require|include)\s{1,}([0-9a-z_\.]{1,})\s*}/i' => '<?php include $this->getIncludePath(\'${2}\')?>',

        // tag to import css file,javascript file
        '/{(res):([a-z]{1,})\s+([^\}]+)\s*}/i' => '<?php echo $this->importResource(\'${2}\', "${3}")?>',

        /*
         * {run}标签： 执行php表达式
         * {safeEcho}标签： isset后判断输出模板
         * {date}标签：根据时间戳输出格式化日期
         * {cut}标签：裁剪字指定长度的字符串,注意截取的格式是UTF-8,多余的字符会用...表示
         */
        '/{run\s+(.*?)}/i' => '<?php ${1} ?>',
        '/{safeEcho\s+(.*?)}/i' => '<?php if(isset(${1})) echo ${1} ?>',
        // 历史代码
        '/{saleEcho\s+(.*?)}/i' => '<?php if(isset(${1})) echo ${1} ?>',
        '/{date\s+(.*?)(\s+(.*?))?}/i' => '<?php echo \herosphp\core\Template::getDate(${1}, "${2}") ?>',
        '/{cut\s+(.*?)(\s+(.*?))?}/i' => '<?php echo \herosphp\core\Template::cutString("${1}", "${2}") ?>',
    ];

    // static resource
    private static array $_res_temp = [
        'css' => "<link rel=\"stylesheet\" type=\"text/css\" href=\"{url}\" />\n",
        'less' => "<link rel=\"stylesheet/less\" type=\"text/css\" href=\"{url}\" />\n",
        'js' => "<script charset=\"utf-8\" type=\"text/javascript\" src=\"{url}\"></script>\n",
    ];

    public function __construct()
    {
        $configs = GF::getAppConfig('template');
        $skin = $configs['skin'];

        if (! empty($configs['rules'])) {
            $this->addRules($configs['rules']);
        }

        $debug = GF::getAppConfig('debug');
        if ($debug) {
            self::$_cache = false;
        } else {
            self::$_cache = true;
        }

        // suggest base_path
        self::$_temp_dir = BASE_PATH."views/{$skin}/";

        self::$_compile_dir = RUNTIME_PATH."views/{$skin}/";
    }

    // add new template rules
    public function addRules(array $rules): void
    {
        if (! empty($rules)) {
            self::$_temp_rules = array_merge(self::$_temp_rules, $rules);
        }
    }

    // 为模板注入变量
    public function assign(string $key, mixed $value): void
    {
        $this->_temp_vars[$key] = $value;
    }

    // 获取页面执行后的代码
    public function getExecutedHtml(string $file, array $data): string
    {
        if (empty($file)) {
            throw new TemplateException('Template file is needed.');
        }

        if (! empty($data)) {
            $this->_temp_vars = array_merge_recursive($this->_temp_vars, $data);
        }

        $tempFile = self::$_temp_dir.$file.self::$_temp_suffix;
        $compileFile = self::$_compile_dir.$file.'.php';
        if (! file_exists($tempFile)) {
            throw new TemplateException("template file {$tempFile} not found.");
        }

        ob_start();
        $this->_compileTemplate($tempFile, $compileFile);
        // extract template vars
        extract($this->_temp_vars);
        include $compileFile;

        $html = ob_get_contents();
        ob_end_clean();

        return  $html;
    }

    // 获取被包含模板的路径，路径使用 . 替代 /, 如: user.profile => user/profile.html
    public function getIncludePath($tempPath = null): string
    {
        if (empty($tempPath)) {
            return '';
        }

        $filename = str_replace('.', '/', $tempPath);
        $tempFile = self::$_temp_dir.$filename.self::$_temp_suffix;
        $compileFile = self::$_compile_dir.$filename.'.php';
        $this->_compileTemplate($tempFile, $compileFile);

        return $compileFile;
    }

    public function importResource($type, $path)
    {
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $template = self::$_res_temp[$type];

        return str_replace('{url}', $path, $template);
    }

    /**
     * 获取日期
     */
    public static function getDate(?int $timestamp, string $format = 'Y-m-d H:i:s'): string
    {
        return date($format, $timestamp);
    }

    /**
     * 裁剪字符串，使用utf-8 编码裁剪.
     *
     * @param  string  $str 要裁剪的字符串
     * @param  int  $length 字符串长度
     */
    public static function cutString(string $str, int $length): string
    {
        if (mb_strlen($str, 'UTF-8') <= $length) {
            return $str;
        }

        return mb_substr($str, 0, $length, 'UTF-8').'...';
    }

    /**
     * compile template
     */
    private function _compileTemplate(string $tempFile, string $compileFile): void
    {
        // use compile cache
        if (file_exists($compileFile) && self::$_cache === true) {
            return;
        }

        // compile template
        $content = @file_get_contents($tempFile);
        if ($content === false) {
            throw new TemplateException("failed to load template file {$tempFile}");
        }
        $content = preg_replace(array_keys(self::$_temp_rules), self::$_temp_rules, $content);

        // create compile dir
        if (! file_exists(dirname($compileFile))) {
            FileUtil::makeFileDirs(dirname($compileFile));
        }

        // create compile file
        if (! file_put_contents($compileFile, $content, LOCK_EX)) {
            throw new TemplateException("failed to create compiled file {$compileFile}");
        }
    }
}
