<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class TransferReceiptsModel extends \Model\BaseModel
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
        return 'ext_transfer_receipt';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['ti_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($data = array())
    {
        $sql = 'SELECT t.*, ti.ti_number, ti.warehouse_from, ti.warehouse_to, 
            wh.title AS warehouse_from_name, wht.title AS warehouse_to_should_be, whi.title AS warehouse_to_name,
            a.name AS completed_by_name 
            FROM {tablePrefix}ext_transfer_receipt t 
            LEFT JOIN {tablePrefix}ext_transfer_issue ti ON ti.id = t.ti_id 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = ti.warehouse_from 
            LEFT JOIN {tablePrefix}ext_warehouse wht ON wht.id = ti.warehouse_to 
            LEFT JOIN {tablePrefix}ext_warehouse whi ON whi.id = t.warehouse_id 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.completed_by  
            WHERE 1';

        $params = [];
        if (isset($data['ti_id'])) {
            $sql .= ' AND t.ti_id =:ti_id';
            $params['ti_id'] = $data['ti_id'];
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
            ti.ti_number, ti.warehouse_from, ti.warehouse_to, wh.title AS warehouse_from_name,  
            whi.title AS warehouse_to_name, wht.title AS warehouse_to_should_be, 
             ac.name AS completed_by_name
            FROM {tablePrefix}ext_transfer_receipt t 
            LEFT JOIN {tablePrefix}ext_transfer_issue ti ON ti.id = t.ti_id 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = ti.warehouse_from 
            LEFT JOIN {tablePrefix}ext_warehouse wht ON wht.id = ti.warehouse_to  
            LEFT JOIN {tablePrefix}ext_warehouse whi ON whi.id = t.warehouse_id 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
            LEFT JOIN {tablePrefix}admin ac ON ac.id = t.completed_by 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

    public function getLastTrNumber($tr_serie = null)
    {
        $sql = 'SELECT MAX(t.tr_nr) AS max_nr 
            FROM {tablePrefix}ext_transfer_receipt t 
            WHERE 1';

        $params = [];
        if (!empty($ti_serie)) {
            $sql .= ' AND t.tr_serie =:tr_serie';
            $params['tr_serie'] = $tr_serie;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row;
    }

    public function getHistory($data)
    {
        $sql = 'SELECT tr.tr_number, ti.warehouse_from, 
            wh.title AS warehouse_from_name, tr.effective_date, t.*     
            FROM {tablePrefix}ext_transfer_receipt_item t 
            LEFT JOIN {tablePrefix}ext_transfer_receipt tr ON tr.id = t.tr_id 
            LEFT JOIN {tablePrefix}ext_transfer_issue ti ON ti.id = tr.ti_id 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = ti.warehouse_from  
            WHERE t.product_id =:product_id';

        $params = [ 'product_id' => $data['product_id'] ];
        if (!empty($data['warehouse_id'])) {
            $sql .= ' AND ti.warehouse_to =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (!empty($data['status'])) {
            $sql .= ' AND tr.status =:status';
            $params['status'] = $data['status'];
        }

        if (isset($data['date_start']) && isset($data['date_end'])) {
            $sql .= ' AND DATE_FORMAT(tr.completed_at, "%Y-%m-%d") BETWEEN :date_start AND :date_end';
            $params['date_start'] = $data['date_start'];
            $params['date_end'] = $data['date_end'];
        }

        $sql .= ' ORDER BY tr.completed_at DESC';

        if (!isset($data['limit'])) {
            $sql .= ' LIMIT 100';
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function hasInCompleteReceipt($ti_id)
    {
        $sql = 'SELECT COUNT(t.id) AS count      
            FROM {tablePrefix}ext_transfer_receipt t 
            WHERE t.ti_id =:ti_id AND t.status <> :status';

        $params = [ 'ti_id' => $ti_id, 'status' => self::STATUS_COMPLETED ];

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return ($row['count'] > 0)? true : false;
    }

    public function getRecipients($data)
    {
        $sql = 'SELECT t.warehouse_id, wh.title AS warehouse_name 
            FROM {tablePrefix}ext_transfer_receipt t 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = t.warehouse_id 
            WHERE t.ti_id =:ti_id';

        $params = [ 'ti_id' => $data['ti_id'] ];

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        if (isset($data['output']) && $data['output'] == 'text') {
            $wh_names = [];
            foreach ($rows as $i => $row) {
                if (!empty($row['warehouse_name']))
                    array_push($wh_names, $row['warehouse_name']);
            }
            
            return (is_array($wh_names))? implode(", ", $wh_names) : false;
        }

        return $rows;
    }

    public function getQuery($data)
    {
        $sql = 'SELECT wh.title AS warehouse_to_name, t.*, 
            ti.warehouse_from, wf.title AS warehouse_from_name, a.name AS completed_by_name,
            ti.ti_number AS issue_number   
            FROM {tablePrefix}ext_transfer_receipt t 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = t.warehouse_id 
            LEFT JOIN {tablePrefix}ext_transfer_issue ti ON ti.id = t.ti_id 
            LEFT JOIN {tablePrefix}ext_warehouse wf ON wf.id = ti.warehouse_from  
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

        if (isset($data['transfer_out'])) {
            $sql .= ' OR ti.warehouse_from =:warehouse_from';
            $params['warehouse_from'] = $data['warehouse_id'];
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

    public function getItems($tr_id = 0)
    {
        $sql = 'SELECT t.product_id, t.title, t.quantity, t.unit, ti.quantity AS quantity_issue  
            FROM {tablePrefix}ext_transfer_receipt_item t   
            LEFT JOIN {tablePrefix}ext_transfer_issue_item ti ON ti.id = t.ti_item_id 
            WHERE 1';

        $params = [];
        if ($tr_id > 0) {
            $sql .= ' AND t.tr_id =:tr_id';
            $params['tr_id'] = $tr_id;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        $items = [];
        foreach ($rows as $i => $row) {
            $row['selisih'] = (int)$row['quantity'] - (int)$row['quantity_issue'];
            $items[$row['product_id']] = $row;
        }

        return $items;
    }
}
