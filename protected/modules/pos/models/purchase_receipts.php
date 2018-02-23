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
        $sql = 'SELECT t.*, po.po_number, wh.title AS warehouse_name, po.supplier_id, sp.name AS supplier_name     
            FROM {tablePrefix}ext_purchase_receipt t 
            LEFT JOIN {tablePrefix}ext_purchase_order po ON po.id = t.po_id 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = t.warehouse_id 
            LEFT JOIN {tablePrefix}ext_supplier sp ON sp.id = po.supplier_id  
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
            po.po_number, wh.title AS warehouse_name, po.supplier_id, sp.name AS supplier_name  
            FROM {tablePrefix}ext_purchase_receipt t 
            LEFT JOIN {tablePrefix}ext_purchase_order po ON po.id = t.po_id 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = t.warehouse_id 
            LEFT JOIN {tablePrefix}ext_supplier sp ON sp.id = po.supplier_id  
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
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
}
