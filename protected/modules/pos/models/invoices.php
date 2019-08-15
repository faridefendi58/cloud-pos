<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class InvoicesModel extends \Model\BaseModel
{
    const STATUS_PAID = 1;
    const STATUS_UNPAID = 0;
    const STATUS_REFUND = 2;

    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_invoice';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [];
    }

    public function getInvoiceNumber($status, $type)
    {
        $sql = 'SELECT MAX(t.nr) AS max_nr 
            FROM {tablePrefix}ext_invoice t 
            WHERE t.status =:status';

        $params = [ 'status' => $status ];

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        $pmodel = new \Model\OptionsModel();
        $ext_pos = $pmodel->getOption('ext_pos');
        if ($status == self::STATUS_PAID) {
            if (!empty($ext_pos['paid_invoice_series'])) {
                $serie = $ext_pos['paid_invoice_series'];
            } else {
                $serie = 'PAID-'. date("Y").'-';
            }
        } elseif ($status == self::STATUS_UNPAID) {
            if (!empty($ext_pos['unpaid_invoice_series'])) {
                $serie = $ext_pos['unpaid_invoice_series'];
            } else {
                $serie = 'UNPAID-'. date("Y").'-';
            }
        } else {
            if (!empty($ext_pos['refund_invoice_series'])) {
                $serie = $ext_pos['refund_invoice_series'];
            } else {
                $serie = 'REFUND-'. date("Y").'-';
            }
        }

        $next_nr = $row['max_nr'] +1;
        if($type == 'serie')
            return $serie;
        else
            return $next_nr;
    }

    public function getInvoiceFormatedNumber($data = array())
    {
        if(in_array('id', array_keys($data)) && $data['id'] == 0)
            $model = $this;
        else
            $model = self::model()->findByPk($data['id']);

        $nr = str_repeat('0',4-strlen($model->nr));

        return $model->serie.$nr.$model->nr;
    }

    public function getInvoiceFormatedNumber2($serie, $nr)
    {
        $zero = str_repeat('0',4-strlen($nr));

        return $serie.$zero.$nr;
    }

    public function getData($data = array())
    {
        $sql = 'SELECT t.*, SUM(i.price*i.quantity) AS total, c.name AS customer_name, 
            c.email as customer_email, c.telephone AS customer_phone, c.address AS customer_address, c.status AS customer_status, 
            w.title AS warehouse_name 
            FROM {tablePrefix}ext_invoice t 
            LEFT JOIN {tablePrefix}ext_invoice_item i ON t.id = i.invoice_id 
            LEFT JOIN {tablePrefix}ext_customer c ON c.id = t.customer_id 
            LEFT JOIN {tablePrefix}ext_warehouse w ON w.id = t.warehouse_id 
            WHERE 1';

        $params = [];
        if (isset($data['status'])) {
            $sql .= ' AND t.status =:status';
            $params['status'] = $data['status'];
        }

        if (isset($data['status_order'])) {
            if (strtolower($data['status_order']) == 'selesai') {
                $sql .= ' AND t.status = 1 AND t.delivered = 1';
            } elseif (strtolower($data['status_order']) == 'lunas') {
                $sql .= ' AND t.status = 1 AND t.delivered = 0';
            } elseif (strtolower($data['status_order']) == 'utang_tempo') {
                $sql .= ' AND t.status = 0 AND t.delivered = 1';
            } elseif (strtolower($data['status_order']) == 'belum_lunas') {
                $sql .= ' AND t.status = 0 AND t.delivered = 0';
            }
        }

        if (isset($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['created_at_from']) && isset($data['created_at_to'])) {
            $sql .= ' AND t.created_at BETWEEN :created_at_from AND :created_at_to';
            $params['created_at_from'] = date("Y-m-d H:i:s", strtotime($data['created_at_from']));
            $params['created_at_to'] = date("Y-m-d H:i:s", strtotime($data['created_at_to']));
        }

        if (isset($data['paid_at_from']) && isset($data['paid_at_to'])) {
            $sql .= ' AND t.paid_at BETWEEN :paid_at_from AND :paid_at_to';
            $params['paid_at_from'] = date("Y-m-d H:i:s", strtotime($data['paid_at_from']));
            $params['paid_at_to'] = date("Y-m-d H:i:s", strtotime($data['paid_at_to']));
        }

        if (isset($data['delivered_at_from']) && isset($data['delivered_at_to'])) {
            $sql .= ' AND t.delivered_at BETWEEN :delivered_at_from AND :delivered_at_to';
            $params['delivered_at_from'] = date("Y-m-d H:i:s", strtotime($data['delivered_at_from']));
            $params['delivered_at_to'] = date("Y-m-d H:i:s", strtotime($data['delivered_at_to']));
        }

        if (isset($data['delivered_plan_at_from']) && isset($data['delivered_plan_at_to'])) {
            $sql .= ' AND t.delivered_plan_at BETWEEN :delivered_plan_at_from AND :delivered_plan_at_to';
            $params['delivered_plan_at_from'] = date("Y-m-d H:i:s", strtotime($data['delivered_plan_at_from']));
            $params['delivered_plan_at_to'] = date("Y-m-d H:i:s", strtotime($data['delivered_plan_at_to']));
        }

		if (isset($data['customer_name'])) {
            $sql .= ' AND LOWER(c.name) LIKE "%'. strtolower($data['customer_name']) .'%"';
        }

		if (isset($data['customer_phone'])) {
            $sql .= ' AND c.telephone =:phone ';
			$params['phone'] = $data['customer_phone'];
        }

		if (isset($data['invoice_number'])) {
            $sql .= ' AND t.nr =:invoice_number ';
			if (($pos = strpos($data['invoice_number'], "-")) !== FALSE) {
				$exp = explode("-", $data['invoice_number']);
				$nr = @end($exp);
				$params['invoice_number'] = (int)$nr;
			} else {
				$params['invoice_number'] = (int)$data['invoice_number'];
			}
        }

        $sql .= ' GROUP BY t.id';
        if (!isset($data['order_by'])) {
            $sql .= ' ORDER BY t.created_at DESC';
        } else {
            $order_type = 'DESC';
            if (isset($data['order_type'])) {
                $order_type = $data['order_type'];
            }
            $sql .= ' ORDER BY t.'. $data['order_by'] .' '.$order_type;
        }

        if (!isset($data['limit'])) {
            $sql .= ' LIMIT 20';
        } else {
            $sql .= ' LIMIT '.$data['limit'];
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function listStatus()
    {
        return [
            self::STATUS_UNPAID => 'Unpaid',
            self::STATUS_PAID => 'Paid',
            self::STATUS_REFUND => 'Retur',
        ];
    }

    public function getStatus($data)
    {
        $statuses = self::listStatus();

        return $statuses[$data['status']];
    }

    public function getSeries()
    {
        $sql = 'SELECT t.serie 
            FROM {tablePrefix}ext_invoice t  
            WHERE 1';

        $params = [];

        $sql .= ' GROUP BY t.serie';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function getItem($data = array())
    {
        $sql = 'SELECT t.id, t.serie, t.nr, t.notes, t.status, t.created_at, t.paid_at, 
            SUM(ii.price*ii.quantity) AS total, t.discount,
            t.customer_id, c.name AS customer_name, 
            t.warehouse_id, w.title AS warehouse_name, t.config, 
            t.created_by, a.name AS created_by_name, 
            t.paid_by, ab.name AS paid_by_name, 
            t.refunded_by, ac.name AS refunded_by_name,
            t.delivered, t.delivered_at, ad.name AS delivered_by_name 
            FROM {tablePrefix}ext_invoice t 
            JOIN {tablePrefix}ext_invoice_item ii ON t.id = ii.invoice_id 
            LEFT JOIN {tablePrefix}ext_customer c ON c.id = t.customer_id 
            LEFT JOIN {tablePrefix}ext_warehouse w ON w.id = t.warehouse_id 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.paid_by 
            LEFT JOIN {tablePrefix}admin ac ON ac.id = t.refunded_by 
            LEFT JOIN {tablePrefix}admin ad ON ad.id = t.delivered_by 
            WHERE 1';

        $params = [];
        if (isset($data['status'])) {
            $sql .= ' AND t.status =:status';
            $params['status'] = $data['status'];
        }

        if (isset($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['id'])) {
            $sql .= ' AND t.id =:id';
            $params['id'] = $data['id'];
        }

        if (isset($data['serie'])) {
            $sql .= ' AND t.serie =:serie';
            $params['serie'] = $data['serie'];
        }

        if (isset($data['nr'])) {
            $sql .= ' AND t.nr =:nr';
            $params['nr'] = $data['nr'];
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row;
    }
}
