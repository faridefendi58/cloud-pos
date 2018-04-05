<?php

namespace Pos\Controllers;

use Components\BaseController as BaseController;

class WarehousesController extends BaseController
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
        $app->map(['GET'], '/group/view', [$this, 'view_group']);
        $app->map(['POST'], '/group/create', [$this, 'create_group']);
        $app->map(['GET', 'POST'], '/group/update/[{id}]', [$this, 'update_group']);
        $app->map(['POST'], '/group/delete/[{id}]', [$this, 'delete_group']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => [
                    'view', 'create', 'update', 'delete',
                    'group/view', 'group/create', 'group/update', 'group/delete',
                    ],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view', 'group/view'],
                'expression' => $this->hasAccess('pos/warehouses/read'),
            ],
            ['allow',
                'actions' => ['create', 'group/create'],
                'expression' => $this->hasAccess('pos/warehouses/create'),
            ],
            ['allow',
                'actions' => ['update', 'group/update'],
                'expression' => $this->hasAccess('pos/warehouses/update'),
            ],
            ['allow',
                'actions' => ['delete', 'group/delete'],
                'expression' => $this->hasAccess('pos/warehouses/delete'),
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
        
        $model = new \Model\WarehousesModel();
        $warehouses = $model->getData();

        // wh group
        $wgmodel = new \Model\WarehouseGroupsModel();
        $groups = $wgmodel->getData();

        return $this->_container->module->render(
            $response, 
            'warehouses/view.html',
            [
                'warehouses' => $warehouses,
                'groups' => $groups
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

        $model = new \Model\WarehousesModel();
        if (isset($_POST['Warehouses'])) {
            $model->title = $_POST['Warehouses']['title'];
            $model->phone = $_POST['Warehouses']['phone'];
            $model->address = $_POST['Warehouses']['address'];
            $model->notes = $_POST['Warehouses']['notes'];
            if (isset($_POST['Warehouses']['group_id']))
                $model->group_id = $_POST['Warehouses']['group_id'];
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            try {
                $save = \Model\WarehousesModel::model()->save($model);
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

        $model = \Model\WarehousesModel::model()->findByPk($args['id']);
        $wmodel = new \Model\WarehousesModel();
        $detail = $wmodel->getDetail($args['id']);

        // wh group
        $wgmodel = new \Model\WarehouseGroupsModel();
        $groups = $wgmodel->getData();

        if (isset($_POST['Warehouses'])){
            $model->title = $_POST['Warehouses']['title'];
            $model->phone = $_POST['Warehouses']['phone'];
            $model->address = $_POST['Warehouses']['address'];
            $model->notes = $_POST['Warehouses']['notes'];
            if (isset($_POST['Warehouses']['group_id']))
                $model->group_id = $_POST['Warehouses']['group_id'];
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            $update = \Model\WarehousesModel::model()->update($model);
            if ($update) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                $message = \Model\WarehousesModel::model()->getErrors(false);
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => $message,
                    ], 201);
            }
        }

        return $this->_container->module->render($response, 'warehouses/update.html', [
            'model' => $model,
            'detail' => $detail,
            'groups' => $groups
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

        $model = \Model\WarehousesModel::model()->findByPk($args['id']);
        $delete = \Model\WarehousesModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }

    public function view_group($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $wgmodel = new \Model\WarehouseGroupsModel();
        $groups = $wgmodel->getData();

        $amodel = new \Model\AdminModel();
        $admins = $amodel->getData(['status' => \Model\AdminModel::STATUS_ACTIVE]);

        return $this->_container->module->render(
            $response,
            'warehouses/group_view.html',
            [
                'groups' => $groups,
                'admins' => $admins
            ]
        );
    }

    public function create_group($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\WarehouseGroupsModel();
        if (isset($_POST['WarehouseGroups'])) {
            $model->title = $_POST['WarehouseGroups']['title'];
            $model->description = $_POST['WarehouseGroups']['description'];
            if (isset($_POST['WarehouseGroups']['pic'])) {
                $pic = [];
                if (is_array($_POST['WarehouseGroups']['pic'])) {
                    foreach ($_POST['WarehouseGroups']['pic'] as $i => $admin_id) {
                        $amodel = \Model\AdminModel::model()->findByPk($admin_id);
                        if ($amodel instanceof \RedBeanPHP\OODBBean) {
                            $pic[$amodel->id] = [
                                    'name' => $amodel->name,
                                    'email' => $amodel->email
                                ];
                        }
                    }
                }
                $model->pic = json_encode($pic);
            }
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            try {
                $save = \Model\WarehouseGroupsModel::model()->save($model);
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

    public function update_group($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = \Model\WarehouseGroupsModel::model()->findByPk($args['id']);
        $wmodel = new \Model\WarehouseGroupsModel();
        $detail = $wmodel->getDetail($args['id']);

        // admin list
        $amodel = new \Model\AdminModel();
        $admins = $amodel->getData(['status' => \Model\AdminModel::STATUS_ACTIVE]);

        if (isset($_POST['WarehouseGroups'])){
            $model->title = $_POST['WarehouseGroups']['title'];
            $model->description = $_POST['WarehouseGroups']['description'];
            if (isset($_POST['WarehouseGroups']['pic'])) {
                $pic = [];
                if (is_array($_POST['WarehouseGroups']['pic'])) {
                    foreach ($_POST['WarehouseGroups']['pic'] as $i => $admin_id) {
                        $amodel = \Model\AdminModel::model()->findByPk($admin_id);
                        if ($amodel instanceof \RedBeanPHP\OODBBean) {
                            $pic[$amodel->id] = [
                                'name' => $amodel->name,
                                'email' => $amodel->email
                            ];
                        }
                    }
                }
                $model->pic = json_encode($pic);
            }
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            $update = \Model\WarehouseGroupsModel::model()->update($model);
            if ($update) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                $message = \Model\WarehouseGroupsModel::model()->getErrors(false);
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => $message,
                    ], 201);
            }
        }

        return $this->_container->module->render($response, 'warehouses/group_update.html', [
            'model' => $model,
            'detail' => $detail,
            'admins' => $admins
        ]);
    }

    public function delete_group($request, $response, $args)
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

        $model = \Model\WarehouseGroupsModel::model()->findByPk($args['id']);
        $delete = \Model\WarehouseGroupsModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        }
    }
}