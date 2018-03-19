<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;

class ReceiptController extends BaseController
{
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/get-issue', [$this, 'get_issue']);
        $app->map(['GET'], '/list-issue', [$this, 'list_issue']);
        $app->map(['GET'], '/list-issue-number', [$this, 'list_issue_number']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['get-issue', 'list-issue', 'list-issue-number'],
                'users'=> ['@'],
            ]
        ];
    }

    public function get_issue($request, $response, $args)
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
        if (is_array($params) && in_array('issue_number', array_keys($params))){
            $model = \Model\PurchaseOrdersModel::model()->findByAttributes(['po_number' => $params['issue_number']]);
            if ($model instanceof \RedBeanPHP\OODBBean) {
                $po_model = new \Model\PurchaseOrdersModel();
                $result_data = $po_model->getDetail($model->id);
                $result['success'] = 1;
                $result['data'] = array_merge(['type' => 'purchase_order'], $result_data);
            } else {
                $model = \Model\TransferIssuesModel::model()->findByAttributes(['ti_number' => $params['issue_number']]);
                if ($model instanceof \RedBeanPHP\OODBBean) {
                    $ti_model = new \Model\TransferIssuesModel();
                    $result_data = $ti_model->getDetail($model->id);
                    $result['success'] = 1;
                    $result['data'] = array_merge(['type' => 'transfer_issue'], $result_data);
                } else {
                    $result['success'] = 0;
                    $result['message'] = 'Nomor pengadaan tidak ditemukan.';
                }
            }
        }
        
        return $response->withJson($result, 201);
    }

    public function list_issue($request, $response, $args)
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
        $po_model = new \Model\PurchaseOrdersModel();
        $result_data = $po_model->getData(['status' => \Model\PurchaseOrdersModel::STATUS_ON_PROCESS]);
        if (is_array($result_data) && count($result_data)>0) {
            $result['success'] = 1;
            $result['data']['purchase_order'] = $result_data;
        }

        $ti_model = new \Model\TransferIssuesModel();
        $result_ti_data = $ti_model->getData(['status' => \Model\TransferIssuesModel::STATUS_ON_PROCESS]);
        if (is_array($result_ti_data) && count($result_ti_data)>0) {
            $result['success'] = 1;
            $result['data']['transfer_issue'] = $result_ti_data;
        }

        return $response->withJson($result, 201);
    }

    public function list_issue_number($request, $response, $args)
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
        $po_model = new \Model\PurchaseOrdersModel();
        $params = $request->getParams();
        $status = \Model\PurchaseOrdersModel::STATUS_ON_PROCESS;
        if (isset($params['status'])) {
            $status = $params['status'];
        }
        $result_data = $po_model->getData(['status' => $status]);
        if (is_array($result_data) && count($result_data)>0) {
            $result['success'] = 1;
            foreach ($result_data as $i => $po_result) {
                $result['data'][] = $po_result['po_number'];
            }
        }

        $ti_model = new \Model\TransferIssuesModel();
        $result_ti_data = $ti_model->getData(['status' => $status]);
        if (is_array($result_ti_data) && count($result_ti_data)>0) {
            $result['success'] = 1;
            foreach ($result_ti_data as $i => $ti_result) {
                $result['data'][] = $ti_result['ti_number'];
            }
        }

        return $response->withJson($result, 201);
    }
}