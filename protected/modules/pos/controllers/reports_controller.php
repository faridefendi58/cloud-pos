<?php

namespace Pos\Controllers;

use Components\BaseController as BaseController;

class ReportsController extends BaseController
{
    protected $_login_url = '/pos/default/login';
    
    public function __construct($app, $user)
    {
        parent::__construct($app, $user);
    }

    public function register($app)
    {
        $app->map(['GET'], '/stock', [$this, 'stock']);
        $app->map(['GET'], '/activity', [$this, 'activity']);
        $app->map(['GET'], '/sales', [$this, 'sales']);
        $app->map(['GET'], '/daily-transaction', [$this, 'daily_transaction']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['stock'],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['stock', 'activity', 'sales', 'daily-transaction'],
                'expression' => $this->hasAccess('pos/reports/read'),
            ],
            ['deny',
                'users' => ['*'],
            ],
        ];
    }

    public function stock($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }
        
        $model = new \Model\WarehousesModel();
        $warehouses = $model->getData();

        $stocks = []; $purchases = []; $params = []; $transfers = [];
        if (isset($_GET['wh'])) {
            $warehouse = $model->model()->findByPk($_GET['wh']);
            $psmodel = new \Model\ProductStocksModel();

            if (isset($_GET['start'])) {
                $date_start = date("Y-m-d", $_GET['start']/1000);
            } else {
                $date_start = date("Y-m-").'01';
            }

            if (isset($_GET['end'])) {
                $date_end = date("Y-m-d", $_GET['end']/1000);
            } else {
                $date_end = date("Y-m-d");
            }

            $params = [
                'warehouse_id' => $warehouse->id,
                'date_start' => $date_start,
                'date_end' => $date_end
            ];

            $stocks = $psmodel->getQuery($params);
        }

        return $this->_container->module->render(
            $response, 
            'reports/stock.html',
            [
                'warehouses' => $warehouses,
                'warehouse' => isset($_GET['wh'])? $warehouse : false,
                'stocks' => $stocks,
            ]
        );
    }

    public function activity($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\WarehousesModel();
        $warehouses = $model->getData();

        $stocks = []; $purchases = []; $params = []; $transfers = [];
        if (isset($_GET['wh'])) {
            $warehouse = $model->model()->findByPk($_GET['wh']);
            $psmodel = new \Model\ProductStocksModel();

            if (isset($_GET['start'])) {
                $date_start = date("Y-m-d", $_GET['start']/1000);
            } else {
                $date_start = date("Y-m-").'01';
            }

            if (isset($_GET['end'])) {
                $date_end = date("Y-m-d", $_GET['end']/1000);
            } else {
                $date_end = date("Y-m-d");
            }

            $params = [
                'warehouse_id' => $warehouse->id,
                'date_start' => $date_start,
                'date_end' => $date_end
            ];

            $prmodel = new \Model\PurchaseReceiptsModel();
            $params2 = array_merge($params, ['status'=>\Model\PurchaseReceiptsModel::STATUS_COMPLETED]);
            $purchases = $prmodel->getQuery($params2);

            $trmodel = new \Model\TransferReceiptsModel();
            $params3 = array_merge($params, ['transfer_out'=>true]);
            $transfers = $trmodel->getQuery($params3);

            $timodel = new \Model\TransferIssuesModel();
            $params4 = array_merge($params, ['warehouse_from' => $warehouse->id]);
            $transfer_issues = $timodel->getData($params4);

            $iimodel = new \Model\InventoryIssuesModel();
            $inventory_issues = $iimodel->getData($params);
        }

        return $this->_container->module->render(
            $response,
            'reports/activity.html',
            [
                'warehouses' => $warehouses,
                'warehouse' => isset($_GET['wh'])? $warehouse : false,
                'purchases' => $purchases,
                'params' => $params,
                'transfers' => $transfers,
                'transfer_issues' => $transfer_issues,
                'inventory_issues' => $inventory_issues
            ]
        );
    }

    public function sales($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\WarehousesModel();
        $warehouses = $model->getData();
        $whp_model = new \Model\WarehouseProductsModel();
        $products = $whp_model->getWhProducts();

        $datas = [];
        if (isset($_GET['wh'])) {
            $warehouse = $model->model()->findByPk($_GET['wh']);
            $products = $whp_model->getWhProducts(['warehouse_id' => $warehouse->id]);
            $ph_model = new \Model\PaymentHistoryModel();

            if (isset($_GET['start'])) {
                $date_start = date("Y-m-d", $_GET['start']/1000);
            } else {
                $date_start = date("Y-m-").'01';
            }

            if (isset($_GET['end'])) {
                $date_end = date("Y-m-d", $_GET['end']/1000);
            } else {
                $date_end = date("Y-m-d");
            }

            $params = [
                'warehouse_id' => $warehouse->id,
                'date_start' => $date_start,
                'date_end' => $date_end
            ];

            $datas = $ph_model->getRangeSales($params);
        }

        return $this->_container->module->render(
            $response,
            'reports/sales.html',
            [
                'warehouses' => $warehouses,
                'warehouse' => isset($_GET['wh'])? $warehouse : false,
                'products' => $products,
                'datas' => $datas,
            ]
        );
    }

    public function daily_transaction($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\WarehousesModel();
        $warehouses = $model->getData();
        $whp_model = new \Model\WarehouseProductsModel();
        $products = $whp_model->getWhProducts();

        $datas = [];
        if (isset($_GET['wh'])) {
            $warehouse = $model->model()->findByPk($_GET['wh']);
            $products = $whp_model->getWhProducts(['warehouse_id' => $warehouse->id]);
            $ph_model = new \Model\PaymentHistoryModel();

            if (isset($_GET['start'])) {
                $date_start = date("Y-m-d", $_GET['start']/1000);
            } else {
                $date_start = date("Y-m-d");
            }

            if (isset($_GET['end'])) {
                $date_end = date("Y-m-d", $_GET['end']/1000);
            } else {
                $date_end = date("Y-m-d");
            }

            $params = [
                'warehouse_id' => $warehouse->id,
                'date_start' => $date_start,
                'date_end' => $date_end
            ];

            $datas = $ph_model->getDailyTransactions($params);
            /*echo '<pre>';
            print_r($datas);
            echo '</pre>';
            exit;*/
        }

        return $this->_container->module->render(
            $response,
            'reports/daily_transaction.html',
            [
                'warehouses' => $warehouses,
                'warehouse' => isset($_GET['wh'])? $warehouse : false,
                'products' => $products,
                'datas' => $datas,
            ]
        );
    }
}