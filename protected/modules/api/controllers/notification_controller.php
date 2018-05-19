<?php

namespace Api\Controllers;

use Components\ApiBaseController as BaseController;
use function FastRoute\TestFixtures\empty_options_cached;

class NotificationController extends BaseController
{
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/list', [$this, 'get_list']);
        $app->map(['POST'], '/read', [$this, 'get_read']);
        $app->map(['GET'], '/count', [$this, 'get_count']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['list', 'read', 'count'],
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
        $params = $request->getParams();
        $pmodel = new \Model\NotificationsModel();

        if (!isset($params['status']))
            $params['status'] = \Model\NotificationRecipientsModel::STATUS_UNREAD;

        $items = $pmodel->getData(['admin_id'=>$params['admin_id'],'status'=>$params['status']]);
        if (is_array($items)){
            $result['success'] = 1;
            $items2 = [];
            foreach ($items as $i => $item) {
                if ($item['rel_id'] > 0 && !empty($item['rel_type'])) {
                    switch ($item['rel_type']) {
                        case 'purchase_order':
                            $rmodel = new \Model\PurchaseOrdersModel();
                            $item['rel_detail'] = $rmodel->getDetail($item['rel_id']);
                            break;
                        case 'transfer_issue':
                            $rmodel = new \Model\TransferIssuesModel();
                            $item['rel_detail'] = $rmodel->getDetail($item['rel_id']);
                            break;
                        case 'inventory_issue':
                            $rmodel = new \Model\InventoryIssuesModel();
                            $item['rel_detail'] = $rmodel->getDetail($item['rel_id']);
                            break;
                    }
                }
                $items2[$i] = $item;
            }
            $result['data'] = $items2;
        } else {
            $result = [
                'success' => 0,
                'message' => "Data notifikasi tidak ditemukan.",
            ];
        }

        return $response->withJson($result, 201);
    }

    public function get_read($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);

        if (!$isAllowed['allow']) {
            $result = [
                'success' => 0,
                'message' => $isAllowed['message'],
            ];
            return $response->withJson($result, 201);
        }

        $params = $request->getParams();
        $result = [ 'success' => 0 ];
        if (isset($params['notification_id']) && isset($params['admin_id'])) {
            $model = \Model\NotificationRecipientsModel::model()->findByAttributes(['notification_id'=>$params['notification_id'], 'admin_id'=>$params['admin_id']]);
            if ($model instanceof \RedBeanPHP\OODBBean) {
                $model->status = \Model\NotificationRecipientsModel::STATUS_READ;
                $model->updated_at = date("Y-m-d H:i:s");
                $update = \Model\NotificationRecipientsModel::model()->update($model);
                if ($update) {
                    $result['success'] = 1;
                    $result['message'] = 'Data berhasil diubah';
                } else {
                    $result['message'] = 'Data gagal diubah';
                }
            } else {
                $result['message'] = 'Data notifikasi tidak ditemukan.';
            }
        } else {
            $result['message'] = 'Data notifikasi tidak ditemukan.';
        }

        return $response->withJson($result, 201);
    }

    public function get_count($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);

        if (!$isAllowed['allow']) {
            $result = [
                'success' => 0,
                'message' => $isAllowed['message'],
            ];
            return $response->withJson($result, 201);
        }

        $params = $request->getParams();
        $result = [ 'success' => 0, 'count' => 0 ];
        if (isset($params['admin_id'])) {
            $qry_params = [
                'status' => \Model\NotificationRecipientsModel::STATUS_UNREAD,
                'admin_id' => $params['admin_id']
            ];
            $count = \Model\NotificationRecipientsModel::model()->count($qry_params);
            if ($count > 0) {
                $result['success'] = 1;
                $result['message'] = "Ada ". $count ." notifikasi baru yang belum dibaca.";
                $result['count'] = (int) $count;
            } else {
                $result['message'] = "Tidak ditemukan notifikasi baru.";
            }
        }

        return $response->withJson($result, 201);
    }
}