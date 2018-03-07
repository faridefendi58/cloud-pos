<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class InventoryReceiptsModel extends \Model\BaseModel
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
        return 'ext_inventory_receipt';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['ir_number', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($data = array())
    {
        $sql = 'SELECT t.*, wh.title AS warehouse_name 
            FROM {tablePrefix}ext_inventory_receipt t 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = t.warehouse_id 
            WHERE 1';

        $params = [];

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
            wh.title AS warehouse_name 
            FROM {tablePrefix}ext_inventory_receipt t 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = t.warehouse_id 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

    public function getLastIrNumber($ir_serie = null)
    {
        $sql = 'SELECT MAX(t.ir_nr) AS max_nr 
            FROM {tablePrefix}ext_inventory_receipt t 
            WHERE 1';

        $params = [];
        if (!empty($ir_serie)) {
            $sql .= ' AND t.ir_serie =:ir_serie';
            $params['ir_serie'] = $ir_serie;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row;
    }

    public function getHistory($data)
    {
        $sql = 'SELECT ir.ir_number, ir.warehouse_id, wh.title AS warehouse_name, ir.effective_date, ir.notes, t.*     
            FROM {tablePrefix}ext_inventory_receipt_item t 
            LEFT JOIN {tablePrefix}ext_inventory_receipt ir ON ir.id = t.ir_id 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = ir.warehouse_id 
            WHERE t.product_id =:product_id';

        $params = [ 'product_id' => $data['product_id'] ];
        if (!empty($data['warehouse_id'])) {
            $sql .= ' AND ir.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (!empty($data['status'])) {
            $sql .= ' AND ir.status =:status';
            $params['status'] = $data['status'];
        }

        if (isset($data['date_start']) && isset($data['date_end'])) {
            $sql .= ' AND DATE_FORMAT(ir.completed_at, "%Y-%m-%d") BETWEEN :date_start AND :date_end';
            $params['date_start'] = $data['date_start'];
            $params['date_end'] = $data['date_end'];
        }

        $sql .= ' ORDER BY ir.completed_at DESC';

        if (!isset($data['limit'])) {
            $sql .= ' LIMIT 100';
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }
}
