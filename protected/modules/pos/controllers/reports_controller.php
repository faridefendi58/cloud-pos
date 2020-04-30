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
        $app->map(['GET'], '/inventory-history', [$this, 'inventory_history']);
    }

    public function accessRules()
    {
        return [
            ['allow',
                'actions' => ['stock'],
                'users'=> ['@'],
            ],
            ['allow',
                'actions' => ['stock', 'activity', 'sales', 'daily-transaction', 'inventory-history'],
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

        $datas = []; $fees = [];
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
            $f_model = new \Model\InvoiceFeesModel();
            $fees = $f_model->getWHSFeeEachDate($params);
        }

        return $this->_container->module->render(
            $response,
            'reports/sales.html',
            [
                'warehouses' => $warehouses,
                'warehouse' => isset($_GET['wh'])? $warehouse : false,
                'products' => $products,
                'datas' => $datas,
                'fees' => $fees
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

    public function inventory_history($request, $response, $args)
    {
        $isAllowed = $this->isAllowed($request, $response);
        if ($isAllowed instanceof \Slim\Http\Response)
            return $isAllowed;

        if(!$isAllowed){
            return $this->notAllowedAction();
        }

        $model = new \Model\WarehousesModel();
        $warehouses = $model->getData();
        $products = [];
        $ti_model = new \Model\TransferIssuesModel();
        $tr_model = new \Model\TransferReceiptsModel();
        $ii_model = new \Model\InventoryIssuesModel();
        $i_model = new \Model\InvoicesModel();

        $date_start = date("Y-m-01");
        $date_end = date("Y-m-d");
        $datas = []; $non_transactions = [];
        if (isset($_GET['wh'])) {
            $warehouse = $model->model()->findByPk($_GET['wh']);
            $products = $model->getProducts(['warehouse_id'=>$_GET['wh']]);

            if (isset($_GET['start'])) {
                $date_start = date("Y-m-d", $_GET['start']/1000);
            } else {
                $date_start = date("Y-m-01");
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

            $datas = $ti_model->getWHHistory($params);
            $non_transactions = $ii_model->nonTransactions($params);
            $transactions = $i_model->getTransactionHistory($params);
        }

        $non_transaction_types = [];
        $ext_pos = $this->_container->get('settings')['params']['ext_pos'];
        if (!empty($ext_pos)) {
            $ext_pos = json_decode($ext_pos, true);
            if (is_array($ext_pos) && array_key_exists('non_transaction_type', $ext_pos)) {
                $non_transaction_types = $ext_pos['non_transaction_type'];
            }
        }

        return $this->_container->module->render(
            $response,
            'reports/inventory_history.html',
            [
                'warehouses' => $warehouses,
                'warehouse' => isset($_GET['wh'])? $warehouse : false,
                'ti_model' => $ti_model,
                'tr_model' => $tr_model,
                'ii_model' => $ii_model,
                'i_model' => $i_model,
                'products' => $products,
                'datas' => $datas,
                'non_transactions' => $non_transactions,
                'date_start' => $date_start,
                'date_end' => $date_end,
                'non_transaction_types' => $non_transaction_types,
                'transactions' => $transactions
            ]
        );
    }
}