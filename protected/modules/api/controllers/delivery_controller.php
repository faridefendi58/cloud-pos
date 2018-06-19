<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;

class DeliveryController extends BaseController
{
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/list', [$this, 'get_list']);
        $app->map(['POST'], '/update-item', [$this, 'get_update']);
        $app->map(['POST'], '/delete-item', [$this, 'get_delete']);
        $app->map(['POST'], '/confirm-receipt', [$this, 'get_confirm_receipt']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['list', 'update-item', 'delete-item', 'confirm-receipt'],
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
        $do_model = new \Model\DeliveryOrdersModel();
        $params = $request->getParams();
        $params_data = [];
        if (isset($params['status'])) {
            $params_data['status'] = $params['status'];
        }

        if (isset($params['po_id'])) {
            $params_data['po_id'] = $params['po_id'];
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
                    //$suplier_id[$spl['supplier_id']] = $spl['supplier_id'];
                    array_push($suplier_id, $spl['supplier_id']);
                }
            }
            if (count($suplier_id) > 0) {
                $params_data['supplier_id'] = $suplier_id;
            }
        }

        $result_data = $do_model->getData($params_data);
        if (is_array($result_data) && count($result_data)>0) {
            $result['success'] = 1;
            foreach ($result_data as $i => $do_result) {
                $result['data'][] = $do_result['do_number'];
                $result['detail'][$do_result['do_number']] = $do_result;
                $result['po_data'][$do_result['do_number']] = $do_result['po_number'];
                $result['po_origin'][$do_result['do_number']] = $do_result['supplier_name'];
                $result['po_destination'][$do_result['do_number']] = $do_result['wh_group_name'];
            }
        }

        return $response->withJson($result, 201);
    }

    public function get_update($request, $response, $args)
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
        $model = null;
        if (isset($params['po_item_id'])) {
            $model = \Model\PurchaseOrderItemsModel::model()->findByPk($params['po_item_id']);
        }

        if ($model != null) {
            $old_quantity = $model->quantity;
            if (isset($params['quantity'])) {
                $model->quantity = (int) $params['quantity'];
                $model->available_qty = (int) $params['quantity'];
            }

            $model->updated_at = date("Y-m-d H:i:s");
            $model->updated_by = $params['admin_id'];
            $update = \Model\PurchaseOrderItemsModel::model()->update($model);
            if ($update) {
                // create logs
                $logs_model = new \Model\PurchaseOrderLogsModel();
                $logs_model->po_id = $model->po_id;
                $logs_model->notes = 'Jumlah '. $model->title .' diubah dari '. $old_quantity .' menjadi '.
                    $model->quantity .' '. $model->unit;

                if (!empty($params['admin_id'])) {
                    $admin_model = \Model\AdminModel::model()->findByPk($params['admin_id']);
                    if ($admin_model instanceof \RedBeanPHP\OODBBean) {
                        $logs_model->notes .= ' oleh '. $admin_model->name;
                    }
                }

                $logs_model->created_at = date("Y-m-d H:i:s");
                $logs_model->updated_by = $params['admin_id'];
                $save_logs = \Model\PurchaseOrderLogsModel::model()->save($logs_model);

                $result['success'] = 1;
                $result['message'] = 'Data item berhasil diubah.';
            } else {
                $result['message'] = 'Data item gagal diubah.';
            }
        }

        return $response->withJson($result, 201);
    }

    public function get_delete($request, $response, $args)
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
        $model = null;
        if (isset($params['po_item_id'])) {
            $model = \Model\PurchaseOrderItemsModel::model()->findByPk($params['po_item_id']);
        }

        if ($model != null) {
            $notes = $model->title .' dengan jumlah '. $model->quantity .' telah dihapus';
            $delete = \Model\PurchaseOrderItemsModel::model()->delete($model);
            if ($delete) {
                // create logs
                $logs_model = new \Model\PurchaseOrderLogsModel();
                $logs_model->po_id = $model->po_id;
                $logs_model->notes = $notes;

                if (!empty($params['admin_id'])) {
                    $admin_model = \Model\AdminModel::model()->findByPk($params['admin_id']);
                    if ($admin_model instanceof \RedBeanPHP\OODBBean) {
                        $logs_model->notes .= ' oleh '. $admin_model->name;
                    }
                }

                $logs_model->created_at = date("Y-m-d H:i:s");
                $logs_model->updated_by = $params['admin_id'];
                $save_logs = \Model\PurchaseOrderLogsModel::model()->save($logs_model);

                $result['success'] = 1;
                $result['message'] = 'Data item berhasil dihapus.';
            } else {
                $result['message'] = 'Data item gagal dihapus.';
            }
        }

        return $response->withJson($result, 201);
    }

    public function get_confirm_receipt($request, $response, $args)
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
        if (isset($params['do_number'])) {
            $model = \Model\DeliveryOrdersModel::model()->findByAttributes(['do_number' => $params['do_number']]);
            if ($model instanceof \RedBeanPHP\OODBBean) {
                $po_model = \Model\PurchaseOrdersModel::model()->findByPk($model->po_id);
                if (isset($params['notes'])) {
                    $notes = $po_model->notes;
                    if (!empty($notes))
                        $notes .= "<br/>";
                    $notes .= $params['notes'];
                    $po_model->notes = $notes;
                }
                $po_model->received_at = date("Y-m-d H:i:s");
                $po_model->received_by = $params['admin_id'];
                $po_model->updated_at = date("Y-m-d H:i:s");
                $update = \Model\PurchaseOrdersModel::model()->update($po_model);
                if ($update) {
                    $model->status = \Model\DeliveryOrdersModel::STATUS_COMPLETED;
                    $model->completed_at = date("Y-m-d H:i:s");
                    $model->completed_by = $params['admin_id'];
                    $update2 = \Model\DeliveryOrdersModel::model()->update($model);

                    $admin_model = \Model\AdminModel::model()->findByPk($params['admin_id']);
                    // send notification
                    $notif_params = [];
                    $notif_params['recipients'] = [$model->created_by];
                    $whg_model = \Model\WarehouseGroupsModel::model()->findByPk($po_model->wh_group_id);
                    if ($whg_model instanceof \RedBeanPHP\OODBBean && !empty($whg_model->pic)) {
                        $pics = array_keys(json_decode($whg_model->pic, true));
                        $notif_params['recipients'] = array_merge($notif_params['recipients'], $pics);
                    }

                    if (!in_array($po_model->created_by, $notif_params['recipients']))
                        array_push($notif_params['recipients'], $po_model->created_by);

                    $notif_params['message'] = "Nomor pengiriman ".$model->do_number." untuk Purchase Order ".$po_model->po_number;
                    $notif_params['message'] .= " telah diterima oleh ".$admin_model->name." pada tanggal ".date("d F Y H:i", strtotime($po_model->received_at));
                    if (!empty($params['notes']))
                        $notif_params['message'] .= " dengan rincian : ".$params['notes'];
                    $notif_params['rel_id'] = $po_model->id;
                    $notif_params['rel_type'] = \Model\NotificationsModel::TYPE_PURCHASE_ORDER;

                    $notif_params['issue_number'] = $model->do_number;
                    $notif_params['rel_activity'] = 'DeliveryActivity';
                    $this->_sendNotification($notif_params);

                    $result['success'] = 1;
                    $result['message'] = $notif_params['message'];
                }
            } else {
                $result['message'] = 'Nomor pengiriman tidak ditemukan.';
            }
        }

        return $response->withJson($result, 201);
    }
}
