<?php

namespace backend\controllers;

use backend\models\search\AuthItemSearch;
use common\models\ExportSetting;
use Yii;
use app\models\OrderToTransactor;
use backend\models\Admin;
use common\models\Order;
use common\models\Servicer;
use common\models\Snapshot;
use common\models\Transator;
use common\models\OrderQuery;
use common\models\ProductQuery;
use common\models\Type;
use common\models\UploadForm;
use yii\web\UploadedFile;

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
            $data = OrderQuery::find()->all();
        }

        if (empty($data)) {
           Yii::$app->session->setFlash('error', '没有可以导出的数据');
           return $this->redirect(Yii::$app->request->referrer);
        }

        $file_name = "订单" . date('Y_m_d');
        if (isset($queryParams['OrderQuery']['order_classify']) && !empty($queryParams['OrderQuery']['order_classify'])) {
            $classify = Type::getComboClassify();
            $file_name = isset($classify[$queryParams['OrderQuery']['order_classify']]) ? $classify[$queryParams['OrderQuery']['order_classify']]  : '';
            $file_name .= "订单" . date('Y_m_d');
        }

        //查询当前用户的角色
        $authManager = Yii::$app->authManager;
        $roleNames = $authManager->getRolesByUser(Yii::$app->user->id);

        //查询导出设定
        $setting = ExportSetting::findOne(["rolename" => current($roleNames)->name]);

        if (is_null($setting) || $setting->showfinance == 0) {
            return $this->_exportPartOfExcel($data, $file_name);
        } else {
            return $this->_exportExcel($data, $file_name);
        }

    }


    public function actionImport()
    {
        $model = new UploadForm();

        if (Yii::$app->request->isPost) {
            $uploadFile = UploadedFile::getInstance($model,'file');
            $this->_importExcel($uploadFile->tempName);
        }

        return $this->render('import', [
            'model' => $model
        ]);
    }

    private function _importExcel($excelFilename)
    {
        $reader = \PHPExcel_IOFactory::createReader('Excel2007'); // 读取 excel 文档
        $PHPExcel = $reader->load($excelFilename); // 文档名称
        $sheet = $PHPExcel->getSheet(0); // 读取第一个工作表(编号从 0 开始)
        $highestRow = $sheet->getHighestRow(); // 取得总行数

        $indexToColumn =  [ 1=>'A',2=>'B',3=>'C',4=>'D',5=>'E',6=>'F',7=>'G',8=>'H',9=>'I',10=>'J',11=>'K',12=>'L',13=>'M', 14=>'N',15=>'O',16=>'P',17=>'Q',18=>'R',19=>'S',20=>'T',21=>'U',22=>'V',23=>'W',24=>'X',25=>'Y',26=>'Z',27=>'AA',28=>'AB',29=>'AC',30=>'AD',31=>'AE',32=>'AF',33=>'AG',34=>'AH',35=>'AI',36=>'AJ',37=>'AK','38'=>'AL'];

        $columnToField = [
            'A' => 'customer_id',
            'B' => 'order_num',
            'C' => 'order_date',//order_date
            'D' => 'collect_date',
            /*'E' => 'deliver_date',*/
            'E' => 'entry_date',
            'F' => 'combo_product',
            'G' => 'combo_type',
            'H' => 'servicer',
            'I' => 'operator_id',//操作人员
            'J' => 'transator_id',//办理人名称
            'K' => 'combo_classify',//套餐类型
            'L' => 'combo_name',
            'M' => 'single_sum',
            'N' => 'total_person',
            'O' => 'balance_sum',
            'P' => 'flushphoto_sum',
            'Q' => 'carrier_sum',//快递
            'R' => '',//合计
            'S' => 'combo_charge',//手续费
            'T' => '',//实收
            'U' => 'combo_cost',//单项实付合计
            'V' => 'total_person',//数量
            'W' => 'output_balance_sum',//补差
            'X' => 'output_flushphoto_sum',//照片
            'Y' => 'output_carrier_sum',//快递
            'Z' => '',//实付合计
            'AA' => '',//利润
            'AB' => 'back_addressee',
            'AC' => 'back_telphone',
            'AD' => 'back_address',
            'AE' => 'putsign_date',
            'AF' => 'delivergood_date',
            'AG' => '',//寄回客人单号
            'AH' => 'pay_date',
            'AI' => 'receipt_date',
            'AJ' => 'company_receipt_date', //店铺收款日
            'AK' => 'pay_account',
            'AL' => 'remark'
        ];

        $importTotal = 0;

        foreach ($sheet->getRowIterator() as $row) {  //逐行处理

            //创建order对象
            $order = new Order();
            $snapshot = new Snapshot();
            $servicer = new Servicer();
            $existTransactor = [];
            $notExistTransactorName = [];

            if ($row->getRowIndex() > 2 ) {  //确定从哪一行开始读取

                $column = 1;
                foreach ($row->getCellIterator() as $cell) { //逐列读取

                    if (!isset($indexToColumn[$column])) {
                        continue;
                    }

                    $field = $columnToField[$indexToColumn[$column]];

                    if ($field) {
                        $data = $cell->getValue(); //获取cell中数据
                        //echo $order->getAttributeLabel($field)," : ", $data, "   ";
                       // echo $field," : ", $data, "   ";

                        switch ($field)
                        {
                            case 'order_num':
                                //判断订单号是否存在
                                $order->order_num = trim($data,  "'");
                                break;

                            case 'combo_type':
                                $types = Type::getComboType();
                                $type = array_search($data, $types);
                                $snapshot->$field = (string)$type;
                                $order->order_type = (string)$type;
                                break;

                           case 'servicer':
                                //查找系统 有没有此客服
                                 $servicer->name = $data;
                                break;

                            case 'operator_id':
                                //查找操作用户
                                $uid = Admin::findOne(['username' => $data]);
                                if ($uid) {
                                    $order->$field = $uid->id;
                                }
                                break;

                            case 'transator_id':
                                //查找办理人
                                $transactors = explode(' ',trim($data));
                                if (!empty($transactors)) {
                                    foreach ($transactors as $transactor) {
                                        $_transactor = Transator::findOne(['name' => $transactor]);
                                        if ($_transactor) {
                                            $existTransactor[] = $_transactor->tid;
                                        } else {
                                            $notExistTransactorName[] = $transactor;
                                        }

                                    }
                                }
                                break;

                            case 'combo_classify':
                                $classify = Type::getComboClassify();
                                $type = array_search($data, $classify);
                                $snapshot->$field = (string)$type;
                                $snapshot->snap_combo_id = '0';
                                $order->order_classify = (string)$type;
                                break;

                            case 'combo_name':
                            case 'combo_product':
                            case 'combo_charge':
                            case 'combo_cost':
                                $snapshot->$field = (string)$data;
                                break;
                            case 'back_telphone':
                                $data = trim($data, "'");
                                $order->$field = (string)$data;
                                break;
                            case 'order_date':
                            case 'collect_date':
                            case 'deliver_date':
                            case 'entry_date':
                            case 'putsign_date':
                            case 'delivergood_date':
                            case 'pay_date':
                            case 'receipt_date':
                            case 'company_receipt_date':
                                if ($data) {
                                    $data = date('Y-m-d', strtotime(str_replace(['月', '日'],['/', ''], $data)));
                                    $order->$field = $data;
                                }
                                break;
                            case 'remark':
                                $order->remark = $data;
                                break;
                            default:
                                $order->$field = $data;
                                break;
                        }
                    }
                    $column++;
                }

                //对象已经收集数据完毕
                //判断订单是否不存在 办理人是否存在
                $isExistOrder = Order::findOne(['order_num' => $order->order_num]);
                $servicerData = Servicer::findOne(['name'=>$servicer->name]);

                if (is_null($isExistOrder) && !is_null($servicerData)) {

                    //保存快照
                    $snapshotResult = $snapshot->save();

                    //新增订单
                    $order->transactor_id = "0";
                    $order->combo_id = $snapshot->id;
                    $order->back_addressee = empty($order->back_addressee)? "-" : $order->back_addressee;
                    $order->custom_servicer_id = $servicerData->id;
                    $order->output_balance_sum = empty($order->output_balance_sum) ? '0.00' : $order->output_balance_sum;

                    $orderResult = $order->save();

                    //处理不存在的办理人
                    if (!empty($notExistTransactorName)) {
                        foreach ($notExistTransactorName as $newTrName) {
                            $newTransactor = new Transator();
                            $newTransactor->name = $newTrName;
                            /*$newTransactor->remark = "";*/
                            $transactorResult = $newTransactor->save(false);
                            $isExistOrder[] = $newTransactor->tid;
                        }
                    }

                    //处理所有办理人的 订单-办理人关系
                    foreach ($existTransactor as $existTrId) {
                        $orderToTran = (new OrderToTransactor());
                        $orderToTran->t_id = $existTrId;
                        $orderToTran->o_id = $order->id;
                        $orderToTran->save();
                    }

                    if (!$orderResult || !$snapshotResult ) {
                       // var_dump($snapshot->getErrors());
                       // var_dump($order->getErrors());
                        if (isset($newTransactor)) {
                            //var_dump($newTransactor->getErrors());
                        }
                        exit;
                    }
                    $importTotal++;
                }



               /* echo "<hr/>";
                var_dump($existTransactor);
                var_dump($notExistTransactorName);
                echo "<hr/>";
                //print_r($snapshot); ok
                echo "<hr/>";
                //print_r($servicer); ok 保存之前检查name
                echo "<hr/>";
                //print_r($order);*/

            }

           // echo '<hr/>';
        }

        if ($importTotal > 0) {
            \Yii::$app->session->setFlash('success', "本次成功导入{$importTotal}条订单数据");
        } else {
            \Yii::$app->session->setFlash('error', "没有符合条件的订单");
        }


    }

    private function getHeaderStyle()
    {
        return  $headStyle = array(
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
    }

    private function _exportPartOfExcel($data, $file_name)
    {
        //初始化实例
        $objPHPExcel = new \PHPExcel();
        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);

        //设置表头
        $headStyle = $this->getHeaderStyle();

        $sheet = $objPHPExcel->getActiveSheet();

        $columns = [
            ['A1','B1','C1','D1','E1','F1','G1','H1','I1','J1','K1','L1','M1','N1','O1','P1','Q1','R1','S1','T1','U1','V1','W1','X1','Y1','Z1','AA1','AB1','AC1','AD1','AE1','AF1','AG1',],
            ['A2','B2','C2','D2','E2','F2','G2','H2','I2','J2','K2','L2','M2','N2','O2','P2','Q2','R2','S2','T2','U2','V2','W2','X2','Y2','Z2','AA2','AB2','AC2','AD2','AE2','AF2','AG2',]
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
        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(8);
        $sheet->getColumnDimension('H')->setWidth(8);
        $sheet->getColumnDimension('J')->setWidth(45);
        $sheet->getColumnDimension('L')->setWidth(16);
        $sheet->getColumnDimension('W')->setWidth(16);
        $sheet->getColumnDimension('X')->setWidth(16);
        $sheet->getColumnDimension('Y')->setWidth(35);
        $sheet->getColumnDimension('Z')->setWidth(15);
        $sheet->getColumnDimension('AA')->setWidth(15);
        $sheet->getColumnDimension('AB')->setWidth(15);
        $sheet->getColumnDimension('AC')->setWidth(16);
        $sheet->getColumnDimension('AD')->setWidth(15);
        $sheet->getColumnDimension('AE')->setWidth(16);
        $sheet->getColumnDimension('AF')->setWidth(16);
        $sheet->getColumnDimension('AG')->setWidth(16);
        $sheet->getColumnDimension('AH')->setWidth(16);
        $sheet->getColumnDimension('AI')->setWidth(16);
        $sheet->getColumnDimension('AJ')->setWidth(20);

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
            'S1' => '支出',
            'W1' => '发货',
            'AC1' => '结算',
            'A2' => '客人ID',
            'B2' => '淘宝订单号',
            'C2' => '订单日期',
            'D2' => '收资料日',
            'E2' => '入馆日',
            'F2' => '名称',
            'G2' => '类型',
            'H2' => "接待\n销售",
            'I2' => "操作\n人员",
            'J2' => '办理人',
            'K2' => '套餐类型',
            'L2' => '套餐名称',
            'M2' => '数量',
            'N2' => '补差',
            'O2' => '照片',
            'P2' => '快递',
            'Q2' => '手续费',
            'R2' => '实付合计',
            'S2' => '数量',
            'T2' => '补差',
            'U2' => '照片',
            'V2' => '快递',
            'W2' => '收件人',
            'X2' => '收件电话',
            'Y2' => '收件地址',
            'Z2' => "出签\n日期",
            'AA2' => "发货\n日期",
            'AB2' => '寄回客人单号',
            'AC2' => '支付日期',
            'AD2' => "店铺收款日",
            'AE2' => "公司收款日",
            'AF2' => "收款帐户",
            'AG2' => '备注'
        ];

        foreach ($fieldAttribute as $column_x => $field) {
            $sheet->setCellValue($column_x,  $field);
        }

        $headOne = ['A1:L1', 'M1:R1','S1:V1', 'W1:AB1', 'AC1:AF1'];
        foreach ($headOne as $head) {
            $sheet->mergeCells($head);
        }

        //填充内容
        $columnFieldMap = [
            'A' => 'customer_id',
            'B' => 'order_num',
            'C' => 'order_date',//order_date
            'D' => 'collect_date',
            'E' => 'entry_date',
            'F' => 'combo_product',
            'G' => 'combo_classify',
            'H' => 'service_name',
            'I' => 'operator',//操作人员
            'J' => 'transactor',//办理人名称
            'K' => 'combo_type',//套餐类型
            'L' => 'combo_name',//套餐名称
            'M' => 'total_person',//数量
            'N' => 'balance_sum',//补差收入
            'O' => 'flushphoto_sum',//冲洗照片补差收入
            'P' => 'carrier_sum',//快递补差收入
            'Q' => 'charge',//手续费
            'R' => 'pay_total',//实付合计
            'S' => 'total_person',//数量
            'T' => 'output_balance_sum',//补差
            'U' => 'output_flushphoto_sum',//照片
            'V' => 'output_carrier_sum',//快递
            'W' => 'back_addressee',
            'X' => 'back_telphone',
            'Y' => 'back_address',
            'Z' => 'putsign_date',
            'AA' => 'delivergood_date',
            'AB' => 'deliver_order',//寄回客人单号
            'AC' => 'pay_date',//
            'AD' => 'receipt_date',
            'AE' => 'company_receipt_date', //店铺收款日
            'AF' => 'pay_account',
            'AG' => 'remark'
        ];

        $row = 3;

        //设置默认字体 大小 颜色
        $objPHPExcel->getDefaultStyle()->getFont()->setName( '宋体');
        $objPHPExcel->getDefaultStyle()->getFont()->setSize(9);
        $objPHPExcel->getDefaultStyle()->getFont()->setColor(new \PHPExcel_Style_Color(\PHPExcel_Style_Color::COLOR_RED));

        //统计
        $total_person_sum = 0;

        foreach ($data as $object) {

            //数据准备
            $charge = $object->snapshot->combo_charge;

            foreach ($columnFieldMap as $_column => $_field) {

                //自动换行
                $sheet->getStyle($_column . $row)->getAlignment()->setWrapText(true);

                //设置行高
                $sheet->getRowDimension($row)->setRowHeight(23);

                //设置边框
                $sheet->getStyle("A{$row}:AL{$row}")->applyFromArray($borderStyle);

                //设置水平竖直居中
                $sheet->getStyle($_column . $row)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($_column . $row)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);

                //设置自动换行
                $sheet->getStyle($_column)->getAlignment()->setWrapText(true);

                //填充数据
                $cellValue = false;

                switch ($_field)
                {

                    case 'order_num':
                        $cellValue = "'" . str_replace([',','，'],"  ", $object->order_num);
                        $sheet->getStyle($_column . $row)->getAlignment()->setWrapText(true);
                        $sheet->getStyle($_column . $row)->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_TEXT);
                        break;

                    case 'combo_product':
                        $product = $object->snapshot->combo_product;
                        $cellValue = $product ? $product : '已删除';
                        break;

                    case 'combo_type':
                        $type = Type::getComboType();
                        $cellValue = isset($type[$object->order_type]) ? $type[$object->order_type] : '已删除';
                        break;

                    case 'service_name':
                        $servicer = $object->servicer->name;
                        $cellValue = $servicer ? $servicer : '已删除';
                        break;

                    case 'operator':
                        $operator = $object->operator->username;
                        $cellValue = $operator ? $operator : '已删除';
                        break;

                    case 'combo_classify':
                        $classify = Type::getComboClassify();
                        $cellValue = isset($classify[$object->snapshot->combo_classify]) ? $classify[$object->snapshot->combo_classify] : ' ';
                        break;

                    case 'combo_name':
                        $combo = $object->snapshot->combo_name;
                        $cellValue = $combo ? $combo : "丢失数据";
                        break;

                    case 'total_person':
                        $cellValue = $object->total_person;
                        $total_person_sum += $cellValue;
                        break;

                    case 'pay_total':
                        //乘以手续费率
                        $income = $object->total_person * $object->single_sum +
                            $object->flushphoto_sum +
                            $object->carrier_sum +
                            $object->balance_sum;

                        $cellValue = $income * ( $charge > 0 ? $charge : 1);
                        break;

                    case 'deliver_order':
                        if ($object->deliver_order) {
                            $cellValue = "'" . $object->deliver_order;
                        }
                        break;

                    case 'charge':
                        $cellValue = $charge;
                        break;

                    case 'transactor':
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
                        break;

                    case 'order_date':
                    case 'collect_date':
                    case 'entry_date':
                    case 'putsign_date':
                    case 'delivergood_date':
                    case 'receipt_date':
                    case 'company_receipt_date':
                    case 'pay_date':
                        if (strpos($_field, 'date') !== false ) {
                            if ($object->$_field) {
                                $cellValue = date('n月j日', strtotime($object->$_field));
                            } else {
                                $cellValue = "";
                            }
                        } else {
                            $cellValue = !empty($object->$_field) ? $object->$_field : '';
                        }
                    break;

                    default:
                        $cellValue = $object->$_field;
                        break;
                }

                if ($cellValue) {
                    if (isset($$_field)) {
                        $$_field += $cellValue;
                    }
                    $sheet->setCellValue($_column . $row, $cellValue);
                }
            }
            $row++;
        }

        $sheet->getRowDimension($row)->setRowHeight(23);

        //设置边框
        $sheet->getStyle("A{$row}:AH{$row}")->applyFromArray($borderStyle);
        //填充统计数据颜色
        $hoverColumn = ['N'=>'ffff00','R'=>'ff0000','T'=>'ff0000','V'=>'ff0000','O'=>'','P'=>'','Q'=>'','U'=>'','W'=>'','X'=>'','Y'=>'','Z'=>'','AA'=>''];
        foreach ($hoverColumn as $column => $colorCode) {
            //居中
            $sheet->getStyle( $column . $row)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_JUSTIFY);
            $sheet->getStyle(  $column . $row)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            //设置字体
            $sheet->getStyle( $column . $row)->getFont()->setSize(11);
            $sheet->getStyle( $column . $row)->getFont()->setBold(true);
            $sheet->getStyle( $column . $row)->getFont()->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_BLACK);
            //设置颜色
            if ($colorCode) {
                $sheet->getStyle( $column . $row)->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID);
                $sheet->getStyle($column . $row)->getFill()->getStartColor()->setRGB($colorCode);
            }
        }

        //填充统计数据
        $calculate = ['N','O','P','R','S','T','U','V'];
        foreach ($calculate as $col) {
            $sheet->setCellValue($col . $row, $this->getSumString($col,3,$row-1));
        }

        //设置文件名称
        $file_name = !empty($file_name)? $file_name : "阳光假日报表" . date('Ymd_His');

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

    private function _exportExcel($data, $file_name)
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
            ['A1','B1','C1','D1','E1','F1','G1','H1','I1','J1','K1','L1','M1','N1','O1','P1','Q1','R1','S1','T1','U1','V1','W1','X1','Y1','Z1','AA1','AB1','AC1','AD1','AE1','AF1','AG1','AH1','AI1','AJ1','AK1','AL1'],
            ['A2','B2','C2','D2','E2','F2','G2','H2','I2','J2','K2','L2','M2','N2','O2','P2','Q2','R2','S2','T2','U2','V2','W2','X2','Y2','Z2','AA2','AB2','AC2','AD2','AE2','AF2','AG2','AH2','AI2','AJ2','AK2','AL2']
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
        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(8);
        $sheet->getColumnDimension('D')->setWidth(8);
        $sheet->getColumnDimension('E')->setWidth(8);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(8);
        $sheet->getColumnDimension('H')->setWidth(8);
        $sheet->getColumnDimension('J')->setWidth(35);
        $sheet->getColumnDimension('L')->setWidth(10);
        $sheet->getColumnDimension('AB')->setWidth(15);
        $sheet->getColumnDimension('AC')->setWidth(15);
        $sheet->getColumnDimension('AD')->setWidth(45);
        $sheet->getColumnDimension('AE')->setWidth(16);
        $sheet->getColumnDimension('AF')->setWidth(15);
        $sheet->getColumnDimension('AG')->setWidth(16);
        $sheet->getColumnDimension('AH')->setWidth(16);
        $sheet->getColumnDimension('AI')->setWidth(16);
        $sheet->getColumnDimension('AJ')->setWidth(16);
        $sheet->getColumnDimension('AK')->setWidth(16);
        $sheet->getColumnDimension('AL')->setWidth(20);

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
            'AB1' => '发货',
            'AH1' => '结算',
            'A2' => '客人ID',
            'B2' => '淘宝订单号',
            'C2' => '订单日期',
            'D2' => '收资料日',
            /*'E2' => '寄珠海日期',*/
            'E2' => '入馆日',
            'F2' => '名称',
            'G2' => '类型',
            'H2' => "接待\n销售",
            'I2' => "操作\n人员",
            'J2' => '办理人',
            'K2' => '套餐类型',
            'L2' => '套餐名称',
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
            'AH2' => '支付日期',
            'AI2' => "店铺收款日",
            'AJ2' => "公司收款日",
            'AK2' => "收款帐户",
            'AL2' => '备注'
        ];

        foreach ($fieldAttribute as $column_x => $field) {
            $sheet->setCellValue($column_x,  $field);
        }

        $headOne = ['A1:L1', 'M1:T1', 'U1:Z1', 'AB1:AG1', 'AH1:AL1'];
        foreach ($headOne as $head) {
            $sheet->mergeCells($head);
        }

        //填充内容
        $columnFieldMap = [
            'A' => 'customer_id',
            'B' => '',
            'C' => 'order_date',//order_date
            'D' => 'collect_date',
//            'E' => 'deliver_date',
            'E' => 'entry_date',
            'F' => ['snapshot','combo_product'],
            'G' => '',
            'H' => ['servicer','name'],
            'I' => '',//操作人员
            'J' => '',//办理人名称
            'K' => '',//套餐类型
            'L' => '',//套餐名称
            'M' => 'single_sum',
            'N' => '',
            'O' => 'balance_sum',//补差收入
            'P' => 'flushphoto_sum',//冲洗照片补差收入
            'Q' => 'carrier_sum',//快递补差收入
            'R' => '',//合计
            'S' => '',//手续费
            'T' => '',//实收
            'U' => '',//单项实付合计
            'V' => 'total_person',//数量
            'W' => 'output_balance_sum',//补差
            'X' => 'output_flushphoto_sum',//照片
            'Y' => 'output_carrier_sum',//快递
            'Z' => '',//实付合计
            'AA' => '',//利润
            'AB' => 'back_addressee',
            'AC' => 'back_telphone',
            'AD' => 'back_address',
            'AE' => 'putsign_date',
            'AF' => 'delivergood_date',
            'AG' => '',//寄回客人单号
            'AH' => 'pay_date',//
            'AI' => 'receipt_date',
            'AJ' => 'company_receipt_date', //店铺收款日
            'AK' => 'pay_account',
            'AL' => 'remark'
        ];

        $row = 3;

        //设置默认字体 大小 颜色
        $objPHPExcel->getDefaultStyle()->getFont()->setName( '宋体');
        $objPHPExcel->getDefaultStyle()->getFont()->setSize(9);
        $objPHPExcel->getDefaultStyle()->getFont()->setColor(new \PHPExcel_Style_Color(\PHPExcel_Style_Color::COLOR_RED));

        //统计
        $total_person_sum = 0;

        foreach ($data as $object) {

            //数据准备
            $charge = $object->snapshot->combo_charge;
            $cost = $object->snapshot->combo_cost;

            foreach ($columnFieldMap as $_column => $_field) {

                //自动换行
                $sheet->getStyle($_column . $row)->getAlignment()->setWrapText(true);

                //设置行高
                $sheet->getRowDimension($row)->setRowHeight(23);

                //设置边框
                $sheet->getStyle("A{$row}:AL{$row}")->applyFromArray($borderStyle);

                //设置水平竖直居中
                $sheet->getStyle($_column . $row)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($_column . $row)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);

                //设置宽度
                $width = $sheet->getColumnDimension($_column)->getWidth();
               // $sheet->getColumnDimension($_column)->setWidth($width);

                //设置自动换行
                $sheet->getStyle($_column)->getAlignment()->setWrapText(true);

                //填充数据
                $cellValue = false;

                if (!empty($_field) && !is_array($_field)) {

                    if (strpos($_field, 'date') !== false ) {

                        if ($object->$_field) {
                            $cellValue = date('n月j日', strtotime($object->$_field));
                        } else {
                            $cellValue = "";
                        }
                    } else {
                        $cellValue = !empty($object->$_field) ? $object->$_field : '';
                    }

                } elseif ($_column == 'B') {
                    $cellValue = "'" . str_replace([',','，'],"  ", $object->order_num);
                    $sheet->getStyle($_column . $row)->getAlignment()->setWrapText(true);
                    $sheet->getStyle($_column . $row)->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_TEXT);

                } elseif ($_column == 'F') {
                    $product = $object->snapshot->combo_product;
                    $cellValue = $product ? $product : '已删除';

                } elseif ($_column == 'G') {
                    $type = Type::getComboType();
                    $cellValue = isset($type[$object->order_type]) ? $type[$object->order_type] : '已删除';

                } elseif ($_column == 'H') {
                    $servicer = $object->servicer->name;
                    $cellValue = $servicer ? $servicer : '已删除';

                } elseif ($_column == 'I') {
                    $operator = $object->operator->username;
                    $cellValue = $operator ? $operator : '已删除';

                } elseif ($_column == 'U') {
                    $cost = $object->snapshot->combo_cost;
                    $cellValue = $cost ? $cost : '数据丢失';

                } elseif ($_column == 'J') {
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

                } elseif ($_column == 'K') {

                    $classify = Type::getComboClassify();
                    $cellValue = $classify[$object->snapshot->combo_classify];

                } elseif ($_column == 'L') {

                    $combo = $object->snapshot->combo_name;
                    $cellValue = $combo ? $combo : "丢失数据";

                } elseif ($_column == 'N') {

                    $cellValue = $object->total_person;
                    $total_person_sum += $cellValue;

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

                } elseif ($_column == 'Z') { //支出合计
                    //累加处理
                    $cellValue = $object->total_person * $cost +
                        $object->output_flushphoto_sum +
                        $object->output_carrier_sum +
                        $object->output_balance_sum;

                }elseif ($_column == 'AA') {

                    //收入减支出
                    $income = ($object->total_person * $object->single_sum +
                        $object->flushphoto_sum +
                        $object->carrier_sum +
                        $object->balance_sum) * ( $charge > 0 ? $charge : 1);

                    $cost = $object->total_person * $cost +
                        $object->output_flushphoto_sum +
                        $object->output_carrier_sum +
                        $object->output_balance_sum;

                    $cellValue = $income - $cost;

                }elseif ($_column == 'AG') {
                   // $sheet->getStyle($_column . $row)->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_TEXT);
                    if ($object->deliver_order) {
                        $cellValue = "'" . $object->deliver_order;
                    }

                }

                if ($cellValue) {
                    if (!is_array($_field) && isset($$_field)) {
                        $$_field += $cellValue;
                    }
                    $sheet->setCellValue($_column . $row, $cellValue);
                }
            }
            $row++;
        }

        $sheet->getRowDimension($row)->setRowHeight(23);


        //设置边框
        $sheet->getStyle("A{$row}:AL{$row}")->applyFromArray($borderStyle);
        //填充统计数据颜色
        $hoverColumn = ['N'=>'ffff00','R'=>'ff0000','T'=>'ff0000','V'=>'ff0000','O'=>'','P'=>'','Q'=>'','U'=>'','W'=>'','X'=>'','Y'=>'','Z'=>'','AA'=>''];
        foreach ($hoverColumn as $column => $colorCode) {
            //居中
            $sheet->getStyle( $column . $row)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_JUSTIFY);
            $sheet->getStyle(  $column . $row)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            //设置字体
            $sheet->getStyle( $column . $row)->getFont()->setSize(11);
            $sheet->getStyle( $column . $row)->getFont()->setBold(true);
            $sheet->getStyle( $column . $row)->getFont()->getColor()->setARGB(\PHPExcel_Style_Color::COLOR_BLACK);
            //设置颜色
            if ($colorCode) {
                $sheet->getStyle( $column . $row)->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID);
                $sheet->getStyle($column . $row)->getFill()->getStartColor()->setRGB($colorCode);
            }
        }

        //填充统计数据
        $calculate = ['N','O','P','Q','R','T','U','V','W','X','Y','Z','AA'];
        foreach ($calculate as $col) {
            $sheet->setCellValue($col . $row, $this->getSumString($col,3,$row-1));
        }

        //设置文件名称
        $file_name = !empty($file_name)? $file_name : "阳光假日报表" . date('Ymd_His');

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

    /**
     * 统计表头字符串
     * @param $col
     * @param $start
     * @param $end
     * @return string
     */
    private function getSumString($col,$start,$end)
    {
        $str = '';
        for ($i=$start; $i<=$end; $i++) {
            $str .= ($col . $i . ':' );
        }
        $str = trim($str, ':');
        $str = "=SUM(" . $str . ")";
        return $str;
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
