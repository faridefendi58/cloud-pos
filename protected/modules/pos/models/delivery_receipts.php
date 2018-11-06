<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class DeliveryReceiptsModel extends \Model\BaseModel
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
        return 'ext_delivery_receipt';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['dr_number', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($data = array())
    {
        $sql = 'SELECT t.* 
            FROM {tablePrefix}ext_delivery_receipt t 
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
            do.do_number  
            FROM {tablePrefix}ext_delivery_receipt t 
            LEFT JOIN {tablePrefix}ext_delivery_order do ON do.id = t.do_id 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

    public function getLastDrNumber($dr_serie = null)
    {
        $sql = 'SELECT MAX(t.dr_nr) AS max_nr 
            FROM {tablePrefix}ext_delivery_receipt t 
            WHERE 1';

        $params = [];
        if (!empty($ir_serie)) {
            $sql .= ' AND t.dr_serie =:dr_serie';
            $params['dr_serie'] = $dr_serie;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row;
    }
}
