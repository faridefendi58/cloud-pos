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
        $app->map(['POST', 'GET'], '/incoming', [$this, 'get_incoming_transfer']);
        $app->map(['POST', 'GET'], '/outgoing', [$this, 'get_outgoing_transfer']);
        $app->map(['POST', 'GET'], '/in-out-update', [$this, 'get_in_out_update']);
        $app->map(['POST'], '/history-delete/[{id}]', [$this, 'get_delete_history']);
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

            //remove git stock if transfered from git
            /*if ($model->warehouse_from == 0 || empty($model->warehouse_from)) {
                try {
                    $substract_stock = true;
                    $dr_model = new \Model\DeliveryReceiptItemsModel();
                    if (count($params['items']) > 0) {
                        foreach ($params['items'] as $i => $item) {
                            $quantity = $item['quantity'];
                            $product_id = $item['barcode'];
                            $substract_stock &= $dr_model->subtracting_stok($product_id, $quantity);
                        }
                    }
                } catch (Exception $e) {}
            }*/
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
		if (array_key_exists('type', $params)) {
			if ($params['type'] == \Model\ActivitiesModel::TYPE_TRANSFER_ISSUE) {
				$params['type'] = [\Model\ActivitiesModel::TYPE_TRANSFER_ISSUE, \Model\ActivitiesModel::TYPE_STOCK_OUT];
			} else if ($params['type'] == \Model\ActivitiesModel::TYPE_TRANSFER_RECEIPT) {
				$params['type'] = [\Model\ActivitiesModel::TYPE_TRANSFER_RECEIPT, \Model\ActivitiesModel::TYPE_STOCK_IN];
			}
		}
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
                // missing data
                if ((array_key_exists('is_update_qty', $result_data['configs']) || !empty($result_data['checked_by'])) && $result_data['status'] == 1) {
                    $suffix = '(OUT '. $result_data['warehouse_from_code'] .')';
                    $r_models = \Model\ActivitiesModel::model()->findAllByAttributes(['group_id' => $result_data['group_id']]);
                    $new_items = [];
                    foreach($r_models as $model2) {
                        if ($model2->id != $result_data['id']) {
                            $suffix2 = '(IN '. $result_data['warehouse_to_code'] .')';
                            $_configs = json_decode($model2->configs, true);
                            foreach ($_configs['items'] as $j => $_item) {
                                $_item2 = $_item;
                                $result_data['configs']['items'][$j]['title'] = $result_data['configs']['items'][$j]['title'].' '. $suffix;
                                $new_items[] = $result_data['configs']['items'][$j];
                                $_item['title'] = $_item['title'].' '. $suffix2;
                                $new_items[] = $_item;
                                $qty1 = $result_data['configs']['items'][$j]['quantity'];
                                $qty2 = $_item['quantity'];
                                if ($qty1 <> $qty2) {
                                    $_item2['title'] = $_item2['title'].' (Miss)';
									$missed = $qty2 - $qty1;
									if ($missed > 0) {
										$missed = -1 * $missed;
									}
                                    $_item2['quantity'] = $missed;
                                    $new_items[] = $_item2;
                                }
                            }
                        }
                    }

                    $result_data['configs']['items'] = $new_items;
                }

				$mdl = new \Model\TransferIssuesModel();
				$detail = $mdl->getDetail($result_data['rel_id']);
				$result['data'] = $result_data;
				$result['data']['issue_number'] = $detail['ti_number'];
				$result['data']['detail'] = $detail;
				$r_models = \Model\ActivitiesModel::model()->findAllByAttributes(['group_id' => $result_data['group_id']]);
				foreach($r_models as $model2) {
					if ($model2->id != $result_data['id']) {
						$mdl2 = new \Model\TransferReceiptsModel();
						$rel_result_data = $mdl2->getDetail($model2->rel_id);
						$result['data']['related'] = $model->getItem(['id' => $model2->id]);
						if ($result['data']['related']['type'] == \Model\ActivitiesModel::TYPE_TRANSFER_RECEIPT) {
							$result['data']['related']['issue_number'] = $rel_result_data['tr_number'];
							$result['data']['related']['detail'] = $rel_result_data;
							if (strlen($result['data']['detail']['notes']) < strlen($result['data']['related']['configs']['notes'])) {
								$result['data']['detail']['notes'] = $result['data']['related']['configs']['notes'];
								$result['data']['configs']['notes'] = $result['data']['related']['configs']['notes'];
							}
						}
					}
				}
			} elseif ($result_data['type'] == \Model\ActivitiesModel::TYPE_INVENTORY_ISSUE) {
				$mdl = new \Model\InventoryIssuesModel();
				$detail = $mdl->getDetail($result_data['rel_id']);
				if (array_key_exists('type', $detail)) {
					$ext_pos = $this->_container->get('settings')['params']['ext_pos'];
					if (!empty($ext_pos)) {
						$ext_pos = json_decode($ext_pos, true);
						if (is_array($ext_pos) && array_key_exists('non_transaction_type', $ext_pos)) {
							$types = $ext_pos['non_transaction_type'];
							if (array_key_exists($detail['type'], $types)) {
								$detail['type_name'] = $types[$detail['type']];
							} else {
								$detail['type_name'] = ucwords(str_replace("_", " ", $detail['type']));
							}
						}
					}
				}
				$result['data']['issue_number'] = $detail['ii_number'];
				$result['data'] = $result_data;
				$result['data']['detail'] = $detail;
			} elseif ($result_data['type'] == \Model\ActivitiesModel::TYPE_TRANSFER_RECEIPT) {
                // missing data
                if ((array_key_exists('is_update_qty', $result_data['configs']) || !empty($result_data['checked_by'])) && $result_data['status'] == 1) {
                    $suffix = '(IN '. $result_data['warehouse_to_code'] .')';
                    $r_models = \Model\ActivitiesModel::model()->findAllByAttributes(['group_id' => $result_data['group_id']]);
                    $new_items = [];
                    foreach($r_models as $model2) {
                        if ($model2->id != $result_data['id']) {
                            $suffix2 = '(OUT '. $result_data['warehouse_from_code'] .')';
                            $_configs = json_decode($model2->configs, true);
                            foreach ($_configs['items'] as $j => $_item) {
                                $_item2 = $_item;
                                $result_data['configs']['items'][$j]['title'] = $result_data['configs']['items'][$j]['title'].' '. $suffix;
                                $new_items[] = $result_data['configs']['items'][$j];
                                $_item['title'] = $_item['title'].' '. $suffix2;
                                $new_items[] = $_item;
                                $qty1 = $result_data['configs']['items'][$j]['quantity'];
                                $qty2 = $_item['quantity'];
                                if ($qty1 <> $qty2) {
                                    $_item2['title'] = $_item2['title'].' (Miss)';
									$missed = $qty2 - $qty1;
									if ($missed > 0) {
										$missed = -1 * $missed;
									}
                                    $_item2['quantity'] = $missed;
                                    $new_items[] = $_item2;
                                }
                            }
                        }
                    }

                    $result_data['configs']['items'] = $new_items;
                }

				$mdl = new \Model\TransferReceiptsModel();
				$detail = $mdl->getDetail($result_data['rel_id']);
				$result['data']['issue_number'] = $detail['tr_number'];
				$result['data'] = $result_data;
				$result['data']['detail'] = $detail;
				$r_models = \Model\ActivitiesModel::model()->findAllByAttributes(['group_id' => $result_data['group_id']]);
				foreach($r_models as $model2) {
					if ($model2->id != $result_data['id']) {
						$mdl2 = new \Model\TransferIssuesModel();
						$rel_result_data = $mdl2->getDetail($model2->rel_id);
						$result['data']['related'] = $model->getItem(['id' => $model2->id]);
						if ($result['data']['related']['type'] == \Model\ActivitiesModel::TYPE_TRANSFER_ISSUE) {
							$result['data']['related']['issue_number'] = $rel_result_data['ti_number'];
							$result['data']['related']['detail'] = $rel_result_data;
							if (strlen($result['data']['detail']['notes']) < strlen($result['data']['related']['configs']['notes'])) {
								$result['data']['detail']['notes'] = $result['data']['related']['configs']['notes'];
								$result['data']['configs']['notes'] = $result['data']['related']['configs']['notes'];
							}
						}
					}
				}
			} elseif ($result_data['type'] == \Model\ActivitiesModel::TYPE_STOCK_IN || $result_data['type'] == \Model\ActivitiesModel::TYPE_STOCK_OUT) {
			    $statuss = [-2 => 'CANCELED', -1 => 'NEED-CHECK', 0 => 'PENDING', 1 => 'COMPLETE'];
                $issue_number = 'IN-';
			    if ($result_data['type'] == \Model\ActivitiesModel::TYPE_STOCK_IN) {
                    $issue_number = 'IN-'. $statuss[$result_data['status']];
                } elseif ($result_data['type'] == \Model\ActivitiesModel::TYPE_STOCK_OUT) {
                    $issue_number = 'OUT-'. $statuss[$result_data['status']];
                }

				if ($result_data['status'] == -1) {
					$suffix = '(IN '. $result_data['warehouse_to_code'] .')';
					if ($result_data['type'] == \Model\ActivitiesModel::TYPE_STOCK_OUT) {
						$suffix = '(OUT '. $result_data['warehouse_from_code'] .')';
					}
					$r_models = \Model\ActivitiesModel::model()->findAllByAttributes(['group_id' => $result_data['group_id']]);
					$new_items = []; $missings = [];
					foreach($r_models as $model2) {
						if ($model2->id != $result_data['id']) {
							$suffix2 = '(OUT '. $result_data['warehouse_from_code'] .')';
							if ($model2->type == \Model\ActivitiesModel::TYPE_STOCK_IN) {
								$suffix2 = '(IN '. $result_data['warehouse_to_code'] .')';
							}
							$_configs = json_decode($model2->configs, true);
							foreach ($_configs['items'] as $j => $_item) {
								$_item2 = $_item;
								$result_data['configs']['items'][$j]['title'] = $result_data['configs']['items'][$j]['title'].' '. $suffix;
								$new_items[] = $result_data['configs']['items'][$j];
								$_item['title'] = $_item['title'].' '. $suffix2;
								$new_items[] = $_item;
								$qty1 = $result_data['configs']['items'][$j]['quantity'];
								$qty2 = $_item['quantity'];
								if ($qty1 <> $qty2) {
									$_item2['title'] = $_item2['title'].' (Miss)';
									$missed_qty = $qty2 - $qty1;
									if ($missed_qty > 0) {
										$missed_qty = -1*$missed_qty;
									}
									$_item2['quantity'] = $missed_qty;
									$new_items[] = $_item2;
								}
							}
						}
					}
					
					$result_data['configs']['items'] = $new_items;
				}
			    $result_data['issue_number'] = $issue_number;
                $result['data'] = $result_data;
            }  elseif ($result_data['type'] == \Model\ActivitiesModel::TYPE_PURCHASE_ORDER) {
				$mdl = new \Model\PurchaseOrdersModel();
				$detail = $mdl->getDetail($result_data['rel_id']);
				$detail['configs'] = json_decode($detail['configs'], true);
				if (empty($result_data['configs'])) {
					$result_data['configs'] = $detail['configs'];
				}
				$result_data['issue_number'] = $detail['po_number'];
				$result['data'] = $result_data;
				if (array_key_exists('supplier_id', $detail)) {
					$sup_model = \Model\SuppliersModel::model()->findByPk($detail['supplier_id']);
					if ($sup_model instanceof \RedBeanPHP\OODBBean) {
						$sup_configs = $sup_model->configs;
						if (!empty($sup_configs)) {
							$sup_configs = json_decode($sup_configs, true);
							if (array_key_exists('use_default_price', $sup_configs)) {
								$detail['use_default_price'] = 1;
							}
						}
					}
				}
				$result['data']['detail'] = $detail;
			}
        }

        return $response->withJson($result, 201);
	}

    /**
     * Incoming good from another warehouse
     * @param $request
     * @param $response
     * @param $args
     * @return mixed
     */
    public function get_incoming_transfer($request, $response, $args)
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
        if (isset($params['admin_id']) && isset($params['items'])) {
            $act_model = new \Model\ActivitiesModel();
            $latest_group_id = $act_model->getLatestGroupId();
            //$result['data'] = $params;
            try {
                $act_model->title = 'Stok Masuk IN-PENDING';
                $act_model->type = \Model\ActivitiesModel::TYPE_STOCK_IN;
                if (is_array($params['items'])) {
                    $descs = [];
                    foreach ($params['items'] as $i => $item) {
                        if (array_key_exists('title', $item)) {
                            $descs[] = $item['title'] . ' : ' . $item['quantity'];
                        } else {
                            $p_model = \Model\ProductsModel::model()->findByPk($item['barcode']);
                            if ($p_model instanceof \RedBeanPHP\OODBBean) {
                                $params['items'][$i]['title'] = $p_model->title;
                                $descs[] = $p_model->title . ' : ' . $item['quantity'];
                            }
                        }
                    }
                    $act_model->description = implode("\n", $descs);
                    if (!empty($params['admin_id'])) {
                        $ad_model = \Model\AdminModel::model()->findByPk($params['admin_id']);
                        if ($ad_model instanceof \RedBeanPHP\OODBBean) {
                            $act_model->description .= "\nSubmited By ". $ad_model->name;
                            if (isset($params['notes'])) {
                                $params['notes'] = $ad_model->name ." : ". $params['notes'];
                            }
                        }
                    }
                }
                if (isset($params['warehouse_to'])) {
                    $act_model->warehouse_id = $params['warehouse_to'];
                }
                $act_model->group_id = $latest_group_id + 1;
				$act_model->group_master = 1;
                if (array_key_exists('api-key', $params)) {
                    unset($params['api-key']);
                }
				$params['effective_date'] = date("Y-m-d");
                $act_model->configs = json_encode($params);
                $act_model->created_at = date("Y-m-d H:i:s");
                $act_model->created_by = (isset($params['admin_id'])) ? $params['admin_id'] : 1;
                $save_act = \Model\ActivitiesModel::model()->save(@$act_model);
                if ($save_act) {
                    $act_model2 = new \Model\ActivitiesModel();
                    $act_model2->title = 'Stok Keluar OUT-PENDING';
                    $act_model2->type = \Model\ActivitiesModel::TYPE_STOCK_OUT;
                    if (isset($params['warehouse_from'])) {
                        $act_model2->warehouse_id = $params['warehouse_from'];
                    }
                    $act_model2->group_id = $act_model->group_id;
                    $descs = [];
                    foreach ($params['items'] as $i => $item) {
                        if (array_key_exists('title', $item)) {
                            $descs[] = $item['title'] . ' : -' . $item['quantity'];
                        } else {
                            $p_model = \Model\ProductsModel::model()->findByPk($item['barcode']);
                            if ($p_model instanceof \RedBeanPHP\OODBBean) {
                                $params['items'][$i]['title'] = $p_model->title;
                                $descs[] = $p_model->title . ' : -' . $item['quantity'];
                            }
                        }
                    }
                    $act_model2->description = implode("\n", $descs);
                    if (!empty($params['admin_id'])) {
                        $ad_model = \Model\AdminModel::model()->findByPk($params['admin_id']);
                        if ($ad_model instanceof \RedBeanPHP\OODBBean) {
                            $act_model2->description .= "\nSubmited By ". $ad_model->name;
                        }
                    }
                    $act_model2->configs = $act_model->configs;
                    $act_model2->created_at = date("Y-m-d H:i:s");
                    $act_model2->created_by = $act_model->created_by;
                    if ($act_model2->warehouse_id > 0) {
                        $save_act2 = \Model\ActivitiesModel::model()->save(@$act_model2);
                        if ($save_act2) {
                            // also send notification
                            $notif_params = [];
                            $notif_params['recipients'] = [];
                            //$whs_models = \Model\WarehouseStaffsModel::model()->findAllByAttributes(['warehouse_id' => $act_model2->warehouse_id]);
							$_whs_model = new \Model\WarehouseStaffsModel();
							$whs_models = $_whs_model->getStockVerifiers(['warehouse_id' => $act_model2->warehouse_id]);
                            foreach ($whs_models as $whs_model) {
								array_push($notif_params['recipients'], $whs_model['admin_id']);
                            }

                            if (count($notif_params['recipients']) > 0) {
                                $wh_mod = \Model\WarehousesModel::model()->findByPk($act_model2->warehouse_id);
                                $notif_params['message'] = "Verifikasi stok keluar dari " . $wh_mod->title . ". ";
                                $notif_params['message'] .= "Dengan data : " . $act_model2->description;
                                $notif_params['rel_type'] = \Model\NotificationsModel::TYPE_STOCK_OUT;
                                $notif_params['rel_id'] = $act_model2->id;

                                $notif_params['issue_number'] = "OUT-PENDING";
                                $notif_params['rel_activity'] = 'PurchaseDetailActivity';
                                $notif_params['warehouse_id'] = $act_model2->warehouse_id;
                                $this->_sendNotification($notif_params);
								//fcm notice
								$targets = ['fcm_cashier_'. $act_model2->warehouse_id];
								foreach ($targets as $i => $target) {
									$fcm_data = ['rel_activity' => 'PurchaseDetailActivity', 'rel_id' => $act_model2->id, 'rel_type' => \Model\NotificationsModel::TYPE_STOCK_OUT, 'warehouse_id' => $act_model2->warehouse_id];
									$this->sendFCMNotification("Verifikasi Stok Keluar", "Mohon verifikasi stok keluar dari " . $wh_mod->title, $fcm_data, $target);
								}
                            }
                        }
                    }

                    $result['success'] = 1;
                    $result['message'] = $act_model->title. ' telah berhasil disimpan';
                    $result['id'] = $act_model->id;
                    $result['issue_number'] = 'IN-PENDING';
                }
            } catch (\Exception $e) {
                $result['message'] = $e->getMessage();
            }
        }

        return $response->withJson($result, 201);
    }

    /**
     * Outgoing good from another warehouse
     * @param $request
     * @param $response
     * @param $args
     * @return mixed
     */
    public function get_outgoing_transfer($request, $response, $args)
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
        if (isset($params['admin_id']) && isset($params['items'])) {
            $act_model = new \Model\ActivitiesModel();
            $latest_group_id = $act_model->getLatestGroupId();
            try {
                $act_model->title = 'Stok Keluar OUT-PENDING';
                $act_model->type = \Model\ActivitiesModel::TYPE_STOCK_OUT;
                if (is_array($params['items'])) {
                    $descs = [];
                    foreach ($params['items'] as $i => $item) {
                        if (array_key_exists('title', $item)) {
                            $descs[] = $item['title'] . ' : -' . $item['quantity'];
                        } else {
                            $p_model = \Model\ProductsModel::model()->findByPk($item['barcode']);
                            if ($p_model instanceof \RedBeanPHP\OODBBean) {
                                $params['items'][$i]['title'] = $p_model->title;
                                $descs[] = $p_model->title . ' : -' . $item['quantity'];
                            }
                        }
                    }
                    $act_model->description = implode("\n", $descs);
                    if (!empty($params['admin_id'])) {
                        $ad_model = \Model\AdminModel::model()->findByPk($params['admin_id']);
                        if ($ad_model instanceof \RedBeanPHP\OODBBean) {
                            $act_model->description .= "\nSubmited By ". $ad_model->name;
                            if (isset($params['notes'])) {
                                $params['notes'] = $ad_model->name ." : ". $params['notes'];
                            }
                        }
                    }
                }
                if (isset($params['warehouse_from'])) {
                    $act_model->warehouse_id = $params['warehouse_from'];
                }
                $act_model->group_id = $latest_group_id + 1;
				$act_model->group_master = 1;
                if (array_key_exists('api-key', $params)) {
                    unset($params['api-key']);
                }
				$params['effective_date'] = date("Y-m-d");
                $act_model->configs = json_encode($params);
                $act_model->created_at = date("Y-m-d H:i:s");
                $act_model->created_by = (isset($params['admin_id'])) ? $params['admin_id'] : 1;
                $save_act = \Model\ActivitiesModel::model()->save(@$act_model);
                if ($save_act) {
                    $act_model2 = new \Model\ActivitiesModel();
                    $act_model2->title = 'Stok Masuk IN-PENDING';
                    $act_model2->type = \Model\ActivitiesModel::TYPE_STOCK_IN;
                    if (isset($params['warehouse_to'])) {
                        $act_model2->warehouse_id = $params['warehouse_to'];
                    }
                    $act_model2->group_id = $act_model->group_id;
                    $descs = [];
                    foreach ($params['items'] as $i => $item) {
                        if (array_key_exists('title', $item)) {
                            $descs[] = $item['title'] . ' : ' . $item['quantity'];
                        } else {
                            $p_model = \Model\ProductsModel::model()->findByPk($item['barcode']);
                            if ($p_model instanceof \RedBeanPHP\OODBBean) {
                                $params['items'][$i]['title'] = $p_model->title;
                                $descs[] = $p_model->title . ' : ' . $item['quantity'];
                            }
                        }
                    }
                    $act_model2->description = implode("\n", $descs);
                    if (!empty($params['admin_id'])) {
                        $ad_model = \Model\AdminModel::model()->findByPk($params['admin_id']);
                        if ($ad_model instanceof \RedBeanPHP\OODBBean) {
                            $act_model2->description .= "\nSubmited By ". $ad_model->name;
                        }
                    }
                    $act_model2->configs = $act_model->configs;
                    $act_model2->created_at = date("Y-m-d H:i:s");
                    $act_model2->created_by = $act_model->created_by;
                    if ($act_model2->warehouse_id > 0) {
                        $save_act2 = \Model\ActivitiesModel::model()->save(@$act_model2);
                        if ($save_act2) {
                            // also send notification
                            $notif_params = [];
                            $notif_params['recipients'] = [];
                            //$whs_models = \Model\WarehouseStaffsModel::model()->findAllByAttributes(['warehouse_id' => $act_model2->warehouse_id]);
							$_whs_model = new \Model\WarehouseStaffsModel();
							$whs_models = $_whs_model->getStockVerifiers(['warehouse_id' => $act_model2->warehouse_id]);
                            foreach ($whs_models as $whs_model) {
                                array_push($notif_params['recipients'], $whs_model['admin_id']);
                            }

                            if (count($notif_params['recipients']) > 0) {
                                $wh_mod = \Model\WarehousesModel::model()->findByPk($params['warehouse_from']);
                                $notif_params['message'] = "Verifikasi stok masuk dari " . $wh_mod->title . ". ";
                                $notif_params['message'] .= "Dengan data : " . $act_model2->description;
                                $notif_params['rel_type'] = \Model\NotificationsModel::TYPE_STOCK_IN;
                                $notif_params['rel_id'] = $act_model2->id;

                                $notif_params['issue_number'] = "IN-PENDING";
                                $notif_params['rel_activity'] = 'PurchaseDetailActivity';
                                $notif_params['warehouse_id'] = $act_model2->warehouse_id;
                                $this->_sendNotification($notif_params);

								//fcm notice
								$targets = ['fcm_cashier_'. $act_model2->warehouse_id];
								foreach ($targets as $i => $target) {
									$fcm_data = ['rel_activity' => 'PurchaseDetailActivity', 'rel_id' => $act_model2->id, 'rel_type' => \Model\NotificationsModel::TYPE_STOCK_IN, 'warehouse_id' => $act_model2->warehouse_id];
									$this->sendFCMNotification("Verifikasi Stok Masuk", "Mohon verifikasi stok masuk dari " . $wh_mod->title, $fcm_data, $target);
								}
                            }
                        }
                    }
                    $result['success'] = 1;
                    $result['message'] = $act_model->title. ' telah berhasil disimpan';
                    $result['id'] = $act_model->id;
                    $result['issue_number'] = 'OUT-PENDING';
                }
            } catch (\Exception $e) {
                $result['message'] = $e->getMessage();
            }
        }

        return $response->withJson($result, 201);
    }

    public function get_in_out_update($request, $response, $args)
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
        if (isset($params['admin_id']) && isset($params['id'])) {
            $amodel = \Model\AdminModel::model()->findByPk($params['admin_id']);
            $model = \Model\ActivitiesModel::model()->findByPk($params['id']);
            if ($model instanceof \RedBeanPHP\OODBBean) {
                $missed_items = $this->getMissedTransferItems($model->group_id);
                $need_auto_adjust = false;
                if (array_key_exists('surplus', $missed_items) && !array_key_exists('defisit', $missed_items)) {
                    $need_auto_adjust = true;
                }
                // avoid auto adjust if has selisih more than 20
                if (array_key_exists('avoid_auto_adjust', $missed_items) && $missed_items['avoid_auto_adjust']) {
                    $need_auto_adjust = false;
                }
				$old_status = $model->status;
                $configs = json_decode($model->configs, true);
                if (is_array($configs)) {
					if (array_key_exists('is_update_qty', $configs) && !isset($params['force_confirm'])) {
					    if (!$need_auto_adjust) {
                            $params['status'] = -1;
                        }
					}
                    if (isset($params['warehouse_from'])) {
                        $configs['warehouse_from'] = $params['warehouse_from'];
                    }

                    if (isset($params['warehouse_to'])) {
                        $configs['warehouse_to'] = $params['warehouse_to'];
                    }

                    $descs = [];
                    if (isset($params['items']) && is_array(($params['items']))) {
                        foreach ($params['items'] as $i => $item) {
                            if (array_key_exists('title', $item)) {
                                $descs[] = $item['title'] . ' : ' . $item['quantity'];
                            } else {
                                $p_model = \Model\ProductsModel::model()->findByPk($item['barcode']);
                                if ($p_model instanceof \RedBeanPHP\OODBBean) {
                                    $params['items'][$i]['title'] = $p_model->title;
                                    $descs[] = $p_model->title . ' : ' . $item['quantity'];
                                }
                            }
                        }
                        $configs['items'] = $params['items'];
                    }

					if (isset($params['effective_date']) && !empty($params['effective_date'])) {
						$configs['effective_date'] = $params['effective_date'];
					}

					if (isset($params['is_update_qty']) && $model->group_master == 0) {
						$configs['is_update_qty'] = $params['is_update_qty'];
					}

                    if (isset($params['notes'])) {
                        $_note = $amodel->name ." : ". $params['notes'];
                        if (strpos($configs['notes'], $_note) === false) {
                            $configs['notes'] .= "\n" . $_note;
                        }
                    }

                    $model->configs = json_encode($configs);
                }

				$already_updated = false; $avoid_sync_configs = false;
                if (isset($params['status'])) {
					if ((int)$params['status'] == $model->status || (in_array($model->status, ['1', '-2']))) {
						$already_updated = true;
					}
                    $model->status = (int)$params['status'];
					if ($model->status == -2) {
						if ($model->type == \Model\ActivitiesModel::TYPE_STOCK_IN) {
							$model->title = 'Stok Masuk IN-CANCELED';
						} elseif ($model->type == \Model\ActivitiesModel::TYPE_STOCK_OUT) {
							$model->title = 'Stok Keluar OUT-CANCELED';
						}
					} elseif ($model->status == -1) {
						if ($model->type == \Model\ActivitiesModel::TYPE_STOCK_IN) {
							$model->title = 'Stok Masuk IN-NEEDCHECK';
						} elseif ($model->type == \Model\ActivitiesModel::TYPE_STOCK_OUT) {
							$model->title = 'Stok Keluar OUT-NEEDCHECK';
						}
						if ($model->group_master == 0) {
							$avoid_sync_configs = true;
							$params['update_related'] = 1;
						}
					}
                }

                $model->updated_at = date("Y-m-d H:i:s");
                $model->updated_by = $params['admin_id'];
				$update = false;
				if (!$already_updated) {
                	$update = \Model\ActivitiesModel::model()->update(@$model);
				} else {
					$result['success'] = 1;
                    $result['message'] = 'Data gagal disimpan';
				}
                if ($update) {
                    $result['success'] = 1;
                    $result['message'] = 'Data telah berhasil disimpan';
                    if ($model->status == 1) { //finised
                        try {
                            // start auto update qty if surplus
                            if ($need_auto_adjust) {
                                if ($model->type == \Model\ActivitiesModel::TYPE_STOCK_IN) {
                                    $st_out_model = \Model\ActivitiesModel::model()->findByAttributes(['group_id' => $model->group_id, 'type' => \Model\ActivitiesModel::TYPE_STOCK_OUT]);
                                    if ($st_out_model instanceof \RedBeanPHP\OODBBean) {
                                        $_cfgs = json_decode($st_out_model->configs, true);
                                        $wh_to_code = ''; $wh_from_code = '';
                                        if (!empty($_cfgs['warehouse_to'])) {
                                            $wh_to_model = \Model\WarehousesModel::model()->findByPk($_cfgs['warehouse_to']);
                                            $wh_to_code = $wh_to_model->code;
                                            $wh_from_model = \Model\WarehousesModel::model()->findByPk($_cfgs['warehouse_from']);
                                            $wh_from_code = $wh_from_model->code;
                                        }
                                        $_items = $_cfgs['items']; $do_update = 0; $adjust_note = '';
                                        foreach ($_items as $_i => $_item) {
                                            if (array_key_exists($_item['barcode'], $missed_items['need_adjust'])) {
                                                $adjust_val = $missed_items['need_adjust'][$_item['barcode']];
                                                if ($adjust_val > 0) {
                                                    $_items[$_i]['quantity_wrong'] = $_item['quantity'];
                                                    $_items[$_i]['quantity'] = $adjust_val;
                                                    // add system auto correction
                                                    $adjust_note .= "\nSystem : OUT ". $wh_from_code ." ". $_item['quantity'] ." ". $_item['name'] ." Auto adjust ". $adjust_val ." ". $_item['name'];
                                                    $adjust_note .= "\nOUT ". $wh_from_code ." ". $adjust_val ." ". $_item['name'];
                                                    $adjust_note .= "\nIN ". $wh_to_code ." ". $adjust_val ." ". $_item['name'];
													$_cfgs['notes'] .= $adjust_note;
                                                    $do_update = $do_update + 1;
                                                }
                                            }
                                        }
                                        if ($do_update > 0) {
                                            $_cfgs['items'] = $_items;
                                            $st_out_model->configs = json_encode($_cfgs);
                                            $update = \Model\ActivitiesModel::model()->update(@$st_out_model);
											if ($update) {
												//update also stock in
												if (!empty($adjust_note)) {
													$in_configs = json_decode($model->configs, true);
													$in_configs['notes'] .= $adjust_note;
													$model->configs = json_encode($in_configs);
													$update2 = \Model\ActivitiesModel::model()->update($model);
												}
											}
                                        }
                                    }
                                } elseif ($model->type == \Model\ActivitiesModel::TYPE_STOCK_OUT) {
                                    $_cfgs = json_decode($model->configs, true);
                                    $wh_to_code = ''; $wh_from_code = '';
                                    if (!empty($_cfgs['warehouse_to'])) {
                                        $wh_to_model = \Model\WarehousesModel::model()->findByPk($_cfgs['warehouse_to']);
                                        $wh_to_code = $wh_to_model->code;
                                        $wh_from_model = \Model\WarehousesModel::model()->findByPk($_cfgs['warehouse_from']);
                                        $wh_from_code = $wh_from_model->code;
                                    }
                                    $_items = $_cfgs['items']; $do_update = 0; $adjust_note = '';
                                    foreach ($_items as $_i => $_item) {
                                        if (array_key_exists($_item['barcode'], $missed_items['need_adjust'])) {
                                            $adjust_val = $missed_items['need_adjust'][$_item['barcode']];
                                            if ($adjust_val > 0) {
                                                $_items[$_i]['quantity_wrong'] = $_item['quantity'];
                                                $_items[$_i]['quantity'] = $adjust_val;
                                                // add system auto correction
                                                $adjust_note .= "\nSystem : OUT ". $wh_from_code ." ". $_item['quantity'] ." ". $_item['name'] ." Auto adjust ". $adjust_val ." ". $_item['name'];
                                                $adjust_note .= "\nOUT ". $wh_from_code ." ". $adjust_val ." ". $_item['name'];
                                                $adjust_note .= "\nIN ". $wh_to_code ." ". $adjust_val ." ". $_item['name'];
												$_cfgs['notes'] .= $adjust_note;
                                                $do_update = $do_update + 1;
                                            }
                                        }
                                    }
                                    if ($do_update > 0) {
                                        $_cfgs['items'] = $_items;
                                        $model->configs = json_encode($_cfgs);
                                        $update = \Model\ActivitiesModel::model()->update(@$model);
										//update also stock in
										if (!empty($adjust_note)) {
											$st_in_model = \Model\ActivitiesModel::model()->findByAttributes(['group_id' => $model->group_id, 'type' => \Model\ActivitiesModel::TYPE_STOCK_IN]);
			                                if ($st_in_model instanceof \RedBeanPHP\OODBBean) {
												$in_configs = json_decode($st_in_model->configs, true);
												$in_configs['notes'] .= $adjust_note;
												$st_in_model->configs = json_encode($in_configs);
												$update2 = \Model\ActivitiesModel::model()->update($st_in_model);
											}
										}
                                    }
                                }
                            }
                            // end of auto update

							$checked_by_manager = false;
							if (isset($params['force_confirm']) && ($old_status == -1)) {
								$checked_by_manager = true;
							}
                            $this->create_real_issue($model, $params['admin_id'], $checked_by_manager);
                        } catch (\Exception $e){$result['errors'] = $e->getMessage();}
                    } elseif ($model->status == -1) {
                        try {
                            // also send notification
                            $notif_params = [];
                            $notif_params['recipients'] = [];
                            $wh_mod = new \Model\WarehouseStaffsModel();
                            $whs_models = $wh_mod->getManagers(['warehouse_id' => $model->warehouse_id]);
                            foreach ($whs_models as $i => $whs_model) {
                                array_push($notif_params['recipients'], $whs_model['admin_id']);
                            }

                            if (count($notif_params['recipients']) > 0) {
                                $notif_params['message'] = "Verifikasi Sengketa Stok Transfer";
                                $notif_params['message'] .= "Dengan data : " . $model->description;
                                $notif_params['rel_type'] = ($model->type == \Model\ActivitiesModel::TYPE_STOCK_OUT)? \Model\NotificationsModel::TYPE_STOCK_OUT : \Model\NotificationsModel::TYPE_STOCK_IN;
                                $notif_params['rel_id'] = $model->id;

                                $notif_params['issue_number'] = ($model->type == \Model\ActivitiesModel::TYPE_STOCK_OUT)? "OUT-NEEDCHECK" : "IN-NEEDCHECK";
                                $notif_params['rel_activity'] = 'PurchaseDetailActivity';
                                $notif_params['warehouse_id'] = $model->warehouse_id;
                                $this->_sendNotification($notif_params);
                            }

                            //fcm notice
                            $wh_model = \Model\WarehousesModel::model()->findByPk($model->id);
                            $fcm_data = [
                                'rel_activity' => 'PurchaseDetailActivity',
                                'rel_id' => $model->id,
                                'rel_type' => ($model->type == \Model\ActivitiesModel::TYPE_STOCK_OUT)? \Model\NotificationsModel::TYPE_STOCK_OUT : \Model\NotificationsModel::TYPE_STOCK_IN,
                                'warehouse_id' => $model->warehouse_id
                            ];
                            $this->sendFCMNotification("Verifikasi Sengketa Stok", "Mohon verifikasi sengketa stok transfer " . $wh_model->title, $fcm_data, 'fcm_manager_'. $model->warehouse_id);

                            // send notification in both side
                            if (!isset($params['update_related']) || $params['update_related'] == 0) {
                                if ($model->type == \Model\ActivitiesModel::TYPE_STOCK_OUT) {
                                    $in_model = \Model\ActivitiesModel::model()->findByAttributes(['group_id' => $model->group_id, 'type' => \Model\ActivitiesModel::TYPE_STOCK_IN]);
                                    // update notes
                                    $_in_configs = json_decode($in_model->configs, true);
                                    $_out_configs = json_decode($model->configs, true);
                                    if (strlen($_in_configs['notes']) < strlen($_out_configs['notes'])) {
                                        $_in_configs['notes'] = $_out_configs['notes'];
                                        $in_model->configs = json_encode($_in_configs);
                                        $in_update = \Model\ActivitiesModel::model()->update($in_model);
                                    }

                                    $whs_models = $wh_mod->getManagers(['warehouse_id' => $in_model->warehouse_id]);
                                    $notif_params['recipients'] = [];
                                    foreach ($whs_models as $i => $whs_model) {
                                        array_push($notif_params['recipients'], $whs_model['admin_id']);
                                    }

                                    if (count($notif_params['recipients']) > 0) {
                                        $notif_params['message'] = "Verifikasi Sengketa Stok Transfer";
                                        $notif_params['message'] .= "Dengan data : " . $in_model->description;
                                        $notif_params['rel_type'] = \Model\NotificationsModel::TYPE_STOCK_IN;
                                        $notif_params['rel_id'] = $in_model->id;

                                        $notif_params['issue_number'] = "IN-NEEDCHECK";
                                        $notif_params['rel_activity'] = 'PurchaseDetailActivity';
                                        $notif_params['warehouse_id'] = $in_model->warehouse_id;
                                        $this->_sendNotification($notif_params);
                                    }

									// send notice jg ke staff terkait adanya sengketa
									$_whs_model = new \Model\WarehouseStaffsModel();
									$whs_models = $wh_mod->getStockVerifiers(['warehouse_id' => $in_model->warehouse_id]);
									$notif_params['recipients'] = [];
				                    foreach ($whs_models as $whs_model) {
										array_push($notif_params['recipients'], $whs_model['admin_id']);
				                    }

				                    if (count($notif_params['recipients']) > 0) {
				                        $wh_mod = \Model\WarehousesModel::model()->findByPk($in_model->warehouse_id);
				                        $notif_params['message'] = "Transfer Stok Menunggu Verifikasi Manager";
				                        $notif_params['rel_type'] = \Model\NotificationsModel::TYPE_STOCK_IN;
				                        $notif_params['rel_id'] = $in_model->id;

				                        $notif_params['issue_number'] = "IN-NEEDCHECK";
                                        $notif_params['rel_activity'] = 'PurchaseDetailActivity';
                                        $notif_params['warehouse_id'] = $in_model->warehouse_id;
				                        $this->_sendNotification($notif_params);
										//fcm notice
										$targets = ['fcm_cashier_'. $in_model->warehouse_id];
										foreach ($targets as $i => $target) {
											$fcm_data = ['rel_activity' => 'PurchaseDetailActivity', 'rel_id' => $in_model->id, 'rel_type' => \Model\NotificationsModel::TYPE_STOCK_IN, 'warehouse_id' => $in_model->warehouse_id];
											$this->sendFCMNotification("Menunggu Verifikasi Manager", "Proses transfer stok masih menunggu verifikasi manager karena terdapat perbedaan data.", $fcm_data, $target);
										}
				                    }
                                } elseif ($model->type == \Model\ActivitiesModel::TYPE_STOCK_IN) {
                                    $out_model = \Model\ActivitiesModel::model()->findByAttributes(['group_id' => $model->group_id, 'type' => \Model\ActivitiesModel::TYPE_STOCK_OUT]);
                                    // update notes
                                    $_out_configs = json_decode($out_model->configs, true);
                                    $_in_configs = json_decode($model->configs, true);
                                    if (strlen($_out_configs['notes']) < strlen($_in_configs['notes'])) {
                                        $_out_configs['notes'] = $_in_configs['notes'];
                                        $out_model->configs = json_encode($_out_configs);
                                        $out_update = \Model\ActivitiesModel::model()->update($out_model);
                                    }
                                    $whs_models = $wh_mod->getManagers(['warehouse_id' => $out_model->warehouse_id]);
                                    $notif_params['recipients'] = [];
                                    foreach ($whs_models as $i => $whs_model) {
                                        array_push($notif_params['recipients'], $whs_model['admin_id']);
                                    }

                                    if (count($notif_params['recipients']) > 0) {
                                        $notif_params['message'] = "Verifikasi Sengketa Stok Transfer";
                                        $notif_params['message'] .= "Dengan data : " . $out_model->description;
                                        $notif_params['rel_type'] = \Model\NotificationsModel::TYPE_STOCK_OUT;
                                        $notif_params['rel_id'] = $out_model->id;

                                        $notif_params['issue_number'] = "OUT-NEEDCHECK";
                                        $notif_params['rel_activity'] = 'PurchaseDetailActivity';
                                        $notif_params['warehouse_id'] = $out_model->warehouse_id;
                                        $this->_sendNotification($notif_params);
                                    }

									// send notice jg ke staff terkait adanya sengketa
									$_whs_model = new \Model\WarehouseStaffsModel();
									$whs_models = $wh_mod->getStockVerifiers(['warehouse_id' => $out_model->warehouse_id]);
									$notif_params['recipients'] = [];
				                    foreach ($whs_models as $whs_model) {
										array_push($notif_params['recipients'], $whs_model['admin_id']);
				                    }

				                    if (count($notif_params['recipients']) > 0) {
				                        $wh_mod = \Model\WarehousesModel::model()->findByPk($out_model->warehouse_id);
				                        $notif_params['message'] = "Transfer Stok Menunggu Verifikasi Manager";
				                        $notif_params['rel_type'] = \Model\NotificationsModel::TYPE_STOCK_OUT;
				                        $notif_params['rel_id'] = $out_model->id;

				                        $notif_params['issue_number'] = "IN-NEEDCHECK";
                                        $notif_params['rel_activity'] = 'PurchaseDetailActivity';
                                        $notif_params['warehouse_id'] = $out_model->warehouse_id;
				                        $this->_sendNotification($notif_params);
										//fcm notice
										$targets = ['fcm_cashier_'. $out_model->warehouse_id];
										foreach ($targets as $i => $target) {
											$fcm_data = ['rel_activity' => 'PurchaseDetailActivity', 'rel_id' => $out_model->id, 'rel_type' => \Model\NotificationsModel::TYPE_STOCK_OUT, 'warehouse_id' => $out_model->warehouse_id];
											$this->sendFCMNotification("Menunggu Verifikasi Manager", "Proses transfer stok masih menunggu verifikasi manager karena terdapat perbedaan data.", $fcm_data, $target);
										}
				                    }
                                }
                            }
                        } catch (\Exception $e){}
                    }

					if (($model->group_master > 0) || isset($params['update_related'])) { // update the related data
						$rel_models = \Model\ActivitiesModel::model()->findAllByAttributes(['group_id' => $model->group_id]);
						if (count($rel_models) > 0) {
							foreach($rel_models as $rel_model) {
								if ($rel_model->id != $model->id) {
									if (isset($params['status'])) {
										$rel_model->status = (int)$params['status'];
										if ($rel_model->status == -2) {
											if ($rel_model->type == \Model\ActivitiesModel::TYPE_STOCK_IN) {
												$rel_model->title = 'Stok Masuk IN-CANCELED';
											} elseif ($rel_model->type == \Model\ActivitiesModel::TYPE_STOCK_OUT) {
												$rel_model->title = 'Stok Keluar OUT-CANCELED';
											}
											$rel_model->canceled_at = date("Y-m-d H:i:s");
		                					$rel_model->canceled_by = $params['admin_id'];
										} elseif ($rel_model->status == -1) {
											if ($rel_model->type == \Model\ActivitiesModel::TYPE_STOCK_IN) {
												$rel_model->title = 'Stok Masuk IN-NEEDCHECK';
											} elseif ($rel_model->type == \Model\ActivitiesModel::TYPE_STOCK_OUT) {
												$rel_model->title = 'Stok Keluar OUT-NEEDCHECK';
											}
										}
									}
									if (!$avoid_sync_configs) {
										$rel_model->configs = $model->configs;
									}
									$rel_model->updated_at = date("Y-m-d H:i:s");
                					$rel_model->updated_by = $params['admin_id'];
                					$update2 = \Model\ActivitiesModel::model()->update(@$rel_model);
                					if ($update2) {
                                        if ($rel_model->status == -1) {
                                            try {
                                                // also send notification
                                                $notif_params = [];
                                                $notif_params['recipients'] = [];
                                                $wh_mod = new \Model\WarehouseStaffsModel();
                                                $whs_models = $wh_mod->getManagers(['warehouse_id' => $rel_model->warehouse_id]);
                                                foreach ($whs_models as $i => $whs_model) {
                                                    array_push($notif_params['recipients'], $whs_model['admin_id']);
                                                }

                                                if (count($notif_params['recipients']) > 0) {
                                                    $notif_params['message'] = "Verifikasi Sengketa Stok Transfer";
                                                    $notif_params['message'] .= "Dengan data : " . $rel_model->description;
                                                    $notif_params['rel_type'] = \Model\NotificationsModel::TYPE_STOCK_IN;
                                                    $notif_params['rel_id'] = $rel_model->id;

                                                    $notif_params['issue_number'] = "IN-NEEDCHECK";
                                                    $notif_params['rel_activity'] = 'PurchaseDetailActivity';
                                                    $this->_sendNotification($notif_params);
                                                }
                                            } catch (\Exception $e){}
                                        }
                                    }
								}
							}
						}
					}
                }
            }
        }

        return $response->withJson($result, 201);
    }

    /*
     * $model = ActivitiesModel
     */
    private function create_real_issue($model, $admin_id, $checked_by_manager) {
        if (!empty($model->configs)) {
            $in_model = null; $avoid_fcm = $model->warehouse_id;
            if ($model->type == \Model\ActivitiesModel::TYPE_STOCK_OUT) {
                // find the stock in
                $in_model = \Model\ActivitiesModel::model()->findByAttributes(['group_id' => $model->group_id, 'type' => \Model\ActivitiesModel::TYPE_STOCK_IN]);
            } else {
                $in_model = $model;
                $model = \Model\ActivitiesModel::model()->findByAttributes(['group_id' => $model->group_id, 'type' => \Model\ActivitiesModel::TYPE_STOCK_OUT]);
            }
            $configs = json_decode($model->configs, true);

            $ti_model = new \Model\TransferIssuesModel();
            $ti_number = \Pos\Controllers\TransfersController::get_ti_number();
            $ti_model->ti_number = $ti_number['serie_nr'];
            $ti_model->ti_serie = $ti_number['serie'];
            $ti_model->ti_nr = $ti_number['nr'];
            $ti_model->base_price = 0;
            $ti_model->warehouse_from = $configs['warehouse_from'];
            if (isset($configs['warehouse_to']))
                $ti_model->warehouse_to = $configs['warehouse_to'];
            $ti_model->date_transfer = date("Y-m-d H:i:s");
            $ti_model->status = \Model\TransferIssuesModel::STATUS_COMPLETED;
            if (isset($configs['notes']))
                $ti_model->notes = $configs['notes'];
            $ti_model->created_at = date("Y-m-d H:i:s");
            $ti_model->created_by = $admin_id;
            $save = \Model\TransferIssuesModel::model()->save(@$ti_model);
            if ($save) {
                $tot_price = 0; $descs = [];
                foreach ($configs['items'] as $i => $item) {
					if (array_key_exists('title', $item)) {
						$descs[] = $item['title'] . ' : -' . $item['quantity'];
					} else {
						$p_model = \Model\ProductsModel::model()->findByPk($item['barcode']);
						if ($p_model instanceof \RedBeanPHP\OODBBean) {
							$descs[] = $p_model->title . ' : -' . $item['quantity'];
						}
					}

                    $product = \Model\ProductsModel::model()->findByPk($item['barcode']);
                    $product_id = $product->id;
                    $quantity = $item['quantity'];
                    $imodel[$product_id] = new \Model\TransferIssueItemsModel();
                    $imodel[$product_id]->ti_id = $ti_model->id;
                    $imodel[$product_id]->product_id = $product_id;
                    $imodel[$product_id]->title = $product->title;
                    $imodel[$product_id]->quantity = $quantity;
                    $imodel[$product_id]->available_qty = $quantity;
                    $imodel[$product_id]->unit = $product->unit;
                    $whp_model = new \Model\WarehouseProductsModel();
                    $cur_cost = $whp_model->getCurrentCost(['product_id' => $product_id, 'warehouse_id' => $ti_model->warehouse_from]);
                    $imodel[$product_id]->price = $cur_cost;
                    $imodel[$product_id]->created_at = date("Y-m-d H:i:s");
                    $imodel[$product_id]->created_by = $ti_model->created_by;

                    if ($product_id > 0 && $imodel[$product_id]->quantity > 0) {
                        $save2 = \Model\TransferIssueItemsModel::model()->save($imodel[$product_id]);
                        if ($save2) {
                            $tot_price = $tot_price + ($product->current_cost * $quantity);
                        }
                    }
                }

                // updating price of po data
                if ($tot_price > 0) {
                    $pomodel = \Model\TransferIssuesModel::model()->findByPk($ti_model->id);
                    $pomodel->base_price = $tot_price;
                    $update = \Model\TransferIssuesModel::model()->update($pomodel);
                }

				// update history
				$model->title = 'Stok Keluar '. $ti_model->ti_number;
				$model->rel_id = $ti_model->id;
				$model->type = \Model\ActivitiesModel::TYPE_TRANSFER_ISSUE;
				$model->status = 1;
				$model->finished_at = date("Y-m-d H:i:s");
				$model->finished_by = $admin_id;
				if ($checked_by_manager) {
					$model->checked_at = date("Y-m-d H:i:s");
					$model->checked_by = $admin_id;
				}
				$model->updated_at = date("Y-m-d H:i:s");
                $model->updated_by = $admin_id;
                $update1 = \Model\ActivitiesModel::model()->update(@$model);
                if ($update1) {
                    try {
                        $wh_mod = \Model\WarehousesModel::model()->findByPk($configs['warehouse_from']);
                        $wh_to_mod = \Model\WarehousesModel::model()->findByPk($configs['warehouse_to']);

                        $substract_stock = \Pos\Controllers\TransfersController::_substract_stock(['ti_id' => $ti_model->id, 'admin_id' => $admin_id]);
                        $notif_params = [];
                        $notif_params['recipients'] = [];
                        $_whs_model = new \Model\WarehouseStaffsModel();
                        $whs_models = $_whs_model->getStockVerifiers(['warehouse_id' => $model->warehouse_id]);
                        foreach ($whs_models as $whs_model) {
                            array_push($notif_params['recipients'], $whs_model['admin_id']);
                        }

                        if (count($notif_params['recipients']) > 0) {
                            $notif_params['message'] = "Stok keluar dari " . $wh_mod->title . " ke ". $wh_to_mod->title ." telah diverifikasi ";
                            $notif_params['message'] .= "dengan data : " . $model->description;
                            $notif_params['rel_type'] = \Model\NotificationsModel::TYPE_STOCK_OUT;
                            $notif_params['rel_id'] = $model->id;

                            $notif_params['issue_number'] = $ti_model->ti_number;
                            $notif_params['rel_activity'] = 'PurchaseDetailActivity';
                            $notif_params['warehouse_id'] = $model->warehouse_id;
                            $this->_sendNotification($notif_params);

                            //fcm notice
							if ($checked_by_manager || ($model->warehouse_id != $avoid_fcm)) {
		                        $targets = ['fcm_cashier_'. $model->warehouse_id];
		                        foreach ($targets as $i => $target) {
		                            $fcm_data = ['rel_activity' => 'PurchaseDetailActivity', 'rel_id' => $model->id, 'rel_type' => \Model\NotificationsModel::TYPE_STOCK_OUT, 'warehouse_id' => $model->warehouse_id];
		                            $this->sendFCMNotification("Stok Terverifikasi", $notif_params['message'], $fcm_data, $target);
		                        }
							}
                        }

                        // also send to manager
                        $notif_params['recipients'] = [];
                        $whs_models = $_whs_model->getManagers(['warehouse_id' => $model->warehouse_id]);
                        foreach ($whs_models as $i => $whs_model) {
                            array_push($notif_params['recipients'], $whs_model['admin_id']);
                        }

                        if (count($notif_params['recipients']) > 0) {
                            $notif_params['message'] = "Stok keluar dari " . $wh_mod->title . " ke ". $wh_to_mod->title ." telah diverifikasi ";
                            $notif_params['message'] .= "dengan data : " . $model->description;
                            $notif_params['rel_type'] = \Model\NotificationsModel::TYPE_STOCK_OUT;
                            $notif_params['rel_id'] = $model->id;

                            $notif_params['issue_number'] = $ti_model->ti_number;
                            $notif_params['rel_activity'] = 'PurchaseDetailActivity';
                            $notif_params['warehouse_id'] = $model->warehouse_id;
                            $this->_sendNotification($notif_params);

                            //fcm notice
                            $targets = ['fcm_manager_'. $model->warehouse_id];
                            foreach ($targets as $i => $target) {
                                $fcm_data = ['rel_activity' => 'PurchaseDetailActivity', 'rel_id' => $model->id, 'rel_type' => \Model\NotificationsModel::TYPE_STOCK_OUT, 'warehouse_id' => $model->warehouse_id];
                                $this->sendFCMNotification("Stok Keluar Terverifikasi Otomatis", $notif_params['message'], $fcm_data, $target);
                            }
                        }
                    } catch (\Exception $e) {
                        var_dump($e->getMessage());
                    }
                }

                if ($in_model instanceof \RedBeanPHP\OODBBean) {
                    $act_model = new \Model\ActivitiesModel();
                    $latest_group_id = $act_model->getLatestGroupId();
					$dont_fcm = false;
					if (!$checked_by_manager && $in_model->warehouse_id == $avoid_fcm) {
						$dont_fcm = true;
					}
                    $this->create_real_receipt($ti_model->id, $in_model, $latest_group_id, $admin_id, $checked_by_manager, $dont_fcm);
                }
            }
        }

        return false;
    }

    private function create_real_receipt($ti_id, $act_model, $latest_group_id, $admin_id, $checked_by_manager, $dont_fcm) {
        $rmodel = \Model\TransferReceiptsModel::model()->findByAttributes(['ti_id' => $ti_id]);
        if (!$rmodel instanceof \RedBeanPHP\OODBBean) {
            $model = new \Model\TransferReceiptsModel();
            $tr_number = \Pos\Controllers\TransfersController::get_tr_number();
            $model->tr_number = $tr_number['serie_nr'];
            $model->tr_serie = $tr_number['serie'];
            $model->tr_nr = $tr_number['nr'];
            $model->ti_id = $ti_id;
            $model->warehouse_id = $act_model->warehouse_id;
            $model->effective_date = date("Y-m-d H:i:s");
            $model->created_at = date("Y-m-d H:i:s");
            $model->created_by = $act_model->created_by;
            $save = \Model\TransferReceiptsModel::model()->save(@$model);
            if ($save) {
                $data = json_decode($act_model->configs, true);
				$descs = [];
                if (isset($data['items']) && is_array($data['items'])) {
                    $tot_quantity = 0; $quantity_max = 0;
                    foreach ($data['items'] as $i => $item ) {
						if (array_key_exists('title', $item)) {
							$descs[] = $item['title'] . ' : ' . $item['quantity'];
						} else {
							$p_model = \Model\ProductsModel::model()->findByPk($item['barcode']);
							if ($p_model instanceof \RedBeanPHP\OODBBean) {
								$descs[] = $p_model->title . ' : ' . $item['quantity'];
							}
						}
                        $product_id = $item['barcode'];
                        $quantity = $item['quantity'];
                        $ti_item = \Model\TransferIssueItemsModel::model()->findByAttributes(['product_id' => $product_id, 'ti_id' => $ti_id]);
                        if ($ti_item instanceof \RedBeanPHP\OODBBean) {
                            $primodel[$product_id] = new \Model\TransferReceiptItemsModel();
                            $primodel[$product_id]->tr_id = $model->id;
                            $primodel[$product_id]->ti_item_id = $ti_item->id;
                            $primodel[$product_id]->product_id = $product_id;
                            if (array_key_exists('title', $item)) {
                                $primodel[$product_id]->title = $item['title'];
                            } else {
                                $product[$product_id] = \Model\ProductsModel::model()->findByPk($product_id);
                                $primodel[$product_id]->title = $product[$product_id]->title;
                            }
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
                    $timodel->updated_by = $admin_id;
                    $timodel->completed_at = date("Y-m-d H:i:s");
                    $timodel->completed_by = $admin_id;

                    $update_status = \Model\TransferIssuesModel::model()->update($timodel);
                }

                // update history
				$act_model->title = 'Stok Masuk '. $model->tr_number;
				$act_model->rel_id = $model->id;
				$act_model->type = \Model\ActivitiesModel::TYPE_TRANSFER_RECEIPT;
                $act_model->status = 1;
				$act_model->finished_at = date("Y-m-d H:i:s");
				$act_model->finished_by = $admin_id;
				if ($checked_by_manager) {
					$act_model->checked_at = date("Y-m-d H:i:s");
					$act_model->checked_by = $admin_id;
				}
                $act_model->updated_at = date("Y-m-d H:i:s");
                $act_model->updated_by = $admin_id;
                $update_status_act = \Model\ActivitiesModel::model()->update($act_model);
                if ($update_status_act) {
                    $notif_params = [];
                    $notif_params['recipients'] = [];
                    $_whs_model = new \Model\WarehouseStaffsModel();
                    $whs_models = $_whs_model->getStockVerifiers(['warehouse_id' => $act_model->warehouse_id]);
                    foreach ($whs_models as $whs_model) {
                        array_push($notif_params['recipients'], $whs_model['admin_id']);
                    }

                    if (count($notif_params['recipients']) > 0) {
                        $wh_mod = \Model\WarehousesModel::model()->findByPk($act_model->warehouse_id);
                        $notif_params['message'] = "Stok masuk ke " . $wh_mod->title  ." telah terverifikasi ";
                        $notif_params['message'] .= "dengan data : " . $act_model->description;
                        $notif_params['rel_type'] = \Model\NotificationsModel::TYPE_STOCK_IN;
                        $notif_params['rel_id'] = $act_model->id;

                        $notif_params['issue_number'] = $model->tr_number;
                        $notif_params['rel_activity'] = 'PurchaseDetailActivity';
                        $notif_params['warehouse_id'] = $act_model->warehouse_id;
                        $this->_sendNotification($notif_params);

                        //fcm notice
                        $targets = ['fcm_cashier_'. $act_model->warehouse_id];
                        foreach ($targets as $i => $target) {
                            $fcm_data = ['rel_activity' => 'PurchaseDetailActivity', 'rel_id' => $act_model->id, 'rel_type' => \Model\NotificationsModel::TYPE_STOCK_IN, 'warehouse_id' => $act_model->warehouse_id];
                            $this->sendFCMNotification("Stok Terverifikasi", $notif_params['message'], $fcm_data, $target);
                        }
                    }

                    // also send to manager
                    $notif_params['recipients'] = [];
                    $whs_models = $_whs_model->getManagers(['warehouse_id' => $act_model->warehouse_id]);
                    foreach ($whs_models as $i => $whs_model) {
                        array_push($notif_params['recipients'], $whs_model['admin_id']);
                    }

                    if (count($notif_params['recipients']) > 0) {
                        $wh_mod = \Model\WarehousesModel::model()->findByPk($act_model->warehouse_id);
                        $notif_params['message'] = "Stok masuk ke " . $wh_mod->title  ." telah terverifikasi otomatis ";
                        $notif_params['message'] .= "dengan data : " . $act_model->description;
                        $notif_params['rel_type'] = \Model\NotificationsModel::TYPE_STOCK_IN;
                        $notif_params['rel_id'] = $act_model->id;

                        $notif_params['issue_number'] = $model->tr_number;
                        $notif_params['rel_activity'] = 'PurchaseDetailActivity';
                        $notif_params['warehouse_id'] = $act_model->warehouse_id;
                        $this->_sendNotification($notif_params);

                        //fcm notice
						if (!$dont_fcm) {
		                    $targets = ['fcm_cashier_'. $act_model->warehouse_id];
		                    foreach ($targets as $i => $target) {
		                        $fcm_data = ['rel_activity' => 'PurchaseDetailActivity', 'rel_id' => $act_model->id, 'rel_type' => \Model\NotificationsModel::TYPE_STOCK_IN, 'warehouse_id' => $act_model->warehouse_id];
		                        $this->sendFCMNotification("Stok Masuk Terverifikasi Otomatis", $notif_params['message'], $fcm_data, $target);
		                    }
						}
                    }
                }

                if ($tot_quantity > 0) {
                    // directly add to wh stock
                    try {
                        $add_to_stock = \Pos\Controllers\TransfersController::_add_to_stock(['tr_id' => $model->id, 'admin_id' => $model->created_by]);
                    } catch (\Exception $e) {
                        var_dump($e->getMessage());
                    }
                }
            }
        }
    }

	public function get_delete_history($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);

        if (!$isAllowed['allow']) {
            $result = [
                'success' => 0,
                'message' => $isAllowed['message'],
            ];
            return $response->withJson($result, 201);
        }

		if (!isset($args['id'])) {
            $result = [
                'success' => 0,
                'message' => 'Unable to remove data',
            ];
            return $response->withJson($result, 201);
        }

        $result = ['success' => 0];
        $params = $request->getParams();
		if (isset($params['admin_id']) && isset($params['id']) && ($params['id'] == $args['id'])) {
            $model = \Model\ActivitiesModel::model()->findByPk($params['id']);
            if ($model instanceof \RedBeanPHP\OODBBean) {
				if ($model->group_master > 0) {
					$delete = \Model\ActivitiesModel::model()->deleteAllByAttributes(['group_id' => $model->group_id]);
				} else {
					$delete = \Model\ActivitiesModel::model()->delete($model);
				}
				$result['success'] = 1;
				$result['message'] = 'Data telah berhasil dihapus';
			}
		}

        return $response->withJson($result, 201);
    }

    /**
     * @param $group_id
     * @return array
     * ex result : array(2) {
     * ["surplus"]=> array(4) { ["barcode"]=> int(12) ["stock_in"]=> string(2) "11" ["stock_out"]=> string(2) "10" ["selisih"]=> int(1) }
     * ["defisit"]=> array(4) { ["barcode"]=> int(1) ["stock_in"]=> string(2) "19" ["stock_out"]=> string(2) "20" ["selisih"]=> int(-1) }
     * }
     */
    private function getMissedTransferItems($group_id) {
        $models = \Model\ActivitiesModel::model()->findAllByAttributes(['group_id' => $group_id]);
        $missed_items = [];
        foreach ($models as $model) {
            $configs = json_decode($model->configs, true);
            if (array_key_exists('items', $configs)) {
                $items = $configs['items'];
                foreach ($items as $i => $item) {
                    $missed_items[$item['barcode']][$model->type] = $item['quantity'];
                }
            }
        }

        $missed_data = []; $has_max_missed =  0;
        $max_missed = 20; // maximum missed item for auto adjust
        if (count($missed_items) > 0) {
            $type_in = \Model\ActivitiesModel::TYPE_STOCK_IN;
            $type_out = \Model\ActivitiesModel::TYPE_STOCK_OUT;
            foreach ($missed_items as $barcode => $missed_item) {
                if (array_key_exists($type_in, $missed_item)
                    && array_key_exists($type_out, $missed_item)
                    && ($missed_item[$type_in] != $missed_item[$type_out])) {
                    $selisih = $missed_item[$type_in] - $missed_item[$type_out];
                    if ($missed_item[$type_in] > $missed_item[$type_out]) {
                        $missed_data['surplus'][$barcode] = ['barcode' => $barcode, $type_in => $missed_item[$type_in], $type_out => $missed_item[$type_out], 'selisih' => $selisih];
                        $missed_data['need_adjust'][$barcode] = $missed_item[$type_in];
                    } elseif ($missed_item[$type_in] < $missed_item[$type_out]) {
                        $missed_data['defisit'][$barcode] = ['barcode' => $barcode, $type_in => $missed_item[$type_in], $type_out => $missed_item[$type_out], 'selisih' => $selisih];
                    }
                    if ($selisih > $max_missed) {
                        $has_max_missed = $has_max_missed + 1;
                    }
                }
            }
        }

        $missed_data['avoid_auto_adjust'] = false;
        if ($has_max_missed > 0) {
            $missed_data['avoid_auto_adjust'] = true;
        }

        return $missed_data;
    }
}
