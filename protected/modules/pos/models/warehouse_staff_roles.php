<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class WarehouseStaffRolesModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_warehouse_staff_role';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['title', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData()
    {
        $sql = 'SELECT t.*, ab.name AS admin_creator_name    
            FROM {tablePrefix}ext_warehouse_staff_role t 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.created_by   
            WHERE 1';

        $sql .= ' ORDER BY t.id DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql );

        return $rows;
    }

    /**
     * @param $id
     * @return array
     */
    public function getDetail($id)
    {
        $sql = 'SELECT t.*, a.name AS created_by_name, ab.name AS updated_by_name  
            FROM {tablePrefix}ext_warehouse_staff_role t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

    public function getRules()
    {
        return [
            'purchase_order' => [
                'title' => 'Purchase Order',
                'items' => ['create', 'read', 'update', 'delete']
                ],
            'transfer_issue' => [
                'title' => 'Transfer Stok',
                'items' => ['create', 'read', 'update', 'delete']
                ],
            'inventory_issue' => [
                'title' => 'Transaksi Non Penjualan',
                'items' => ['create', 'read', 'update', 'delete']
                ],
            'purchase_receipt' => [
                'title' => 'Penerimaan PO',
                'items' => ['create', 'read', 'update', 'delete']
                ],
            'transfer_receipt' => [
                'title' => 'Penerimaan Transfer Stok',
                'items' => ['create', 'read', 'update', 'delete']
                ],
            'inventory_receipt' => [
                'title' => 'Penambahan Persediaan (Non PO/Transfer)',
                'items' => ['create', 'read', 'update', 'delete']
                ],
        ];
    }
}
