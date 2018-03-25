<?php

namespace Pos\Controllers;

use Components\BaseController as BaseController;
use PHPMailer\PHPMailer\Exception;

class PurchasesController extends BaseController
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
        $app->map(['POST'], '/complete/[{id}]', [$this, 'complete']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => [
                    'view', 'create', 'update', 'delete', 'delete-item',
                    'view-receipt', 'create-receipt', 'update-receipt', 'delete-receipt',
                    'create-receipt-item', 'complete-receipt', 'cancel-receipt'
                ],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view', 'view-receipt'],
                'expression' => $this->hasAccess('pos/purchases/read'),
            ],
            ['allow',
                'actions' => ['create', 'create-receipt'],
                'expression' => $this->hasAccess('pos/purchases/create'),
            ],
            ['allow',
                'actions' => ['update', 'update-receipt'],
                'expression' => $this->hasAccess('pos/purchases/update'),
            ],
            ['allow',
                'actions' => ['delete', 'delete-item', 'delete-receipt'],
                'expression' => $this->hasAccess('pos/purchases/delete'),
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
        
        $model = new \Model\PurchaseOrdersModel();
        $purchases = $model->getData();
        $smodel = new \Model\SuppliersModel();
        $suppliers = $smodel->getData();
        $spmodel = new \Model\ShipmentsModel();
        $shipments = $spmodel->getData();

        return $this->_container->module->render(
            $response, 
            'purchases/view.html',
            [
                'purchases' => $purchases,
                'suppliers' => $suppliers,
                'shipments' => $shipments
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

        $model = new \Model\PurchaseOrdersModel();
        if (isset($_POST['PurchaseOrders'])) {
            $po_number = $this->get_po_number();
            if (!empty($_POST['PurchaseOrders']['po_number']))
                $model->po_number = $_POST['PurchaseOrders']['po_number'];
            else {
                $model->po_number = $po_number['serie_nr'];
            }
            $model->po_serie = $po_number['serie'];
            $model->po_nr = $po_number['nr'];
            $model->price_netto = 0;
            $model->supplier_id = $_POST['PurchaseOrders']['supplier_id'];
            $model->date_order = date("Y-m-d H:i:s", strtotime($_POST['PurchaseOrders']['date_order']));
            if (!empty($_POST['PurchaseOrders']['due_date']))
                $model->due_date = date("Y-m-d H:i:s", strtotime($_POST['PurchaseOrders']['due_date']));
            $model->shipment_id = $_POST['PurchaseOrders']['shipment_id'];
            $model->status = 'onprocess';
            $model->notes = $_POST['PurchaseOrders']['notes'];
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            $save = \Model\PurchaseOrdersModel::model()->save(@$model);

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

        $model = \Model\PurchaseOrdersModel::model()->findByPk($args['id']);
        $wmodel = new \Model\PurchaseOrdersModel();
        $detail = $wmodel->getDetail($args['id']);
        $smodel = new \Model\SuppliersModel();
        $suppliers = $smodel->getData();
        $spmodel = new \Model\ShipmentsModel();
        $shipments = $spmodel->getData();
        $pimodel = new \Model\PurchaseOrderItemsModel();
        $items = $pimodel->getData($args['id']);
        $prmodel = new \Model\ProductsModel();
        $products = $prmodel->getData();
        $prcmodel = new \Model\PurchaseReceiptsModel();
        $receipts = $prcmodel->getData(['po_id'=>$model->id]);
        $primodel = new \Model\PurchaseReceiptItemsModel();

        if (isset($_POST['PurchaseOrders'])){
            $model->po_number = $_POST['PurchaseOrders']['po_number'];
            $model->supplier_id = $_POST['PurchaseOrders']['supplier_id'];
            $model->date_order = date("Y-m-d H:i:s", strtotime($_POST['PurchaseOrders']['date_order']));
            if (!empty($_POST['PurchaseOrders']['due_date']))
                $model->due_date = date("Y-m-d H:i:s", strtotime($_POST['PurchaseOrders']['due_date']));
            $model->shipment_id = $_POST['PurchaseOrders']['shipment_id'];
            $model->status = $_POST['PurchaseOrders']['status'];
            $model->notes = $_POST['PurchaseOrders']['notes'];
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            $update = \Model\PurchaseOrdersModel::model()->update($model);
            if ($update) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                $message = \Model\PurchaseOrdersModel::model()->getErrors(false);
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => $message,
                    ], 201);
            }
        }

        return $this->_container->module->render($response, 'purchases/update.html', [
            'model' => $model,
            'detail' => $detail,
            'suppliers' => $suppliers,
            'shipments' => $shipments,
            'items' => $items,
            'products' => $products,
            'receipts' => $receipts,
            'primodel' => $primodel
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

        $model = \Model\PurchaseOrdersModel::model()->findByPk($args['id']);
        $delete = \Model\PurchaseOrdersModel::model()->delete($model);
        if ($delete) {
            $delete2 = \Model\PurchaseOrderItemsModel::model()->deleteAllByAttributes(['po_id'=>$args['id']]);
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }

    private function get_po_number()
    {
        $pmodel = new \Model\OptionsModel();
        $ext_pos = $pmodel->getOption('ext_pos');
        $prefiks = $ext_pos['po_prefiks'];
        if (empty($prefiks)) {
            $prefiks = 'PO-';
        }

        $wmodel = new \Model\PurchaseOrdersModel();
        $max_nr = $wmodel->getLastPoNumber($prefiks);
        
        if (empty($max_nr['max_nr'])) {
            $next_nr = 1;
        } else {
            $next_nr = $max_nr['max_nr'] + 1;
        }

        $po_number = str_repeat("0", 5 - strlen($max_nr)).$next_nr;

        return [
                'serie' => $prefiks,
                'nr' => $next_nr,
                'serie_nr' => $prefiks.$po_number
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

        if (isset($_POST['PurchaseOrderItems']) && !empty($_POST['PurchaseOrderItems']['po_id'])) {
            $tot_price = 0; $success = true;
            foreach ($_POST['PurchaseOrderItems']['product_id'] as $i => $product_id) {
                $product = \Model\ProductsModel::model()->findByPk($product_id);
                if (empty($_POST['PurchaseOrderItems']['id'][$i])) { //create new record
                    $model[$i] = new \Model\PurchaseOrderItemsModel();
                    $model[$i]->po_id = $_POST['PurchaseOrderItems']['po_id'];
                    $model[$i]->product_id = $product_id;
                    $model[$i]->title = $product->title;
                    $model[$i]->quantity = $_POST['PurchaseOrderItems']['quantity'][$i];
                    $model[$i]->unit = $product->unit;
                    $model[$i]->price = $_POST['PurchaseOrderItems']['price'][$i];
                    $model[$i]->created_at = date("Y-m-d H:i:s");
                    $model[$i]->created_by = $this->_user->id;

                    if ($product_id > 0 && $model[$i]->quantity > 0 && $model[$i]->price > 0) {
                        $save = \Model\PurchaseOrderItemsModel::model()->save($model[$i]);
                        if ($save) {
                            $tot_price = $tot_price + ($_POST['PurchaseOrderItems']['price'][$i] * $_POST['PurchaseOrderItems']['quantity'][$i]);
                        }
                    } else {
                        $success = false;
                    }
                } else { //update the old record
                    $pmodel[$i] = \Model\PurchaseOrderItemsModel::model()->findByPk($_POST['PurchaseOrderItems']['id'][$i]);
                    $pmodel[$i]->product_id = $product_id;
                    $pmodel[$i]->title = $product->title;
                    $pmodel[$i]->quantity = $_POST['PurchaseOrderItems']['quantity'][$i];
                    $pmodel[$i]->unit = $product->unit;
                    $pmodel[$i]->price = $_POST['PurchaseOrderItems']['price'][$i];
                    $pmodel[$i]->updated_at = date("Y-m-d H:i:s");
                    $pmodel[$i]->updated_by = $this->_user->id;
                    if ($product_id > 0 && $pmodel[$i]->quantity > 0 && $pmodel[$i]->price > 0) {
                        try {
                            $update = \Model\PurchaseOrderItemsModel::model()->update($pmodel[$i]);
                        } catch (\Exception $e) {
                            var_dump($e->getMessage()); exit;
                        }
                        $tot_price = $tot_price + ($_POST['PurchaseOrderItems']['price'][$i] * $_POST['PurchaseOrderItems']['quantity'][$i]);
                    }
                }
            }

            // updating price of po data
            if ($tot_price > 0) {
                $pomodel = \Model\PurchaseOrdersModel::model()->findByPk($_POST['PurchaseOrderItems']['po_id']);
                $pomodel->price_netto = $tot_price;
                $update = \Model\PurchaseOrdersModel::model()->update($pomodel);
            }

            return $response->withJson(
                [
                    'status' => ($success)? 'success' : 'failed',
                    'message' => ($success)? 'Data berhasil disimpan.' : 'Pastikan pilih nama produk, isikan jumlah dan harga dengan nilai lebih dari 0.',
                ], 201);
        }

        return $this->_container->module->render(
            $response,
            'purchases/_items_form.html',
            [
                'show_delete_btn' => true,
                'products' => $products
            ]);
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

        $model = \Model\PurchaseOrderItemsModel::model()->findByPk($_POST['id']);
        $delete = \Model\PurchaseOrderItemsModel::model()->delete($model);
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

        $model = new \Model\PurchaseReceiptsModel();
        $receipts = $model->getData();

        $pomodel = new \Model\PurchaseOrdersModel();
        $purchases = $pomodel->getData(['status'=>'onprocess']);

        $smodel = new \Model\SuppliersModel();
        $suppliers = $smodel->getData();

        $wmodel = new \Model\WarehousesModel();
        $warehouses = $wmodel->getData();

        return $this->_container->module->render(
            $response,
            'purchases/view_receipt.html',
            [
                'receipts' => $receipts,
                'purchases' => $purchases,
                'suppliers' => $suppliers,
                'warehouses' => $warehouses
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

        $model = new \Model\PurchaseReceiptsModel();
        if (isset($_POST['PurchaseReceipts'])) {
            $pr_number = $this->get_pr_number();
            $model->pr_number = $pr_number['serie_nr'];
            $model->pr_serie = $pr_number['serie'];
            $model->pr_nr = $pr_number['nr'];
            $model->po_id = $_POST['PurchaseReceipts']['po_id'];
            $model->warehouse_id = $_POST['PurchaseReceipts']['warehouse_id'];
            $model->effective_date = date("Y-m-d H:i:s", strtotime($_POST['PurchaseReceipts']['effective_date']));
            $model->notes = $_POST['PurchaseReceipts']['notes'];
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            $save = \Model\PurchaseReceiptsModel::model()->save(@$model);

            if ($save) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'id' => $model->id
                    ], 201);
            } else {
                $failed_reason = \Model\PurchaseReceiptsModel::model()->getErrors();
                return $response->withJson(['status'=>'failed', 'message'=>$failed_reason], 201);
            }
        }
    }

    public function get_pr_number()
    {
        $pmodel = new \Model\OptionsModel();
        $ext_pos = $pmodel->getOption('ext_pos');
        $prefiks = $ext_pos['pr_prefiks'];
        if (empty($prefiks)) {
            $prefiks = 'PR-';
        }

        $wmodel = new \Model\PurchaseReceiptsModel();
        $max_nr = $wmodel->getLastPrNumber($prefiks);

        if (empty($max_nr['max_nr'])) {
            $next_nr = 1;
        } else {
            $next_nr = $max_nr['max_nr'] + 1;
        }

        $po_number = str_repeat("0", 5 - strlen($max_nr)).$next_nr;

        return [
            'serie' => $prefiks,
            'nr' => $next_nr,
            'serie_nr' => $prefiks.$po_number
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

        $model = \Model\PurchaseReceiptsModel::model()->findByPk($args['id']);
        $wmodel = new \Model\PurchaseReceiptsModel();
        $detail = $wmodel->getDetail($args['id']);

        $smodel = new \Model\SuppliersModel();
        $suppliers = $smodel->getData();

        $pimodel = new \Model\PurchaseOrderItemsModel();
        $items = $pimodel->getData($model->po_id);

        $wmodel = new \Model\WarehousesModel();
        $warehouses = $wmodel->getData();

        $pomodel = new \Model\PurchaseOrdersModel();
        $purchases = $pomodel->getData();

        $primodel = new \Model\PurchaseReceiptItemsModel();

        if (isset($_POST['PurchaseReceipts'])){
            $model->warehouse_id = $_POST['PurchaseReceipts']['warehouse_id'];
            $model->effective_date = date("Y-m-d H:i:s", strtotime($_POST['PurchaseReceipts']['effective_date']));
            $model->notes = $_POST['PurchaseOrders']['notes'];
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            $update = \Model\PurchaseReceiptsModel::model()->update($model);
            if ($update) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                $message = \Model\PurchaseReceiptsModel::model()->getErrors(false);
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => $message,
                    ], 201);
            }
        }

        return $this->_container->module->render($response, 'purchases/update_receipt.html', [
            'model' => $model,
            'detail' => $detail,
            'suppliers' => $suppliers,
            'items' => $items,
            'warehouses' => $warehouses,
            'purchases' => $purchases,
            'primodel' => $primodel
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

        $model = \Model\PurchaseReceiptsModel::model()->findByPk($_POST['id']);
        $delete = \Model\PurchaseReceiptsModel::model()->delete($model);
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

        $primodel = new \Model\PurchaseReceiptItemsModel();
        if (isset($_POST['PurchaseReceiptItems'])) {
            $model = \Model\PurchaseReceiptsModel::model()->findByPk($_POST['PurchaseReceiptItems']['pr_id']);
            $quantity_max = 0; $quantity = 0;
            foreach ($_POST['PurchaseReceiptItems']['product_id'] as $item_id => $product_id) {
                if (in_array($item_id, array_keys($_POST['PurchaseReceiptItems']['item_id']))) { // if checked
                    $item_data = $primodel->getDataByProduct(['pr_id'=>$model->id,'product_id'=>$product_id]);
                    if (empty($item_data)) {
                        $primodel2[$item_id] = new \Model\PurchaseReceiptItemsModel();
                        $primodel2[$item_id]->pr_id = $model->id;
                        $primodel2[$item_id]->po_item_id = $item_id;
                        $primodel2[$item_id]->product_id = $product_id;
                        $product[$item_id] = \Model\ProductsModel::model()->findByPk($product_id);
                        $primodel2[$item_id]->title = $product[$item_id]->title;
                        $primodel2[$item_id]->quantity = $_POST['PurchaseReceiptItems']['quantity'][$item_id];
                        $primodel2[$item_id]->quantity_max = $_POST['PurchaseReceiptItems']['quantity_max'][$item_id];
                        $primodel2[$item_id]->unit = $_POST['PurchaseReceiptItems']['unit'][$item_id];
                        $primodel2[$item_id]->price = $_POST['PurchaseReceiptItems']['price'][$item_id];
                        $primodel2[$item_id]->created_at = date("Y-m-d H:i:s");
                        $primodel2[$item_id]->created_by = $this->_user->id;

                        $save = \Model\PurchaseReceiptItemsModel::model()->save($primodel2[$item_id]);
                        $quantity = $quantity + $_POST['PurchaseReceiptItems']['quantity'][$item_id];
                        if (!$save) {
                            var_dump(\Model\PurchaseReceiptItemsModel::model()->getErrors()); exit;
                        }
                    } else {
                        $primodel2[$item_id] = \Model\PurchaseReceiptItemsModel::model()->findByAttributes(['pr_id'=>$model->id,'product_id'=>$product_id]);
                        $primodel2[$item_id]->po_item_id = $item_id;
                        $primodel2[$item_id]->quantity = $_POST['PurchaseReceiptItems']['quantity'][$item_id];
                        $update = \Model\PurchaseReceiptItemsModel::model()->update($primodel2[$item_id]);
                        $quantity = $quantity + $_POST['PurchaseReceiptItems']['quantity'][$item_id];
                    }
                } else {
                    $bean = \Model\PurchaseReceiptItemsModel::model()->findByAttributes(['pr_id'=>$model->id,'product_id'=>$product_id]);
                    if (!empty($bean)) {
                        $delete = \Model\PurchaseReceiptItemsModel::model()->delete($bean);
                    }
                }
                $quantity_max = $quantity_max + $_POST['PurchaseReceiptItems']['quantity_max'][$item_id];
            }

            $pomodel = \Model\PurchaseOrdersModel::model()->findByPk($model->po_id);
            if ($pomodel->status !== \Model\PurchaseOrdersModel::STATUS_COMPLETED && $quantity == $quantity_max) {
                $pomodel->status = \Model\PurchaseOrdersModel::STATUS_COMPLETED;
                $pomodel->updated_at = date("Y-m-d H:i:s");
                $pomodel->updated_by = $this->_user->id;

                $update_status = \Model\PurchaseOrdersModel::model()->update($pomodel);
            }

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
            $add_to_stock = $this->_add_to_stock(['pr_id' => $_POST['id']]);
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
            $remove_from_stock = $this->_remove_from_stock(['pr_id' => $_POST['id']]);
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

    public function complete($request, $response, $args)
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
            $pomodel = \Model\PurchaseOrdersModel::model()->findByPk($_POST['id']);
            if ($pomodel->status !== \Model\PurchaseOrdersModel::STATUS_COMPLETED) {
                $pomodel->status = \Model\PurchaseOrdersModel::STATUS_COMPLETED;
                $pomodel->completed_by = $this->_user->id;
                $pomodel->completed_at = date("Y-m-d H:i:s");
                $pomodel->updated_at = date("Y-m-d H:i:s");
                $pomodel->updated_by = $this->_user->id;

                $update_status = \Model\PurchaseOrdersModel::model()->update($pomodel);

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
     * @param $data : pr_id
     * @return bool
     */
    protected function _add_to_stock($data)
    {
        if (!isset($data['pr_id']))
            return false;

        $model = \Model\PurchaseReceiptsModel::model()->findByPk($data['pr_id']);
        $receipt_items = \Model\PurchaseReceiptItemsModel::model()->findAllByAttributes(['pr_id' => $data['pr_id'], 'added_in_stock' => 0]);
        if (is_array($receipt_items)) {
            foreach ($receipt_items as $item_id => $item_data) {
                // add to stok
                $stock_params = ['product_id' => $item_data['product_id'], 'warehouse_id' => $model->warehouse_id];
                $stock = \Model\ProductStocksModel::model()->findByAttributes($stock_params);
                $update_stock = false;
                if ($stock instanceof \RedBeanPHP\OODBBean) {
                    $stock->quantity = $stock->quantity + $item_data->quantity;
                    $stock->updated_at = date("Y-m-d H:i:s");
                    $stock->updated_by = $this->_user->id;
                    $update_stock = \Model\ProductStocksModel::model()->update($stock);
                } else {
                    $new_stock = new \Model\ProductStocksModel();
                    $new_stock->product_id = $item_data['product_id'];
                    $new_stock->warehouse_id = $model->warehouse_id;
                    $new_stock->quantity = $item_data->quantity;
                    $new_stock->created_at = date("Y-m-d H:i:s");
                    $new_stock->created_by = $this->_user->id;
                    $update_stock = \Model\ProductStocksModel::model()->save($new_stock);
                }
                if ($update_stock) {
                    // make a history
                    $item_data->added_in_stock = 1;
                    $item_data->added_value = $item_data->quantity;
                    $item_data->added_at = date("Y-m-d H:i:s");
                    $item_data->updated_at = date("Y-m-d H:i:s");
                    $item_data->updated_by = $this->_user->id;
                    $update = \Model\PurchaseReceiptItemsModel::model()->update($item_data);
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
            if ($model->status != \Model\PurchaseReceiptsModel::STATUS_COMPLETED) {
                $model->status = \Model\PurchaseReceiptsModel::STATUS_COMPLETED;
                $model->completed_at = date("Y-m-d H:i:s");
                $model->completed_by = $this->_user->id;
                $model->updated_at = date("Y-m-d H:i:s");
                $model->updated_by = $this->_user->id;
                $update_receipt = \Model\PurchaseReceiptsModel::model()->update($model);
            }

            return true;
        }

        return false;
    }

    /**
     * Removing stock on cancel purchase receipt
     * @param $data : pr_id
     * @return bool
     */
    protected function _remove_from_stock($data)
    {
        if (!isset($data['pr_id']))
            return false;

        $model = \Model\PurchaseReceiptsModel::model()->findByPk($data['pr_id']);
        $receipt_items = \Model\PurchaseReceiptItemsModel::model()->findAllByAttributes(['pr_id' => $data['pr_id'], 'added_in_stock' => 1]);
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
                    $update = \Model\PurchaseReceiptItemsModel::model()->update($item_data);
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
            if ($model->status != \Model\PurchaseReceiptsModel::STATUS_CANCELED) {
                $model->status = \Model\PurchaseReceiptsModel::STATUS_CANCELED;
                $model->canceled_at = date("Y-m-d H:i:s");
                $model->updated_at = date("Y-m-d H:i:s");
                $model->updated_by = $this->_user->id;
                $update_receipt = \Model\PurchaseReceiptsModel::model()->update($model);
            }

            return true;
        }

        return false;
    }
}