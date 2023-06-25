<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace herosphp\plugins\excel;

use herosphp\core\HttpResponse;
use herosphp\exception\HeroException;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * @author chenzifan
 * @note composer install "phpoffice/spreadsheet"
 */
class Export
{
    /**
     * excel表格导出
     *
     * @param  string  $fileName  文件名称 $name='测试导出';
     * @param  array  $headArr  表头名称 $header=['表头A','表头B'];
     * @param  array  $data  要导出的数据 $data=[['测试','测试'],['测试','测试']]
     * @param  bool  $auto  是否开启根据表头自适应宽度 默认开启
     * @param  string  $title
     * @param  string  $keywords
     * @param  string  $creator
     * @param  string  $lastModifiedBy
     * @param  string  $description
     * @return HttpResponse
     *
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public static function export(
        string $fileName,
        array $headArr = [],
        array $data = [],
        bool $auto = true,
        string $title = 'MondaPHP',
        string $keywords = 'MondaPHP',
        string $creator = 'MondaPHP',
        string $lastModifiedBy = 'MondaPHP',
        string $description = 'MondaPHP xlsx documentation',
    ): HttpResponse {
        if (! class_exists(Spreadsheet::class)) {
            throw new HeroException('please composer install "phpoffice/phpspreadsheet"');
        }
        $fileName .= '.xlsx';
        $objPHPExcel = new Spreadsheet;
        $objPHPExcel->getProperties()
            ->setTitle($title)
            ->setCreator($creator)
            ->setLastModifiedBy($lastModifiedBy)
            ->setDescription($description)
            ->setKeywords($keywords);

        $key = ord('A'); // 设置表头
        $key2 = ord('@'); //	超过26列会报错的解决方案
        $objPHPExcel->getDefaultStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getDefaultStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $colum = 'A';
        foreach ($headArr as $v) {
            // 超过26列会报错的解决方案
            if ($key > ord('Z')) {
                $key2 += 1;
                $key = ord('A');
                $colum = chr($key2).chr($key); //超过26个字母时才会启用
            } else {
                if ($key2 >= ord('A')) {
                    $colum = chr($key2).chr($key);
                } else {
                    $colum = chr($key);
                }
            }
            // 写入表头
            $objPHPExcel->setActiveSheetIndex(0)->setCellValue($colum.'1', $v);
            // 自适应宽度
            if ($auto) {
                $len = strlen(iconv('utf-8', 'gbk', $v));
                $objPHPExcel->getActiveSheet()->getColumnDimension($colum)->setWidth($len + 8);
            }
            $key += 1;
        }
        $column = 2;
        $objActSheet = $objPHPExcel->getActiveSheet();
        $objActSheet->setTitle('工作簿');
        // 写入行数据
        foreach ($data as $key => $rows) {
            $span = ord('A');
            $span2 = ord('@');
            // 按列写入
            foreach ($rows as $keyName => $value) {
                // 超过26列会报错的解决方案
                if ($span > ord('Z')) {
                    $span2 += 1;
                    $span = ord('A');
                    $tmpSpan = chr($span2).chr($span); //超过26个字母时才会启用
                } else {
                    if ($span2 >= ord('A')) {
                        $tmpSpan = chr($span2).chr($span);
                    } else {
                        $tmpSpan = chr($span);
                    }
                }
                // 写入数据
                $objActSheet->setCellValue($tmpSpan.$column, $value);
                $span++;
            }
            $column++;
        }
        // 自动加边框
        $styleThinBlackBorderOutline = [
            'borders' => [
                'allborders' => [ //设置全部边框
                    'style' => Border::BORDER_THIN, //粗的是thick
                ],
            ],
        ];
        $objPHPExcel->getActiveSheet()->getStyle('A1:'.$colum.--$column)->applyFromArray($styleThinBlackBorderOutline);
        $fileName = urlencode($fileName);
        $objPHPExcel->setActiveSheetIndex(0);
        $httpResponse = new HttpResponse;
        $writer = new Xlsx($objPHPExcel);
        ob_start();
        $writer->save('php://output');
        $c = ob_get_clean();
        // 构造response
        $httpResponse->withHeaders([
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment;filename='.$fileName,
            'Cache-Control' => 'max-age=0',
        ])->withBody($c);
        // 释放内存
        $objPHPExcel->disconnectWorksheets();
        unset($spreadsheet, $writer);

        return $httpResponse;
    }
}
