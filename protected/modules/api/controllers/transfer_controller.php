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
        $app->map(['GET'], '/detail', [$this, 'get_detail']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['create', 'list', 'detail'],
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

            if (isset($params['wh_group_name'])) {
                $whgmodel = \Model\WarehouseGroupsModel::model()->findByAttributes(['title' => $params['wh_group_name']]);
                if ($whgmodel instanceof \RedBeanPHP\OODBBean) {
                    $params['wh_group_id'] = $whgmodel->id;
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
            if (isset($params['wh_group_id']))
                $model->wh_group_id = $params['wh_group_id'];
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

        if ($result['success'] > 0) {
            // send notification to related user
            $params2 = [
                'rel_type' => \Model\NotificationsModel::TYPE_TRANSFER_ISSUE,
                'rel_id' => $model->id
            ];

            if ($model->wh_group_id > 0) {
                $whg_model = \Model\WarehouseGroupsModel::model()->findByPk($model->wh_group_id);
                if ($whg_model instanceof \RedBeanPHP\OODBBean && !empty($whg_model->pic)) {
                    $params2['recipients'] = array_keys(json_decode($whg_model->pic, true));
                    $po_model = new \Model\TransferIssuesModel();
                    $po_detail = $po_model->getDetail($model->id);
                    $params2['message'] = "Ada Perpindahan Stok ".$po_detail['ti_number']." untuk WH area ". $whg_model->title." ";
                    $params2['message'] .= "yang dipesan oleh ".$po_detail['created_by_name'];
                    if (!empty($po_detail['due_date'])) {
                        $params2['message'] .= " untuk tanggal ". date("d F Y", strtotime($po_detail['due_date'])).".";
                    }

                    $params2['issue_number'] = $po_detail['ti_number'];
                    $params2['rel_activity'] = 'TransferActivity';
                    //send to wh pic
                    $this->_sendNotification($params2);
                }
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

    public function get_detail($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);

        if (!$isAllowed['allow']) {
            $result = [
                'success' => 0,
                'message' => $isAllowed['message'],
            ];
            return $response->withJson($result, 201);
        }

        $result = [ 'success' => 0 ];
        $params = $request->getParams();
        if (isset($params['issue_number'])) {
            $po_model = \Model\TransferIssuesModel::model()->findByAttributes(['ti_number'=>$params['issue_number']]);
            if ($po_model instanceof \RedBeanPHP\OODBBean) {
                $model = new \Model\TransferIssuesModel();
                // po data
                $data = $model->getDetail($po_model->id);
                // po history
                $history = [
                    [
                        'title' => $data['ti_number'].' diterbitkan oleh '.$data['created_by_name'],
                        'date' => date("d M Y H:i", strtotime($data['created_at'])),
                        'data' => array(),
                        'notes' => '',
                    ]
                ];

                /*$d_model = new \Model\DeliveryOrdersModel();
                $delivery = $d_model->getData(['po_id' => $po_model->id]);
                if (is_array($delivery) && is_array($delivery[0])) {
                    $supplier_data = [
                        'title' => 'Order diterima oleh supplier',
                        'date' => date("d M Y H:i", strtotime($delivery[0]['created_at'])),
                        'detail' => $delivery[0],
                        'data' => array(),
                        'notes' => 'Diterima dan diproses oleh '.$delivery[0]['admin_name']
                    ];
                    array_push($history, $supplier_data);

                    $shipping_data = [
                        'title' => 'Barang dikirim oleh '.$delivery[0]['admin_name'].' melalui '.$data['shipment_name'],
                        'date' => date("d M Y", strtotime($delivery[0]['shipping_date'])),
                        'detail' => $delivery[0],
                        'data' => array(),
                        'notes' => ''
                    ];
                    if (isset($delivery[0]['resi_number'])) {
                        $shipping_data['notes'] = 'Nomor resi '.$delivery[0]['resi_number'];
                    } else {
                        $shipping_data['notes'] = 'Kode pengiriman '.$delivery[0]['do_number'];
                    }

                    array_push($history, $shipping_data);

                    if ($data['received_at']) {
                        $rc_data = [
                            'title' => 'Barang diterima oleh '.$data['received_by_name'],
                            'date' => date("d M Y", strtotime($data['received_at'])),
                            'data' => array(),
                            'notes' => $data['notes']
                        ];
                        array_push($history, $rc_data);
                    }

                    $prc_model = new \Model\PurchaseReceiptsModel();
                    $prc_datas = $prc_model->getData(['po_id' => $po_model->id]);
                    if (is_array($prc_datas) && count($prc_datas)>0) {
                        for ($i = 0; $i < count($prc_datas); $i++) {
                            $prci_model = new \Model\PurchaseReceiptItemsModel();

                            $prc_data = [
                                'title' => 'Stok diterima oleh warehouse '.$prc_datas[$i]['warehouse_name'],
                                'date' => date("d M Y H:i", strtotime($prc_datas[$i]['created_at'])),
                                'data' => $prc_datas[$i]
                            ];

                            $prc_items = $prci_model->getData($prc_datas[$i]['id']);
                            $items_titles = [];
                            if (is_array($prc_items)) {
                                foreach ($prc_items as $j => $prc_item) {
                                    $items_titles[] = $prc_item['title'].' '.$prc_item['quantity'].' '.$prc_item['unit'];
                                }
                                $prc_data['data']['items'] = $prc_items;
                            }

                            $items_title = implode(", ", $items_titles);

                            if (!empty($items_title)) {
                                $prc_data['notes'] = 'Rincian penerimaan : '.$items_title;
                            } else {
                                $prc_data['notes'] = '';
                            }
                            array_push($history, $prc_data);
                        }
                    }
                }*/

                //end of history
                if ($data['status'] == \Model\TransferIssuesModel::STATUS_COMPLETED) {
                    $complete_data = [
                        'title' => $data['ti_number']." sudah selesai.",
                        'date' => date("d M Y H:i", strtotime($data['completed_at'])),
                        'data' => array(),
                        'notes' => ''
                    ];
                    array_push($history, $complete_data);
                }

                //set the items
                $it_model = new \Model\TransferIssueItemsModel();
                $po_items = $it_model->getData($po_model->id);

                $result['success'] = 1;
                $result['data'] = $data;
                $result['history'] = $history;
                $result['items'] = $po_items;
            } else {
                $result['message'] = 'Nomor issue tidak ditemukan.';
            }
        }

        return $response->withJson($result, 201);
    }
}