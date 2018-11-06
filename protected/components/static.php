<?php

namespace Components;

class CStatic
{
    protected $_container;
    protected $_user;

    public function __construct($container, $user)
    {
        $this->_container = $container;
        $this->_user = $user;
    }

    /**
     * usage ex : {{ App.call.model("\\Model\\SurveyModel", 'getTotals', {'id':model.id}) }}
     * or {{ App.call.model("SurveyModel", 'getTotals', {'id':model.id}) }}
     * @param $class_name
     * @param $method
     * @param $params
     * @return mixed
     */
    public function model($class_name, $method, $params)
    {
        if (strpos($class_name, '\\Model\\') === false
            && strpos($class_name, '\\ExtensionsModel\\') === false){
            $class_name = '\\Model\\'.$class_name;
        }

        // avoid using call_user_func_array due to PHP Deprecated:  call_user_func_array() expects parameter 1 to be a valid callback,
        // non-static method Model\SurveyAnswerModel::get() should not be called statically
        //return call_user_func_array( [$class_name, $method] , [$params] );

        $class_object = new $class_name;
        return $class_object->$method($params);
    }

    public function initModel($class_name)
    {
        if (strpos($class_name, '\\Model\\') === false
            && strpos($class_name, '\\ExtensionsModel\\') === false){
            $class_name = '\\Model\\'.$class_name;
        }

        $class_object = new $class_name;

        return $class_object;
    }
}