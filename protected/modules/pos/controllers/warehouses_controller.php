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
        $app->map(['GET'], '/staff/view/[{id}]', [$this, 'view_staff']);
        $app->map(['GET', 'POST'], '/staff/create/[{id}]', [$this, 'create_staff']);
        $app->map(['POST'], '/staff/delete/[{id}]', [$this, 'delete_staff']);
        $app->map(['POST'], '/role/create', [$this, 'create_role']);
        $app->map(['GET', 'POST'], '/role/update/[{id}]', [$this, 'update_role']);
        $app->map(['POST'], '/role/delete/[{id}]', [$this, 'delete_role']);
        $app->map(['GET'], '/price-item/[{id}]', [$this, 'price_item']);
        $app->map(['POST'], '/product-prices/[{id}]', [$this, 'price_prices']);
        $app->map(['POST'], '/product-stock/[{id}]', [$this, 'product_stock']);
        $app->map(['POST'], '/product-fees/[{id}]', [$this, 'price_fees']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => [
                    'view', 'create', 'update', 'delete',
                    'group/view', 'group/create', 'group/update', 'group/delete',
                    'staff/view', 'staff/create', 'staff/delete',
                    'role/create'
                    ],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view', 'group/view', 'staff/view'],
                'expression' => $this->hasAccess('pos/warehouses/read'),
            ],
            ['allow',
                'actions' => ['create', 'group/create', 'staff/create', 'role/create'],
                'expression' => $this->hasAccess('pos/warehouses/create'),
            ],
            ['allow',
                'actions' => ['update', 'group/update'],
                'expression' => $this->hasAccess('pos/warehouses/update'),
            ],
            ['allow',
                'actions' => ['delete', 'group/delete', 'staff/delete'],
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

    public function view_staff($request, $response, $args)
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

        $wsmodel = new \Model\WarehouseStaffsModel();
        $staffs = $wsmodel->getData($args['id']);

        $wmodel = \Model\WarehousesModel::model()->findByPk($args['id']);

        $amodel = new \Model\AdminModel();
        $admins = $amodel->getData(['status' => \Model\AdminModel::STATUS_ACTIVE]);

        $rmodel = new \Model\WarehouseStaffRolesModel();
        $roles = $rmodel->getData();
        $rules = $rmodel->getRules();

        return $this->_container->module->render(
            $response,
            'warehouses/staff_view.html',
            [
                'staffs' => $staffs,
                'admins' => $admins,
                'roles' => $roles,
                'warehouse' => $wmodel,
                'rules' => $rules
            ]
        );
    }

    public function create_staff($request, $response, $args)
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

        $amodel = new \Model\AdminModel();
        $admins = $amodel->getData(['status' => \Model\AdminModel::STATUS_ACTIVE]);
        $rmodel = new \Model\WarehouseStaffRolesModel();
        $roles = $rmodel->getData();

        if (isset($_POST['WarehouseStaffs'])) {
            $success = 0;
            foreach ($_POST['WarehouseStaffs']['admin_id'] as $i => $admin_id) {
                if (empty($_POST['WarehouseStaffs']['id'][$i])) { //create new record
                    $model[$i] = new \Model\WarehouseStaffsModel();
                    $model[$i]->warehouse_id = $args['id'];
                    $model[$i]->admin_id = $admin_id;
                    $model[$i]->role_id = $_POST['WarehouseStaffs']['role_id'][$i];
                    $model[$i]->created_at = date("Y-m-d H:i:s");
                    $model[$i]->created_by = $this->_user->id;

                    $save = \Model\WarehouseStaffsModel::model()->save($model[$i]);
                    if ($save) {
                        $success = $success + 1;
                    }
                } else { //update the old record
                    $pmodel[$i] = \Model\WarehouseStaffsModel::model()->findByPk($_POST['WarehouseStaffs']['id'][$i]);
                    $pmodel[$i]->admin_id = $admin_id;
                    $pmodel[$i]->role_id = $_POST['WarehouseStaffs']['role_id'][$i];
                    $pmodel[$i]->updated_at = date("Y-m-d H:i:s");
                    $pmodel[$i]->updated_by = $this->_user->id;
                    try {
                        $update = \Model\WarehouseStaffsModel::model()->update($pmodel[$i]);
                        if ($update) {
                            $success = $success + 1;
                        }
                    } catch (\Exception $e) {
                        var_dump($e->getMessage()); exit;
                    }
                }
            }

            if ($success > 0)  {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                    ], 201);
            } else {
                return $response->withJson(
                    [
                        'status' => 'failed',
                        'message' => 'Tidak ada data yang berhasil disimpan.',
                    ], 201);
            }
        } else {
            return $this->_container->module->render(
                $response,
                'warehouses/_form_staff_items.html',
                [
                    'show_delete_btn' => true,
                    'admins' => $admins,
                    'roles' => $roles
                ]);
        }
    }

    public function delete_staff($request, $response, $args)
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

        $model = \Model\WarehouseStaffsModel::model()->findByPk($_POST['id']);
        $delete = \Model\WarehouseStaffsModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        } else {
            return $response->withJson(
                [
                    'status' => 'failed',
                    'message' => \Model\WarehouseStaffsModel::model()->getErrors(),
                ], 201);
        }
    }

    public function create_role($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\WarehouseStaffRolesModel();
        if (isset($_POST['WarehouseStaffRoles'])) {
            $model->title = $_POST['WarehouseStaffRoles']['title'];
            $model->description = $_POST['WarehouseStaffRoles']['description'];
            if (isset($_POST['WarehouseStaffRoles']['roles'])) {
                $roles = [];
                foreach ($_POST['WarehouseStaffRoles']['roles'] as $role_id => $role_name) {
                    $roles[$role_id] = array_keys($role_name);
                }
                $model->roles = json_encode($roles);
            }
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $this->_user->id;
            try {
                $save = \Model\WarehouseStaffRolesModel::model()->save($model);
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

    public function update_role($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \Model\WarehouseStaffRolesModel::model()->findByPk($args['id']);
        $rmodel = new \Model\WarehouseStaffRolesModel();
        $rules = $rmodel->getRules();

        if (isset($_POST['WarehouseStaffRoles'])) {
            $model->title = $_POST['WarehouseStaffRoles']['title'];
            $model->description = $_POST['WarehouseStaffRoles']['description'];
            if (isset($_POST['WarehouseStaffRoles']['roles'])) {
                $roles = [];
                foreach ($_POST['WarehouseStaffRoles']['roles'] as $role_id => $role_name) {
                    $roles[$role_id] = array_keys($role_name);
                }
                $model->roles = json_encode($roles);
            }
            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $this->_user->id;
            try {
                $save = \Model\WarehouseStaffRolesModel::model()->update($model);
            } catch (\Exception $e) {
                var_dump($e->getMessage()); exit;
            }

            if ($save) {
                return $response->withJson(
                    [
                        'status' => 'success',
                        'message' => 'Data berhasil disimpan.',
                        'updated' => true
                    ], 201);
            } else {
                return $response->withJson(['status'=>'failed'], 201);
            }
        }

        return $this->_container->module->render(
            $response,
            'warehouses/_role_form.html',
            [
                'model' => $model,
                'rules' => $rules
            ]);
    }

    public function delete_role($request, $response, $args)
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

        $model = \Model\WarehouseStaffRolesModel::model()->findByPk($_POST['id']);
        $delete = \Model\WarehouseStaffRolesModel::model()->delete($model);
        if ($delete) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => 'Data berhasil dihapus.',
                ], 201);
        } else {
            return $response->withJson(
                [
                    'status' => 'failed',
                    'message' => \Model\WarehouseStaffRolesModel::model()->getErrors(),
                ], 201);
        }
    }

    public function price_item($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $product = \Model\ProductsModel::model()->findByPk($args['id']);

        return $this->_container->module->render(
            $response,
            'warehouses/_product_price_form.html', ['product' => $product]);
    }

    public function price_prices($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        $save_counter = 0;
        if (isset($_POST['WarehouseProducts']) && count($_POST['WarehouseProducts']['product_id'])>0) {
            foreach ($_POST['WarehouseProducts']['product_id'] as $idx => $product_id) {
                if (is_array($_POST['WarehouseProducts'][$product_id]['_qty'])) {
                    foreach ($_POST['WarehouseProducts'][$product_id]['_qty'] as $_qid => $_qval) {
                        $_qty = (int)$_POST['WarehouseProducts'][$product_id]['_qty'][$_qid];
                        $delimeter = $_POST['WarehouseProducts'][$product_id]['delimeter'][$_qid];
                        if ($delimeter == 'less_than') {
                            if ($_qid == 0) {
                                $_POST['WarehouseProducts'][$product_id]['quantity'][$_qid] = 1;
                            } else {
                                // check the prev data
                                $_POST['WarehouseProducts'][$product_id]['quantity'][$_qid] = $_POST['WarehouseProducts'][$product_id]['quantity_max'][$_qid-1] + 1;
                            }
                            $_POST['WarehouseProducts'][$product_id]['quantity_max'][$_qid] = $_qty - 1;
                        } elseif ($delimeter == 'more_than') {
                            // has next data
                            if (array_key_exists($_qid+1, $_POST['WarehouseProducts'][$product_id]['_qty'])) {
                                $_POST['WarehouseProducts'][$product_id]['quantity_max'][$_qid] = (int)$_POST['WarehouseProducts'][$product_id]['_qty'][$_qid+1];
                            } else {
                                $_POST['WarehouseProducts'][$product_id]['quantity_max'][$_qid] = 1000;
                            }
                            $_POST['WarehouseProducts'][$product_id]['quantity'][$_qid] = $_qty + 1;
                        } elseif ($delimeter == 'less_than_equal') {
                            if ($_qid == 0) {
                                $_POST['WarehouseProducts'][$product_id]['quantity'][$_qid] = 1;
                            } else {
                                $_POST['WarehouseProducts'][$product_id]['quantity'][$_qid] = $_POST['WarehouseProducts'][$product_id]['quantity_max'][$_qid-1] + 1;
                            }
                            $_POST['WarehouseProducts'][$product_id]['quantity_max'][$_qid] = $_qty;
                        } else { //should be more than equal
                            // has next data
                            if (array_key_exists($_qid+1, $_POST['WarehouseProducts'][$product_id]['_qty'])) {
                                $_POST['WarehouseProducts'][$product_id]['quantity_max'][$_qid] = (int)$_POST['WarehouseProducts'][$product_id]['_qty'][$_qid+1];
                            } else {
                                $_POST['WarehouseProducts'][$product_id]['quantity_max'][$_qid] = 1000;
                            }
                            $_POST['WarehouseProducts'][$product_id]['quantity'][$_qid] = (int)$_qty;
                        }
                    }
                }
                // build the configs
                if (is_array($_POST['WarehouseProducts'][$product_id]['quantity'])) {
                    $items = [];
                    foreach ($_POST['WarehouseProducts'][$product_id]['quantity'] as $qid => $qval) {
                        $quantity_max = $_POST['WarehouseProducts'][$product_id]['quantity_max'][$qid];
                        if ($quantity_max <= 0) {
                            $quantity_max = $qval;
                        }
                        $price = $_POST['WarehouseProducts'][$product_id]['price'][$qid];
                        $delimeter = $_POST['WarehouseProducts'][$product_id]['delimeter'][$qid];
                        $_qty = $_POST['WarehouseProducts'][$product_id]['_qty'][$qid];
                        $data = ['quantity' => $qval, 'quantity_max' => $quantity_max, 'price' => $price, 'delimeter' => $delimeter, '_qty' => $_qty];
                        $items[$qid] = $data;
                    }
                }

                $model = \Model\WarehouseProductsModel::model()->findByAttributes(['product_id' => $product_id, 'warehouse_id' => $_POST['WarehouseProducts']['warehouse_id']]);
                if ($model instanceof \RedBeanPHP\OODBBean) {
                    $model->priority = $_POST['WarehouseProducts'][$product_id]['priority'];
                    $model->configs = json_encode($items);
                    $model->updated_at = date("Y-m-d H:i:s");
                    $model->updated_by = $this->_user->id;
                    $simpan = \Model\WarehouseProductsModel::model()->update(@$model);
                    if ($simpan) {
                        $save_counter = $save_counter + 1;
                    }
                } else {
                    $model = new \Model\WarehouseProductsModel();
                    $model->warehouse_id = $_POST['WarehouseProducts']['warehouse_id'];
                    $model->product_id = $product_id;
                    $model->priority = $_POST['WarehouseProducts'][$product_id]['priority'];
                    $model->configs = json_encode($items);
                    $model->created_at = date("Y-m-d H:i:s");
                    $model->created_by = $this->_user->id;
                    $simpan = \Model\WarehouseProductsModel::model()->save(@$model);
                    if ($simpan) {
                        $save_counter = $save_counter + 1;
                    }
                }
            }
        }

        if ($save_counter > 0) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => $save_counter.' data berhasil disimpan.',
                ], 201);
        } else {
            return $response->withJson(
                [
                    'status' => 'failed',
                    'message' => 'Data gagal disimpan',
                ], 201);
        }
    }

    public function product_stock($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        if (!isset($args['id'])) {
            return false;
        }

        $save_counter = 0;
        if (isset($_POST['ProductStocks'])) {
            foreach ($_POST['ProductStocks']['product_id'] as $j => $product_id) {
                $tot_stock = $_POST['ProductStocks'][$product_id];
                $model = new \Model\ProductStocksModel();
                $current_stock = $model->getStock(['warehouse_id' => $args['id'], 'product_id' => $product_id]);
                $new_stock = $tot_stock - $current_stock;
                $model->warehouse_id = $args['id'];
                $model->product_id = $product_id;
                $model->quantity = $new_stock;
                $model->created_at = date("Y-m-d H:i:s");
                $model->created_by = $this->_user->id;
                $simpan = \Model\ProductStocksModel::model()->save(@$model);
                if ($simpan) {
                    $save_counter = $save_counter + 1;
                }
            }
        }

        if ($save_counter > 0) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => $save_counter.' data berhasil disimpan.',
                ], 201);
        } else {
            return $response->withJson(
                [
                    'status' => 'failed',
                    'message' => 'Data gagal disimpan',
                ], 201);
        }
    }

    public function price_fees($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if (!$isAllowed) {
            return $this->notAllowedAction();
        }

        $save_counter = 0;
        if (isset($_POST['WarehouseProducts']) && count($_POST['WarehouseProducts']['product_id'])>0) {
            foreach ($_POST['WarehouseProducts']['product_id'] as $idx => $product_id) {
                if (is_array($_POST['WarehouseProducts'][$product_id]['_qty'])) {
                    foreach ($_POST['WarehouseProducts'][$product_id]['_qty'] as $_qid => $_qval) {
                        $_qty = (int)$_POST['WarehouseProducts'][$product_id]['_qty'][$_qid];
                        $delimeter = $_POST['WarehouseProducts'][$product_id]['delimeter'][$_qid];
                        if ($delimeter == 'less_than') {
                            if ($_qid == 0) {
                                $_POST['WarehouseProducts'][$product_id]['quantity'][$_qid] = 1;
                            } else {
                                // check the prev data
                                $_POST['WarehouseProducts'][$product_id]['quantity'][$_qid] = $_POST['WarehouseProducts'][$product_id]['quantity_max'][$_qid-1] + 1;
                            }
                            $_POST['WarehouseProducts'][$product_id]['quantity_max'][$_qid] = $_qty - 1;
                        } elseif ($delimeter == 'more_than') {
                            // has next data
                            if (array_key_exists($_qid+1, $_POST['WarehouseProducts'][$product_id]['_qty'])) {
                                $_POST['WarehouseProducts'][$product_id]['quantity_max'][$_qid] = (int)$_POST['WarehouseProducts'][$product_id]['_qty'][$_qid+1];
                            } else {
                                $_POST['WarehouseProducts'][$product_id]['quantity_max'][$_qid] = 1000;
                            }
                            $_POST['WarehouseProducts'][$product_id]['quantity'][$_qid] = $_qty + 1;
                        } elseif ($delimeter == 'less_than_equal') {
                            if ($_qid == 0) {
                                $_POST['WarehouseProducts'][$product_id]['quantity'][$_qid] = 1;
                            } else {
                                $_POST['WarehouseProducts'][$product_id]['quantity'][$_qid] = $_POST['WarehouseProducts'][$product_id]['quantity_max'][$_qid-1] + 1;
                            }
                            $_POST['WarehouseProducts'][$product_id]['quantity_max'][$_qid] = $_qty;
                        } else { //should be more than equal
                            // has next data
                            if (array_key_exists($_qid+1, $_POST['WarehouseProducts'][$product_id]['_qty'])) {
                                $_POST['WarehouseProducts'][$product_id]['quantity_max'][$_qid] = (int)$_POST['WarehouseProducts'][$product_id]['_qty'][$_qid+1];
                            } else {
                                $_POST['WarehouseProducts'][$product_id]['quantity_max'][$_qid] = 1000;
                            }
                            $_POST['WarehouseProducts'][$product_id]['quantity'][$_qid] = (int)$_qty;
                        }
                    }
                }
                // build the configs
                if (is_array($_POST['WarehouseProducts'][$product_id]['quantity'])) {
                    $items = [];
                    foreach ($_POST['WarehouseProducts'][$product_id]['quantity'] as $qid => $qval) {
                        $quantity_max = $_POST['WarehouseProducts'][$product_id]['quantity_max'][$qid];
                        if ($quantity_max <= 0) {
                            $quantity_max = $qval;
                        }
                        $price = $_POST['WarehouseProducts'][$product_id]['price'][$qid];
                        $delimeter = $_POST['WarehouseProducts'][$product_id]['delimeter'][$qid];
                        $_qty = $_POST['WarehouseProducts'][$product_id]['_qty'][$qid];
                        $data = ['quantity' => $qval, 'quantity_max' => $quantity_max, 'price' => $price, 'delimeter' => $delimeter, '_qty' => $_qty];
                        $items[$qid] = $data;
                    }
                }

                $model = \Model\WarehouseProductFeesModel::model()->findByAttributes(['product_id' => $product_id, 'warehouse_id' => $_POST['WarehouseProducts']['warehouse_id']]);
                if ($model instanceof \RedBeanPHP\OODBBean) {
                    $model->priority = $_POST['WarehouseProducts'][$product_id]['priority'];
                    $model->configs = json_encode($items);
                    $model->updated_at = date("Y-m-d H:i:s");
                    $model->updated_by = $this->_user->id;
                    $simpan = \Model\WarehouseProductFeesModel::model()->update(@$model);
                    if ($simpan) {
                        $save_counter = $save_counter + 1;
                    }
                } else {
                    $model = new \Model\WarehouseProductFeesModel();
                    $model->warehouse_id = $_POST['WarehouseProducts']['warehouse_id'];
                    $model->product_id = $product_id;
                    $model->priority = $_POST['WarehouseProducts'][$product_id]['priority'];
                    $model->configs = json_encode($items);
                    $model->created_at = date("Y-m-d H:i:s");
                    $model->created_by = $this->_user->id;
                    $simpan = \Model\WarehouseProductFeesModel::model()->save(@$model);
                    if ($simpan) {
                        $save_counter = $save_counter + 1;
                    }
                }
            }
        }

        if ($save_counter > 0) {
            return $response->withJson(
                [
                    'status' => 'success',
                    'message' => $save_counter.' data berhasil disimpan.',
                ], 201);
        } else {
            return $response->withJson(
                [
                    'status' => 'failed',
                    'message' => 'Data gagal disimpan',
                ], 201);
        }
    }
}