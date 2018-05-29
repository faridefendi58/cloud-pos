<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;
use function FastRoute\TestFixtures\empty_options_cached;

class PurchaseController extends BaseController
{
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['POST'], '/create', [$this, 'create']);
        $app->map(['GET'], '/list', [$this, 'get_list']);
        $app->map(['POST'], '/create-shipping', [$this, 'create_shipping']);
        $app->map(['GET'], '/detail', [$this, 'get_detail']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['create', 'list', 'create-shipping', 'detail'],
                'users'=> ['@'],
            ]
        ];
    }

    /**
     * @param $request: admin_id, items, prices, supplier_name, shipment_name, supplier_id,
     * wh_group_name, due_date, is_pre_order
     * @param $response
     * @param $args
     * @return mixed
     */
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
            $purchase_items = [];
            $items = explode("-", $params['items']);
            if (is_array($items)) {
                foreach ($items as $i => $item) {
                    $p_count = explode(",", $item);
                    if (is_array($p_count)) {
                        $purchase_items[$p_count[0]] = (int) $p_count[1];
                    }
                }
            }

            $purchase_prices = [];
            if (isset($params['prices'])) {
                $prices = explode("-", $params['prices']);
                if (is_array($prices)) {
                    foreach ($prices as $i => $price) {
                        $pr_count = explode(",", $price);
                        if (is_array($pr_count)) {
                            $purchase_prices[$pr_count[0]] = (int) $pr_count[1];
                        }
                    }
                }
            }

            if (count($purchase_items) <= 0) {
                $result = ["success" => 0, "message" => "Pastikan pilih item sebelum disimpan."];
                return $response->withJson($result, 201);
            }

            if (isset($params['supplier_name'])) {
                $spmodel = \Model\SuppliersModel::model()->findByAttributes(['name' => $params['supplier_name']]);
                if ($spmodel instanceof \RedBeanPHP\OODBBean) {
                    $params['supplier_id'] = $spmodel->id;
                }
            }

            if (isset($params['shipment_name'])) {
                $shmodel = \Model\ShipmentsModel::model()->findByAttributes(['title' => $params['shipment_name']]);
                if ($shmodel instanceof \RedBeanPHP\OODBBean) {
                    $params['shipment_id'] = $shmodel->id;
                }
            }

            if (empty($params['supplier_id']) || empty($params['shipment_id'])) {
                $result = ["success" => 0, "message" => "Supplier atau Cara pengiriman tidak boleh kosong."];
                return $response->withJson($result, 201);
            }

            if (isset($params['wh_group_name'])) {
                $whgmodel = \Model\WarehouseGroupsModel::model()->findByAttributes(['title' => $params['wh_group_name']]);
                if ($whgmodel instanceof \RedBeanPHP\OODBBean) {
                    $params['wh_group_id'] = $whgmodel->id;
                }
            }

            if (isset($params['due_date'])) {
                $params['due_date'] = date("Y-m-d H:i:s", strtotime($params['due_date']));
            }

            if (isset($params['is_pre_order'])) {
                if ($params['is_pre_order'] == 'true' || (int)$params['is_pre_order'] == 1 || $params['is_pre_order'] == true) {
                    $params['is_pre_order'] = 1;
                } else {
                    $params['is_pre_order'] = 0;
                }
            }

            $model = new \Model\PurchaseOrdersModel();
            $po_number = \Pos\Controllers\PurchasesController::get_po_number();
            $model->po_number = $po_number['serie_nr'];
            $model->po_serie = $po_number['serie'];
            $model->po_nr = $po_number['nr'];
            $model->price_netto = 0;
            if (isset($params['supplier_id']))
                $model->supplier_id = $params['supplier_id'];
            $model->date_order = date("Y-m-d H:i:s");
            if (isset($params['due_date'])) {
                $model->due_date = $params['due_date'];
            }
            if (isset($params['shipment_id']))
                $model->shipment_id = $params['shipment_id'];
            if (isset($params['wh_group_id']))
                $model->wh_group_id = $params['wh_group_id'];
            if (isset($params['is_pre_order']) && $params['is_pre_order'] > 0) {
                $model->is_pre_order = $params['is_pre_order'];
                $model->status = \Model\PurchaseOrdersModel::STATUS_PENDING;
            } else {
                $model->status = \Model\PurchaseOrdersModel::STATUS_ON_PROCESS;
            }
            if (isset($params['notes']))
                $model->notes = $params['notes'];
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = (isset($params['admin_id'])) ? $params['admin_id'] : 1;
            $save = \Model\PurchaseOrdersModel::model()->save(@$model);
            if ($save) {
                $tot_price = 0;
                foreach ($purchase_items as $product_id => $quantity) {
                    $product = \Model\ProductsModel::model()->findByPk($product_id);
                    $imodel[$product_id] = new \Model\PurchaseOrderItemsModel();
                    $imodel[$product_id]->po_id = $model->id;
                    $imodel[$product_id]->product_id = $product_id;
                    $imodel[$product_id]->title = $product->title;
                    $imodel[$product_id]->quantity = $quantity;
                    $imodel[$product_id]->available_qty = $quantity;
                    $imodel[$product_id]->unit = $product->unit;
                    if (isset($purchase_prices[$product_id]))
                        $imodel[$product_id]->price = $purchase_prices[$product_id];
                    else
                        $imodel[$product_id]->price = $product->current_cost;
                    $imodel[$product_id]->created_at = date("Y-m-d H:i:s");
                    $imodel[$product_id]->created_by = $model->created_by;

                    if ($product_id > 0 && $imodel[$product_id]->quantity > 0) {
                        $save2 = \Model\PurchaseOrderItemsModel::model()->save($imodel[$product_id]);
                        if ($save2) {
                            $tot_price = $tot_price + ($imodel[$product_id]->price * $quantity);
                        }
                    }
                }

                // updating price of po data
                if ($tot_price > 0) {
                    $pomodel = \Model\PurchaseOrdersModel::model()->findByPk($model->id);
                    $pomodel->price_netto = $tot_price;
                    $update = \Model\PurchaseOrdersModel::model()->update($pomodel);

                    $result = [
                        "success" => 1,
                        "id" => $model->id,
                        'message' => 'Data berhasi disimpan.',
                        "issue_number" => $model->po_number
                    ];
                } else {
                    $result = ["success" => 0, "message" => "Tidak ada item yang dapat disimpan."];
                }
            } else {
                $result = [
                    "success" => 0,
                    "message" => \Model\PurchaseOrdersModel::model()->getErrors(false, false, false)
                ];
            }
        }

        if ($result['success'] > 0) {
            // send notification to related user
            $params2 = [
                'rel_type' => \Model\NotificationsModel::TYPE_PURCHASE_ORDER,
                'rel_id' => $model->id
            ];
            if ($model->is_pre_order > 0 && $model->status == \Model\PurchaseOrdersModel::STATUS_PENDING) {
                $sp_pic = new \Model\SupplierPicsModel();
                $supliers = $sp_pic->getData(['supplier_id' => $model->supplier_id]);
                $supplier_pic = [];
                if (is_array($supliers) && count($supliers) > 0) {
                    foreach ($supliers as $j => $spl) {
                        $supplier_pic[$spl['admin_id']] = $spl['admin_name'];
                    }
                }

                if ($model->wh_group_id > 0) {
                    $whg_model = \Model\WarehouseGroupsModel::model()->findByPk($model->wh_group_id);
                    if ($whg_model instanceof \RedBeanPHP\OODBBean && !empty($whg_model->pic)) {
                        $params2['recipients'] = array_keys(json_decode($whg_model->pic, true));
                        $po_model = new \Model\PurchaseOrdersModel();
                        $po_detail = $po_model->getDetail($model->id);
                        $params2['message'] = "Ada PO (Purchase Order) baru ".$po_detail['po_number']." untuk WH area ". $whg_model->title." ";
                        $params2['message'] .= "yang dipesan oleh ".$po_detail['created_by_name'];
                        if (!empty($po_detail['due_date'])) {
                            $params2['message'] .= " untuk tanggal ". date("d F Y", strtotime($po_detail['due_date'])).".";
                        }
                        if (count($supplier_pic) > 0) {
                            $sp_pic = implode(" atau ", $supplier_pic);
                            $params2['message'] .= " Menunggu konfirmasi dari ". $sp_pic;
                        }

                        $params2['issue_number'] = $po_detail['po_number'];
                        $params2['rel_activity'] = 'DeliveryActivity';
                        //send to wh pic
                        $this->_sendNotification($params2);
                    }
                }

                $params2['recipients'] = []; // empty the recipients
                if (count($supplier_pic) > 0) {
                    $params2['recipients'] = array_keys($supplier_pic);
                    $po_model = new \Model\PurchaseOrdersModel();
                    $po_detail = $po_model->getDetail($model->id);
                    $params2['message'] = "Ada PO (Purchase Order) baru ".$po_detail['po_number']." ";
                    if ($whg_model instanceof \RedBeanPHP\OODBBean) {
                        $params2['message'] .= " untuk WH area ". $whg_model->title." ";
                    }
                    $params2['message'] .= "yang dipesan oleh ".$po_detail['created_by_name'];
                    if (!empty($po_detail['due_date'])) {
                        $params2['message'] .= " untuk tanggal ". date("d F Y", strtotime($po_detail['due_date'])).".";
                    }
                    $params2['message'] .= " Mohon segera ditindaklanjuti.";

                    $params2['issue_number'] = $po_detail['po_number'];
                    $params2['rel_activity'] = 'DeliveryActivity';
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
        $po_model = new \Model\PurchaseOrdersModel();
        $params = $request->getParams();
        $status = \Model\PurchaseOrdersModel::STATUS_ON_PROCESS;
        $params_data = ['status' => $status];
        if (isset($params['status'])) {
            $params_data['status'] = $params['status'];
        }

        if (isset($params['all_status'])) {
            unset($params_data['status']);
        }

        if (isset($params['is_pre_order'])) {
            $params_data['is_pre_order'] = $params['is_pre_order'];
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
            $sp_pic = new \Model\SupplierPicsModel();
            $supliers = $sp_pic->getData(['admin_id' => $params['admin_id']]);
            $suplier_id = [];
            if (is_array($supliers) && count($supliers) > 0) {
                foreach ($supliers as $j => $spl) {
                    $suplier_id[$spl['supplier_id']] = $spl['supplier_id'];
                }
            }
            if (count($suplier_id) > 0) {
                $params_data['supplier_id'] = $suplier_id;
            }
        }

        $result_data = $po_model->getData($params_data);
        if (is_array($result_data) && count($result_data)>0) {
            $result['success'] = 1;
            foreach ($result_data as $i => $po_result) {
                $result['data'][] = $po_result['po_number'];
                $result['origin'][$po_result['po_number']] = $po_result['supplier_name'];
                $result['destination'][$po_result['po_number']] = $po_result['wh_group_name'];
                $result['detail'][] = $po_result;
            }
        }

        return $response->withJson($result, 201);
    }

    /**
     * @param $request : admin_id, issue_number, shipment_name, resi_number, shipping_date
     * @param $response
     * @param $args
     * @return mixed
     */
    public function create_shipping($request, $response, $args)
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
        if (is_array($params) && in_array('issue_number', array_keys($params))) {
            $model = \Model\PurchaseOrdersModel::model()->findByAttributes(['po_number' => $params['issue_number']]);
            if ($model instanceof \RedBeanPHP\OODBBean) {
                if (isset($params['shipment_name'])) {
                    $shmodel = \Model\ShipmentsModel::model()->findByAttributes(['title' => $params['shipment_name']]);
                    if ($shmodel instanceof \RedBeanPHP\OODBBean) {
                        $model->shipment_id = $shmodel->id;
                    }
                }
                $model->status = \Model\PurchaseOrdersModel::STATUS_ON_PROCESS;
                $model->updated_at = date("Y-m-d H:i:s");
                $model->updated_by = $params['admin_id'];
                $update = \Model\PurchaseOrdersModel::model()->update($model);
                if ($update) {
                    $result['success'] = 1;
                    // create delivery order
                    $do_params = [
                        'po_id' => $model->id,
                        'admin_id' => $params['admin_id']
                    ];
                    if (isset($params['resi_number'])) {
                        $do_params['resi_number'] = $params['resi_number'];
                    }
                    if (isset($params['shipping_date'])) {
                        $do_params['shipping_date'] = date("Y-m-d H:i:s", strtotime($params['shipping_date']));
                    }
                    if (isset($params['notes'])) {
                        $do_params['notes'] = $params['notes'];
                    }
                    $do_id = $this->create_delivery_order($do_params);
                    if ($model->wh_group_id > 0 && $do_id > 0) {
                        $do_model = new \Model\DeliveryOrdersModel();
                        $do_detail = $do_model->getDetail($do_id);
                        $whg_model = \Model\WarehouseGroupsModel::model()->findByPk($model->wh_group_id);
                        if ($whg_model instanceof \RedBeanPHP\OODBBean && !empty($whg_model->pic)) {
                            $params['recipients'] = array_keys(json_decode($whg_model->pic, true));
                            $po_model = new \Model\PurchaseOrdersModel();
                            $po_detail = $po_model->getDetail($model->id);
                            $params['message'] = "PO (Purchase Order) ".$po_detail['po_number']." untuk WH area ". $whg_model->title." ";
                            $params['message'] .= "yang dipesan oleh ".$po_detail['created_by_name'];
                            $params['message'] .= " telah dikirim oleh ". $do_detail['created_by_name'] ." pada tanggal ". date("d F Y", strtotime($do_detail['shipping_date'])) ." melalui ". $po_detail['shipment_name'] .".";
                            if (!empty($do_detail['resi_number'])) {
                                $params['message'] .= " Nomor Resi : ".$do_detail['resi_number'];
                            }
                            $params['rel_id'] = $model->id;
                            $params['rel_type'] = \Model\NotificationsModel::TYPE_PURCHASE_ORDER;
                            $params['issue_number'] = $po_detail['po_number'];
                            $params['rel_activity'] = 'PurchaseActivity';
                            $this->_sendNotification($params);
                            $result['message'] = $params['message'];
                        }
                    }
                } else {
                    $result['success'] = 0;
                    $result['message'] = 'Gagal memperbarui data PO.';
                }
            } else {
                $result['success'] = 0;
                $result['message'] = 'PO tidak ditemukan.';
            }
        }

        return $response->withJson($result, 201);
    }

    /**
     * @param $data : po_id, resi_number, shipping_date, admin_id
     * @return bool|mixed
     */
    public function create_delivery_order($data)
    {
        if (!isset($data['po_id'])) {
            return false;
        }

        $model = new \Model\DeliveryOrdersModel();
        $model->po_id = $data['po_id'];
        $do_number = \Pos\Controllers\PurchasesController::get_do_number();
        $model->do_number = $do_number['serie_nr'];
        $model->do_serie = $do_number['serie'];
        $model->do_nr = $do_number['nr'];
        if (isset($data['resi_number'])) {
            $model->resi_number = $data['resi_number'];
        }
        if (isset($data['shipping_date'])) {
            $model->shipping_date = $data['shipping_date'];
        } else {
            $model->shipping_date = date("Y-m-d H:i:s");
        }

        if (isset($data['notes'])) {
            $model->notes = $data['notes'];
        }

        $model->created_at = date("Y-m-d H:i:s");
        $model->created_by = (isset($data['admin_id'])) ? $data['admin_id'] : 1;
        $save = \Model\DeliveryOrdersModel::model()->save(@$model);
        if ($save) {
            return $model->id;
        }

        return false;
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
            $po_model = \Model\PurchaseOrdersModel::model()->findByAttributes(['po_number'=>$params['issue_number']]);
            if ($po_model instanceof \RedBeanPHP\OODBBean) {
                $model = new \Model\PurchaseOrdersModel();
                // po data
                $data = $model->getDetail($po_model->id);
                // po history
                $history = [
                    [
                        'title' => $data['po_number'].' diterbitkan oleh '.$data['created_by_name'],
                        'date' => date("d M Y H:i", strtotime($data['created_at'])),
                        'data' => array(),
                        'notes' => '',
                    ]
                ];

                $d_model = new \Model\DeliveryOrdersModel();
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
                }

                //end of history
                if ($data['status'] == \Model\PurchaseOrdersModel::STATUS_COMPLETED) {
                    $complete_data = [
                        'title' => $data['po_number']." sudah selesai.",
                        'date' => date("d M Y H:i", strtotime($data['completed_at'])),
                        'data' => array(),
                        'notes' => ''
                    ];
                    array_push($history, $complete_data);
                }

                //set the items
                $it_model = new \Model\PurchaseOrderItemsModel();
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
