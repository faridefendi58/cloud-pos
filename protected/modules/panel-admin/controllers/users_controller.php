<?php

namespace PanelAdmin\Controllers;

use Components\BaseController as BaseController;

class UsersController extends BaseController
{

    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/view', [$this, 'view']);
        $app->map(['GET', 'POST'], '/create', [$this, 'create']);
        $app->map(['GET', 'POST'], '/update/[{id}]', [$this, 'update']);
        $app->map(['POST'], '/delete/[{id}]', [$this, 'delete']);
        $app->map(['GET'], '/group', [$this, 'group']);
        $app->map(['GET', 'POST'], '/group/create', [$this, 'group_create']);
        $app->map(['GET', 'POST'], '/group/update/[{id}]', [$this, 'group_update']);
        $app->map(['POST'], '/group/delete/[{id}]', [$this, 'group_delete']);
        $app->map(['GET', 'POST'], '/group/priviledge/[{id}]', [$this, 'group_priviledge']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['view', 'create', 'update', 'delete', 'group', 'group/create', 'group/update', 'group/delete', 'group/priviledge'],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view', 'group'],
                'expression' => $this->hasAccess('panel-admin/users/read'),
            ],
            ['allow',
                'actions' => ['create', 'group/create', 'group/priviledge'],
                'expression' => $this->hasAccess('panel-admin/users/create'),
            ],
            ['allow',
                'actions' => ['update', 'group/update'],
                'expression' => $this->hasAccess('panel-admin/users/update'),
            ],
            ['allow',
                'actions' => ['delete', 'group/delete'],
                'expression' => $this->hasAccess('panel-admin/users/delete'),
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

        $models = \Model\AdminModel::model()->findAll();

        return $this->_container->module->render($response, 'users/view.html', [
            'models' => $models,
            'cmodel' => new \Model\AdminModel(),
        ]);
    }

    public function create($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\AdminModel('create');

        if (isset($_POST['Admin'])){
            $model->username = $_POST['Admin']['username'];
            $model->salt = md5(uniqid());
            $model->password = $_POST['Admin']['password'];
            //$model->password = $model->hasPassword($_POST['Admin']['password'], $model->salt);
            $model->username = $_POST['Admin']['username'];
            $model->email = $_POST['Admin']['email'];
            $model->group_id = $_POST['Admin']['group_id'];
            $model->status = $_POST['Admin']['status'];
            $model->created_at = date('Y-m-d H:i:s');
            $create = \Model\AdminModel::model()->save($model);
            if ($create) {
                $bean = \Model\AdminModel::model()->findByAttributes(['username'=>$model->username]);
                $bean->password = $model->hasPassword($model->password, $model->salt);
                $update = \Model\AdminModel::model()->update($bean, false);

                $message = 'Data Anda telah berhasil disimpan.';
                $success = true;
            } else {
                $message = \Model\AdminModel::model()->getErrors(false);
                $errors = \Model\AdminModel::model()->getErrors(true, true);
                $success = false;
            }
        }

        return $this->_container->module->render($response, 'users/create.html', [
            'model' => $model,
            'message' => ($message) ? $message : null,
            'success' => $success,
            'errors' => $errors
        ]);
    }

    public function update($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = \Model\AdminModel::model()->findByPk($args['id']);

        if (isset($_POST['Admin'])){
            $model->username = $_POST['Admin']['username'];
            $model->email = $_POST['Admin']['email'];
            $model->group_id = $_POST['Admin']['group_id'];
            $model->status = $_POST['Admin']['status'];
            $update = \Model\AdminModel::model()->update($model);
            if ($update) {
                $message = 'Data Anda telah berhasil diubah.';
                $success = true;
            } else {
                $message = \Model\AdminModel::model()->getErrors(false);
                $success = false;
            }
        }

        return $this->_container->module->render($response, 'users/update.html', [
            'model' => $model,
            'admin' => new \Model\AdminModel(),
            'message' => ($message) ? $message : null,
            'success' => $success
        ]);
    }

