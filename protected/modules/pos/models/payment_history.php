<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class PaymentHistoryModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_payment_history';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['invoice_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData()
    {
        $sql = 'SELECT t.*   
            FROM {tablePrefix}ext_payment_history t
            LEFT JOIN {tablePrefix}ext_invoice i ON i.id = t.invoice_id 
            WHERE 1';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql );

        return $rows;
    }

    public function getDataByInvoice() {
        $sql = 'SELECT t.*, (SELECT COUNT(h.id) AS tot FROM {tablePrefix}ext_payment_history h WHERE h.invoice_id = t.id) AS payment_count, 
            wh.title AS warehouse_name   
            FROM {tablePrefix}ext_invoice t
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = t.warehouse_id 
            WHERE 1';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql );

        return $rows;
    }

    public function getDailyTransactions($data = []) {
        $sql = 'SELECT DATE_FORMAT(t.created_at, "%Y-%m-%d %H:%i:%s") AS trans_date, t.channel_id, SUM((t.amount - t.change_due)) AS amnt, t.invoice_id,
            c.name AS customer_name, i.customer_id
            FROM {tablePrefix}ext_payment_history t
            LEFT JOIN {tablePrefix}ext_invoice i ON i.id = t.invoice_id 
            LEFT JOIN {tablePrefix}ext_customer c ON c.id = i.customer_id 
            WHERE 1';

        $params = [];
        if (isset($data['warehouse_id'])) {
            $sql .= ' AND i.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['date_start']) && isset($data['date_end'])) {
            $sql .= ' AND (DATE_FORMAT(t.created_at, "%Y-%m-%d") BETWEEN :date_start AND :date_end)';
            $params['date_start'] = $data['date_start'];
            $params['date_end'] = $data['date_end'];
        } else {
            $data['date_start'] = date("Y-m").'-01';
            $data['date_end'] = date("Y-m-d");
        }

        $sql .= ' GROUP BY t.invoice_id ORDER BY t.created_at ASC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $dates = R::getAll( $sql, $params );
        $rows = [];
        $periods = new \DatePeriod(new \DateTime($data['date_start']), new \DateInterval('P1D'), new \DateTime($data['date_end']));
        foreach ($periods as $p => $period) {
            if (strtotime($period->format('Y-m-d')) <= time()) {
                $rows[$period->format('Y-m-d')] = [];
            }
        }
        if (count($dates) > 0) {
            foreach ($dates as $date) {
                $rows[$date['trans_date']]['customer'] =  ['id' => $date['customer_id'], 'name' => $date['customer_name']];
                $sql2 = 'SELECT t.channel_id, SUM((t.amount - t.change_due)) AS amnt   
                    FROM {tablePrefix}ext_payment_history t
                    LEFT JOIN {tablePrefix}ext_invoice i ON i.id = t.invoice_id 
                    WHERE t.invoice_id =:invoice_id';

                $params2 = ['invoice_id' => $date['invoice_id']];

                $sql2 .= ' GROUP BY t.channel_id';

                $sql2 = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql2);

                $channels = R::getAll( $sql2, $params2 );
                $_items = [];
                if (count($channels) > 0) {
                    foreach ($channels as $channel) {
                        $_items[$channel['channel_id']] = $channel['amnt'];
                    }
                }
                $rows[$date['trans_date']]['payments'] =  $_items;

                $sql3 = 'SELECT DISTINCT t.invoice_id, o.product_id, o.quantity, 
                    (SELECT SUM(it.quantity) FROM tbl_ext_invoice_item it WHERE it.invoice_id = t.invoice_id) AS tot_qty, 
                    o.price, COALESCE(o.discount, 0) AS discount
                    FROM {tablePrefix}ext_payment_history t 
                    LEFT JOIN {tablePrefix}ext_order o ON o.invoice_id = t.invoice_id
                    WHERE t.invoice_id =:invoice_id';

                $params3 = ['invoice_id' => $date['invoice_id']];

                //$sql3 .= ' GROUP BY o.product_id';

                $sql3 = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql3);

                $products = R::getAll( $sql3, $params3 );
                $_items = [];
                if (count($products) > 0) {
                    foreach ($products as $product) {
                        if ($product['tot_qty'] <5) {
                            $_items[$product['product_id']]['min1'] = (int)$product['quantity'];
                        } elseif ($product['tot_qty'] >=5 && $product['tot_qty'] <10) {
                            $_items[$product['product_id']]['min5'] = (int)$product['quantity'];
                        } elseif ($product['tot_qty'] >=10 && $product['tot_qty'] <30) {
                            $_items[$product['product_id']]['min10'] = (int)$product['quantity'];
                        } elseif ($product['tot_qty'] >=30) {
                            $_items[$product['product_id']]['min30'] = (int)$product['quantity'];
                        }
                        $_items[$product['product_id']]['tot_price'] = $_items[$product['product_id']]['tot_price'] + (($product['price'] - $product['discount'])*$product['quantity']);
                    }
                }
                $rows[$date['trans_date']]['products'] =  $_items;
            }
        }

        return $rows;
    }

    public function getRangeSales($data = []) {
        $sql = 'SELECT DATE_FORMAT(t.created_at, "%Y-%m-%d") AS trans_date, t.channel_id, SUM((t.amount - t.change_due)) AS amnt   
            FROM {tablePrefix}ext_payment_history t
            LEFT JOIN {tablePrefix}ext_invoice i ON i.id = t.invoice_id 
            WHERE 1';

        $params = [];
        if (isset($data['warehouse_id'])) {
            $sql .= ' AND i.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['date_start']) && isset($data['date_end'])) {
            $sql .= ' AND (DATE_FORMAT(t.created_at, "%Y-%m-%d") BETWEEN :date_start AND :date_end)';
            $params['date_start'] = $data['date_start'];
            $params['date_end'] = $data['date_end'];
        } else {
            $data['date_start'] = date("Y-m").'-01';
            $data['date_end'] = date("Y-m-d");
        }

        $sql .= ' GROUP BY trans_date ORDER BY trans_date ASC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $dates = R::getAll( $sql, $params );
        $rows = [];
        $periods = new \DatePeriod(new \DateTime($data['date_start']), new \DateInterval('P1D'), new \DateTime($data['date_end']));
        foreach ($periods as $p => $period) {
            if (strtotime($period->format('Y-m-d')) <= time()) {
                $rows[$period->format('Y-m-d')] = [];
            }
        }
        if (count($dates) > 0) {
            foreach ($dates as $date) {
                $sql2 = 'SELECT t.channel_id, SUM((t.amount - t.change_due)) AS amnt   
                    FROM {tablePrefix}ext_payment_history t
                    LEFT JOIN {tablePrefix}ext_invoice i ON i.id = t.invoice_id 
                    WHERE DATE_FORMAT(t.created_at, "%Y-%m-%d") =:trans_date';

                $params2 = ['trans_date' => $date['trans_date']];
                if (isset($data['warehouse_id'])) {
                    $sql2 .= ' AND i.warehouse_id =:warehouse_id';
                    $params2['warehouse_id'] = $data['warehouse_id'];
                }

                $sql2 .= ' GROUP BY t.channel_id';

                $sql2 = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql2);

                $channels = R::getAll( $sql2, $params2 );
                $_items = [];
                if (count($channels) > 0) {
                    foreach ($channels as $channel) {
                        $_items[$channel['channel_id']] = $channel['amnt'];
                    }
                }
                $rows[$date['trans_date']]['payments'] =  $_items;

                $sql3 = 'SELECT DISTINCT t.invoice_id, o.product_id, o.quantity, 
                    (SELECT SUM(it.quantity) FROM tbl_ext_invoice_item it WHERE it.invoice_id = t.invoice_id) AS tot_qty, 
                    o.price, COALESCE(o.discount, 0) AS discount
                    FROM {tablePrefix}ext_payment_history t 
                    LEFT JOIN {tablePrefix}ext_order o ON o.invoice_id = t.invoice_id
                    WHERE DATE_FORMAT(t.created_at, "%Y-%m-%d") =:trans_date';

                $params3 = ['trans_date' => $date['trans_date']];
                if (isset($data['warehouse_id'])) {
                    $sql3 .= ' AND o.warehouse_id =:warehouse_id';
                    $params3['warehouse_id'] = $data['warehouse_id'];
                }

                $sql3 = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql3);

                $products = R::getAll( $sql3, $params3 );
                $_items = [];
                if (count($products) > 0) {
                    foreach ($products as $product) {
                        if ($product['tot_qty'] <5) {
                            $_items[$product['product_id']]['min1'] = (int)$product['quantity'];
                        } elseif ($product['tot_qty'] >=5 && $product['tot_qty'] <10) {
                            $_items[$product['product_id']]['min5'] = (int)$product['quantity'];
                        } elseif ($product['tot_qty'] >=10 && $product['tot_qty'] <30) {
                            $_items[$product['product_id']]['min10'] = (int)$product['quantity'];
                        } elseif ($product['tot_qty'] >=30) {
                            $_items[$product['product_id']]['min30'] = (int)$product['quantity'];
                        }
                        $_items[$product['product_id']]['tot_price'] = $_items[$product['product_id']]['tot_price'] + (($product['price'] - $product['discount'])*$product['quantity']);
                    }
                }
                $rows[$date['trans_date']]['products'] =  $_items;
            }
        }

        return $rows;
    }
}
