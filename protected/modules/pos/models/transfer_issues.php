<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class TransferIssuesModel extends \Model\BaseModel
{
    const STATUS_PENDING = 'pending';
    const STATUS_ON_PROCESS = 'processed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELED = 'canceled';

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_transfer_issue';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['ti_number', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($data = null)
    {
        $sql = 'SELECT t.*, a.name AS admin_name, wf.title AS warehouse_from_name, wt.title AS warehouse_to_name   
            FROM {tablePrefix}ext_transfer_issue t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_warehouse wf ON wf.id = t.warehouse_from 
            LEFT JOIN {tablePrefix}ext_warehouse wt ON wt.id = t.warehouse_to  
            WHERE 1';

        $params = [];
        if (is_array($data)) {
            if (isset($data['status'])) {
                $sql .= ' AND t.status =:status';
                $params['status'] = $data['status'];
            }

            if (isset($data['warehouse_from'])) {
                $sql .= ' AND t.warehouse_from =:warehouse_from';
                $params['warehouse_from'] = $data['warehouse_from'];
            }

            if (isset($data['date_start']) && isset($data['date_end'])) {
                $sql .= ' AND DATE_FORMAT(t.date_transfer, "%Y-%m-%d") BETWEEN :date_start AND :date_end';
                $params['date_start'] = $data['date_start'];
                $params['date_end'] = $data['date_end'];
            }
        }

        $sql .= ' ORDER BY t.date_transfer DESC';

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
            wf.title AS warehouse_from_name, wt.title AS warehouse_to_name   
            FROM {tablePrefix}ext_transfer_issue t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
            LEFT JOIN {tablePrefix}ext_warehouse wf ON wf.id = t.warehouse_from 
            LEFT JOIN {tablePrefix}ext_warehouse wt ON wt.id = t.warehouse_to  
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

    public function getLastTiNumber($ti_serie = null)
    {
        $sql = 'SELECT MAX(t.ti_nr) AS max_nr 
            FROM {tablePrefix}ext_transfer_issue t 
            WHERE 1';

        $params = [];
        if (!empty($po_serie)) {
            $sql .= ' AND t.ti_serie =:ti_serie';
            $params['ti_serie'] = $ti_serie;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row;
    }
}