    public function delete($request, $response, $args)
    {
        if ($this->_user->isGuest()){
            return $response->withRedirect($this->_login_url);
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \Model\AdminModel::model()->findByPk($args['id']);
        $delete = \Model\AdminModel::model()->delete($model);
        if ($delete) {
            $message = 'Your data is successfully deleted.';
            echo true;
        }
    }

    public function group($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $models = \Model\AdminGroupModel::model()->findAll();

        return $this->_container->module->render($response, 'users/group.html', [
            'models' => $models,
            'cmodel' => new \Model\AdminGroupModel(),
        ]);
    }

    public function group_create($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\AdminGroupModel('create');

        if (isset($_POST['AdminGroup'])){
            $model->name = $_POST['AdminGroup']['name'];
            $model->description = $_POST['AdminGroup']['description'];
            $model->created_at = date('Y-m-d H:i:s');
            $create = \Model\AdminGroupModel::model()->save($model);
            if ($create) {
                $message = 'Data Anda telah berhasil disimpan.';
                $success = true;
            } else {
                $message = \Model\AdminGroupModel::model()->getErrors(false);
                $errors = \Model\AdminGroupModel::model()->getErrors(true, true);
                $success = false;
            }
        }

        return $this->_container->module->render($response, 'users/group_create.html', [
            'model' => $model,
            'message' => ($message) ? $message : null,
            'success' => $success,
            'errors' => $errors
        ]);
    }

    public function group_update($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = \Model\AdminGroupModel::model()->findByPk($args['id']);

        if (isset($_POST['AdminGroup'])){
            $model->name = $_POST['AdminGroup']['name'];
            $model->description = $_POST['AdminGroup']['description'];
            $model->updated_at = date('Y-m-d H:i:s');
            $update = \Model\AdminGroupModel::model()->update($model);
            if ($update) {
                $message = 'Data Anda telah berhasil diubah.';
                $success = true;
            } else {
                $message = \Model\AdminGroupModel::model()->getErrors(false);
                $success = false;
            }
        }

        return $this->_container->module->render($response, 'users/group_update.html', [
            'model' => $model,
            'admin' => new \Model\AdminGroupModel(),
            'message' => ($message) ? $message : null,
            'success' => $success
        ]);
    }

    public function group_delete($request, $response, $args)
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

        $model = \Model\AdminGroupModel::model()->findByPk($args['id']);
        $delete = \Model\AdminGroupModel::model()->delete($model);
        if ($delete) {
            $message = 'Your data is successfully deleted.';
            echo true;
        }
    }

    public function group_priviledge($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = \Model\AdminGroupModel::model()->findByPk($args['id']);
        $items = [];
        foreach (glob($this->_settings['basePath'].'/modules/*/*controllers', GLOB_ONLYDIR|GLOB_NOSORT) as $controller) {
            //$cname = basename($controller, '.php');
            $end = end(explode('modules/', $controller));
            $module = explode("/", $end);
            if (is_dir($controller)){
                foreach (glob($controller.'/*_controller.php') as $cname){
                    if (is_file($cname)){
                        $c_end = end(explode('controllers/', $cname));
                        $file_name = $c_end;
                        $ctrls = explode('_', $c_end);
                    }
                    array_push($items, [ 'path' => $cname, 'module' => $module[0], 'controller' => $ctrls[0]]);
                }
            }
        }

        if (isset($_POST['Priviledge'])){
            $model->priviledge = json_encode($_POST['Priviledge']);
            $model->updated_at = date('Y-m-d H:i:s');
            $update = \Model\AdminGroupModel::model()->update($model);
            if ($update) {
                $message = 'Data Anda telah berhasil diubah.';
                $success = true;
            } else {
                $message = \Model\AdminGroupModel::model()->getErrors(false);
                $success = false;
            }
        }

        return $this->_container->module->render($response, 'users/group_priviledge.html', [
            'model' => $model,
            'admin' => new \Model\AdminGroupModel(),
            'items' => $items,
            'priviledge' => json_decode($model->priviledge, true),
            'message' => ($message) ? $message : null,
            'success' => $success
        ]);
    }
}