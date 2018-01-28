<?php

namespace backend\controllers;

use Yii;
use common\models\OrderQuery;
use common\models\ProductQuery;
use common\models\Type;

class ExcelController extends BaseController
{
    public function actionIndex()
    {

        $queryParams = Yii::$app->request->get('orderQuery');
        $selected_id = Yii::$app->request->get('selected_id');


        if ($selected_id) {
            //直接选中的导出
            $data = OrderQuery::find()->where("id in ($selected_id)")->all();

        } elseif ($queryParams) {
            //查询时的导出
            $queryParams = json_decode(base64_decode($queryParams), true);
            $searchModel = new OrderQuery();
            $data = $searchModel->search($queryParams, $all = true)->getModels();

        } else {
            //非查询时的导出
            $data = OrderQuery::find()->limit(10)->all();
        }

        if (empty($data)) {
           Yii::$app->session->setFlash('error', '没有可以导出的数据');
           return $this->redirect(Yii::$app->request->referrer);
        }

        return $this->_exportExcel($data);
    }

    private function _exportExcel($data)
    {
        //初始化实例
        $objPHPExcel = new \PHPExcel();
        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);


        //设置表头
        $headStyle = array(
            'font' => array(
                'bold' => true,
                'name' => '宋体'
            ),
            'alignment' => array(
                'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                'vertical' => \PHPExcel_Style_Alignment::VERTICAL_CENTER
            ),
            'borders' => array(
                'top' => array(
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,
                ),
                'left' => array(
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,
                ),
                'right' => array(
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,
                ),
                'bottom' => array(
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,
                ),
            ),
            'fill' => array(
                'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                'startcolor' => array(
                    'rgb' => '92D050',
                ),

            ),
        );

        $sheet = $objPHPExcel->getActiveSheet();

        $columns = [
            ['A1','B1','C1','D1','E1','F1','G1','H1','I1','J1','K1','L1','M1','N1','O1','P1','Q1','R1','S1','T1','U1','V1','W1','X1','Y1','Z1','AA1','AB1','AC1','AD1','AE1','AF1','AG1','AH1','AI1','AJ1','AK1'],
            ['A2','B2','C2','D2','E2','F2','G2','H2','I2','J2','K2','L2','M2','N2','O2','P2','Q2','R2','S2','T2','U2','V2','W2','X2','Y2','Z2','AA2','AB2','AC2','AD2','AE2','AF2','AG2','AH2','AI2','AJ2','AK2']
        ];

        foreach ($columns as $lines) {
            foreach ($lines as $column) {
                //设置自动换行
                $sheet->getStyle($column)->getAlignment()->setWrapText(true);
                //设置原谅色
                $sheet->getStyle($column)->applyFromArray($headStyle);
            }
        }

        //设置行高
        $sheet->getRowDimension('1')->setRowHeight(25);
        $sheet->getRowDimension('2')->setRowHeight(35);

        //设置宽度
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(8);
        $sheet->getColumnDimension('D')->setWidth(8);
        $sheet->getColumnDimension('E')->setWidth(8);
        $sheet->getColumnDimension('F')->setWidth(8);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(8);
        $sheet->getColumnDimension('I')->setWidth(8);
        $sheet->getColumnDimension('K')->setWidth(35);
        $sheet->getColumnDimension('AB')->setWidth(15);
        $sheet->getColumnDimension('AC')->setWidth(8);
        $sheet->getColumnDimension('AD')->setWidth(35);
        $sheet->getColumnDimension('AE')->setWidth(8);
        $sheet->getColumnDimension('AF')->setWidth(15);
        $sheet->getColumnDimension('AG')->setWidth(8);
        $sheet->getColumnDimension('AH')->setWidth(8);
        $sheet->getColumnDimension('AI')->setWidth(8);
        $sheet->getColumnDimension('AJ')->setWidth(8);
        $sheet->getColumnDimension('AK')->setWidth(20);

        //设置边框
        $borderStyle = array(
            'borders' => array(
                'allborders' => array(
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,//细边框
                    'color' => array('argb' => '00000000'),
                ),
            ),
        );


        $fieldAttribute = [
            'A1' => '订单详情',
            'M1' => '收入',
            'U1' => '支出',
            'AD1' => '发货',
            'AH1' => '结算',
            'A2' => '客人ID',
            'B2' => '淘宝订单号',
            'C2' => '订单日期',
            'D2' => '收资料日',
            'E2' => '寄珠海日期',
            'F2' => '入馆日',
            'G2' => '国家',
            'H2' => '类型',
            'I2' => "接待\n销售",
            'J2' => "操作\n人员",
            'K2' => '办理人',
            'L2' => '套餐类型',
            'M2' => '单项实收',
            'N2' => '数量',
            'O2' => '补差',
            'P2' => '照片',
            'Q2' => '快递',
            'R2' => '合计',
            'S2' => '手续费',
            'T2' => '实收',
            'U2' => '单项实付',
            'V2' => '数量',
            'W2' => '补差',
            'X2' => '照片',
            'Y2' => '快递',
            'Z2' => '实付合计',
            'AA2' => '利润',
            'AB2' => '收件人',
            'AC2' => '收件电话',
            'AD2' => '收件地址',
            'AE2' => "出签\n日期",
            'AF2' => "发货\n日期",
            'AG2' => '寄回客人单号',
            'AH2' => "支付日期",
            'AI2' => "店铺收款日",
            'AJ2' => "公司收款日",
            'AK2' => '备注'
        ];

        foreach ($fieldAttribute as $column_x => $field) {
            $sheet->setCellValue($column_x,  $field);
        }

        $headOne = ['A1:L1', 'M1:R1', 'U1:Z1', 'AB1:AG1', 'AH1:AJ1'];
        foreach ($headOne as $head) {
            $sheet->mergeCells($head);
        }

        //填充内容
        $columnFieldMap = [
            'A' => 'customer_id',
            'B' => '',
            'C' => 'order_date',//order_date
            'D' => 'collect_date',
            'E' => 'deliver_date',
            'F' => 'entry_date',
            'G' => ['snapshot','combo_product'],
            'H' => '',
            'I' => ['servicer','name'],
            'J' => '',//操作人员
            'K' => '',//办理人名称
            'L' => '',//套餐类型
            'M' => 'single_sum',
            'N' => 'total_person',
            'O' => 'balance_sum',
            'P' => 'flushphoto_sum',
            'Q' => 'carrier_sum',//快递
            'R' => '',//合计
            'S' => '',//手续费
            'T' => '',//实收
            'U' => '',//单项实付合计
            'V' => 'total_person',//数量
            'W' => 'balance_sum',//补差
            'X' => 'flushphoto_sum',//照片
            'Y' => 'carrier_sum',//快递
            'Z' => '',//实付合计
            'AA' => '',//利润
            'AB' => 'back_addressee',
            'AC' => 'back_telphone',
            'AD' => 'back_address',
            'AE' => 'putsign_date',
            'AF' => 'delivergood_date',
            'AG' => '',//寄回客人单号
            'AH' => 'pay_date',
            'AI' => 'receipt_date', //店铺收款日
            'AJ' => 'company_receipt_date',
            'AK' => 'remark'
        ];

        $row = 3;

        //设置默认字体 大小 颜色
        $objPHPExcel->getDefaultStyle()->getFont()->setName( '宋体');
        $objPHPExcel->getDefaultStyle()->getFont()->setSize(9);
        $objPHPExcel->getDefaultStyle()->getFont()->setColor(new \PHPExcel_Style_Color(\PHPExcel_Style_Color::COLOR_RED));

        foreach ($data as $object) {

            //数据准备
            $charge = $object->snapshot->combo_charge;
            $cost = $object->snapshot->combo_cost;

            foreach ($columnFieldMap as $_column => $_field) {
                //设置行高
                $sheet->getRowDimension($row)->setRowHeight(23);

                //设置边框
                $sheet->getStyle("A{$row}:AK{$row}")->applyFromArray($borderStyle);

                //设置水平竖直居中
                $sheet->getStyle($_column . $row)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($_column . $row)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);

                //设置宽度
                $width = $sheet->getColumnDimension($_column)->getWidth();
                $sheet->getColumnDimension($_column)->setWidth($width * 1.1);

                //设置自动换行
                $sheet->getStyle($_column)->getAlignment()->setWrapText(true);

                //填充数据
                $cellValue = false;

                if (!empty($_field) && !is_array($_field)) {

                    if (strpos($_field, 'date') !== false ) {

                        if ($object->$_field !== '1970-01-01') {
                            $cellValue = date('n月j日', strtotime($object->$_field));
                        } else {
                            $cellValue = "未设置";
                        }
                    } else {
                        $cellValue = !empty($object->$_field) ? $object->$_field : '未设置';
                    }

                } elseif ($_column == 'B') {
                    $cellValue = str_replace([',','，'],"  ", $object->order_num);
                    $sheet->getStyle($_column . $row)->getAlignment()->setWrapText(true);
                    $sheet->getStyle($_column . $row)->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_TEXT);

                } elseif ($_column == 'G') {
                    $product = $object->snapshot->combo_product;
                    $cellValue = $product ? $product : '已删除';

                } elseif ($_column == 'H') {
                    $type = Type::getComboType();
                    $cellValue = isset($type[$object->order_type]) ? $type[$object->order_type] : '已删除';

                } elseif ($_column == 'I') {
                    $servicer = $object->servicer->name;
                    $cellValue = $servicer ? $servicer : '已删除';

                } elseif ($_column == 'J') {
                    $operator = $object->operator->username;
                    $cellValue = $operator ? $operator : '已删除';

                } elseif ($_column == 'U') {
                    $cost = $object->snapshot->combo_cost;
                    $cellValue = $cost ? $cost : '数据丢失';

                } elseif ($_column == 'K') {
                    $transactors = $object->relatedTransactor;
                    $str = "";
                    foreach ($transactors as $transator) {
                        $str .= $transator['name'] . " ";
                    }
                    $cellValue = $str ? $str : '已删除';

                    //设置批注
                    if ($object->remark) {
                        $commentAuthor = $object->operator->username;
                        $commentAuthor = !empty($commentAuthor) ? $commentAuthor : "PHPExcel";
                        $sheet->getComment( $_column . $row)->setAuthor($commentAuthor);     //设置作者
                        $objCommentRichText = $sheet->getComment($_column . $row )->getText()->createTextRun($commentAuthor . " :");  //添加批注
                        $objCommentRichText->getFont()->setBold( true);  //将现有批注加粗
                        $sheet->getComment( $_column . $row)->getText()->createTextRun("\r\n" );      //添加更多批注
                        $sheet->getComment( $_column . $row)->getText()->createTextRun($object->remark);
                        $sheet->getComment( $_column . $row)->setWidth('100pt' );      //设置批注显示的宽高 ，在office中有效在wps中无效
                        $sheet->getComment( $_column . $row)->setHeight('100pt' );
                        $sheet->getComment( $_column . $row)->setMarginLeft('150pt' );
                        $sheet->getComment( $_column . $row)->getFillColor()->setRGB('FFFFD8' );      //设置背景色 ，在office中有效在wps中无效
                    }

                } elseif ($_column == 'L') {
                    $combo = $object->snapshot->combo_name;
                    $cellValue = $combo ? $combo : "丢失数据";

                } elseif ($_column == 'R') { //收入合计
                    //累加处理
                    $cellValue = $object->total_person * $object->single_sum +
                        $object->flushphoto_sum +
                        $object->carrier_sum +
                        $object->balance_sum;

                } elseif ($_column == 'S') {
                    //手续费率
                    $cellValue = $charge;

                } elseif ($_column == 'T') {
                    //乘以手续费率
                    $income = $object->total_person * $object->single_sum +
                        $object->flushphoto_sum +
                        $object->carrier_sum +
                        $object->balance_sum;

                    $cellValue = $income * ( $charge > 0 ? $charge : 1);;

                }   elseif ($_column == 'Z') { //支出合计
                    //累加处理
                    $cellValue = $object->total_person * $cost +
                        $object->flushphoto_sum +
                        $object->carrier_sum +
                        $object->balance_sum;

                }elseif ($_column == 'AA') {
                    //收入减支出
                    $income = ($object->total_person * $object->single_sum +
                        $object->flushphoto_sum +
                        $object->carrier_sum +
                        $object->balance_sum) * ( $charge > 0 ? $charge : 1);

                    $cost = $object->total_person * $cost +
                        $object->flushphoto_sum +
                        $object->carrier_sum +
                        $object->balance_sum;

                    $cellValue = $income - $cost;
                }

                if ($cellValue) {
                    $sheet->setCellValue($_column . $row, $cellValue);
                }
            }
            $row++;
        }

        //设置文件名称
        $file_name = "阳光假日天猫报表" . date('Ymd_His');

        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");;
        header('Content-Disposition:attachment;filename='.$file_name.'.xlsx');
        header("Content-Transfer-Encoding:binary");

        $objWriter->save('php://output');

    }

    
    public function actionIndex2()
    {
       $cellStyle = [
           'font' => [
               'bold' => false,
               'color' => ['rgb' => 'FF0000'],
               'size' => 9,
               'name' => '宋体'
           ],
           'alignment' => [
               'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
           ],
           'borders' => [
               'top' => [
                   'style' => \PHPExcel_Style_Border::BORDER_THIN,
               ],
           ],
       ];

        /*设置单元格颜色*/
       $file = \Yii::createObject([
            'class' => 'codemix\excelexport\ExcelFile',
            'sheets' => [
                'Users' => [
                    'class' => 'codemix\excelexport\ActiveExcelSheet',
                    'query' => ProductQuery::find()->limit(10),
                    'callbacks' => [
                        // $cell is a PHPExcel_Cell object
                        'A' => function ($cell, $row, $column) use ($cellStyle) {
                            $cell->getStyle()->applyFromArray($cellStyle);
                        },
                        'B' => function ($cell, $row, $column)  use ($cellStyle)  {
                            $cell->getStyle()->applyFromArray($cellStyle);
                        },
                        'C' => function ($cell, $row, $column)  use ($cellStyle)  {
                            $cell->getStyle()->applyFromArray($cellStyle);
                        },
                        'D' => function ($cell, $row, $column)  use ($cellStyle)  {
                            $cell->getStyle()->applyFromArray($cellStyle);
                        },
                        'E' => function ($cell, $row, $column)  use ($cellStyle)  {
                            $cell->getStyle()->applyFromArray($cellStyle);
                        },
                    ],
                ],
            ],
        ]);

        $headColumn = ['A1:K1', 'L1:Q1', 'R1:Y1', 'AA1:AF1', 'AG1:AH1'];
        foreach ($headColumn as $head) {
           // $file->getWorkbook()->getSheet(0)->mergeCells($head);
        }

       //设置表头颜色
        $headStyle = array(

            'font' => array(
                'bold' => true,
            ),
            'alignment' => array(
                'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                    'top' => array(
                        'style' => \PHPExcel_Style_Border::BORDER_THIN,
                    ),
                    'left' => array(
                        'style' => \PHPExcel_Style_Border::BORDER_THIN,
                    ),
                    'right' => array(
                        'style' => \PHPExcel_Style_Border::BORDER_THIN,
                    ),
                    'bottom' => array(
                        'style' => \PHPExcel_Style_Border::BORDER_THIN,
                    ),
            ),
            'fill' => array(
                'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                'startcolor' => array(
                    'argb' => '92d050',
                ),
            ),
        );

        $file->getWorkbook()->getSheet(0)->getStyle("A1")->applyFromArray($headStyle);
        $file->getWorkbook()->getSheet(0)->getStyle("B1")->applyFromArray($headStyle);

       // $file->getWorkbook()->getSheet(0)->getStyle("L1")->applyFromArray($headStyle);


        $file->send(date('YMDHIs').'.xlsx');
        return $this->render('index');
    }

}
