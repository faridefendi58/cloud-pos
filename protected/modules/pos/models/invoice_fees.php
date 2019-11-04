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
        $sql = 'SELECT t.*, i.serie AS invoice_serie, i.nr AS invoice_nr, i.status AS invoice_status, i.config AS invoice_configs,
            w.title AS warehouse_name, SUM(ii.price*ii.quantity) AS total_revenue, SUM(t.fee) AS total_fee, COUNT(t.id) AS total_transaction     
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

	public function getSummaryData($data = array())
    {
        $sql = 'SELECT DATE_FORMAT(t.created_at, "%Y-%m-%d") AS created_date, COUNT(t.invoice_id) AS total_transaction, 
			SUM(ii.price*ii.quantity) AS total_revenue, SUM(t.fee) AS total_fee     
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
}
