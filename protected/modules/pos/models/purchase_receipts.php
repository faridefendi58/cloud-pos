<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class PurchaseReceiptsModel extends \Model\BaseModel
{
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELED = 'canceled';
    
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_purchase_receipt';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['po_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($data = array())
    {
        $sql = 'SELECT t.*, po.po_number, wh.title AS warehouse_name, po.supplier_id, sp.name AS supplier_name,
            a.name AS completed_by_name 
            FROM {tablePrefix}ext_purchase_receipt t 
            LEFT JOIN {tablePrefix}ext_purchase_order po ON po.id = t.po_id 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = t.warehouse_id 
            LEFT JOIN {tablePrefix}ext_supplier sp ON sp.id = po.supplier_id 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.completed_by 
            WHERE 1';

        $params = [];
        if (isset($data['po_id'])) {
            $sql .= ' AND t.po_id =:po_id';
            $params['po_id'] = $data['po_id'];
        }

        $sql .= ' ORDER BY t.id DESC';
        
        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    /**
     * @param $id
     * @return array
     */
    public function getDetail($id)
    {
        $sql = 'SELECT t.*, a.name AS created_by_name, ab.name AS updated_by_name, 
            po.po_number, wh.title AS warehouse_name, po.supplier_id, sp.name AS supplier_name, 
            ac.name AS completed_by_name 
            FROM {tablePrefix}ext_purchase_receipt t 
            LEFT JOIN {tablePrefix}ext_purchase_order po ON po.id = t.po_id 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = t.warehouse_id 
            LEFT JOIN {tablePrefix}ext_supplier sp ON sp.id = po.supplier_id  
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
            LEFT JOIN {tablePrefix}admin ac ON ac.id = t.completed_by 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

    public function getLastPrNumber($pr_serie = null)
    {
        $sql = 'SELECT MAX(t.pr_nr) AS max_nr 
            FROM {tablePrefix}ext_purchase_receipt t 
            WHERE 1';

        $params = [];
        if (!empty($po_serie)) {
            $sql .= ' AND t.pr_serie =:pr_serie';
            $params['pr_serie'] = $pr_serie;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row;
    }

    public function getHistory($data)
    {
        $sql = 'SELECT pr.pr_number, pr.warehouse_id, wh.title AS warehouse_name, pr.effective_date, t.*, 
            po.supplier_id, sp.name AS supplier_name 
            FROM {tablePrefix}ext_purchase_receipt_item t 
            LEFT JOIN {tablePrefix}ext_purchase_receipt pr ON pr.id = t.pr_id 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = pr.warehouse_id 
            LEFT JOIN {tablePrefix}ext_purchase_order po ON po.id = pr.po_id 
            LEFT JOIN {tablePrefix}ext_supplier sp ON sp.id = po.supplier_id 
            WHERE 1';

        $params = [];
        if (isset($data['product_id'])) {
            $sql .= ' AND t.product_id =:product_id';
            $params = [ 'product_id' => $data['product_id'] ];
        }

        if (!empty($data['warehouse_id'])) {
            $sql .= ' AND pr.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (!empty($data['status'])) {
            $sql .= ' AND pr.status =:status';
            $params['status'] = $data['status'];
        }

        if (isset($data['date_start']) && isset($data['date_end'])) {
            $sql .= ' AND DATE_FORMAT(pr.completed_at, "%Y-%m-%d") BETWEEN :date_start AND :date_end';
            $params['date_start'] = $data['date_start'];
            $params['date_end'] = $data['date_end'];
        }

        $sql .= ' ORDER BY pr.completed_at DESC';

        if (!isset($data['limit'])) {
            $sql .= ' LIMIT 100';
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function getQuery($data)
    {
        $sql = 'SELECT wh.title AS warehouse_name, t.*, 
            po.supplier_id, sp.name AS supplier_name, a.name AS completed_by_name,
            po.po_number AS issue_number 
            FROM {tablePrefix}ext_purchase_receipt t 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = t.warehouse_id 
            LEFT JOIN {tablePrefix}ext_purchase_order po ON po.id = t.po_id 
            LEFT JOIN {tablePrefix}ext_supplier sp ON sp.id = po.supplier_id 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.completed_by 
            WHERE 1';

        $params = [];

        if (!empty($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (!empty($data['status'])) {
            $sql .= ' AND t.status =:status';
            $params['status'] = $data['status'];
        }

        if (isset($data['date_start']) && isset($data['date_end'])) {
            $sql .= ' AND DATE_FORMAT(t.completed_at, "%Y-%m-%d") BETWEEN :date_start AND :date_end';
            $params['date_start'] = $data['date_start'];
            $params['date_end'] = $data['date_end'];
        }

        if (!empty($data['wh_group_id'])) {
            if (!is_array($data['wh_group_id'])) {
                $sql .= ' AND wh.group_id =:group_id';
                $params['group_id'] = $data['wh_group_id'];
            } else {
                $group_id = implode(", ", $data['wh_group_id']);
                $sql .= ' AND wh.group_id IN ('.$group_id.')';
            }
        }

        $sql .= ' ORDER BY t.effective_date DESC';

        if (!isset($data['limit'])) {
            $sql .= ' LIMIT 100';
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }
}
