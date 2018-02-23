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
            wh.title AS warehouse_from_name, wht.title AS warehouse_to_name 
            FROM {tablePrefix}ext_transfer_receipt t 
            LEFT JOIN {tablePrefix}ext_transfer_issue ti ON ti.id = t.ti_id 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = ti.warehouse_from 
            LEFT JOIN {tablePrefix}ext_warehouse wht ON wht.id = ti.warehouse_to 
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
            ti.ti_number, ti.warehouse_from, ti.warehouse_to, wh.title AS warehouse_from_name, wht.title AS warehouse_to_name
            FROM {tablePrefix}ext_transfer_receipt t 
            LEFT JOIN {tablePrefix}ext_transfer_issue ti ON ti.id = t.ti_id 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = ti.warehouse_from 
            LEFT JOIN {tablePrefix}ext_warehouse wht ON wht.id = ti.warehouse_to  
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
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
}
