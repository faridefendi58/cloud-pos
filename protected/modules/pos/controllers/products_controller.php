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
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['view', 'create', 'update', 'delete', 'create-category', 'delete-category'],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view'],
                'expression' => $this->hasAccess('pos/products/read'),
            ],
            ['allow',
                'actions' => ['create','create-category'],
                'expression' => $this->hasAccess('pos/products/create'),
            ],
            ['allow',
                'actions' => ['update','price'],
                'expression' => $this->hasAccess('pos/products/update'),
            ],
            ['allow',
                'actions' => ['delete', 'delete-category'],
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

        return $this->_container->module->render(
            $response, 
            'products/view.html', 
            [
                'products' => $products,
                'categories' => $categories
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
            $model->product_category_id = $_POST['Products']['product_category_id'];
            $model->description = $_POST['Products']['description'];
            $model->active = $_POST['Products']['active'];
            $model->created_at = date("Y-m-d H:i:s");
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

        if (isset($_POST['Products'])){
            $model->title = $_POST['Products']['title'];
            $model->product_category_id = $_POST['Products']['product_category_id'];
            $model->description = $_POST['Products']['description'];
            $model->active = $_POST['Products']['active'];
            $model->updated_at = date("Y-m-d H:i:s");
            $update = \Model\ProductsModel::model()->update($model);
            if ($update) {
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
            'categories' => $categories
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
}