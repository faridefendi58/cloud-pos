<?php

namespace Pos\Controllers;

use Components\BaseController as BaseController;

class InventoriesController extends BaseController
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
        $app->map(['POST'], '/proceed-issue/[{id}]', [$this, 'proceed_issue']);
        $app->map(['GET'], '/view-receipt', [$this, 'view_receipt']);
        $app->map(['POST'], '/create-receipt', [$this, 'create_receipt']);
        $app->map(['GET', 'POST'], '/update-receipt/[{id}]', [$this, 'update_receipt']);
        $app->map(['POST'], '/delete-receipt/[{id}]', [$this, 'delete_receipt']);
        $app->map(['POST'], '/create-receipt-item', [$this, 'create_receipt_item']);
        $app->map(['POST'], '/delete-receipt-item/[{id}]', [$this, 'delete_receipt_item']);
        $app->map(['POST'], '/complete-receipt/[{id}]', [$this, 'complete_receipt']);
        $app->map(['POST'], '/cancel-receipt/[{id}]', [$this, 'cancel_receipt']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => [
                    'view', 'create', 'update', 'delete',
                    'create-item', 'delete-item', 'proceed-issue',
                    'view-receipt', 'create-receipt', 'update-receipt', 'delete-receipt',
                    'create-receipt-item', 'delete-receipt-item', 'complete-receipt', 'cancel-receipt'
                ],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view', 'view-receipt'],
                'expression' => $this->hasAccess('pos/inventories/read'),
            ],
            ['allow',
                'actions' => ['create', 'create-item', 'create-receipt', 'create-receipt-item', 'complete-receipt'],
                'expression' => $this->hasAccess('pos/inventories/create'),
            ],
            ['allow',
                'actions' => ['update', 'proceed-issue', 'update-receipt'],
                'expression' => $this->hasAccess('pos/inventories/update'),
            ],
            ['allow',
                'actions' => ['delete', 'delete-item', 'delete-receipt', 'delete-receipt-item', 'cancel-receipt'],
                'expression' => $this->hasAccess('pos/inventories/delete'),
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
        
        $model = new \Model\InventoryIssuesModel();
        $inventories = $model->getData();
        $whmodel = new \Model\WarehousesModel();
        $warehouses = $whmodel->getData();

        return $this->_container->module->render(
            $response, 
            'inventories/view.html',
            [
                'inventories' => $inventories,
                'warehouses' => $warehouses
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

        if (isset($_POST['InventoryIssues'])) {
            $model = new \Model\InventoryIssuesModel();
            $ii_number = $this->get_ii_number();
            if (!empty($_POST['InventoryIssues']['ii_number']))
                $model->ii_number = $_POST['InventoryIssues']['ii_number'];
            else {
                $model->ii_number = $ii_number['serie_nr'];
            }
            $model->ii_serie = $ii_number['serie'];
            $model->ii_nr = $ii_number['nr'];
            $model->warehouse_id = $_POST['InventoryIssues']['warehouse_id'];
            $model->effective_date = date("Y-m-d H:i:s", strtotime($_POST['InventoryIssues']['effective_date']));
            $model->notes = $_POST['InventoryIssues']['notes'];
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            $save = \Model\InventoryIssuesModel::model()->save(@$model);

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

        return $response->withJson(
            [
                'status' => 'failed',
                'message' => 'Data gagal disimpan.',
            ], 201);
    }

    public function update($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = \Model\InventoryIssuesModel::model()->findByPk($args['id']);
        $smodel = new \Model\InventoryIssuesModel();
        $detail = $smodel->getDetail($args['id']);

        $whmodel = new \Model\WarehousesModel();
        $warehouses = $whmodel->getData();

        $pmodel = new \Model\ProductsModel();
        $products = $pmodel->getData();

        $timodel = new \Model\InventoryIssueItemsModel();
        $items = $timodel->getData($args['id']);

        if (isset($_POST['InventoryIssues'])){
            $model->warehouse_id = $_POST['InventoryIssues']['warehouse_id'];
            $model->effective_date = date("Y-m-d H:i:s", strtotime($_POST['InventoryIssues']['effective_date']));
            $model->notes = $_POST['InventoryIssues']['notes'];
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            $update = \Model\InventoryIssuesModel::model()->update($model);
            if ($update) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                $message = \Model\InventoryIssuesModel::model()->getErrors(false);
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => $message,
                    ], 201);
            }
        }

        return $this->_container->module->render($response, 'inventories/update.html', [
            'model' => $model,
            'detail' => $detail,
            'warehouses' => $warehouses,
            'products' => $products,
            'items' => $items
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

        $model = \Model\InventoryIssuesModel::model()->findByPk($args['id']);
        $delete = \Model\InventoryIssuesModel::model()->delete($model);
        if ($delete) {
            $delete_items = \Model\InventoryIssueItemsModel::model()->deleteAllByAttributes(['ii_id' => $args['id']]);
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
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

        if (isset($_POST['InventoryIssueItems']) && !empty($_POST['InventoryIssueItems']['ii_id'])) {
            $tot_price = 0;
            foreach ($_POST['InventoryIssueItems']['product_id'] as $i => $product_id) {
                $product = \Model\ProductsModel::model()->findByPk($product_id);
                if (empty($_POST['InventoryIssueItems']['id'][$i])) { //create new record
                    $model[$i] = new \Model\InventoryIssueItemsModel();
                    $model[$i]->ii_id = $_POST['InventoryIssueItems']['ii_id'];
                    $model[$i]->product_id = $product_id;
                    $model[$i]->title = $product->title;
                    $model[$i]->quantity = $_POST['InventoryIssueItems']['quantity'][$i];
                    $model[$i]->unit = $product->unit;
                    $model[$i]->price = $product->current_cost;
                    $model[$i]->created_at = date("Y-m-d H:i:s");
                    $model[$i]->created_by = $this->_user->id;

                    if ($product_id > 0 && $model[$i]->quantity > 0) {
                        $save = \Model\InventoryIssueItemsModel::model()->save($model[$i]);
                        if ($save) {
                            $tot_price = $tot_price + ($product->current_cost * $_POST['InventoryIssueItems']['quantity'][$i]);
                        }
                    }
                } else { //update the old record
                    $pmodel[$i] = \Model\InventoryIssueItemsModel::model()->findByPk($_POST['InventoryIssueItems']['id'][$i]);
                    $pmodel[$i]->product_id = $product_id;
                    $pmodel[$i]->title = $product->title;
                    $pmodel[$i]->quantity = $_POST['InventoryIssueItems']['quantity'][$i];
                    $pmodel[$i]->unit = $product->unit;
                    $pmodel[$i]->price = $product->current_cost;
                    $pmodel[$i]->updated_at = date("Y-m-d H:i:s");
                    $pmodel[$i]->updated_by = $this->_user->id;
                    if ($product_id > 0 && $pmodel[$i]->quantity > 0) {
                        try {
                            $update = \Model\InventoryIssueItemsModel::model()->update($pmodel[$i]);
                        } catch (\Exception $e) {
                            var_dump($e->getMessage()); exit;
                        }
                        $tot_price = $tot_price + ($product->current_cost * $_POST['InventoryIssueItems']['quantity'][$i]);
                    }
                }
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
                'inventories/_items_form.html',
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

        $model = \Model\InventoryIssueItemsModel::model()->findByPk($_POST['id']);
        $delete = \Model\InventoryIssueItemsModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }

    public function proceed_issue($request, $response, $args)
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
            $substract_stock = $this->_substract_stock(['ii_id' => $_POST['id']]);
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

    public function view_receipt($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\InventoryReceiptsModel();
        $receipts = $model->getData();

        $pomodel = new \Model\InventoryIssuesModel();
        $transfers = $pomodel->getData(['status' => \Model\InventoryIssuesModel::STATUS_PENDING]);

        $wmodel = new \Model\WarehousesModel();
        $warehouses = $wmodel->getData();

        return $this->_container->module->render(
            $response,
            'inventories/view_receipt.html',
            [
                'receipts' => $receipts,
                'transfers' => $transfers,
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

        $model = new \Model\InventoryReceiptsModel();
        if (isset($_POST['InventoryReceipts'])) {
            $ir_number = $this->get_ir_number();
            $model->ir_number = $ir_number['serie_nr'];
            $model->ir_serie = $ir_number['serie'];
            $model->ir_nr = $ir_number['nr'];
            $model->warehouse_id = $_POST['InventoryReceipts']['warehouse_id'];
            $model->effective_date = date("Y-m-d H:i:s", strtotime($_POST['InventoryReceipts']['effective_date']));
            $model->notes = $_POST['InventoryReceipts']['notes'];
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            $save = \Model\InventoryReceiptsModel::model()->save(@$model);

            if ($save) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'id' => $model->id
                    ], 201);
            } else {
                $failed_reason = \Model\InventoryReceiptsModel::model()->getErrors();
                return $response->withJson(['status'=>'failed', 'message'=>$failed_reason], 201);
            }
        }
    }

    public function update_receipt($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = \Model\InventoryReceiptsModel::model()->findByPk($args['id']);
        $wmodel = new \Model\InventoryReceiptsModel();
        $detail = $wmodel->getDetail($args['id']);

        $pimodel = new \Model\InventoryReceiptItemsModel();
        $items = $pimodel->getData($model->id);

        $wmodel = new \Model\WarehousesModel();
        $warehouses = $wmodel->getData();

        $pomodel = new \Model\InventoryIssuesModel();
        $transfers = $pomodel->getData();

        $irimodel = new \Model\InventoryReceiptItemsModel();

        $pmodel = new \Model\ProductsModel();
        $products = $pmodel->getData();

        if (isset($_POST['InventoryReceipts'])){
            $model->warehouse_id = $_POST['InventoryReceipts']['warehouse_id'];
            $model->effective_date = date("Y-m-d H:i:s", strtotime($_POST['InventoryReceipts']['effective_date']));
            $model->notes = $_POST['InventoryReceipts']['notes'];
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            $update = \Model\InventoryReceiptsModel::model()->update($model);
            if ($update) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                $message = \Model\InventoryReceiptsModel::model()->getErrors(false);
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => $message,
                    ], 201);
            }
        }

        return $this->_container->module->render($response, 'inventories/update_receipt.html', [
            'model' => $model,
            'detail' => $detail,
            'items' => $items,
            'warehouses' => $warehouses,
            'transfers' => $transfers,
            'irimodel' => $irimodel,
            'products' => $products
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

        $model = \Model\InventoryReceiptsModel::model()->findByPk($_POST['id']);
        $delete = \Model\InventoryReceiptsModel::model()->delete($model);
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
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $prmodel = new \Model\ProductsModel();
        $products = $prmodel->getData();

        if (isset($_POST['InventoryReceiptItems'])) {
            $tot_price = 0;
            foreach ($_POST['InventoryReceiptItems']['product_id'] as $i => $product_id) {
                $product = \Model\ProductsModel::model()->findByPk($product_id);
                if (empty($_POST['InventoryReceiptItems']['id'][$i])) { //create new record
                    $model[$i] = new \Model\InventoryReceiptItemsModel();
                    $model[$i]->ir_id = $_POST['InventoryReceiptItems']['ir_id'];
                    $model[$i]->product_id = $product_id;
                    $model[$i]->title = $product->title;
                    $model[$i]->quantity = $_POST['InventoryReceiptItems']['quantity'][$i];
                    $model[$i]->unit = $product->unit;
                    $model[$i]->price = $product->current_cost;
                    $model[$i]->created_at = date("Y-m-d H:i:s");
                    $model[$i]->created_by = $this->_user->id;

                    if ($product_id > 0 && $model[$i]->quantity > 0) {
                        $save = \Model\InventoryReceiptItemsModel::model()->save($model[$i]);
                        if ($save) {
                            $tot_price = $tot_price + ($product->current_cost * $_POST['InventoryReceiptItems']['quantity'][$i]);
                        }
                    }
                } else { //update the old record
                    $pmodel[$i] = \Model\InventoryReceiptItemsModel::model()->findByPk($_POST['InventoryReceiptItems']['id'][$i]);
                    $pmodel[$i]->product_id = $product_id;
                    $pmodel[$i]->title = $product->title;
                    $pmodel[$i]->quantity = $_POST['InventoryReceiptItems']['quantity'][$i];
                    $pmodel[$i]->unit = $product->unit;
                    $pmodel[$i]->price = $product->current_cost;
                    $pmodel[$i]->updated_at = date("Y-m-d H:i:s");
                    $pmodel[$i]->updated_by = $this->_user->id;
                    if ($product_id > 0 && $pmodel[$i]->quantity > 0) {
                        try {
                            $update = \Model\InventoryReceiptItemsModel::model()->update($pmodel[$i]);
                        } catch (\Exception $e) {
                            var_dump($e->getMessage()); exit;
                        }
                        $tot_price = $tot_price + ($product->current_cost * $_POST['InventoryReceiptItems']['quantity'][$i]);
                    }
                }
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
                'inventories/_items_receipt_form.html',
                [
                    'show_delete_btn' => true,
                    'products' => $products
                ]);
        }
    }

    public function delete_receipt_item($request, $response, $args)
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

        $model = \Model\InventoryReceiptItemsModel::model()->findByPk($_POST['id']);
        $delete = \Model\InventoryReceiptItemsModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
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
            $add_to_stock = $this->_add_to_stock(['ir_id' => $_POST['id']]);
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
            $remove_from_stock = $this->_remove_from_stock(['ir_id' => $_POST['id']]);
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

    private function get_ii_number()
    {
        $pmodel = new \Model\OptionsModel();
        $ext_pos = $pmodel->getOption('ext_pos');
        $prefiks = $ext_pos['ii_prefiks'];
        if (empty($prefiks)) {
            $prefiks = 'II-';
        }

        $wmodel = new \Model\InventoryIssuesModel();
        $max_nr = $wmodel->getLastIiNumber($prefiks);

        if (empty($max_nr['max_nr'])) {
            $next_nr = 1;
        } else {
            $next_nr = $max_nr['max_nr'] + 1;
        }

        $ii_number = str_repeat("0", 5 - strlen($max_nr)).$next_nr;

        return [
            'serie' => $prefiks,
            'nr' => $next_nr,
            'serie_nr' => $prefiks.$ii_number
        ];
    }

    protected function _substract_stock($data)
    {
        if (!isset($data['ii_id']))
            return false;

        $model = \Model\InventoryIssuesModel::model()->findByPk($data['ii_id']);
        $transfer_items = \Model\InventoryIssueItemsModel::model()->findAllByAttributes(['ii_id' => $data['ii_id'], 'substract_stock' => 0]);
        if (is_array($transfer_items)) {
            foreach ($transfer_items as $item_id => $item_data) {
                // add to stok
                $stock_params = ['product_id' => $item_data['product_id'], 'warehouse_id' => $model->warehouse_id];
                $stock = \Model\ProductStocksModel::model()->findByAttributes($stock_params);
                $update_stock = false;
                if ($stock instanceof \RedBeanPHP\OODBBean) {
                    $stock->quantity = $stock->quantity - $item_data->quantity;
                    $stock->updated_at = date("Y-m-d H:i:s");
                    $stock->updated_by = $this->_user->id;
                    $update_stock = \Model\ProductStocksModel::model()->update($stock);
                }
                if ($update_stock) {
                    // make a history
                    $item_data->substract_stock = 1;
                    $item_data->substract_value = $item_data->quantity;
                    $item_data->substracted_at = date("Y-m-d H:i:s");
                    $item_data->updated_at = date("Y-m-d H:i:s");
                    $item_data->updated_by = $this->_user->id;
                    $update = \Model\InventoryIssueItemsModel::model()->update($item_data);
                }
            }
            // also update the receipt status
            if ($model->status != \Model\InventoryIssuesModel::STATUS_COMPLETED) {
                $model->status = \Model\InventoryIssuesModel::STATUS_COMPLETED;
                $model->completed_at = date("Y-m-d H:i:s");
                $model->updated_at = date("Y-m-d H:i:s");
                $model->updated_by = $this->_user->id;
                $update_issue = \Model\InventoryIssuesModel::model()->update($model);
            }

            return true;
        }

        return false;
    }

    private function get_ir_number()
    {
        $pmodel = new \Model\OptionsModel();
        $ext_pos = $pmodel->getOption('ext_pos');
        $prefiks = $ext_pos['ir_prefiks'];
        if (empty($prefiks)) {
            $prefiks = 'IR-';
        }

        $wmodel = new \Model\InventoryReceiptsModel();
        $max_nr = $wmodel->getLastIrNumber($prefiks);

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

    protected function _add_to_stock($data)
    {
        if (!isset($data['ir_id']))
            return false;

        $model = \Model\InventoryReceiptsModel::model()->findByPk($data['ir_id']);
        $receipt_items = \Model\InventoryReceiptItemsModel::model()->findAllByAttributes(['ir_id' => $data['ir_id'], 'added_in_stock' => 0]);
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
                    $update = \Model\InventoryReceiptItemsModel::model()->update($item_data);
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
            if ($model->status != \Model\InventoryReceiptsModel::STATUS_COMPLETED) {
                $model->status = \Model\InventoryReceiptsModel::STATUS_COMPLETED;
                $model->completed_at = date("Y-m-d H:i:s");
                $model->updated_at = date("Y-m-d H:i:s");
                $model->updated_by = $this->_user->id;
                $update_receipt = \Model\InventoryReceiptsModel::model()->update($model);
            }

            return true;
        }

        return false;
    }

    protected function _remove_from_stock($data)
    {
        if (!isset($data['ir_id']))
            return false;

        $model = \Model\InventoryReceiptsModel::model()->findByPk($data['ir_id']);
        $receipt_items = \Model\InventoryReceiptItemsModel::model()->findAllByAttributes(['ir_id' => $data['ir_id'], 'added_in_stock' => 1]);
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
                    $update = \Model\InventoryReceiptItemsModel::model()->update($item_data);
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
            if ($model->status != \Model\InventoryReceiptsModel::STATUS_CANCELED) {
                $model->status = \Model\InventoryReceiptsModel::STATUS_CANCELED;
                $model->canceled_at = date("Y-m-d H:i:s");
                $model->updated_at = date("Y-m-d H:i:s");
                $model->updated_by = $this->_user->id;
                $update_receipt = \Model\InventoryReceiptsModel::model()->update($model);
            }

            return true;
        }

        return false;
    }
}