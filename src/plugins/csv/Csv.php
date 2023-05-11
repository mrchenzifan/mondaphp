<?php

declare(strict_types=1);

namespace herosphp\plugins\csv;

use herosphp\WebApp;
use Workerman\Connection\TcpConnection;

class Csv implements ExportInterface
{
    private string $fileName;

    protected TcpConnection $connection;

    public function __construct(string $fileName)
    {
        $this->connection = WebApp::$connection;
        $this->fileName = urlencode($fileName.'.csv');
        $this->setHeader();
    }

    public function putTitle(array $title): void
    {
        $data = [];
        foreach ($title as $item) {
            $data[] = mb_convert_encoding($item, 'utf-8');
        }
        $this->connection->send(implode(',', $data)."\n", true);
    }

    public function putData(array $data): void
    {
        foreach ($data as $item) {
            $regData = [];
            foreach ($item as $v) {
                $v = static::filterSpecialValue((string) $v);
                $regData[] = $v;
            }
            $this->connection->send(implode(',', $regData)."\n", true);
        }
        $this->connection->baseWrite();
    }

    public function export(): void
    {
        $this->connection->close();
    }

    public function setHeader(): void
    {
        $headRaw = "HTTP/1.1 200 OK\r\nServer: MDServer\r\n"
            ."Content-type:text/csv;charset=utf-8\r\n"
            ."Content-Disposition:attachment;filename=\"{$this->fileName}\";\r\n"
            ."Cache-Control:must-revalidate,post-check=0,pre-check=0\r\n"
            ."Expires:0\r\n\r\n"
            ."\xEF\xBB\xBF";
        $this->connection->send($headRaw, true);
    }

    protected static function filterSpecialValue(string $v): string
    {
        //过滤回车等字符
        $v = preg_replace("/(\r\n|\n|\r|\t)/i", '', $v);
        //过滤双引号
        $v = str_replace('"', '""', $v);
        //过滤特殊字符
        $v = static::replaceSpecialChar($v);
        //过滤英文逗号，保证一个数据是一个单元格
        return '"'."\t".$v.'"';
    }

    protected static function replaceSpecialChar(string $strParam): string
    {
        preg_match_all('/[\x{4e00}-\x{9fff}\d\w\s[:punct:]]+/u', $strParam, $result);

        return implode('', $result[0]);
    }
}
