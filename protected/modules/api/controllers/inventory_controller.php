<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;
use function FastRoute\TestFixtures\empty_options_cached;

class InventoryController extends BaseController
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
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['create', 'list', 'detail', 'create-v2'],
                'users'=> ['@'],
            ]
        ];
    }

    /**
     * @param $request: admin_id, items, warehouse_from
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

            if (count($purchase_items) <= 0) {
                $result = ["success" => 0, "message" => "Pastikan pilih item sebelum disimpan."];
                return $response->withJson($result, 201);
            }

            if (isset($params['warehouse_name'])) {
                $whmodel = \Model\WarehousesModel::model()->findByAttributes(['title' => $params['warehouse_name']]);
                if ($whmodel instanceof \RedBeanPHP\OODBBean) {
                    $params['warehouse_id'] = $whmodel->id;
                }
            }

            /*if (isset($params['due_date'])) {
                $params['due_date'] = date("Y-m-d H:i:s", strtotime($params['due_date']));
            }*/

            $model = new \Model\InventoryIssuesModel();
            $ii_number = \Pos\Controllers\InventoriesController::get_ii_number();
            $model->ii_number = $ii_number['serie_nr'];
            $model->ii_serie = $ii_number['serie'];
            $model->ii_nr = $ii_number['nr'];
            if (isset($params['warehouse_id']))
                $model->warehouse_id = $params['warehouse_id'];
            $model->status = \Model\InventoryIssuesModel::STATUS_PENDING;
            if (isset($params['notes']))
                $model->notes = $params['notes'];
            if (isset($params['effective_date'])) {
                $model->effective_date = date("Y-m-d H:i:s", strtotime($params['effective_date']));
                $model->created_at = $model->effective_date;
            } else {
                $model->effective_date = date("Y-m-d H:i:s");
                $model->created_at = date("Y-m-d H:i:s");
            }
            $model->created_by = (isset($params['admin_id'])) ? $params['admin_id'] : 1;
            $save = \Model\InventoryIssuesModel::model()->save(@$model);
            if ($save) {
                $tot_price = 0; $stock_updated = 0;
                foreach ($purchase_items as $product_id => $quantity) {
                    $product = \Model\ProductsModel::model()->findByPk($product_id);
                    $imodel[$product_id] = new \Model\InventoryIssueItemsModel();
                    $imodel[$product_id]->ii_id = $model->id;
                    $imodel[$product_id]->product_id = $product_id;
                    $imodel[$product_id]->title = $product->title;
                    $imodel[$product_id]->quantity = $quantity;
                    $imodel[$product_id]->unit = $product->unit;
                    $imodel[$product_id]->price = $product->current_cost;
                    $imodel[$product_id]->created_at = $model->created_at;
                    $imodel[$product_id]->created_by = $model->created_by;

                    if ($product_id > 0 && $imodel[$product_id]->quantity > 0) {
                        $save2 = \Model\InventoryIssueItemsModel::model()->save($imodel[$product_id]);
                        if ($save2) {
                            $tot_price = $tot_price + ($imodel[$product_id]->price * $quantity);
                            // update stock
                            $stock_params = ['product_id' => $product_id, 'warehouse_id' => $model->warehouse_id];
                            $stock = \Model\ProductStocksModel::model()->findByAttributes($stock_params);
                            $update_stock = false;
                            if ($stock instanceof \RedBeanPHP\OODBBean) {
                                $stock->quantity = $stock->quantity - $quantity;
                                $stock->updated_at = date("Y-m-d H:i:s");
                                $stock->updated_by = $model->created_by;
                                $update_stock = \Model\ProductStocksModel::model()->update($stock);
                            }
                            if ($update_stock) {
                                // also update current price
                                $pmodel = new \Model\ProductsModel();
                                $current_cost = $pmodel->getCurrentCost($product_id);
                                $product->current_cost = $current_cost;
                                $product->updated_at = date("Y-m-d H:i:s");
                                $product->updated_by = $model->created_by;
                                $update_product = \Model\ProductsModel::model()->update($product);
                                $stock_updated = $stock_updated + 1;
                            }
                        }
                    }
                }

                // updating price of po data
                if ($tot_price > 0) {
                    $result = [
                        "success" => 1,
                        "id" => $model->id,
                        'message' => 'Data berhasil disimpan.',
                        "issue_number" => $model->ii_number
                    ];
                    // update status
                    if ($stock_updated > 0) {
                        $ii_model = \Model\InventoryIssuesModel::model()->findByPk($model->id);
                        if ($ii_model instanceof \RedBeanPHP\OODBBean) {
                            $ii_model->status = \Model\InventoryIssuesModel::STATUS_COMPLETED;
                            $ii_model->updated_at = date("Y-m-d H:i:s");
                            $update_status = \Model\InventoryIssuesModel::model()->update($ii_model);
                        }
                    }
                } else {
                    $result = ["success" => 0, "message" => "Tidak ada item yang dapat disimpan."];
                }
            } else {
                $result = [
                    "success" => 0,
                    "message" => \Model\InventoryIssuesModel::model()->getErrors(false, false, false)
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
        $po_model = new \Model\InventoryIssuesModel();
        $params = $request->getParams();
        //$status = \Model\PurchaseOrdersModel::STATUS_ON_PROCESS;
        //$params_data = ['status' => $status];
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

        $result_data = $po_model->getData($params_data);
        if (is_array($result_data) && count($result_data)>0) {
            $result['success'] = 1;
            $it_models = new \Model\InventoryIssueItemsModel();
            foreach ($result_data as $i => $po_result) {
                $result['data'][] = $po_result['ii_number'];
                $result['origin'][$po_result['ii_number']] = $po_result['warehouse_name'];
                $result['detail'][] = $po_result;
                $items = $it_models->getData($po_result['id']);
                $items_in_string = [];
                if (is_array($items)) {
                    foreach ($items as $i => $item) {
                        array_push($items_in_string, $item['title'].' ('.$item['quantity'].')');
                    }
                }
                $result['items'][$po_result['ii_number']] = implode(", ", $items_in_string);
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

        $result = ['success' => 0];
        $params = $request->getParams();
        if (isset($params['admin_id']) && isset($params['items'])) {
			$model = new \Model\InventoryIssuesModel();
            $ii_number = \Pos\Controllers\InventoriesController::get_ii_number();
            $model->ii_number = $ii_number['serie_nr'];
            $model->ii_serie = $ii_number['serie'];
            $model->ii_nr = $ii_number['nr'];
            if (isset($params['warehouse_id']))
                $model->warehouse_id = $params['warehouse_id'];
            $model->status = \Model\InventoryIssuesModel::STATUS_PENDING;
            if (isset($params['notes']))
                $model->notes = $params['notes'];
            if (isset($params['effective_date'])) {
                $model->effective_date = date("Y-m-d H:i:s", strtotime($params['effective_date']));
                $model->created_at = $model->effective_date;
            } else {
                $model->effective_date = date("Y-m-d H:i:s");
                $model->created_at = date("Y-m-d H:i:s");
            }
            $model->created_by = (isset($params['admin_id'])) ? $params['admin_id'] : 1;
            $save = \Model\InventoryIssuesModel::model()->save(@$model);
            if ($save) {
                $tot_price = 0; $stock_updated = 0; $desc = [];
                foreach ($params['items'] as $i => $item) {
					$product = \Model\ProductsModel::model()->findByPk($item['barcode']);
					$product_id = $product->id;
					$quantity = $item['quantity'];
                    $imodel[$product_id] = new \Model\InventoryIssueItemsModel();
                    $imodel[$product_id]->ii_id = $model->id;
                    $imodel[$product_id]->product_id = $product_id;
                    $imodel[$product_id]->title = $product->title;
                    $imodel[$product_id]->quantity = $quantity;
                    $imodel[$product_id]->unit = $product->unit;
                    $imodel[$product_id]->price = $product->current_cost;
                    $imodel[$product_id]->created_at = $model->created_at;
                    $imodel[$product_id]->created_by = $model->created_by;

                    if ($product_id > 0 && $imodel[$product_id]->quantity > 0) {
                        $save2 = \Model\InventoryIssueItemsModel::model()->save($imodel[$product_id]);
                        if ($save2) {
                            $tot_price = $tot_price + ($imodel[$product_id]->price * $quantity);
                            // update stock
                            $stock_params = ['product_id' => $product_id, 'warehouse_id' => $model->warehouse_id];
                            $stock = \Model\ProductStocksModel::model()->findByAttributes($stock_params);
                            $update_stock = false;
                            if ($stock instanceof \RedBeanPHP\OODBBean) {
                                $stock->quantity = $stock->quantity - $quantity;
                                $stock->updated_at = date("Y-m-d H:i:s");
                                $stock->updated_by = $model->created_by;
                                $update_stock = \Model\ProductStocksModel::model()->update($stock);
                            }
                            if ($update_stock) {
                                // also update current price
                                $pmodel = new \Model\ProductsModel();
                                $current_cost = $pmodel->getCurrentCost($product_id);
                                $product->current_cost = $current_cost;
                                $product->updated_at = date("Y-m-d H:i:s");
                                $product->updated_by = $model->created_by;
                                $update_product = \Model\ProductsModel::model()->update($product);
                                $stock_updated = $stock_updated + 1;
                            }
							$desc[] = $product->title .' : -'. $quantity;
                        }
                    }
                }

                // updating price of po data
                if ($tot_price > 0) {
                    $result = [
                        "success" => 1,
                        "id" => $model->id,
                        'message' => 'Data berhasil disimpan.',
                        "issue_number" => $model->ii_number
                    ];
                    // update status
                    if ($stock_updated > 0) {
                        $ii_model = \Model\InventoryIssuesModel::model()->findByPk($model->id);
                        if ($ii_model instanceof \RedBeanPHP\OODBBean) {
                            $ii_model->status = \Model\InventoryIssuesModel::STATUS_COMPLETED;
                            $ii_model->updated_at = date("Y-m-d H:i:s");
                            $update_status = \Model\InventoryIssuesModel::model()->update($ii_model);
                        }
                    }
					// add logs
					try {
						$act_model = new \Model\ActivitiesModel();
						$act_model->title = 'Stok Keluar (Non Penjualan) '. $model->ii_number;
						$act_model->rel_id = $model->id;
						$act_model->type = \Model\ActivitiesModel::TYPE_INVENTORY_ISSUE;
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
                } else {
                    $result = ["success" => 0, "message" => "Tidak ada item yang dapat disimpan."];
                }
            } else {
                $result = [
                    "success" => 0,
                    "message" => \Model\InventoryIssuesModel::model()->getErrors(false, false, false)
                ];
            }
		}

		return $response->withJson($result, 201);
	}

}
