<?php

namespace Pos\Controllers;

use Components\BaseController as BaseController;

class SuppliersController extends BaseController
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
        $app->map(['POST'], '/product-prices/[{id}]', [$this, 'product_prices']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['view', 'create', 'update', 'delete'],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view'],
                'expression' => $this->hasAccess('pos/suppliers/read'),
            ],
            ['allow',
                'actions' => ['create'],
                'expression' => $this->hasAccess('pos/suppliers/create'),
            ],
            ['allow',
                'actions' => ['update', 'product-prices'],
                'expression' => $this->hasAccess('pos/suppliers/update'),
            ],
            ['allow',
                'actions' => ['delete'],
                'expression' => $this->hasAccess('pos/suppliers/delete'),
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
        
        $model = new \Model\SuppliersModel();
        $suppliers = $model->getData();

        return $this->_container->module->render(
            $response, 
            'suppliers/view.html',
            [
                'suppliers' => $suppliers
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

        $model = new \Model\SuppliersModel();
        if (isset($_POST['Suppliers'])) {
            $model->name = $_POST['Suppliers']['name'];
            $model->address = $_POST['Suppliers']['address'];
            $model->phone = $_POST['Suppliers']['phone'];
            $model->notes = $_POST['Suppliers']['notes'];
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            try {
                $save = \Model\SuppliersModel::model()->save($model);
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

        $model = \Model\SuppliersModel::model()->findByPk($args['id']);
        $smodel = new \Model\SuppliersModel();
        $detail = $smodel->getDetail($args['id']);

        if (isset($_POST['Suppliers'])){
            $model->name = $_POST['Suppliers']['name'];
            $model->address = $_POST['Suppliers']['address'];
            $model->phone = $_POST['Suppliers']['phone'];
            $model->notes = $_POST['Suppliers']['notes'];
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            $update = \Model\SuppliersModel::model()->update($model);
            if ($update) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                $message = \Model\SuppliersModel::model()->getErrors(false);
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => $message,
                    ], 201);
            }
        }

        return $this->_container->module->render($response, 'suppliers/update.html', [
            'model' => $model,
            'detail' => $detail
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

        $model = \Model\SuppliersModel::model()->findByPk($args['id']);
        $delete = \Model\SuppliersModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }

    public function product_prices($request, $response, $args)
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

        $result = ['success' => 'failed'];
        $model = \Model\SuppliersModel::model()->findByPk($args['id']);
        if ($model instanceof \RedBeanPHP\OODBBean) {
            if (isset($_POST['Suppliers'])) {
                $configs = [];
                if (!empty($model->configs)) {
                    $configs = json_decode($model->configs, true);
                }

                if (count($_POST['Suppliers']['product_id']) > 0) {
                    foreach ($_POST['Suppliers']['product_id'] as $i => $product_id) {
                        $configs['products'][$product_id] = [
                            'id' => $product_id,
                            'title' => $_POST['Suppliers'][$product_id]['title'],
                            'price' => (int)$_POST['Suppliers'][$product_id]['price']
                        ];
                    }
                }

                if (isset($_POST['Suppliers']['use_default_price'])) {
                    $configs['use_default_price'] = 1;
                } else {
                    $configs['use_default_price'] = 0;
                }

                $model->configs = json_encode($configs);
                $model->updated_at = date('c');
                $model->updated_by = $this->_user->id;
                $update = \Model\SuppliersModel::model()->update($model);
                if ($update) {
                    $result['status'] = 'success';
                    $result['message'] = 'Data berhasil disimpan';
                }
            }
        }

        return $response->withJson($result, 201);
    }
}