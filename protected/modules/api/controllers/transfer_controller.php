<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;

class TransferController extends BaseController
{
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['POST'], '/create', [$this, 'create']);
        $app->map(['GET'], '/list', [$this, 'get_list']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['create', 'list'],
                'users'=> ['@'],
            ]
        ];
    }

    public function create($request, $response, $args)
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
        if (isset($params['items'])) {
            $transfer_items = [];
            $items = explode("-", $params['items']);
            if (is_array($items)) {
                foreach ($items as $i => $item) {
                    $p_count = explode(",", $item);
                    if (is_array($p_count)) {
                        $transfer_items[$p_count[0]] = (int) $p_count[1];
                    }
                }
            }

            if (count($transfer_items) <= 0) {
                $result = ["success" => 0, "message" => "Pastikan pilih item sebelum disimpan."];
                return $response->withJson($result, 201);
            }

            if (isset($params['warehouse_from_name'])) {
                $whmodel = \Model\WarehousesModel::model()->findByAttributes(['title' => $params['warehouse_from_name']]);
                if ($whmodel instanceof \RedBeanPHP\OODBBean) {
                    $params['warehouse_from'] = $whmodel->id;
                }
            }

            if (isset($params['warehouse_to_name']) && $params['warehouse_to_name'] != '-') {
                $whtmodel = \Model\WarehousesModel::model()->findByAttributes(['title' => $params['warehouse_to_name']]);
                if ($whtmodel instanceof \RedBeanPHP\OODBBean) {
                    $params['warehouse_to'] = $whtmodel->id;
                }
            }

            $model = new \Model\TransferIssuesModel();
            $ti_number = \Pos\Controllers\TransfersController::get_ti_number();
            $model->ti_number = $ti_number['serie_nr'];
            $model->ti_serie = $ti_number['serie'];
            $model->ti_nr = $ti_number['nr'];
            $model->base_price = 0;
            $model->warehouse_from = $params['warehouse_from'];
            if (isset($params['warehouse_to']))
                $model->warehouse_to = $params['warehouse_to'];
            $model->date_transfer = date("Y-m-d H:i:s");
            $model->status = \Model\TransferIssuesModel::STATUS_ON_PROCESS;
            if (isset($params['notes']))
                $model->notes = $params['notes'];
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = (isset($params['admin_id'])) ? $params['admin_id'] : 1;
            $save = \Model\TransferIssuesModel::model()->save(@$model);
            if ($save) {
                $tot_price = 0;
                foreach ($transfer_items as $product_id => $quantity) {
                    $product = \Model\ProductsModel::model()->findByPk($product_id);
                    $imodel[$product_id] = new \Model\TransferIssueItemsModel();
                    $imodel[$product_id]->ti_id = $model->id;
                    $imodel[$product_id]->product_id = $product_id;
                    $imodel[$product_id]->title = $product->title;
                    $imodel[$product_id]->quantity = $quantity;
                    $imodel[$product_id]->unit = $product->unit;
                    $imodel[$product_id]->price = $product->current_cost;
                    $imodel[$product_id]->created_at = date("Y-m-d H:i:s");
                    $imodel[$product_id]->created_by = $model->created_by;

                    if ($product_id > 0 && $imodel[$product_id]->quantity > 0) {
                        $save2 = \Model\TransferIssueItemsModel::model()->save($imodel[$product_id]);
                        if ($save2) {
                            $tot_price = $tot_price + ($product->current_cost * $quantity);
                        }
                    }
                }

                // updating price of po data
                if ($tot_price > 0) {
                    $pomodel = \Model\TransferIssuesModel::model()->findByPk($model->id);
                    $pomodel->base_price = $tot_price;
                    $update = \Model\TransferIssuesModel::model()->update($pomodel);

                    $result = [
                        "success" => 1,
                        "id" => $model->id,
                        'message' => 'Data berhasi disimpan.',
                        "issue_number" => $model->ti_number
                    ];
                } else {
                    $result = ["success" => 0, "message" => "Tidak ada item yang dapat disimpan."];
                }
            } else {
                $result = [
                    "success" => 0,
                    "message" => \Model\TransferIssuesModel::model()->getErrors(false, false, false)
                ];
            }
        }

        return $response->withJson($result, 201);
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
        $ti_model = new \Model\TransferIssuesModel();
        $params = $request->getParams();
        $status = \Model\TransferIssuesModel::STATUS_ON_PROCESS;
        $params_data = ['status' => $status];
        if (isset($params['status'])) {
            $params_data['status'] = $params['status'];
        }

        if (isset($params['all_status'])) {
            unset($params_data['status']);
        }

        if (isset($params['admin_id'])) {
            $whsmodel = new \Model\WarehouseStaffsModel();
            $wh_staff = $whsmodel->getData(['admin_id' => $params['admin_id']]);
            $wh_groups = [];
            if (is_array($wh_staff) && count($wh_staff) > 0) {
                foreach ($wh_staff as $i => $whs) {
                    $wh_groups[$whs['wh_group_id']] = $whs['wh_group_id'];
                }
            }
            if (count($wh_groups) > 0) {
                $params_data['wh_group_id'] = $wh_groups;
            }
        }

        $result_data = $ti_model->getData($params_data);
        if (is_array($result_data) && count($result_data)>0) {
            $result['success'] = 1;
            foreach ($result_data as $i => $ti_result) {
                $result['data'][] = $ti_result['ti_number'];
                $result['origin'][$ti_result['ti_number']] = $ti_result['warehouse_from_name'];
                $result['destination'][$ti_result['ti_number']] = $ti_result['wh_group_name'];
                $result['detail'][] = $ti_result;
            }
        }

        return $response->withJson($result, 201);
    }
}