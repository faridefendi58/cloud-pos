<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class PurchaseOrderLogsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_purchase_order_log';
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
    public function getData($po_id = 0, $in_string = false)
    {
        $sql = 'SELECT t.*, a.name AS admin_name, po.po_number, po.price_netto   
            FROM {tablePrefix}ext_purchase_order_log t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_purchase_order po ON po.id = t.po_id 
            WHERE 1';

        $params = [];
        if ($po_id > 0) {
            $sql .= ' AND t.po_id =:po_id';
            $params['po_id'] = $po_id;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        if (!$in_string) {
            return $rows;
        } else {
            $notes = [];
            foreach ($rows as $i => $row) {
                array_push($notes, $row['notes']);
            }

            $txt_notes = implode(", ", $notes);

            return $txt_notes;
        }
    }

    /**
     * @param $id
     * @return array
     */
    public function getDetail($id)
    {
        $sql = 'SELECT t.*, a.name AS created_by_name, po.po_number, po.price_netto 
            FROM {tablePrefix}ext_purchase_order_log t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_purchase_order po ON po.id = t.po_id  
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }
}
