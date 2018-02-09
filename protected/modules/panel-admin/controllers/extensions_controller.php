<?php

namespace PanelAdmin\Controllers;

use Components\BaseController as BaseController;

class ExtensionsController extends BaseController
{

    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/view', [$this, 'view']);
        $app->map(['GET', 'POST'], '/setup', [$this, 'setup']);
        $app->map(['GET', 'POST'], '/manage/[{id}]', [$this, 'manage']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['view', 'setup', 'manage'],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view'],
                'expression' => $this->hasAccess('panel-admin/extensions/read'),
            ],
            ['allow',
                'actions' => ['setup', 'manage'],
                'expression' => $this->hasAccess('panel-admin/extensions/create'),
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

        $tools = new \PanelAdmin\Components\AdminTools($this->_settings);
        $installed_exts = $this->_settings['params']['extensions'];
        if (empty($installed_exts))
            $installed_exts = false;
        else
            $installed_exts = json_decode($installed_exts, true);

        return $this->_container->module->render($response, 'extensions/view.html', [
            'extensions' => $tools->getExtensions(),
            'installed_exts' => $installed_exts
        ]);
    }

    public function setup($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $tools = new \PanelAdmin\Components\AdminTools($this->_settings);

        if (isset($_POST['id'])){
            $model = \Model\OptionsModel::model()->findByAttributes(['option_name'=>'extensions']);
            if ($model instanceof \RedBeanPHP\OODBBean) {
                $exts = [];
                if (!empty($model->option_value))
                    $exts = json_decode($model->option_value, true);

                if ((int)$_POST['install'] < 1){
                    if (in_array($_POST['id'], $exts)) {
                        $items = [];
                        foreach ($exts as $i => $ext) {
                            if ($ext != $_POST['id'])
                                array_push($items, $ext);
                        }
                        $exts = $items;
                    }
                } else {
                    if (!in_array($_POST['id'], $exts))
                        array_push($exts, $_POST['id']);
                }

                $model->option_value = json_encode($exts);
                $model->updated_at = date('Y-m-d H:i:s');
                $save = \Model\OptionsModel::model()->update($model);
            } else {
                $exts = [$_POST['id']];
                $model = new \Model\OptionsModel();
                $model->option_name = 'extensions';
                $model->option_value = json_encode($exts);
                $model->created_at = date('Y-m-d H:i:s');
                $save = \Model\OptionsModel::model()->save($model);
            }
            
            if ($save) {
                $message = ($_POST['install'] > 0)? 'Ekstensi '.$_POST['id'].' berhasil diaktifkan' : 'Sukses meng-nonaktifkan ekstensi '.$_POST['id'];
                $success = true;
                $hooks = new \PanelAdmin\Components\AdminHooks($this->_settings);
                $omodel = new \Model\OptionsModel();
                $hooks->onAfterParamsSaved($omodel->getOptions());

                $className = "Extensions\\".ucfirst($_POST['id'])."Service";
                $ecommerce = new $className($this->_settings);
                if ($_POST['install'] > 0) {
                    if (is_object($ecommerce) && method_exists($ecommerce, 'install')) {
                        try {
                            $ecommerce->install();
                        } catch (\Exception $e) {
                            var_dump($e->getMessage());
                        }
                    }
                } else {
                    if (is_object($ecommerce) && method_exists($ecommerce, 'install')) {
                        try {
                            $ecommerce->uninstall();
                        } catch (\Exception $e) {
                            var_dump($e->getMessage());
                        }
                    }
                }
            } else {
                $message = 'Gagal menyimpan data.';
                $success = false;
            }

            return json_encode(['success'=>$success, 'message'=>$message]);
        }
    }

    public function manage($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $tools = new \PanelAdmin\Components\AdminTools($this->_settings);

        $model = \Model\OptionsModel::model()->findByAttributes(['option_name'=>'ext_'.$args['id']]);

        if (isset($_POST['Configs'])){
            if ($model instanceof \RedBeanPHP\OODBBean) {
                $model->option_value = json_encode($_POST['Configs']);
                $model->autoload = 'no';
                $model->updated_at = date('Y-m-d H:i:s');
                $save = \Model\OptionsModel::model()->update($model);
            } else {
                $model = new \Model\OptionsModel();
                $model->option_name = 'ext_'.$args['id'];
                $model->option_value = json_encode($_POST['Configs']);
                $model->autoload = 'no';
                $model->created_at = date('Y-m-d H:i:s');
                $save = \Model\OptionsModel::model()->save($model);
            }

            if ($save) {
                $hooks = new \PanelAdmin\Components\AdminHooks($this->_settings);
                $omodel = new \Model\OptionsModel();
                $hooks->onAfterParamsSaved($omodel->getOptions());
                
                $message = 'Data Anda telah berhasil disimpan.';
                $success = true;
            } else {
                $message = \Model\OptionsModel::model()->getErrors(false);
                $errors = \Model\OptionsModel::model()->getErrors(true, true);
                $success = false;
            }
        }

        return $this->_container->module->render($response, 'extensions/manage.html', [
            'extension' => $tools->getExtension($args['id']),
            'ext_value' => ($model instanceof \RedBeanPHP\OODBBean)? json_decode($model->option_value, true) : false,
            'message' => ($message) ? $message : null,
            'success' => $success,
            'errors' => $errors
        ]);
    }

}
