<?php

namespace PanelAdmin\Controllers;

use Components\BaseController as BaseController;

class ThemesController extends BaseController
{

    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/view', [$this, 'view']);
        $app->map(['GET', 'POST'], '/update', [$this, 'update']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['view', 'update'],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['view'],
                'expression' => $this->hasAccess('panel-admin/themes/read'),
            ],
            ['allow',
                'actions' => ['update'],
                'expression' => $this->hasAccess('panel-admin/themes/update'),
            ],
            ['deny',
                'users' => ['*'],
            ],
        ];
    }

    public function view($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response, $args);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $tools = new \PanelAdmin\Components\AdminTools($this->_settings);

        return $this->_container->module->render($response, 'themes/view.html', [
            'themes' => $tools->getThemes(),
            'current_theme' => $this->_settings['theme']['name'],
            'removable' => (count($tools->getThemes()) > 1)? true : false
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

        $tools = new \PanelAdmin\Components\AdminTools($this->_settings);

        if (isset($_POST['id'])){
            if ((int)$_POST['install'] < 1){
                if (count($tools->getThemes()) < 2)
                    return false;
                else
                    $_POST['id'] = 'default';
            }

            $model = new \Model\OptionsModel();
            $theme = \Model\OptionsModel::model()->findByAttributes(['option_name'=>'theme']);
            if ($theme instanceof \RedBeanPHP\OODBBean) {
                $theme->option_value = $_POST['id'];
                $theme->updated_at = date('Y-m-d H:i:s');
                $update = \Model\OptionsModel::model()->update($theme);

                $hooks = new \PanelAdmin\Components\AdminHooks($this->_settings);
                $omodel = new \Model\OptionsModel();
                $hooks->onAfterParamsSaved($model->getOptions());
            }
            
            if ($update) {
                $message = ($_POST['install'] > 0)? 'Tema berhasil diubah menjadi '.$_POST['id'] : 'Sukses menginstall tema '.$_POST['id'];
                $success = true;
            } else {
                $message = 'Gagal mengubah tema.';
                $success = false;
            }

            return json_encode(['success'=>$success, 'message'=>$message]);
        }
    }
}