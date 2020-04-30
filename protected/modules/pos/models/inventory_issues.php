<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class InventoryIssuesModel extends \Model\BaseModel
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
        return 'ext_inventory_issue';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['ii_number', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($data = null)
    {
        $sql = 'SELECT t.*, a.name AS admin_name, wf.title AS warehouse_name  
            FROM {tablePrefix}ext_inventory_issue t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_warehouse wf ON wf.id = t.warehouse_id 
            WHERE 1';

        $params = [];
        if (is_array($data)) {
            if (isset($data['status'])) {
                $sql .= ' AND t.status =:status';
                $params['status'] = $data['status'];
            }
            if (isset($data['warehouse_id'])) {
                $sql .= ' AND t.warehouse_id =:warehouse_id';
                $params['warehouse_id'] = $data['warehouse_id'];
            }

            if (isset($data['wh_group_id'])) {
                if (is_array($data['wh_group_id'])) {
                    $group_id = implode(", ", $data['wh_group_id']);
                    $sql .= ' AND wf.group_id IN (' . $group_id . ')';
                }
            }
        }

        $sql .= ' ORDER BY t.effective_date DESC';

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
        $sql = 'SELECT t.*,  a.name AS created_by_name, ab.name AS updated_by_name, 
            wf.title AS warehouse_name    
            FROM {tablePrefix}ext_inventory_issue t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
            LEFT JOIN {tablePrefix}ext_warehouse wf ON wf.id = t.warehouse_id 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

    public function getLastIiNumber($ii_serie = null)
    {
        $sql = 'SELECT MAX(t.ii_nr) AS max_nr 
            FROM {tablePrefix}ext_inventory_issue t 
            WHERE 1';

        $params = [];
        if (!empty($ii_serie)) {
            $sql .= ' AND t.ii_serie =:ii_serie';
            $params['ii_serie'] = $ii_serie;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row;
    }

    public function getHistory($data)
    {
        $sql = 'SELECT ii.ii_number, ii.warehouse_id, ii.effective_date, ii.notes, t.* 
            FROM {tablePrefix}ext_inventory_issue_item t 
            LEFT JOIN {tablePrefix}ext_inventory_issue ii ON ii.id = t.ii_id 
            WHERE t.product_id =:product_id';

        $params = [ 'product_id' => $data['product_id'] ];
        if (!empty($data['warehouse_id'])) {
            $sql .= ' AND ii.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (!empty($data['status'])) {
            $sql .= ' AND ii.status =:status';
            $params['status'] = $data['status'];
        }

        if (isset($data['date_start']) && isset($data['date_end'])) {
            $sql .= ' AND DATE_FORMAT(ii.completed_at, "%Y-%m-%d") BETWEEN :date_start AND :date_end';
            $params['date_start'] = $data['date_start'];
            $params['date_end'] = $data['date_end'];
        }

        $sql .= ' ORDER BY ii.completed_at DESC';

        if (!isset($data['limit'])) {
            $sql .= ' LIMIT 100';
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function nonTransactions($data = []) {
        $sql = 'SELECT t.id, DATE_FORMAT(t.created_at, "%Y-%m-%d") AS created_date, t.type 
            FROM {tablePrefix}ext_inventory_issue t  
            WHERE t.status =:status';

        $params = [];
        if (!empty($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (!empty($data['status'])) {
            $params['status'] = $data['status'];
        } else {
            $params['status'] = self::STATUS_COMPLETED;
        }

        if (isset($data['date_start']) && isset($data['date_end'])) {
            $sql .= ' AND DATE_FORMAT(t.created_at, "%Y-%m-%d") BETWEEN :date_start AND :date_end';
            $params['date_start'] = $data['date_start'];
            $params['date_end'] = $data['date_end'];
        }

        $sql .= ' ORDER BY t.created_at DESC';

        if (!isset($data['limit'])) {
            $sql .= ' LIMIT 100';
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function getItems($ii_id = 0) {
        $sql = 'SELECT t.product_id, t.title, t.quantity, t.unit  
            FROM {tablePrefix}ext_inventory_issue_item t    
            WHERE 1';

        $params = [];
        if ($ii_id > 0) {
            $sql .= ' AND t.ii_id =:ii_id';
            $params['ii_id'] = $ii_id;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        $items = [];
        foreach ($rows as $i => $row) {
            $items[$row['product_id']] = $row;
        }

        return $items;
    }
}
