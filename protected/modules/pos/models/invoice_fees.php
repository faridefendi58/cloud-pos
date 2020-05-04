<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class InvoiceFeesModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_invoice_fee';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['warehouse_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($data = array())
    {
        $sql = 'SELECT t.*, i.serie AS invoice_serie, i.nr AS invoice_nr, i.status AS invoice_status, i.config AS invoice_configs, i.paid_at, i.delivered_at,
            w.title AS warehouse_name, (SELECT (SUM(ii.price*ii.quantity) - iv.discount) AS itot FROM {tablePrefix}ext_invoice_item ii 
            LEFT JOIN {tablePrefix}ext_invoice iv ON iv.id = ii.invoice_id WHERE ii.invoice_id = t.invoice_id) AS total_revenue, SUM(t.fee + t.fee_refund) AS total_fee, COUNT(t.id) AS total_transaction     
            FROM {tablePrefix}ext_invoice_fee t 
            LEFT JOIN {tablePrefix}ext_warehouse w ON w.id = t.warehouse_id 
            LEFT JOIN {tablePrefix}ext_invoice i ON i.id = t.invoice_id 
            LEFT JOIN {tablePrefix}ext_invoice_item ii ON ii.id = t.invoice_id 
            WHERE 1';

        $params = [];
        if (isset($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['admin_id'])) {
            $sql .= ' AND t.admin_id =:admin_id';
            $params['admin_id'] = $data['admin_id'];
        }

        if (isset($data['status'])) {
            $sql .= ' AND t.status =:status';
            $params['status'] = $data['status'];
        }

        if (isset($data['date_from'])) {
            $sql .= ' DATE_FORMAT(t.created_at,"%Y-%m-%d") BETWEEN :date_from AND :date_to';
            $params['created_at_from'] = $data['created_at_from'];
            if (!isset($data['created_at_to'])) {
                $data['created_at_to'] = date("Y-m-t", strtotime($data['created_at_from']));
            }
            $params['created_at_to'] = $data['created_at_to'];
        }

		if (isset($data['created_at'])) {
            $sql .= ' AND DATE_FORMAT(t.created_at,"%Y-%m-%d") =:created_at';
            $params['created_at'] = $data['created_at'];
        }

		if (isset($data['group_by'])) {
            $sql .= ' GROUP BY t.'. $data['group_by'];
        }

        $sql .= ' ORDER BY t.created_at ASC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

	public function getSummaryData1($data = array())
    {
        $sql = 'SELECT DATE_FORMAT(t.created_at, "%Y-%m-%d") AS created_date, COUNT(t.invoice_id) AS total_transaction, 
			SUM(ii.price*ii.quantity) AS total_revenue, SUM(t.fee + t.fee_refund) AS total_fee     
            FROM {tablePrefix}ext_invoice_fee t 
            LEFT JOIN {tablePrefix}ext_warehouse w ON w.id = t.warehouse_id 
            LEFT JOIN {tablePrefix}ext_invoice i ON i.id = t.invoice_id 
            LEFT JOIN {tablePrefix}ext_invoice_item ii ON ii.id = t.invoice_id 
            WHERE 1';

        $params = [];
        if (isset($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['admin_id'])) {
            $sql .= ' AND t.admin_id =:admin_id';
            $params['admin_id'] = $data['admin_id'];
        }

        if (isset($data['status'])) {
            $sql .= ' AND t.status =:status';
            $params['status'] = $data['status'];
        }

        if (isset($data['date_from'])) {
            $sql .= ' DATE_FORMAT(t.created_at,"%Y-%m-%d") BETWEEN :date_from AND :date_to';
            $params['created_at_from'] = $data['created_at_from'];
            if (!isset($data['created_at_to'])) {
                $data['created_at_to'] = date("Y-m-t", strtotime($data['created_at_from']));
            }
            $params['created_at_to'] = $data['created_at_to'];
        }

        $sql .= ' GROUP BY DATE_FORMAT(t.created_at, "%Y-%m-%d") ORDER BY t.created_at ASC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

	public function getSummaryData($data = array())
    {
        $where = '';

        $params = [];
        if (isset($data['warehouse_id'])) {
            $where .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['admin_id'])) {
            $where .= ' AND t.admin_id =:admin_id';
            $params['admin_id'] = $data['admin_id'];
        }

        if (isset($data['status'])) {
            $where .= ' AND t.status =:status';
            $params['status'] = $data['status'];
        }

        if (isset($data['created_at_from'])) {
            $where .= ' AND DATE_FORMAT(t.created_at,"%Y-%m-%d") BETWEEN :date_from AND :date_to';
            $params['date_from'] = $data['created_at_from'];
            if (!isset($data['created_at_to'])) {
                $data['created_at_to'] = date("Y-m-t", strtotime($data['created_at_from']));
            }
            $params['date_to'] = $data['created_at_to'];
        }

		$sql = 'SELECT cuk.created_date, SUM(cuk.total_revenue1) AS total_revenue, COUNT(cuk.created_date) AS total_transaction, SUM(cuk.total_fee1) AS total_fee, cuk.invoice_configs1 AS invoice_configs
			FROM (SELECT DATE_FORMAT(t.created_at, "%Y-%m-%d") AS created_date, 
				(SELECT (SUM(ii.price*ii.quantity) - iv.discount) AS itot FROM {tablePrefix}ext_invoice_item ii 
				   LEFT JOIN {tablePrefix}ext_invoice iv ON iv.id = ii.invoice_id WHERE ii.invoice_id = t.invoice_id) AS total_revenue1, 
				SUM(t.fee + t.fee_refund) AS total_fee1, i.config AS invoice_configs1 FROM {tablePrefix}ext_invoice_fee t 
				LEFT JOIN {tablePrefix}ext_invoice i ON i.id = t.invoice_id
            	WHERE 1 '. $where .'  
				GROUP BY t.invoice_id ORDER BY t.created_at ASC) AS cuk 
			GROUP BY cuk.created_date';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    /**
     * @param array $data: dates = array(), warehouse_id
     * @return array
     */
    public function getPaymentEachDateRange($data = array())
    {
        if (!array_key_exists('dates', $data)) {
            return [];
        }

        $date_range = "'". implode("', '", $data['dates']). "'";
        $params = ['warehouse_id' => $data['warehouse_id']];

        $sql = 'SELECT t.invoice_id, DATE_FORMAT(h.created_at,"%Y-%m-%d") AS created_date, ch.code AS pay_channel, t.amount 
          FROM {tablePrefix}ext_payment_history t 
          LEFT JOIN {tablePrefix}ext_invoice_fee h ON h.invoice_id = t.invoice_id
          LEFT JOIN {tablePrefix}ext_payment_channel ch ON ch.id = t.channel_id
          WHERE h.warehouse_id =:warehouse_id AND DATE_FORMAT(h.created_at,"%Y-%m-%d") IN ('. $date_range .')';

        $sql .= ' ORDER BY created_date ASC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    /**
     * @param array $data: date, warehouse_id
     * @return array
     */
    public function getPaymentEachDate($data = array())
    {
        if (!array_key_exists('date', $data)) {
            return [];
        }

        $params = ['warehouse_id' => $data['warehouse_id'], 'created_at' => $data['date']];

        $sql = 'SELECT t.invoice_id, ch.code AS pay_channel, SUM(t.amount) AS amount_tendered, SUM(t.amount - t.change_due) AS amount, t.change_due 
          FROM {tablePrefix}ext_payment_history t 
          LEFT JOIN {tablePrefix}ext_invoice_fee h ON h.invoice_id = t.invoice_id
          LEFT JOIN {tablePrefix}ext_payment_channel ch ON ch.id = t.channel_id
          WHERE h.warehouse_id =:warehouse_id AND DATE_FORMAT(h.created_at,"%Y-%m-%d") =:created_at';

        $sql .= ' GROUP BY ch.code ORDER BY ch.id ASC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

	public function getRefundData($data = array())
    {
        $where = '';

        $params = [];
        if (isset($data['warehouse_id'])) {
            $where .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['admin_id'])) {
            $where .= ' AND t.admin_id =:admin_id';
            $params['admin_id'] = $data['admin_id'];
        }

        if (isset($data['status'])) {
            $where .= ' AND t.status =:status';
            $params['status'] = $data['status'];
        }

        if (isset($data['created_at_from'])) {
            $where .= ' AND DATE_FORMAT(t.created_at,"%Y-%m-%d") BETWEEN :date_from AND :date_to';
            $params['date_from'] = $data['created_at_from'];
            if (!isset($data['created_at_to'])) {
                $data['created_at_to'] = date("Y-m-t", strtotime($data['created_at_from']));
            }
            $params['date_to'] = $data['created_at_to'];
        }

		if (isset($data['created_at'])) {
            $where .= ' AND DATE_FORMAT(t.created_at,"%Y-%m-%d") =:created_at';
			$params['created_at'] = $data['created_at'];
        }

		$sql = 'SELECT i.config FROM {tablePrefix}ext_invoice_fee t 
				LEFT JOIN {tablePrefix}ext_invoice i ON i.refunded_invoice_id = t.invoice_id
            	WHERE 1 '. $where .' AND i.id IS NOT NULL 
				GROUP BY t.invoice_id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

		$items = [];
		if (is_array($rows) && count($rows) > 0) {
			foreach ($rows as $i => $row) {
				$cfg = json_decode($row['config'], true);
				if (array_key_exists('payments', $cfg) && !empty($cfg['payments'])) {
					if (is_array($cfg['payments'])) {
						foreach ($cfg['payments'] as $j => $payment) {
							if (array_key_exists($payment['type'], $items)) {
								$items[$payment['type']] = $items[$payment['type']] + $payment['amount'];
							} else {
								$items[$payment['type']] = $payment['amount'] * 1;
							}
						}
					}
				}
			}
		}

        return $items;
    }

	public function getCounterData($data = array())
    {
        $where = '';

        $params = [];
        if (isset($data['warehouse_id'])) {
            $where .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['admin_id'])) {
            $where .= ' AND t.admin_id =:admin_id';
            $params['admin_id'] = $data['admin_id'];
        }

        if (isset($data['status'])) {
            $where .= ' AND t.status =:status';
            $params['status'] = $data['status'];
        }

        if (isset($data['created_at_from'])) {
            $where .= ' AND DATE_FORMAT(t.created_at,"%Y-%m-%d") BETWEEN :date_from AND :date_to';
            $params['date_from'] = $data['created_at_from'];
            if (!isset($data['created_at_to'])) {
                $data['created_at_to'] = date("Y-m-t", strtotime($data['created_at_from']));
            }
            $params['date_to'] = $data['created_at_to'];
        }

		$sql = 'SELECT t.invoice_id, ii.title, ii.quantity, 
			(SELECT SUM(it.quantity) FROM {tablePrefix}ext_invoice_item it WHERE it.invoice_id = t.invoice_id) AS tot_quantity,
			(SELECT i.config FROM {tablePrefix}ext_invoice i WHERE i.refunded_invoice_id = t.invoice_id) AS refund_configs
			FROM {tablePrefix}ext_invoice_fee t 
			LEFT JOIN {tablePrefix}ext_invoice_item ii ON ii.invoice_id = t.invoice_id
            WHERE 1 '. $where .'  
			ORDER BY t.created_at ASC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function getWHSFeeEachDate($data = []) {
        $params = [];
        $sql = 'SELECT SUM(t.fee - t.fee_refund) AS tot_fee, DATE_FORMAT(i.created_at,"%Y-%m-%d") AS invoice_date
			FROM {tablePrefix}ext_invoice_fee t 
			LEFT JOIN {tablePrefix}ext_invoice i ON i.id = t.invoice_id
            WHERE 1';

        if (isset($data['date_start'])) {
            $sql .= ' AND DATE_FORMAT(i.created_at,"%Y-%m-%d") BETWEEN :date_from AND :date_to';
            $params['date_from'] = $data['date_start'];
            if (!isset($data['date_end'])) {
                $data['date_end'] = date("Y-m-d", strtotime($data['date_to']));
            }
            $params['date_to'] = $data['date_end'];
        }

        if (isset($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id=:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        $sql .= ' GROUP BY DATE_FORMAT(t.created_at,"%Y-%m-%d") ORDER BY invoice_date ASC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);
        $rows = R::getAll( $sql, $params );

        $items = [];
        foreach ($rows as $row) {
            $items[$row['invoice_date']] = (int)$row['tot_fee'];
        }

        return $items;
    }
}
