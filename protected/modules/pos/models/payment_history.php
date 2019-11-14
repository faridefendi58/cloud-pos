<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class PaymentHistoryModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_payment_history';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['invoice_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData()
    {
        $sql = 'SELECT t.*   
            FROM {tablePrefix}ext_payment_history t
            LEFT JOIN {tablePrefix}ext_invoice i ON i.id = t.invoice_id 
            WHERE 1';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql );

        return $rows;
    }

    public function getDataByInvoice() {
        $sql = 'SELECT t.*, (SELECT COUNT(h.id) AS tot FROM {tablePrefix}ext_payment_history h WHERE h.invoice_id = t.id) AS payment_count, 
            wh.title AS warehouse_name   
            FROM {tablePrefix}ext_invoice t
            LEFT JOIN {tablePrefix}ext_payment_history h ON h.invoice_id = t.id 
            LEFT JOIN {tablePrefix}ext_warehouse wh ON wh.id = t.warehouse_id 
            WHERE 1';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql );

        return $rows;
    }
}
