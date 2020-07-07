<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class DepositTakesModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_deposit_take';
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
    public function getData($data = [])
    {
        $sql = 'SELECT t.*, CONCAT(i.serie, REPEAT(0, (4-CHAR_LENGTH(i.nr))), i.nr) AS invoice_number, a.name AS admin_name   
            FROM {tablePrefix}ext_deposit_take t 
            LEFT JOIN {tablePrefix}ext_invoice i ON i.id = t.invoice_id 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            WHERE 1';

        $params = [];
        if (isset($data['invoice_id'])) {
            $sql .= ' AND t.invoice_id =:invoice_id';
            $params['invoice_id'] = $data['invoice_id'];
        }

        $sql .= ' ORDER BY t.id ASC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function getLastTake($data = []) {
        $sql = 'SELECT t.*, CONCAT(i.serie, REPEAT(0, (4-CHAR_LENGTH(i.nr))), i.nr) AS invoice_number, a.name AS admin_name   
            FROM {tablePrefix}ext_deposit_take t 
            LEFT JOIN {tablePrefix}ext_invoice i ON i.id = t.invoice_id 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            WHERE 1';

        $params = [];
        if (isset($data['invoice_id'])) {
            $sql .= ' AND t.invoice_id =:invoice_id';
            $params['invoice_id'] = $data['invoice_id'];
        }

        $sql .= ' ORDER BY t.id DESC LIMIT 1';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );
        $row = null;
        if (is_array($rows) && count($rows) > 0) {
            $row = $rows[0];
            $row['items'] = json_decode($row['items'], true);
        }

        return $row;
    }
}
