<?php

namespace Pos\Controllers;

use Components\BaseController as BaseController;
use PHPMailer\PHPMailer\Exception;

class TransfersController extends BaseController
{
    protected $_login_url = '/pos/default/login';
    
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/view', [$this, 'view']);
        $app->map(['POST'], '/create', [$this, 'create']);
        $app->map(['GET', 'POST'], '/update/[{id}]', [$this, 'update']);
        $app->map(['POST'], '/delete/[{id}]', [$this, 'delete']);
        $app->map(['GET', 'POST'], '/create-item', [$this, 'create_item']);
        $app->map(['POST'], '/delete-item/[{id}]', [$this, 'delete_item']);
        $app->map(['GET'], '/view-receipt', [$this, 'view_receipt']);
        $app->map(['POST'], '/create-receipt', [$this, 'create_receipt']);
        $app->map(['GET', 'POST'], '/update-receipt/[{id}]', [$this, 'update_receipt']);
        $app->map(['POST'], '/delete-receipt/[{id}]', [$this, 'delete_receipt']);
        $app->map(['POST'], '/create-receipt-item', [$this, 'create_receipt_item']);
        $app->map(['POST'], '/complete-receipt/[{id}]', [$this, 'complete_receipt']);
        $app->map(['POST'], '/cancel-receipt/[{id}]', [$this, 'cancel_receipt']);
        $app->map(['POST'], '/proceed-transfer/[{id}]', [$this, 'proceed_transfer']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => [
                    'view', 'create', 'update', 'delete', 'delete-item',
                    'view-receipt', 'create-receipt', 'update-receipt', 'delete-receipt',
                    'create-receipt-item', 'complete-receipt', 'cancel-receipt', 'proceed_transfer'
                ],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view', 'view-receipt'],
                'expression' => $this->hasAccess('pos/transfers/read'),
            ],
            ['allow',
                'actions' => ['create', 'create-receipt'],
                'expression' => $this->hasAccess('pos/transfers/create'),
            ],
            ['allow',
                'actions' => ['update', 'update-receipt'],
                'expression' => $this->hasAccess('pos/transfers/update'),
            ],
            ['allow',
                'actions' => ['delete', 'delete-item', 'delete-receipt'],
                'expression' => $this->hasAccess('pos/transfers/delete'),
            ],
            ['deny',
                'users' => ['*'],
            ],
        ];
    }

    public function view($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }
        
        $model = new \Model\TransferIssuesModel();
        $transfers = $model->getData();
        $wmodel = new \Model\WarehousesModel();
        $warehouses = $wmodel->getData();

        $rmodel = new \Model\TransferReceiptsModel();

        return $this->_container->module->render(
            $response, 
            'transfers/view.html',
            [
                'transfers' => $transfers,
                'warehouses' => $warehouses,
                'rmodel' => $rmodel
            ]
        );
    }

    public function create($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\TransferIssuesModel();
        if (isset($_POST['TransferIssues'])) {
            $ti_number = $this->get_ti_number();
            if (!empty($_POST['TransferIssues']['ti_number']))
                $model->ti_number = $_POST['TransferIssues']['ti_number'];
            else {
                $model->ti_number = $ti_number['serie_nr'];
            }
            $model->ti_serie = $ti_number['serie'];
            $model->ti_nr = $ti_number['nr'];
            $model->base_price = 0;
            $model->warehouse_from = $_POST['TransferIssues']['warehouse_from'];
            $model->warehouse_to = $_POST['TransferIssues']['warehouse_to'];
            $model->date_transfer = date("Y-m-d H:i:s", strtotime($_POST['TransferIssues']['date_transfer']));
            $model->status = \Model\TransferIssuesModel::STATUS_ON_PROCESS;
            $model->notes = $_POST['TransferIssues']['notes'];
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            $save = \Model\TransferIssuesModel::model()->save(@$model);

            if ($save) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'id' => $model->id
                    ], 201);
            } else {
                return $response->withJson(['status'=>'failed'], 201);
            }
        }
    }

    public function update($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = \Model\TransferIssuesModel::model()->findByPk($args['id']);
        $wmodel = new \Model\TransferIssuesModel();
        $detail = $wmodel->getDetail($args['id']);

        $timodel = new \Model\TransferIssueItemsModel();
        $items = $timodel->getData($args['id']);

        $wmodel = new \Model\WarehousesModel();
        $warehouses = $wmodel->getData();

        $prmodel = new \Model\ProductsModel();
        $products = $prmodel->getData();

        $prcmodel = new \Model\TransferReceiptsModel();
        $receipts = $prcmodel->getData(['ti_id'=>$model->id]);

        $trimodel = new \Model\TransferReceiptItemsModel();

        if (isset($_POST['TransferIssues'])){
            $model->warehouse_from = $_POST['TransferIssues']['warehouse_from'];
            $model->warehouse_to = $_POST['TransferIssues']['warehouse_to'];
            $model->date_transfer = date("Y-m-d H:i:s", strtotime($_POST['TransferIssues']['date_transfer']));
            $model->status = $_POST['TransferIssues']['status'];
            $model->notes = $_POST['TransferIssues']['notes'];
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            $update = \Model\TransferIssuesModel::model()->update($model);
            if ($update) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                $message = \Model\TransferIssuesModel::model()->getErrors(false);
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => $message,
                    ], 201);
            }
        }

        return $this->_container->module->render($response, 'transfers/update.html', [
            'model' => $model,
            'detail' => $detail,
            'items' => $items,
            'products' => $products,
            'receipts' => $receipts,
            'trimodel' => $trimodel,
            'warehouses' => $warehouses
        ]);
    }

    public function delete($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \Model\TransferIssuesModel::model()->findByPk($args['id']);
        $delete = \Model\TransferIssuesModel::model()->delete($model);
        if ($delete) {
            $delete2 = \Model\TransferIssueItemsModel::model()->deleteAllByAttributes(['ti_id'=>$args['id']]);
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }

    public function get_ti_number()
    {
        $pmodel = new \Model\OptionsModel();
        $ext_pos = $pmodel->getOption('ext_pos');
        $prefiks = $ext_pos['ti_prefiks'];
        if (empty($prefiks)) {
            $prefiks = 'TI-';
        }

        $wmodel = new \Model\TransferIssuesModel();
        $max_nr = $wmodel->getLastTiNumber($prefiks);
        
        if (empty($max_nr['max_nr'])) {
            $next_nr = 1;
        } else {
            $next_nr = $max_nr['max_nr'] + 1;
        }

        $ti_number = str_repeat("0", 5 - strlen($max_nr)).$next_nr;

        return [
                'serie' => $prefiks,
                'nr' => $next_nr,
                'serie_nr' => $prefiks.$ti_number
            ];
    }

    public function create_item($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $prmodel = new \Model\ProductsModel();
        $products = $prmodel->getData();

        if (isset($_POST['TransferIssueItems']) && !empty($_POST['TransferIssueItems']['ti_id'])) {
            $tot_price = 0;
            foreach ($_POST['TransferIssueItems']['product_id'] as $i => $product_id) {
                $product = \Model\ProductsModel::model()->findByPk($product_id);
                if (empty($_POST['TransferIssueItems']['id'][$i])) { //create new record
                    $model[$i] = new \Model\TransferIssueItemsModel();
                    $model[$i]->ti_id = $_POST['TransferIssueItems']['ti_id'];
                    $model[$i]->product_id = $product_id;
                    $model[$i]->title = $product->title;
                    $model[$i]->quantity = $_POST['TransferIssueItems']['quantity'][$i];
                    $model[$i]->available_qty = $_POST['TransferIssueItems']['quantity'][$i];
                    $model[$i]->unit = $product->unit;
                    $model[$i]->price = $product->current_cost;
                    $model[$i]->created_at = date("Y-m-d H:i:s");
                    $model[$i]->created_by = $this->_user->id;

                    if ($product_id > 0 && $model[$i]->quantity > 0) {
                        $save = \Model\TransferIssueItemsModel::model()->save($model[$i]);
                        if ($save) {
                            $tot_price = $tot_price + ($product->current_cost * $_POST['TransferIssueItems']['quantity'][$i]);
                        }
                    }
                } else { //update the old record
                    $pmodel[$i] = \Model\TransferIssueItemsModel::model()->findByPk($_POST['TransferIssueItems']['id'][$i]);
                    $pmodel[$i]->product_id = $product_id;
                    $pmodel[$i]->title = $product->title;
                    $pmodel[$i]->quantity = $_POST['TransferIssueItems']['quantity'][$i];
                    $pmodel[$i]->available_qty = $_POST['TransferIssueItems']['quantity'][$i];
                    $pmodel[$i]->unit = $product->unit;
                    $pmodel[$i]->price = $product->current_cost;
                    $pmodel[$i]->updated_at = date("Y-m-d H:i:s");
                    $pmodel[$i]->updated_by = $this->_user->id;
                    if ($product_id > 0 && $pmodel[$i]->quantity > 0) {
                        try {
                            $update = \Model\TransferIssueItemsModel::model()->update($pmodel[$i]);
                        } catch (\Exception $e) {
                            var_dump($e->getMessage()); exit;
                        }
                        $tot_price = $tot_price + ($product->current_cost * $_POST['TransferIssueItems']['quantity'][$i]);
                    }
                }
            }

            // updating price of po data
            if ($tot_price > 0) {
                $pomodel = \Model\TransferIssuesModel::model()->findByPk($_POST['TransferIssueItems']['ti_id']);
                $pomodel->base_price = $tot_price;
                $update = \Model\TransferIssuesModel::model()->update($pomodel);
            }

            if ($tot_price > 0)  {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                    ], 201);
            } else {
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => 'Tidak ada data yang berhasil disimpan. Pastikan pilih produk dan isi jumlah itemnya.',
                    ], 201);
            }
        } else {
            return $this->_container->module->render(
                $response,
                'transfers/_items_form.html',
                [
                    'show_delete_btn' => true,
                    'products' => $products
                ]);
        }
    }

    public function delete_item($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \Model\TransferIssueItemsModel::model()->findByPk($_POST['id']);
        $delete = \Model\TransferIssueItemsModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }

    public function view_receipt($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\TransferReceiptsModel();
        $receipts = $model->getData();

        $pomodel = new \Model\TransferIssuesModel();
        $transfers = $pomodel->getData(['status' => \Model\TransferIssuesModel::STATUS_ON_PROCESS]);

        $wmodel = new \Model\WarehousesModel();
        $warehouses = $wmodel->getData();

        return $this->_container->module->render(
            $response,
            'transfers/view_receipt.html',
            [
                'receipts' => $receipts,
                'transfers' => $transfers,
                'warehouses' => $warehouses,
                'model' => $model
            ]
        );
    }

    public function create_receipt($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\TransferReceiptsModel();
        if (isset($_POST['TransferReceipts'])) {
            $tr_number = $this->get_tr_number();
            $model->tr_number = $tr_number['serie_nr'];
            $model->tr_serie = $tr_number['serie'];
            $model->tr_nr = $tr_number['nr'];
            $model->ti_id = $_POST['TransferReceipts']['ti_id'];
            $model->warehouse_id = $_POST['TransferReceipts']['warehouse_id'];
            $model->effective_date = date("Y-m-d H:i:s", strtotime($_POST['TransferReceipts']['effective_date']));
            $model->notes = $_POST['TransferReceipts']['notes'];
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            $save = \Model\TransferReceiptsModel::model()->save(@$model);

            if ($save) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'id' => $model->id
                    ], 201);
            } else {
                $failed_reason = \Model\TransferReceiptsModel::model()->getErrors();
                return $response->withJson(['status'=>'failed', 'message'=>$failed_reason], 201);
            }
        }
    }

    public function get_tr_number()
    {
        $pmodel = new \Model\OptionsModel();
        $ext_pos = $pmodel->getOption('ext_pos');
        $prefiks = $ext_pos['tr_prefiks'];
        if (empty($prefiks)) {
            $prefiks = 'TR-';
        }

        $wmodel = new \Model\TransferReceiptsModel();
        $max_nr = $wmodel->getLastTrNumber($prefiks);

        if (empty($max_nr['max_nr'])) {
            $next_nr = 1;
        } else {
            $next_nr = $max_nr['max_nr'] + 1;
        }

        $ti_number = str_repeat("0", 5 - strlen($max_nr)).$next_nr;

        return [
            'serie' => $prefiks,
            'nr' => $next_nr,
            'serie_nr' => $prefiks.$ti_number
        ];
    }

    public function update_receipt($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = \Model\TransferReceiptsModel::model()->findByPk($args['id']);
        $wmodel = new \Model\TransferReceiptsModel();
        $detail = $wmodel->getDetail($args['id']);

        $pimodel = new \Model\TransferIssueItemsModel();
        $items = $pimodel->getData($model->ti_id);

        $wmodel = new \Model\WarehousesModel();
        $warehouses = $wmodel->getData();

        $pomodel = new \Model\TransferIssuesModel();
        $transfers = $pomodel->getData();

        $trimodel = new \Model\TransferReceiptItemsModel();

        if (isset($_POST['PurchaseReceipts'])){
            $model->warehouse_id = $_POST['PurchaseReceipts']['warehouse_id'];
            $model->effective_date = date("Y-m-d H:i:s", strtotime($_POST['PurchaseReceipts']['effective_date']));
            $model->notes = $_POST['PurchaseReceipts']['notes'];
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            $update = \Model\TransferReceiptsModel::model()->update($model);
            if ($update) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                $message = \Model\TransferReceiptsModel::model()->getErrors(false);
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => $message,
                    ], 201);
            }
        }

        return $this->_container->module->render($response, 'transfers/update_receipt.html', [
            'model' => $model,
            'detail' => $detail,
            'items' => $items,
            'warehouses' => $warehouses,
            'transfers' => $transfers,
            'trimodel' => $trimodel
        ]);
    }

    public function delete_receipt($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \Model\TransferReceiptsModel::model()->findByPk($_POST['id']);
        $delete = \Model\TransferReceiptsModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }

    public function create_receipt_item($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $primodel = new \Model\TransferReceiptItemsModel();
        if (isset($_POST['TransferReceiptItems'])) {
            $model = \Model\TransferReceiptsModel::model()->findByPk($_POST['TransferReceiptItems']['tr_id']);
            $quantity_max = 0; $quantity = 0;
            foreach ($_POST['TransferReceiptItems']['product_id'] as $item_id => $product_id) {
                if (in_array($item_id, array_keys($_POST['TransferReceiptItems']['item_id']))) { // if checked
                    $item_data = $primodel->getDataByProduct(['tr_id'=>$model->id,'product_id'=>$product_id]);
                    if (empty($item_data)) {
                        $primodel2[$item_id] = new \Model\TransferReceiptItemsModel();
                        $primodel2[$item_id]->tr_id = $model->id;
                        $primodel2[$item_id]->ti_item_id = $item_id;
                        $primodel2[$item_id]->product_id = $product_id;
                        $product[$item_id] = \Model\ProductsModel::model()->findByPk($product_id);
                        $primodel2[$item_id]->title = $product[$item_id]->title;
                        $primodel2[$item_id]->quantity = $_POST['TransferReceiptItems']['quantity'][$item_id];
                        $primodel2[$item_id]->quantity_max = $_POST['TransferReceiptItems']['quantity_max'][$item_id];
                        $primodel2[$item_id]->unit = $_POST['TransferReceiptItems']['unit'][$item_id];
                        $primodel2[$item_id]->price = $_POST['TransferReceiptItems']['price'][$item_id];
                        $primodel2[$item_id]->created_at = date("Y-m-d H:i:s");
                        $primodel2[$item_id]->created_by = $this->_user->id;

                        $save = \Model\TransferReceiptItemsModel::model()->save($primodel2[$item_id]);
                        $quantity = $quantity + $_POST['TransferReceiptItems']['quantity'][$item_id];
                        if (!$save) {
                            var_dump(\Model\TransferReceiptItemsModel::model()->getErrors()); exit;
                        }
                    } else {
                        $primodel2[$item_id] = \Model\TransferReceiptItemsModel::model()->findByAttributes(['tr_id'=>$model->id,'product_id'=>$product_id]);
                        $primodel2[$item_id]->ti_item_id = $item_id;
                        $primodel2[$item_id]->quantity = $_POST['TransferReceiptItems']['quantity'][$item_id];
                        $update = \Model\TransferReceiptItemsModel::model()->update($primodel2[$item_id]);
                        $quantity = $quantity + $_POST['TransferReceiptItems']['quantity'][$item_id];
                    }
                } else {
                    $bean = \Model\TransferReceiptItemsModel::model()->findByAttributes(['tr_id'=>$model->id,'product_id'=>$product_id]);
                    if (!empty($bean)) {
                        $delete = \Model\TransferReceiptItemsModel::model()->delete($bean);
                    }
                }
                $quantity_max = $quantity_max + $_POST['TransferReceiptItems']['quantity_max'][$item_id];
            }

            /*$timodel = \Model\TransferIssuesModel::model()->findByPk($model->ti_id);
            if ($timodel->status !== \Model\TransferIssuesModel::STATUS_COMPLETED && $quantity == $quantity_max) {
                $timodel->status = \Model\TransferIssuesModel::STATUS_COMPLETED;
                $timodel->updated_at = date("Y-m-d H:i:s");
                $timodel->updated_by = $this->_user->id;

                $update_status = \Model\TransferIssuesModel::model()->update($timodel);
            }*/

            return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                    ], 201);
        }
    }

    public function complete_receipt($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $status = 'failed'; $message = 'Request gagal dieksekusi.';
        if (isset($_POST['id']) && $_POST['id'] == $args['id']) {
            $add_to_stock = $this->_add_to_stock(['tr_id' => $_POST['id']]);
            if ($add_to_stock) {
                $status = 'success';
                $message = 'Request berhasil dieksekusi.';
            }
        }

        return $response->withJson(
            [
                'status' => $status,
                'message' => $message,
            ], 201);
    }

    public function cancel_receipt($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $status = 'failed'; $message = 'Request gagal dieksekusi.';
        if (isset($_POST['id']) && $_POST['id'] == $args['id']) {
            $remove_from_stock = $this->_remove_from_stock(['tr_id' => $_POST['id']]);
            if ($remove_from_stock) {
                $status = 'success';
                $message = 'Request berhasil dieksekusi.';
            }
        }

        return $response->withJson(
            [
                'status' => $status,
                'message' => $message,
            ], 201);
    }

    public function proceed_transfer($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $status = 'failed'; $message = 'Request gagal dieksekusi.';
        if (isset($_POST['id']) && $_POST['id'] == $args['id']) {
            $substract_stock = $this->_substract_stock(['ti_id' => $_POST['id']]);
            if ($substract_stock) {
                $status = 'success';
                $message = 'Request berhasil dieksekusi.';
            }
        }

        return $response->withJson(
            [
                'status' => $status,
                'message' => $message,
            ], 201);
    }

    /**
     * Adding stock on receiving purchase order
     * @param $data : tr_id
     * @return bool
     */
    public function _add_to_stock($data)
    {
        if (!isset($data['tr_id']))
            return false;

        $model = \Model\TransferReceiptsModel::model()->findByPk($data['tr_id']);
        $timodel = \Model\TransferIssuesModel::model()->findByPk($model->ti_id);
        $receipt_items = \Model\TransferReceiptItemsModel::model()->findAllByAttributes(['tr_id' => $data['tr_id'], 'added_in_stock' => 0]);
        if (is_array($receipt_items)) {
            foreach ($receipt_items as $item_id => $item_data) {
                // add to stok
                $stock_params = ['product_id' => $item_data['product_id'], 'warehouse_id' => $timodel->warehouse_to];
                $new_stock = new \Model\ProductStocksModel();
                $old_quantity = $new_stock->getStock($stock_params);
                $new_stock->product_id = $item_data['product_id'];
                $new_stock->warehouse_id = $timodel->warehouse_to;
                $new_stock->quantity = $item_data->quantity;
                $new_stock->rel_type = 'transfer_receipt';
                $new_stock->rel_id = $data['tr_id'];
                $new_stock->created_at = date("Y-m-d H:i:s");
                $new_stock->created_by = (isset($data['admin_id']))? $data['admin_id'] : $this->_user->id;
                $update_stock = \Model\ProductStocksModel::model()->save($new_stock);
                if ($update_stock) {
                    // save current cost
                    $wh_product = \Model\WarehouseProductsModel::model()->findByAttributes($stock_params);
                    if ($wh_product instanceof \RedBeanPHP\OODBBean) {
                        $current_cost = $wh_product->current_cost;
                        if ($item_data['price'] > 0 && $current_cost > 0) {
                            $tot_quantity = $old_quantity + $item_data['quantity'];
                            if ($tot_quantity <= 0) {
                                $tot_quantity = 1;
                            }
                            $current_cost = (($item_data['price']*$item_data['quantity']) + ($current_cost*$old_quantity))/$tot_quantity;
                        } elseif ($current_cost == 0 && $item_data['price'] > 0) {
                            $current_cost = $item_data['price'];
                        }
                        $wh_product->current_cost = round($current_cost, 2);
                        $wh_product->updated_at = date("Y-m-d H:i:s");
                        $wh_product->updated_by = (isset($data['admin_id']))? $data['admin_id'] : $this->_user->id;
                        $update_cost = \Model\WarehouseProductsModel::model()->update($wh_product);
                    }

                    // make a history
                    $item_data->added_in_stock = 1;
                    $item_data->added_value = $item_data->quantity;
                    $item_data->added_at = date("Y-m-d H:i:s");
                    $item_data->updated_at = date("Y-m-d H:i:s");
                    $item_data->updated_by = (isset($data['admin_id']))? $data['admin_id'] : $this->_user->id;
                    $update = \Model\TransferReceiptItemsModel::model()->update($item_data);
                }
            }
            // also update the receipt status
            if ($model->status != \Model\TransferReceiptsModel::STATUS_COMPLETED) {
                $model->status = \Model\TransferReceiptsModel::STATUS_COMPLETED;
                $model->completed_at = date("Y-m-d H:i:s");
                $model->completed_by = (isset($data['admin_id']))? $data['admin_id'] : $this->_user->id;
                $model->updated_at = date("Y-m-d H:i:s");
                $model->updated_by = (isset($data['admin_id']))? $data['admin_id'] : $this->_user->id;
                $update_receipt = \Model\TransferReceiptsModel::model()->update($model);
                if ($update_receipt) {
                    $trmodel = new \Model\TransferReceiptsModel();
                    $hasInCompleteReceipt = $trmodel->hasInCompleteReceipt($model->ti_id);
                    if (!$hasInCompleteReceipt) {
                        $timodel = \Model\TransferIssuesModel::model()->findByPk($model->ti_id);
                        $timodel->status = \Model\TransferIssuesModel::STATUS_COMPLETED;
                        $timodel->completed_at = date("Y-m-d H:i:s");
                        $timodel->completed_by = (isset($data['admin_id']))? $data['admin_id'] : $this->_user->id;
                        $timodel->updated_at = date("Y-m-d H:i:s");
                        $timodel->updated_by = (isset($data['admin_id']))? $data['admin_id'] : $this->_user->id;
                        $update_issue = \Model\TransferIssuesModel::model()->update($timodel);
                    }
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Removing stock on cancel purchase receipt
     * @param $data : tr_id
     * @return bool
     */
    protected function _remove_from_stock($data)
    {
        if (!isset($data['tr_id']))
            return false;

        $model = \Model\TransferReceiptsModel::model()->findByPk($data['tr_id']);
        $receipt_items = \Model\TransferReceiptItemsModel::model()->findAllByAttributes(['tr_id' => $data['tr_id'], 'added_in_stock' => 1]);
        if (is_array($receipt_items)) {
            foreach ($receipt_items as $item_id => $item_data) {
                // add to stok
                $stock_params = ['product_id' => $item_data['product_id'], 'warehouse_id' => $model->warehouse_id];
                $stock = \Model\ProductStocksModel::model()->findByAttributes($stock_params);
                $update_stock = false;
                if ($stock instanceof \RedBeanPHP\OODBBean) {
                    $stock->quantity = $stock->quantity - $item_data->added_value;
                    $stock->updated_at = date("Y-m-d H:i:s");
                    $stock->updated_by = $this->_user->id;
                    $update_stock = \Model\ProductStocksModel::model()->update($stock);
                }
                if ($update_stock) {
                    // make a history
                    $item_data->removed_value = $item_data->quantity;
                    $item_data->removed_at = date("Y-m-d H:i:s");
                    $item_data->updated_at = date("Y-m-d H:i:s");
                    $item_data->updated_by = $this->_user->id;
                    $update = \Model\TransferReceiptItemsModel::model()->update($item_data);
                    if ($update) {
                        // also update current price
                        $pmodel = new \Model\ProductsModel();
                        $current_cost = $pmodel->getCurrentCost($item_data['product_id']);
                        $product = \Model\ProductsModel::model()->findByPk($item_data['product_id']);
                        $product->current_cost = $current_cost;
                        $product->updated_at = date("Y-m-d H:i:s");
                        $product->updated_by = $this->_user->id;
                        $update_product = \Model\ProductsModel::model()->update($product);
                    }
                }
            }
            // also update the receipt status
            if ($model->status != \Model\TransferReceiptsModel::STATUS_CANCELED) {
                $model->status = \Model\TransferReceiptsModel::STATUS_CANCELED;
                $model->canceled_at = date("Y-m-d H:i:s");
                $model->updated_at = date("Y-m-d H:i:s");
                $model->updated_by = $this->_user->id;
                $update_receipt = \Model\TransferReceiptsModel::model()->update($model);
            }

            return true;
        }

        return false;
    }

    public function _substract_stock($data)
    {
        if (!isset($data['ti_id']))
            return false;

        $model = \Model\TransferIssuesModel::model()->findByPk($data['ti_id']);
        $transfer_items = \Model\TransferIssueItemsModel::model()->findAllByAttributes(['ti_id' => $data['ti_id'], 'substract_stock' => 0]);
        if (is_array($transfer_items)) {
            foreach ($transfer_items as $item_id => $item_data) {
                // add to stok
                /*$update_stock = false;
                $stock_params = ['product_id' => $item_data['product_id'], 'warehouse_id' => $model->warehouse_from];
                $stock = \Model\ProductStocksModel::model()->findByAttributes($stock_params);
                if ($stock instanceof \RedBeanPHP\OODBBean) {
                    $stock->quantity = $stock->quantity - $item_data->quantity;
                    $stock->updated_at = date("Y-m-d H:i:s");
                    $stock->updated_by = (isset($data['admin_id']))? $data['admin_id'] : $this->_user->id;
                    $update_stock = \Model\ProductStocksModel::model()->update($stock);
                }*/
                $new_stock = new \Model\ProductStocksModel();
                $new_stock->product_id = $item_data['product_id'];
                $new_stock->warehouse_id = $model->warehouse_from;
                $new_stock->quantity = -1 * $item_data->quantity;
                $new_stock->rel_type = 'transfer_issue';
                $new_stock->rel_id = $data['ti_id'];
                $new_stock->notes = 'Removed from Transfer Issue #'. $model->ti_number;
                $new_stock->created_at = date("Y-m-d H:i:s");
                $new_stock->created_by = (isset($data['admin_id']))? $data['admin_id'] : $this->_user->id;
                $update_stock = \Model\ProductStocksModel::model()->save($new_stock);
                if ($update_stock) {
                    // make a history
                    $item_data->substract_stock = 1;
                    $item_data->substract_value = $item_data->quantity;
                    $item_data->substracted_at = date("Y-m-d H:i:s");
                    $item_data->updated_at = date("Y-m-d H:i:s");
                    $item_data->updated_by = (isset($data['admin_id']))? $data['admin_id'] : $this->_user->id;
                    $update = \Model\TransferIssueItemsModel::model()->update($item_data);
                }
            }
            // also update the receipt status
            if ($model->status != \Model\TransferIssuesModel::STATUS_ON_PROCESS) {
                $model->status = \Model\TransferIssuesModel::STATUS_ON_PROCESS;
                $model->processed_at = date("Y-m-d H:i:s");
                $model->updated_at = date("Y-m-d H:i:s");
                $model->updated_by = (isset($data['admin_id']))? $data['admin_id'] : $this->_user->id;
                $update_issue = \Model\TransferIssuesModel::model()->update($model);
            }

            return true;
        }

        return false;
    }
}
