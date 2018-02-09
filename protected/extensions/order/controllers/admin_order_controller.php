<?php

namespace Extensions\Controllers;

use Components\BaseController as BaseController;

class AdminOrderController extends BaseController
{
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/view', [$this, 'view']);
        $app->map(['GET'], '/update/[{id}]', [$this, 'update']);
        $app->map(['POST'], '/delete/[{id}]', [$this, 'delete']);
        $app->map(['POST'], '/activate/[{id}]', [$this, 'activate']);
        $app->map(['POST'], '/suspend/[{id}]', [$this, 'suspend']);
        $app->map(['POST'], '/unsuspend/[{id}]', [$this, 'unsuspend']);
        $app->map(['POST'], '/cancel/[{id}]', [$this, 'cancel']);
    }

    public function view($request, $response, $args)
    {
        if ($this->_user->isGuest()){
            return $response->withRedirect($this->_login_url);
        }

        $model = new \ExtensionsModel\ClientOrderModel();
        $orders = \ExtensionsModel\ClientOrderModel::model()->get_list();


        return $this->_container->module->render($response, 'orders/view.html', [
            'model' => $model,
            'orders' => $orders
        ]);
    }

    public function update($request, $response, $args)
    {
        if ($this->_user->isGuest()){
            return $response->withRedirect($this->_login_url);
        }

        $omodel = new \ExtensionsModel\ClientOrderModel();
        $model = \ExtensionsModel\ClientOrderModel::model()->findByPk($args['id']);

        if (!empty($model->product_id) )
            $product = \ExtensionsModel\ProductModel::model()->findByPk($model->product_id);

        return $this->_container->module->render($response, 'orders/update.html', [
            'omodel' => $omodel,
            'model' => $model,
            'product' => $product,
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

        $model = \ExtensionsModel\ClientOrderModel::model()->findByPk($args['id']);
        $delete = \ExtensionsModel\ClientOrderModel::model()->delete($model);
        if ($delete) {
            echo true;
        }
    }

    public function activate($request, $response, $args)
    {
        if ($this->_user->isGuest()){
            return $response->withRedirect($this->_login_url);
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \ExtensionsModel\ClientOrderModel::model()->findByPk($args['id']);

        $service = new \Extensions\OrderService($this->_settings);
        $activate = $service->activate($model);

        echo $activate;
    }

    public function suspend($request, $response, $args)
    {
        if ($this->_user->isGuest()){
            return $response->withRedirect($this->_login_url);
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \ExtensionsModel\ClientOrderModel::model()->findByPk($args['id']);

        $service = new \Extensions\OrderService();
        $suspend = $service->suspend($model);

        echo $suspend;
    }

    public function unsuspend($request, $response, $args)
    {
        if ($this->_user->isGuest()){
            return $response->withRedirect($this->_login_url);
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \ExtensionsModel\ClientOrderModel::model()->findByPk($args['id']);

        $service = new \Extensions\OrderService();
        $unsuspend = $service->unsuspend($model);

        echo $unsuspend;
    }

    public function cancel($request, $response, $args)
    {
        if ($this->_user->isGuest()){
            return $response->withRedirect($this->_login_url);
        }

        if (!isset($args['id'])) {
            return false;
        }

        $model = \ExtensionsModel\ClientOrderModel::model()->findByPk($args['id']);

        $service = new \Extensions\OrderService();
        $cancel = $service->cancel($model);

        echo $cancel;
    }
}