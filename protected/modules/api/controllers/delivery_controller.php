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
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['list'],
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
                    $suplier_id[$spl['supplier_id']] = $spl['supplier_id'];
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
                $result['detail'][$do_result['po_number']] = $do_result;
                $result['po_data'][$do_result['po_number']] = $do_result['po_number'];
                $result['po_origin'][$do_result['po_number']] = $do_result['supplier_name'];
                $result['po_destination'][$do_result['po_number']] = $do_result['wh_group_name'];
            }
        }

        return $response->withJson($result, 201);
    }
}