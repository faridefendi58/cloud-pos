<?php

namespace Pos\Controllers;

use Components\BaseController as BaseController;

class ProductsController extends BaseController
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
        $app->map(['POST'], '/delete/[{name}]', [$this, 'delete']);
        $app->map(['POST'], '/create-category', [$this, 'create_category']);
        $app->map(['GET', 'POST'], '/update-category/[{id}]', [$this, 'update_category']);
        $app->map(['POST'], '/delete-category/[{name}]', [$this, 'delete_category']);
        $app->map(['GET'], '/price/[{id}]', [$this, 'price']);
        $app->map(['POST'], '/info', [$this, 'info']);
        $app->map(['GET', 'POST'], '/create-price/[{id}]', [$this, 'create_price']);
        $app->map(['POST'], '/delete-price/[{id}]', [$this, 'delete_price']);
        $app->map(['GET', 'POST'], '/create-dimension/[{id}]', [$this, 'create_dimension']);
        $app->map(['POST'], '/delete-dimension/[{id}]', [$this, 'delete_dimension']);
        $app->map(['GET'], '/stock/[{id}]', [$this, 'stock']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => [
                    'view', 'create', 'update', 'delete', 'create-category',
                    'delete-category', 'info', 'delete-price', 'create-price',
                    'create-dimension', 'delete-dimension', 'stock'
                ],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view', 'stock'],
                'expression' => $this->hasAccess('pos/products/read'),
            ],
            ['allow',
                'actions' => ['create','create-category', 'create-price', 'create-dimension'],
                'expression' => $this->hasAccess('pos/products/create'),
            ],
            ['allow',
                'actions' => ['update','price'],
                'expression' => $this->hasAccess('pos/products/update'),
            ],
            ['allow',
                'actions' => ['delete', 'delete-category', 'delete-price', 'delete-dimension'],
                'expression' => $this->hasAccess('pos/products/delete'),
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
        
        $model = new \Model\ProductsModel();
        $products = $model->getData();

        $cmodel = new \Model\ProductCategoriesModel();
        $categories = $cmodel->getData();

        $stmodel = new \Model\ProductStocksModel();

        return $this->_container->module->render(
            $response, 
            'products/view.html', 
            [
                'products' => $products,
                'categories' => $categories,
                'stmodel' => $stmodel
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

        $model = new \Model\ProductsModel();
        if (isset($_POST['Products'])) {
            $model->title = $_POST['Products']['title'];
            $model->code = $_POST['Products']['code'];
            $model->product_category_id = $_POST['Products']['product_category_id'];
            if (!empty($_POST['Products']['unit']))
                $model->unit = $_POST['Products']['unit'];
            $model->description = $_POST['Products']['description'];
            $model->active = $_POST['Products']['active'];
            $latest_ordering = $model->getLatestOrder();
            $model->ordering = $latest_ordering + 1;
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            try {
                $save = \Model\ProductsModel::model()->save($model);
            } catch (\Exception $e) {
                var_dump($e->getMessage()); exit;
            }

            if ($save) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
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

        $model = \Model\ProductsModel::model()->findByPk($args['id']);
        $cmodel = new \Model\ProductCategoriesModel();
        $categories = $cmodel->getData();
        $pmodel = new \Model\ProductPricesModel();
        $prices = $pmodel->getData($model->id);

        $stmodel = new \Model\ProductStocksModel();
        $stocks = $stmodel->getData($model->id);

        if (isset($_POST['Products'])){
            $config = [];
            if (!empty($model->config)) {
                $config = json_decode($model->config, true);
            }

            $uploadfile = null;
            if (isset($_FILES['Products']['name']['image'])) {
                $path_info = pathinfo($_FILES['Products']['name']['image']);
                if (!in_array($path_info['extension'], ['jpg','JPG','jpeg','JPEG','png','PNG'])) {
                    echo json_encode(['status'=>'failed','message'=>'Allowed file type are jpg, png']); exit;
                    exit;
                }
                $upload_folder = 'uploads/images/products';
                $file_name = time().'.'.$path_info['extension'];
                $uploadfile = $upload_folder . '/' . $file_name;
                $config['image'] = $uploadfile;
                $model->config = json_encode($config);
            }

            $model->title = $_POST['Products']['title'];
            $model->code = $_POST['Products']['code'];
            $model->product_category_id = $_POST['Products']['product_category_id'];
            if (!empty($_POST['Products']['unit']))
                $model->unit = $_POST['Products']['unit'];
            $model->description = $_POST['Products']['description'];
            $model->active = $_POST['Products']['active'];
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            $update = \Model\ProductsModel::model()->update($model);
            if ($update) {
                if (!empty($uploadfile)) {
                    move_uploaded_file($_FILES['Products']['tmp_name']['image'], $uploadfile);
                }

                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                $message = \Model\ProductsModel::model()->getErrors(false);
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => $message,
                    ], 201);
            }
        }

        return $this->_container->module->render($response, 'products/update.html', [
            'model' => $model,
            'categories' => $categories,
            'prices' => $prices,
            'stocks' => $stocks,
            'stmodel' => $stmodel
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

        if (!isset($args['name'])) {
            return false;
        }

        $model = \Model\ProductsModel::model()->findByPk($args['name']);
        $delete = \Model\ProductsModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }

    public function create_category($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\ProductCategoriesModel();
        if (isset($_POST['ProductCategories'])) {
            $model->title = $_POST['ProductCategories']['title'];
            $model->description = $_POST['ProductCategories']['description'];
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            try {
                $save = \Model\ProductCategoriesModel::model()->save($model);
            } catch (\Exception $e) {
                var_dump($e->getMessage()); exit;
            }

            if ($save) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                    ], 201);
            } else {
                return $response->withJson(['status'=>'failed'], 201);
            }
        }
    }

    public function update_category($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = \Model\ProductCategoriesModel::model()->findByPk($args['id']);

        if (isset($_POST['ProductCategories'])){
            $model->title = $_POST['ProductCategories']['title'];
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            $update = \Model\ProductCategoriesModel::model()->update($model);
            if ($update) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                $message = \Model\ProductCategoriesModel::model()->getErrors(false);
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => $message,
                    ], 201);
            }
        }

        return $this->_container->module->render($response, 'products/update_category.html', [
            'model' => $model
        ]);
    }

    public function delete_category($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        if (!isset($args['name'])) {
            return false;
        }

        $model = \Model\ProductCategoriesModel::model()->findByPk($args['name']);
        $delete = \Model\ProductCategoriesModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }

    public function price($request, $response, $args)
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

        $model = \Model\ProductsModel::model()->findByPk($args['id']);
        
        return $this->_container->module->render($response, 'products/price.html', [
            'model' => $model
        ]);
    }

    public function info($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\ProductsModel();
        $result = $model->getDetail($_POST['id']);

        return $response->withJson(
            [
                'status' => 'success',
                'result' => $result,
            ], 201);
    }

    public function delete_price($request, $response, $args)
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

        $model = \Model\ProductPricesModel::model()->findByPk($_POST['id']);
        $delete = \Model\ProductPricesModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }

    public function create_price($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = \Model\ProductsModel::model()->findByPk($args['id']);

        if (isset($_POST['ProductPrices']) && !empty($_POST['ProductPrices']['product_id'])) {
            foreach ($_POST['ProductPrices']['quantity'] as $i => $quantity) {
                if (empty($_POST['ProductPrices']['id'][$i])) {
                    $model[$i] = new \Model\ProductPricesModel();
                    $model[$i]->product_id = $_POST['ProductPrices']['product_id'];
                    $model[$i]->quantity = $_POST['ProductPrices']['quantity'][$i];
                    $model[$i]->price = $_POST['ProductPrices']['price'][$i];
                    $model[$i]->created_at = date("Y-m-d H:i:s");
                    $model[$i]->created_by = $this->_user->id;
                    if ($model[$i]->quantity > 0 && $model[$i]->price > 0) {
                        $save = \Model\ProductPricesModel::model()->save($model[$i]);
                    }
                } else {
                    $pmodel[$i] = \Model\ProductPricesModel::model()->findByPk($_POST['ProductPrices']['id'][$i]);
                    $pmodel[$i]->quantity = $_POST['ProductPrices']['quantity'][$i];
                    $pmodel[$i]->price = $_POST['ProductPrices']['price'][$i];
                    $pmodel[$i]->updated_at = date("Y-m-d H:i:s");
                    $pmodel[$i]->updated_by = $this->_user->id;
                    if ($pmodel[$i]->quantity > 0 && $pmodel[$i]->price > 0) {
                        try {
                            $update = \Model\ProductPricesModel::model()->update($pmodel[$i]);

                        } catch (\Exception $e) {
                            var_dump($e->getMessage()); exit;
                        }
                    }
                }
            }

            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil disimpan.',
                ], 201);
        }

        return $this->_container->module->render(
            $response,
            'products/_price_form.html',
            [
                'show_delete_btn' => true,
                'model' => $model
            ]);
    }

    public function create_dimension($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = \Model\ProductsModel::model()->findByPk($args['id']);
        $dimensions = [];
        $configs = [];
        if (!empty($model->config)) {
            $configs = json_decode($model->config, true);
            if (!is_array($configs)) {
                $configs = [];
            }
        }

        if (isset($_POST['ProductDimensions']) && !empty($_POST['ProductDimensions']['satuan'])) {
            foreach ($_POST['ProductDimensions']['satuan'] as $i => $satuan) {
                $dim_data = [
                        'id' => $i + 1,
                        'satuan' => $satuan,
                        'nilai' => (int)$_POST['ProductDimensions']['nilai'][$i],
                        'unit' => $_POST['ProductDimensions']['unit'][$i]
                    ];
                array_push($dimensions, $dim_data);
            }
            $configs['dimension'] = $dimensions;

            $model->config = json_encode($configs);
            $update = \Model\ProductsModel::model()->update($model);

            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil disimpan.',
                ], 201);
        }

        return $this->_container->module->render(
            $response,
            'products/_dimensi_form.html',
            [
                'show_delete_btn' => true,
                'model' => $model
            ]);
    }

    public function delete_dimension($request, $response, $args)
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

        $model = \Model\ProductsModel::model()->findByPk($args['id']);
        $configs = json_decode($model->config, true);
        unset($configs['dimension'][$_POST['id']]);
        $model->config = json_encode($configs);

        $update = \Model\ProductsModel::model()->update($model);
        if ($update) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }

    public function stock($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = \Model\ProductsModel::model()->findByPk($args['id']);
        if (!$model instanceof \RedBeanPHP\OODBBean) {
            return false;
        }

        $whmodel = \Model\WarehousesModel::model()->findByPk($_GET['wh']);
        if (!$whmodel instanceof \RedBeanPHP\OODBBean) {
            return false;
        }
        
        if (isset($_GET['wh'])) {
            if (isset($_GET['start'])) {
                $date_start = date("Y-m-d", $_GET['start']/1000);
            } else {
                $date_start = date("Y-m-").'01';
            }

            if (isset($_GET['end'])) {
                $date_end = date("Y-m-d", $_GET['end']/1000);
            } else {
                $date_end = date("Y-m-d");
            }

            $params = [
                'date_from' => $date_start,
                'date_to' => $date_end
            ];

            $prmodel = new \Model\PurchaseReceiptsModel();
            $purchases = $prmodel->getHistory([
                'product_id' => $model->id,
                'warehouse_id' => $_GET['wh'],
                'status' => \Model\PurchaseReceiptsModel::STATUS_COMPLETED,
                'date_start' => $date_start,
                'date_end' => $date_end
            ]);
            $trmodel = new \Model\TransferReceiptsModel();
            $transfers = $trmodel->getHistory([
                'product_id' => $model->id,
                'warehouse_id' => $_GET['wh'],
                'status' => \Model\TransferReceiptsModel::STATUS_COMPLETED,
                'date_start' => $date_start,
                'date_end' => $date_end
            ]);
            $irmodel = new \Model\InventoryReceiptsModel();
            $inventories = $irmodel->getHistory([
                'product_id' => $model->id,
                'warehouse_id' => $_GET['wh'],
                'status' => \Model\InventoryReceiptsModel::STATUS_COMPLETED,
                'date_start' => $date_start,
                'date_end' => $date_end
            ]);
            $iimodel = new \Model\InventoryIssuesModel();
            $iissues = $iimodel->getHistory([
                'product_id' => $model->id,
                'warehouse_id' => $_GET['wh'],
                'status' => \Model\InventoryIssuesModel::STATUS_COMPLETED,
                'date_start' => $date_start,
                'date_end' => $date_end
            ]);
        }

        return $this->_container->module->render($response, 'products/stock.html', [
            'model' => $model,
            'whmodel' => $whmodel,
            'purchases' => $purchases,
            'transfers' => $transfers,
            'inventories' => $inventories,
            'iissues' => $iissues,
            'params' => $params
        ]);
    }
}