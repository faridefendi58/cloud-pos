<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class PaymentChannelsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_payment_channel';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['code', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData()
    {
        $sql = 'SELECT t.*   
            FROM {tablePrefix}ext_payment_channel t
            WHERE 1';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql );

        return $rows;
    }

    public function getChannelIds() {
        $sql = 'SELECT t.id, t.code, t.title   
            FROM {tablePrefix}ext_payment_channel t
            WHERE 1';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql );
        $items = [];
        foreach ($rows as $i => $row) {
            $items[$row['code']] = [ 'id' => $row['id'], 'title' => $row['title'] ];
        }

        return $items;
    }
}
