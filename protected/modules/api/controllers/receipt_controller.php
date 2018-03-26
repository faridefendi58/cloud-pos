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
        $app->map(['POST'], '/confirm', [$this, 'confirm']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['get-issue', 'list-issue', 'list-issue-number', 'confirm'],
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
                $dt_model = new \Model\PurchaseOrderItemsModel();
                $items_data = $dt_model->getData( $model->id );
                $result['data']['items'] = $items_data;
            } else {
                $model = \Model\TransferIssuesModel::model()->findByAttributes(['ti_number' => $params['issue_number']]);
                if ($model instanceof \RedBeanPHP\OODBBean) {
                    $ti_model = new \Model\TransferIssuesModel();
                    $result_data = $ti_model->getDetail($model->id);
                    $result['success'] = 1;
                    $result['data'] = array_merge(['type' => 'transfer_issue'], $result_data);
                    $dt_model = new \Model\TransferIssueItemsModel();
                    $items_data = $dt_model->getData( $model->id );
                    $result['data']['items'] = $items_data;
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
        $result_ti_data = $ti_model->getData(['status' => \Model\TransferIssuesModel::STATUS_PENDING]);
        if (is_array($result_ti_data) && count($result_ti_data)>0) {
            $result['success'] = 1;
            foreach ($result_ti_data as $i => $ti_result) {
                $result['data'][] = $ti_result['ti_number'];
            }
        }

        return $response->withJson($result, 201);
    }

    public function confirm($request, $response, $args)
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
        $model = new \Model\PurchaseOrdersModel();
        $params = $request->getParams();
        if (isset($params['issue_number'])) {
            $result = [
                'success' => 0,
                'message' => "Nomor pengadaan tidak ditemukan.",
            ];
        }

        if (isset($params['type'])) {
            $issue_items = [];
            if (isset($params['items'])) {
                $items = explode("-", $params['items']);
                if (is_array($items)) {
                    foreach ($items as $i => $item) {
                        $p_count = explode(",", $item);
                        if (is_array($p_count)) {
                            $issue_items[$p_count[0]] = (int) $p_count[1];
                        }
                    }
                }
            }

            if (isset($params['warehouse_name']) && !isset($params['warehouse_id'])) {
                $whmodel = \Model\WarehousesModel::model()->findByAttributes(['title' => $params['warehouse_name']]);
                if ($whmodel instanceof \RedBeanPHP\OODBBean) {
                    $params['warehouse_id'] = $whmodel->id;
                }
            }

            if (empty($params['warehouse_id'])) {
                $result = [
                    'success' => 0,
                    'message' => 'Warehouse tidak ditemukan.'
                ];
                return $response->withJson($result, 201);
            }
            
            if ($params['type'] == 'purchase_order') {
                $model = \Model\PurchaseOrdersModel::model()->findByAttributes(['po_number' => $params['issue_number']]);
                if (isset($params['notes'])) {
                    $model->notes = $params['notes'];
                }
                $model->updated_at = date("Y-m-d H:i:s");
                if (isset($params['admin_id'])) {
                    $model->updated_by = $params['admin_id'];
                    $model->received_at = date("Y-m-d H:i:s");
                    $model->received_by = $params['admin_id'];
                }
                $update = \Model\PurchaseOrdersModel::model()->update($model);
                if ($update){
                    $result = [
                        'success' => 1,
                        'message' => 'Data berhasi disimpan.',
                        'id' => $model->id
                    ];
                    if (count($issue_items) > 0) {
                        $params['po_id'] = $model->id;
                        $params['items'] = $issue_items;
                        $receipts = $this->create_receipt_header($params);
                        if ($receipts['success'] <= 0) {
                            $result['success'] = 0;
                            $result['message'] = $receipts['message'];
                        }
                    }
                } else {
                    $result = [
                        'success' => 0,
                        'message' => 'Data gagal disimpan.',
                        'errors' => \Model\PurchaseOrdersModel::model()->getErrors(false, false, false)
                    ];
                }
            } elseif ($params['type'] == 'transfer_issue') {
                $model = \Model\TransferIssuesModel::model()->findByAttributes(['ti_number' => $params['issue_number']]);
                if (isset($params['notes'])) {
                    $model->notes = $params['notes'];
                }
                $model->updated_at = date("Y-m-d H:i:s");
                if (isset($params['admin_id'])) {
                    $model->updated_by = $params['admin_id'];
                    $model->received_at = date("Y-m-d H:i:s");
                    $model->received_by = $params['admin_id'];
                }
                $update = \Model\TransferIssuesModel::model()->update($model);
                if ($update){
                    $result = [
                        'success' => 1,
                        'message' => 'Data berhasi disimpan.',
                        'id' => $model->id
                    ];
                    if (count($issue_items) > 0) {
                        $params['ti_id'] = $model->id;
                        $params['items'] = $issue_items;
                        $receipts = $this->create_receipt_header($params);
                        if ($receipts['success'] <= 0) {
                            $result['success'] = 0;
                            $result['message'] = $receipts['message'];
                        }
                    }
                } else {
                    $result = [
                        'success' => 0,
                        'message' => 'Data gagal disimpan.',
                        'errors' => \Model\TransferIssuesModel::model()->getErrors(false, false, false)
                    ];
                }
            }
        }

        return $response->withJson($result, 201);
    }
    
    private function create_receipt_header($data)
    {
        if ($data['type'] == 'purchase_order') {
            $rmodel = \Model\PurchaseReceiptsModel::model()->findByAttributes(['po_id' => $data['po_id'], 'warehouse_id' => $data['warehouse_id']]);
            if (!$rmodel instanceof \RedBeanPHP\OODBBean) {
                $model = new \Model\PurchaseReceiptsModel();
                $pr_number = \Pos\Controllers\PurchasesController::get_pr_number();
                $model->pr_number = $pr_number['serie_nr'];
                $model->pr_serie = $pr_number['serie'];
                $model->pr_nr = $pr_number['nr'];
                $model->po_id = $data['po_id'];
                $model->warehouse_id = $data['warehouse_id'];
                $model->created_at = date("Y-m-d H:i:s");
                $model->created_by = (isset($data['admin_id'])) ? $data['admin_id'] : 1;
                $save = \Model\PurchaseReceiptsModel::model()->save(@$model);
                if ($save) {
                    $tot_quantity = 0; $quantity_max = 0;
                    if (isset($data['items']) && is_array($data['items'])) {
                        foreach ($data['items'] as $product_id => $quantity) {
                            $po_item = \Model\PurchaseOrderItemsModel::model()->findByAttributes(['product_id' => $product_id, 'po_id' => $data['po_id']]);
                            if ($po_item instanceof \RedBeanPHP\OODBBean) {
                                $primodel[$product_id] = new \Model\PurchaseReceiptItemsModel();
                                $primodel[$product_id]->pr_id = $model->id;
                                $primodel[$product_id]->po_item_id = $po_item->id;
                                $primodel[$product_id]->product_id = $product_id;
                                $product[$product_id] = \Model\ProductsModel::model()->findByPk($product_id);
                                $primodel[$product_id]->title = $product[$product_id]->title;
                                $primodel[$product_id]->quantity = $quantity;
                                $primodel[$product_id]->quantity_max = $po_item->quantity;
                                $primodel[$product_id]->unit = $po_item->unit;
                                $primodel[$product_id]->price = $po_item->price;
                                $primodel[$product_id]->created_at = date("Y-m-d H:i:s");
                                $primodel[$product_id]->created_by = $model->created_by;

                                $save2 = \Model\PurchaseReceiptItemsModel::model()->save($primodel[$product_id]);
                                if ($save2) {
                                    $tot_quantity = $tot_quantity + $quantity;
                                    $quantity_max = $quantity_max + $po_item->quantity;
                                }
                            }
                        }
                    }

                    $pomodel = \Model\PurchaseOrdersModel::model()->findByPk($data['po_id']);
                    if ($pomodel->status !== \Model\PurchaseOrdersModel::STATUS_COMPLETED && $quantity == $quantity_max) {
                        $pomodel->status = \Model\PurchaseOrdersModel::STATUS_COMPLETED;
                        $pomodel->updated_at = date("Y-m-d H:i:s");
                        $pomodel->updated_by = $model->created_by;

                        $update_status = \Model\PurchaseOrdersModel::model()->update($pomodel);
                    }

                    if ($tot_quantity > 0) {
                        // directly add to wh stock
                        $add_to_stock = \Pos\Controllers\PurchasesController::_add_to_stock(['pr_id' => $model->id]);
                    }

                    return ["success" => 1, "id" => $model->id];
                }
            } else {
                return ["success" => 0, "message" => "Purchase order tersebut sudah terkonfirmasi sebelumnya."];
            }
        } elseif ($data['type'] == 'transfer_issue') {
            // check receipt first
            $rmodel = \Model\TransferReceiptsModel::model()->findByAttributes(['ti_id' => $data['ti_id'], 'warehouse_id' => $data['warehouse_id']]);
            if (!$rmodel instanceof \RedBeanPHP\OODBBean) {
                $model = new \Model\TransferReceiptsModel();
                $tr_number = \Pos\Controllers\TransfersController::get_tr_number();
                $model->tr_number = $tr_number['serie_nr'];
                $model->tr_serie = $tr_number['serie'];
                $model->tr_nr = $tr_number['nr'];
                $model->ti_id = $data['ti_id'];
                $model->warehouse_id = $data['warehouse_id'];
                $model->effective_date = date("Y-m-d H:i:s");
                $model->created_at = date("Y-m-d H:i:s");
                $model->created_by = (isset($data['admin_id']))? $data['admin_id'] : 1;
                $save = \Model\TransferReceiptsModel::model()->save(@$model);
                if ($save) {
                    if (isset($data['items']) && is_array($data['items'])) {
                        $tot_quantity = 0; $quantity_max = 0;
                        foreach ($data['items'] as $product_id => $quantity ) {
                            $ti_item = \Model\TransferIssueItemsModel::model()->findByAttributes(['product_id' => $product_id, 'ti_id' => $data['ti_id']]);
                            if ($ti_item instanceof \RedBeanPHP\OODBBean) {
                                $primodel[$product_id] = new \Model\TransferReceiptItemsModel();
                                $primodel[$product_id]->tr_id = $model->id;
                                $primodel[$product_id]->ti_item_id = $ti_item->id;
                                $primodel[$product_id]->product_id = $product_id;
                                $product[$product_id] = \Model\ProductsModel::model()->findByPk($product_id);
                                $primodel[$product_id]->title = $product[$product_id]->title;
                                $primodel[$product_id]->quantity = $quantity;
                                $primodel[$product_id]->quantity_max = $ti_item->quantity;
                                $primodel[$product_id]->unit = $ti_item->unit;
                                $primodel[$product_id]->price = $ti_item->price;
                                $primodel[$product_id]->created_at = date("Y-m-d H:i:s");
                                $primodel[$product_id]->created_by = $model->created_by;

                                $save2 = \Model\TransferReceiptItemsModel::model()->save($primodel[$product_id]);
                                if ($save2) {
                                    $tot_quantity = $tot_quantity + $quantity;
                                    $quantity_max = $quantity_max + $ti_item->quantity;
                                }
                            }
                        }
                    }
                    $timodel = \Model\TransferIssuesModel::model()->findByPk($data['ti_id']);
                    if ($timodel->status !== \Model\TransferIssuesModel::STATUS_COMPLETED && $quantity == $quantity_max) {
                        $timodel->status = \Model\TransferIssuesModel::STATUS_COMPLETED;
                        $timodel->updated_at = date("Y-m-d H:i:s");
                        $timodel->updated_by = $model->created_by;

                        $update_status = \Model\TransferIssuesModel::model()->update($timodel);
                    }

                    if ($tot_quantity > 0) {
                        // directly add to wh stock
                        $add_to_stock = \Pos\Controllers\TransfersController::_add_to_stock(['tr_id' => $model->id]);
                    }

                    return ["success" => 1, "id" => $model->id];
                }
            } else {
                return ["success" => 0, "message" => "Transfer issue tersebut sudah terkonfirmasi sebelumnya."];
            }
        }
    }
}