<?php

namespace PanelAdmin\Controllers;

use Components\BaseController as BaseController;

class ParamsController extends BaseController
{
    protected $_login_url = '/panel-admin/default/login';

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
                'expression' => $this->hasAccess('panel-admin/params/read'),
            ],
            ['allow',
                'actions' => ['create'],
                'expression' => $this->hasAccess('panel-admin/params/create'),
            ],
            ['allow',
                'actions' => ['update'],
                'expression' => $this->hasAccess('panel-admin/params/update'),
            ],
            ['allow',
                'actions' => ['delete'],
                'expression' => $this->hasAccess('panel-admin/params/delete'),
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

        $options = \Model\OptionsModel::model()->findAllByAttributes( ['autoload' => 'yes'] );

        return $this->_container->module->render($response, 'params/view.html', [
            'options' => $options
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

        $model = new \Model\OptionsModel('create');

        if (isset($_POST['Options'])){
            $model->option_name = $_POST['Options']['option_name'];
            $model->option_value = $_POST['Options']['option_value'];
            $model->option_description = $_POST['Options']['option_description'];
            $model->created_at = date('Y-m-d H:i:s');
            $create = \Model\OptionsModel::model()->save($model);
            if ($create) {
                $message = 'Data Anda telah berhasil disimpan.';
                $success = true;

                $hooks = new \PanelAdmin\Components\AdminHooks($this->_settings);
                $hooks->onAfterParamsSaved($model->getOptions());
            } else {
                $message = \Model\OptionsModel::model()->getErrors(false);
                $errors = \Model\OptionsModel::model()->getErrors(true, true);
                $success = false;
            }
        }

        return $this->_container->module->render($response, 'params/create.html', [
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

        $model = \Model\OptionsModel::model()->findByPk($args['id']);

        if (isset($_POST['Options'])){
            $model->option_name = $_POST['Options']['option_name'];
            $model->option_value = $_POST['Options']['option_value'];
            $model->option_description = $_POST['Options']['option_description'];
            $model->updated_at = date('Y-m-d H:i:s');
            $update = \Model\OptionsModel::model()->update($model);
            if ($update) {
                $message = 'Data Anda telah berhasil diubah.';
                $success = true;

                $hooks = new \PanelAdmin\Components\AdminHooks($this->_settings);
                $omodel = new \Model\OptionsModel();
                $hooks->onAfterParamsSaved($omodel->getOptions());

            } else {
                $message = \Model\OptionsModel::model()->getErrors(false);
                $success = false;
            }
        }

        return $this->_container->module->render($response, 'params/update.html', [
            'model' => $model,
            'admin' => new \Model\OptionsModel(),
            'message' => ($message) ? $message : null,
            'success' => $success
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

        $model = \Model\OptionsModel::model()->findByPk($args['id']);
        $delete = \Model\OptionsModel::model()->delete($model);
        if ($delete) {
            $message = 'Data Anda ('.$model->option_name.') telah berhasil dihapus.';

            $hooks = new \PanelAdmin\Components\AdminHooks($this->_settings);
            $omodel = new \Model\OptionsModel();
            $hooks->onAfterParamsSaved($omodel->getOptions());
            
            echo true;
        }
    }
}