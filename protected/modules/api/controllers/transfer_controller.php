<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;
use PHPMailer\PHPMailer\Exception;

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
        $app->map(['POST'], '/create-v2', [$this, 'create_v2']);
        $app->map(['POST', 'GET'], '/create-receipt', [$this, 'create_receipt']);
        $app->map(['GET'], '/history', [$this, 'get_history']);
        $app->map(['GET'], '/history-detail', [$this, 'get_history_detail']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['create', 'list', 'detail', 'create-v2', 'create-receipt', 'history', 'history-detail'],
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
                    $imodel[$product_id]->available_qty = $quantity;
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

            //remove git stock if transfered from git
            if ($model->warehouse_from == 0 || empty($model->warehouse_from)) {
                try {
                    $substract_stock = true;
                    $dr_model = new \Model\DeliveryReceiptItemsModel();
                    foreach ($transfer_items as $product_id => $quantity) {
                        $substract_stock &= $dr_model->subtracting_stok($product_id, $quantity);
                    }
                } catch (Exception $e) {}
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

        if (isset($params['warehouse_from'])) {
            $params_data['warehouse_from'] = $params['warehouse_from'];
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

	public function create_v2($request, $response, $args)
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
			if (isset($params['warehouse_from'])) {
                $params['warehouse_from'] = $params['warehouse_from'];
            }

            if (isset($params['warehouse_id'])) {
                $params['warehouse_from'] = $params['warehouse_id'];
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
                $tot_price = 0; $desc = [];
                foreach ($params['items'] as $i => $item) {
					$product = \Model\ProductsModel::model()->findByPk($item['barcode']);
					$product_id = $product->id;
					$quantity = $item['quantity'];
                    $imodel[$product_id] = new \Model\TransferIssueItemsModel();
                    $imodel[$product_id]->ti_id = $model->id;
                    $imodel[$product_id]->product_id = $product_id;
                    $imodel[$product_id]->title = $product->title;
                    $imodel[$product_id]->quantity = $quantity;
                    $imodel[$product_id]->available_qty = $quantity;
                    $imodel[$product_id]->unit = $product->unit;
                    $imodel[$product_id]->price = $product->current_cost;
                    $imodel[$product_id]->created_at = date("Y-m-d H:i:s");
                    $imodel[$product_id]->created_by = $model->created_by;

                    if ($product_id > 0 && $imodel[$product_id]->quantity > 0) {
                        $save2 = \Model\TransferIssueItemsModel::model()->save($imodel[$product_id]);
                        if ($save2) {
                            $tot_price = $tot_price + ($product->current_cost * $quantity);
							$desc[] = $product->title .' : -'. $quantity;
                        }
                    }
                }

                // updating price of po data
                if ($tot_price > 0) {
                    $pomodel = \Model\TransferIssuesModel::model()->findByPk($model->id);
                    $pomodel->base_price = $tot_price;
                    $update = \Model\TransferIssuesModel::model()->update($pomodel);

					// add logs
					try {
						$act_model = new \Model\ActivitiesModel();
						$act_model->title = 'Stok Keluar '. $model->ti_number;
						$act_model->rel_id = $model->id;
						$act_model->type = \Model\ActivitiesModel::TYPE_TRANSFER_ISSUE;
						if (is_array($desc)) {
									$act_model->description = implode(", ", $desc);
									if (!empty($params['admin_id'])) {
										$ad_model = \Model\AdminModel::model()->findByPk($params['admin_id']);
										if ($ad_model instanceof \RedBeanPHP\OODBBean) {
											$act_model->description .= '. Submited By '. $ad_model->name;
										}
									}
						}
						if (isset($params['warehouse_from'])) {
							$act_model->warehouse_id = $params['warehouse_from'];
						}
						$act_model->created_at = date("Y-m-d H:i:s");
						$act_model->created_by = (isset($params['admin_id'])) ? $params['admin_id'] : 1;
						$save_act = \Model\ActivitiesModel::model()->save($act_model);
					} catch (\Exception $e) {
						$result['message'] = $e->getMessage();
					}
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

            /*if ($model->wh_group_id > 0) {
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
            }*/

            //remove git stock if transfered from git
            if ($model->warehouse_from == 0 || empty($model->warehouse_from)) {
                try {
                    $substract_stock = true;
                    $dr_model = new \Model\DeliveryReceiptItemsModel();
                    foreach ($transfer_items as $product_id => $quantity) {
                        $substract_stock &= $dr_model->subtracting_stok($product_id, $quantity);
                    }
                } catch (Exception $e) {}
            }
        }

        return $response->withJson($result, 201);
    }

	public function create_receipt($request, $response, $args)
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
			$ti_new_mod = new \Model\TransferIssuesModel();
			$ti_id = $ti_new_mod->getDataByWarehouse(['warehouse_from' => $params['warehouse_from'], 'warehouse_to' => $params['warehouse_to']]);
			if (!empty($ti_id)) {
				$ti_model = \Model\TransferIssuesModel::model()->findByPk($ti_id);;
			} else {
				$ti_id = $this->raiseNewIssue($params);
				$ti_model = \Model\TransferIssuesModel::model()->findByPk($ti_id);
			}

			if ($ti_model instanceof \RedBeanPHP\OODBBean) {
				if (!isset($params['warehouse_id'])) {
					$params['warehouse_id'] = $ti_model->warehouse_to;
				}

				$model = new \Model\TransferReceiptsModel();
				$tr_number = \Pos\Controllers\TransfersController::get_tr_number();
				$model->ti_id = $ti_model->id;
				$model->tr_number = $tr_number['serie_nr'];
				$model->tr_serie = $tr_number['serie'];
				$model->tr_nr = $tr_number['nr'];
				$model->warehouse_id = $params['warehouse_id'];
				$model->effective_date = date("Y-m-d H:i:s");
				$model->status = \Model\TransferReceiptsModel::STATUS_PENDING;
				if (isset($params['notes']))
					$model->notes = $params['notes'];
				$model->created_at = date("Y-m-d H:i:s");
				$model->created_by = (isset($params['admin_id'])) ? $params['admin_id'] : 1;
				$model->updated_at = date("Y-m-d H:i:s");
				$model->updated_by = (isset($params['admin_id'])) ? $params['admin_id'] : 1;
				$model->completed_at = date("Y-m-d H:i:s");
				$model->completed_by = (isset($params['admin_id'])) ? $params['admin_id'] : 1;
				$save = \Model\TransferReceiptsModel::model()->save(@$model);
				if ($save) {
					$tot_price = 0; $desc = [];
					foreach ($params['items'] as $i => $item) {
							$product = \Model\ProductsModel::model()->findByPk($item['barcode']);
							$product_id = $product->id;
							$quantity = $item['quantity'];
							$ti_item = \Model\TransferIssueItemsModel::model()->findByAttributes(['ti_id' => $ti_model->id, 'product_id' => $product_id]);

				            $imodel[$product_id] = new \Model\TransferReceiptItemsModel();
				            $imodel[$product_id]->tr_id = $model->id;
							if ($ti_item instanceof \RedBeanPHP\OODBBean) {
								$imodel[$product_id]->ti_item_id = $ti_item->id;
								$imodel[$product_id]->quantity_max = $ti_item->quantity;
							} else {
								$imodel[$product_id]->quantity_max = $ti_item->quantity;
							}
				            $imodel[$product_id]->product_id = $product_id;
				            $imodel[$product_id]->title = $product->title;
				            $imodel[$product_id]->quantity = $quantity;
				            $imodel[$product_id]->unit = $product->unit;
				            $imodel[$product_id]->price = $product->current_cost;
				            $imodel[$product_id]->created_at = date("Y-m-d H:i:s");
				            $imodel[$product_id]->created_by = $model->created_by;

				            if ($product_id > 0 && $imodel[$product_id]->quantity > 0) {
				                $save2 = \Model\TransferReceiptItemsModel::model()->save($imodel[$product_id]);
				                if ($save2) {
				                    $tot_price = $tot_price + ($product->current_cost * $quantity);
									$desc[] = $product->title .' : '. $quantity;
				                }
				            }
					}

					// updating price of ti data
					if ($tot_price > 0) {
						// directly add to wh stock
                        try {
                            $add_to_stock = \Pos\Controllers\TransfersController::_add_to_stock(['tr_id' => $model->id, 'admin_id' => $model->created_by]);
                        } catch (\Exception $e) {
                            $result['message'] = $e->getMessage();
                        }

						// add logs
						try {
                            $act_model = new \Model\ActivitiesModel();
							$act_model->title = 'Stok Masuk '. $model->tr_number;
							$act_model->rel_id = $model->id;
							$act_model->type = \Model\ActivitiesModel::TYPE_TRANSFER_RECEIPT;
							if (is_array($desc)) {
								$act_model->description = implode(", ", $desc);
								if (!empty($params['admin_id'])) {
									$ad_model = \Model\AdminModel::model()->findByPk($params['admin_id']);
									if ($ad_model instanceof \RedBeanPHP\OODBBean) {
										$act_model->description .= '. Submited By '. $ad_model->name;
									}
								}
							}
							if (isset($params['warehouse_id'])) {
								$act_model->warehouse_id = $params['warehouse_id'];
							}
							$act_model->created_at = date("Y-m-d H:i:s");
							$act_model->created_by = (isset($params['admin_id'])) ? $params['admin_id'] : 1;
							$save_act = \Model\ActivitiesModel::model()->save($act_model);
                        } catch (\Exception $e) {
                            $result['message'] = $e->getMessage();
                        }

						$result = [
							"success" => 1,
							"id" => $model->id,
							'message' => 'Data berhasil disimpan.',
							"issue_number" => $model->tr_number
						];
					} else {
						$result = ["success" => 0, "message" => "Tidak ada item yang dapat disimpan."];
					}
				} else {
					$result = [
						"success" => 0,
						"message" => \Model\TransferReceiptsModel::model()->getErrors(false, false, false)
						];
				}	
			}
		}
		return $response->withJson($result, 201);
	}

	private function raiseNewIssue($params = []) {
		if (isset($params['items'])) {
			if (isset($params['warehouse_from'])) {
                $params['warehouse_from'] = $params['warehouse_from'];
            } else {
		        if (isset($params['warehouse_id'])) {
		            $params['warehouse_from'] = $params['warehouse_id'];
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
                $tot_price = 0; $desc = [];
                foreach ($params['items'] as $i => $item) {
					$product = \Model\ProductsModel::model()->findByPk($item['barcode']);
					$product_id = $product->id;
					$quantity = $item['quantity'];
                    $imodel[$product_id] = new \Model\TransferIssueItemsModel();
                    $imodel[$product_id]->ti_id = $model->id;
                    $imodel[$product_id]->product_id = $product_id;
                    $imodel[$product_id]->title = $product->title;
                    $imodel[$product_id]->quantity = $quantity;
                    $imodel[$product_id]->available_qty = $quantity;
                    $imodel[$product_id]->unit = $product->unit;
                    $imodel[$product_id]->price = $product->current_cost;
                    $imodel[$product_id]->created_at = date("Y-m-d H:i:s");
                    $imodel[$product_id]->created_by = $model->created_by;

                    if ($product_id > 0 && $imodel[$product_id]->quantity > 0) {
                        $save2 = \Model\TransferIssueItemsModel::model()->save($imodel[$product_id]);
                        if ($save2) {
                            $tot_price = $tot_price + ($product->current_cost * $quantity);
							$desc[] = $product->title .' : -'. $quantity;
                        }
                    }
                }

                // updating price of po data
                if ($tot_price > 0) {
                    $pomodel = \Model\TransferIssuesModel::model()->findByPk($model->id);
                    $pomodel->base_price = $tot_price;
                    $update = \Model\TransferIssuesModel::model()->update($pomodel);

					// add logs
					try {
						$act_model = new \Model\ActivitiesModel();
						$act_model->title = 'Stok Keluar '. $model->ti_number;
						$act_model->rel_id = $model->id;
						$act_model->type = \Model\ActivitiesModel::TYPE_TRANSFER_ISSUE;
								if (is_array($desc)) {
									$act_model->description = implode(", ", $desc);
									if (!empty($params['admin_id'])) {
										$ad_model = \Model\AdminModel::model()->findByPk($params['admin_id']);
										if ($ad_model instanceof \RedBeanPHP\OODBBean) {
											$act_model->description .= '. Submited By '. $ad_model->name;
										}
									}
								}
						if (isset($params['warehouse_from'])) {
							$act_model->warehouse_id = $params['warehouse_from'];
						}
						$act_model->created_at = date("Y-m-d H:i:s");
						$act_model->created_by = (isset($params['admin_id'])) ? $params['admin_id'] : 1;
						$save_act = \Model\ActivitiesModel::model()->save($act_model);
					} catch (\Exception $e) {
						$result['message'] = $e->getMessage();
					}

					return $model->id;
                } else {
                    return 0;
                }
            } else {
                return 0;
            }
        }

		return 0;
	}

	public function get_history($request, $response, $args)
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

		$model = new \Model\ActivitiesModel();
        $result_data = $model->getData($params);
        if (is_array($result_data) && count($result_data)>0) {
            $result['success'] = 1;
            $result['data'] = $result_data;
        }

        return $response->withJson($result, 201);
	}

	public function get_history_detail($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);

        if (!$isAllowed['allow']) {
            $result = [
                'success' => 0,
                'message' => $isAllowed['message'],
            ];
            return $response->withJson($result, 201);
        }

        $result = ['success' => 0];
        $params = $request->getParams();

		$model = new \Model\ActivitiesModel();
		if (isset($params['issue_id'])) {
			$params['rel_id'] = $params['issue_id'];
		}
        $result_data = $model->getItem($params);
        if (!empty($result_data)) {
            $result['success'] = 1;
			if ($result_data['type'] == \Model\ActivitiesModel::TYPE_TRANSFER_ISSUE) {
				$mdl = new \Model\TransferIssuesModel();
				$result['data'] = $mdl->getDetail($result_data['rel_id']);
				$itm = new \Model\TransferIssueItemsModel();
				$result['items'] = $itm->getData($result_data['rel_id']);
			} elseif ($result_data['type'] == \Model\ActivitiesModel::TYPE_INVENTORY_ISSUE) {
				$mdl = new \Model\TransferIssuesModel();
				$result['data'] = $mdl->getDetail($result_data['rel_id']);
			} elseif ($result_data['type'] == \Model\ActivitiesModel::TYPE_TRANSFER_RECEIPT) {
				$mdl = new \Model\TransferReceiptsModel();
				$result['data'] = $mdl->getDetail($result_data['rel_id']);
			}
        }

        return $response->withJson($result, 201);
	}
}
