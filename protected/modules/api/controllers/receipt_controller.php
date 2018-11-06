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
        $app->map(['GET'], '/list', [$this, 'list_receipt']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => [
                    'get-issue', 'list-issue', 'list-issue-number', 'confirm',
                    'list'
                    ],
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
        $params = $request->getParams();

        $po_model = new \Model\PurchaseOrdersModel();

        $po_params = ['status' => \Model\PurchaseOrdersModel::STATUS_ON_PROCESS];
        if (isset($params['already_received'])) {
            $po_params['already_received'] = 1;
        }

        $result_data = $po_model->getData($po_params);
        if (is_array($result_data) && count($result_data)>0) {
            $result['success'] = 1;
            $result['data']['purchase_order'] = $result_data;
        }

        $ti_model = new \Model\TransferIssuesModel();

        $ti_params = ['status' => \Model\TransferIssuesModel::STATUS_ON_PROCESS];
        $result_ti_data = $ti_model->getData($ti_params);
        if (is_array($result_ti_data) && count($result_ti_data)>0) {
            $result['success'] = 1;
            $result['data']['transfer_issue'] = $result_ti_data;
        }

        return $response->withJson($result, 201);
    }

    /**
     * @param $request
     * @param $response
     * @param $args
     * @return mixed
     * return example :
     * {"success":1,"data":["PO-0000016"],"origin":{"PO-0000016":"Rumah produksi medan"},"destination":{"PO-0000016":"Jakarta"}}
     */
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
        $params = $request->getParams();
        if (!isset($params['just_transfer_issue'])) {
            $po_model = new \Model\PurchaseOrdersModel();
            $status = \Model\PurchaseOrdersModel::STATUS_ON_PROCESS;
            $params_data = ['status' => $status];
            if (isset($params['status'])) {
                $params_data['status'] = $params['status'];
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

            if (isset($params['already_received'])) {
                $params_data['already_received'] = 1;
            }

            $result_data = $po_model->getData($params_data);
            if (is_array($result_data) && count($result_data)>0) {
                $result['success'] = 1;
                foreach ($result_data as $i => $po_result) {
                    $result['data'][] = $po_result['po_number'];
                    $result['origin'][$po_result['po_number']] = $po_result['supplier_name'];
                    $result['destination'][$po_result['po_number']] = $po_result['wh_group_name'];
                }
            }
        }

        $ti_model = new \Model\TransferIssuesModel();
        $params_data2 = ['status' => \Model\TransferIssuesModel::STATUS_ON_PROCESS];
        if (count($params_data['wh_group_id']) > 0) {
            $params_data2['wh_group_id'] = $params_data['wh_group_id'];
        }
        $result_ti_data = $ti_model->getData($params_data2);
        if (is_array($result_ti_data) && count($result_ti_data)>0) {
            $result['success'] = 1;
            foreach ($result_ti_data as $i => $ti_result) {
                $result['data'][] = $ti_result['ti_number'];
                $result['origin'][$ti_result['ti_number']] = $ti_result['warehouse_from_name'];
                $result['destination'][$ti_result['ti_number']] = $ti_result['wh_group_name'];
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

            if (!isset($params['notes']) && empty($params['warehouse_id'])) {
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
                                    // update available_qty
                                    if ($po_item->available_qty > 0) {
                                        $po_item->available_qty = $po_item->available_qty - $quantity;
                                        $po_item->updated_at = date("Y-m-d H:i:s");
                                        $po_item->updated_by = $model->created_by;
                                        $update_po_item = \Model\PurchaseOrderItemsModel::model()->update($po_item);
                                    }
                                }
                            }
                        }
                    }

                    $pomodel = \Model\PurchaseOrdersModel::model()->findByPk($data['po_id']);
                    if ($pomodel->status !== \Model\PurchaseOrdersModel::STATUS_COMPLETED) {
                        $po_mdl = new \Model\PurchaseOrdersModel();
                        $available_items = $po_mdl->available_items(['po_id' => $pomodel->id ]);
                        if (!is_array($available_items) || empty($available_items) || count($available_items) <= 0) {
                            $pomodel->status = \Model\PurchaseOrdersModel::STATUS_COMPLETED;
                            $pomodel->updated_at = date("Y-m-d H:i:s");
                            $pomodel->updated_by = $model->created_by;

                            $update_status = \Model\PurchaseOrdersModel::model()->update($pomodel);
                        }
                    }

                    $result = ["success" => 1, "id" => $model->id, "receipt_number" => $model->pr_number];
                    if ($tot_quantity > 0) {
                        // directly add to wh stock
                        try {
                            $add_to_stock = \Pos\Controllers\PurchasesController::_add_to_stock(['pr_id' => $model->id, 'admin_id' => $model->created_by]);
                        } catch (\Exception $e) {
                            $result['message'] = $e->getMessage();
                        }
                    }

                    return $result;
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
                                    // update available_qty
                                    if ($ti_item->available_qty > 0) {
                                        $ti_item->available_qty = $ti_item->available_qty - $quantity;
                                        $ti_item->updated_at = date("Y-m-d H:i:s");
                                        $ti_item->updated_by = $model->created_by;
                                        $update_ti_item = \Model\TransferIssueItemsModel::model()->update($ti_item);
                                    }
                                }
                            }
                        }
                    }
                    $timodel = \Model\TransferIssuesModel::model()->findByPk($data['ti_id']);
                    if ($timodel->status !== \Model\TransferIssuesModel::STATUS_COMPLETED && $tot_quantity == $quantity_max) {
                        $timodel->status = \Model\TransferIssuesModel::STATUS_COMPLETED;
                        $timodel->updated_at = date("Y-m-d H:i:s");
                        $timodel->updated_by = $model->created_by;
                        $timodel->completed_at = date("Y-m-d H:i:s");
                        $timodel->completed_by = $model->created_by;

                        $update_status = \Model\TransferIssuesModel::model()->update($timodel);
                    }

                    $result = ["success" => 1, "id" => $model->id, "receipt_number" => $model->tr_number];
                    if ($tot_quantity > 0) {
                        // directly add to wh stock
                        try {
                            $add_to_stock = \Pos\Controllers\TransfersController::_add_to_stock(['tr_id' => $model->id, 'admin_id' => $model->created_by]);
                        } catch (\Exception $e) {
                            $result['message'] = $e->getMessage();
                        }
                    }

                    return $result;
                }
            } else {
                return ["success" => 0, "message" => "Transfer issue tersebut sudah terkonfirmasi sebelumnya."];
            }
        }
    }

    /**
     * @param $request : admin_id, status
     * @param $response
     * @param $args
     * @return mixed
     */
    public function list_receipt($request, $response, $args)
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
        $params_data = [];
        if (isset($params['status'])) {
            $params_data['status'] = $params['status'];
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

        $pr_model = new \Model\PurchaseReceiptsModel();
        $result_data = $pr_model->getQuery($params_data);
        if (is_array($result_data) && count($result_data)>0) {
            $result['success'] = 1;
            $pri_model = new \Model\PurchaseReceiptItemsModel();
            foreach ($result_data as $i => $pr_result) {
                $result['data'][] = $pr_result['pr_number'];
                $result['detail'][$pr_result['pr_number']] = $pr_result;
                $result['type'][$pr_result['pr_number']] = 'purchase_order';
                $result['items'][$pr_result['pr_number']] = $pri_model->getData($pr_result['id']);
            }
        }

        $tr_model = new \Model\TransferReceiptsModel();
        $result_data2 = $tr_model->getQuery($params_data);
        if (is_array($result_data2) && count($result_data2)>0) {
            $result['success'] = 1;
            $tri_model = new \Model\TransferReceiptItemsModel();
            foreach ($result_data2 as $i => $tr_result) {
                $result['data'][] = $tr_result['tr_number'];
                $result['detail'][$tr_result['tr_number']] = $tr_result;
                $result['type'][$tr_result['tr_number']] = 'transfer_issue';
                $result['items'][$tr_result['tr_number']] = $tri_model->getData($tr_result['id']);
            }
        }

        return $response->withJson($result, 201);
    }
}