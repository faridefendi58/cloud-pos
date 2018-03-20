<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class PurchaseOrdersModel extends \Model\BaseModel
{
    const STATUS_ON_PROCESS = 'onprocess';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELED = 'canceled';

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_purchase_order';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['po_number', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($data = null)
    {
        $sql = 'SELECT t.*, a.name AS admin_name, s.name AS supplier_name, sh.title AS shipment_name  
            FROM {tablePrefix}ext_purchase_order t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_supplier s ON s.id = t.supplier_id 
            LEFT JOIN {tablePrefix}ext_shipment sh ON sh.id = t.shipment_id  
            WHERE 1';

        $params = [];
        if (is_array($data)) {
            if (isset($data['status'])) {
                $sql .= ' AND t.status =:status';
                $params['status'] = $data['status'];
            }
        }

        $sql .= ' ORDER BY t.date_order DESC';

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
            s.name AS supplier_name 
            FROM {tablePrefix}ext_purchase_order t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
            LEFT JOIN {tablePrefix}ext_supplier s ON s.id = t.supplier_id 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

    public function getLastPoNumber($po_serie = null)
    {
        $sql = 'SELECT MAX(t.po_nr) AS max_nr 
            FROM {tablePrefix}ext_purchase_order t 
            WHERE 1';

        $params = [];
        if (!empty($po_serie)) {
            $sql .= ' AND t.po_serie =:po_serie';
            $params['po_serie'] = $po_serie;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row;
    }
}