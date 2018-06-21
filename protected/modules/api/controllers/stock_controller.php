<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;

class StockController extends BaseController
{
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/list', [$this, 'get_list']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['get-list'],
                'users'=> ['@'],
            ]
        ];
    }

    public function get_list($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);

        if (!$isAllowed['allow']) {
            $result = [
                'success' => 0,
                'message' => $isAllowed['message'],
            ];
            return $response->withJson($result, 201);
        }

        $result = [];
        $params = $request->getParams();
        if (is_array($params) && in_array('warehouse_name', array_keys($params))) {
            $model = \Model\WarehousesModel::model()->findByAttributes(['title' => $params['warehouse_name']]);
        }

        if (is_array($params) && in_array('warehouse_id', array_keys($params))) {
            $model = \Model\WarehousesModel::model()->findByPk($params['warehouse_id']);
        }

        $ps_model = new \Model\ProductStocksModel();
        if ($model instanceof \RedBeanPHP\OODBBean) {
            $ps_params = ['warehouse_id' => $model->id];
            $stocks = $ps_model->getQuery($ps_params);
            $result['success'] = 1;
            $result['data'] = $stocks;
        } else {
            $wh_model = new \Model\WarehousesModel();
            $whs = $wh_model->getData();
            $stock_lists = [];
            foreach ($whs as $i => $wh) {
                $ps_params = ['warehouse_id' => $wh['id']];
                $stocks = $ps_model->getQuery($ps_params);
                $stock_lists[$wh['id']] = ['wh_data' => $wh, 'stock_data' => $stocks];
            }
            $result['success'] = 1;
            $result['data'] = $stock_lists;
        }
        
        return $response->withJson($result, 201);
    }
}