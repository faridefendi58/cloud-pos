<?php

namespace Components;

class Validator
{
    protected $_bean;

    public function __construct($bean)
    {
        $this->_bean = $bean;
    }

    public function execute($rule)
    {
        $attr_data = preg_replace('/\s+/', '', $rule[0]);
        $attrs = explode(",", $attr_data);

        return self::{$rule[1]}($attrs, $rule);
    }

    public function required($attributes, $rule = null)
    {
        $model = $this->_bean;
        $errors = [];
        foreach ($attributes as $i => $attribute){
            if ($model->{$attribute} == null) {
                if (array_key_exists('on', $rule)) {
                    if ($model->getScenario() == $rule['on']) {
                        $errors[$attribute] = $attribute . ' tidak boleh dikosongi.';
                    }
                } else
                    $errors[$attribute] = $attribute . ' tidak boleh dikosongi.';
            }
        }

        return $errors;
    }

    public function email($attributes, $rule = null)
    {
        $model = $this->_bean;
        $errors = [];
        foreach ($attributes as $i => $attribute){
            if (filter_var($model->{$attribute}, FILTER_VALIDATE_EMAIL) === false) {
                if (array_key_exists('on', $rule)) {
                    if ($model->getScenario() == $rule['on']) {
                        $errors[$attribute] = $model->{$attribute}.' bukan email yang valid.';
                    }
                } else
                    $errors[$attribute] = $model->{$attribute}.' bukan email yang valid.';
            }
        }

        return $errors;
    }

    public function numerical($attributes, $rule = null)
    {
        $model = $this->_bean;
        $errors = [];
        foreach ($attributes as $i => $attribute){
            if (!is_numeric($model->{$attribute})) {
                if (array_key_exists('on', $rule)) {
                    if ($model->getScenario() == $rule['on']) {
                        $errors[$attribute] = $model->{$attribute}.' bukan dalam format angka.';
                    }
                } else
                    $errors[$attribute] = $model->{$attribute}.' bukan dalam format angka.';
            } else {
                if (array_key_exists('integerOnly', $rule) && $rule['integerOnly']) {
                    if (is_int($model->{$attribute}))
                        $errors[$attribute] = $model->{$attribute}.' bukan bilangan bulat.';
                }
            }
        }

        return $errors;
    }

    public function length($attributes, $rule = null)
    {
        $model = $this->_bean;
        $errors = [];
        foreach ($attributes as $i => $attribute){
            if (array_key_exists('max', $rule)) {
                if (strlen($model->{$attribute}) > $rule['max']) {
                    $errors[$attribute] = 'Maksimal jumlah karakter untuk '.$model->{$attribute}.' adalah '.$rule['max'].'.';
                }
            }
            if (array_key_exists('min', $rule)) {
                if (strlen($model->{$attribute}) < $rule['min']) {
                    $errors[$attribute] = 'Minimal jumlah karakter untuk '.$model->{$attribute}.' adalah '.$rule['min'].'.';
                }
            }
            if (array_key_exists('on', $rule)) {
                if ($model->getScenario() != $rule['on']) {
                    unset($errors[$attribute]);
                }
            }
        }

        return $errors;
    }

    public function unique($attributes, $rule)
    {
        $model = $this->_bean;
        $errors = [];
        foreach ($attributes as $i => $attribute){
            $data = $model->findByAttributes([$attribute=>$model->{$attribute}]);
            if ($data instanceof \RedBeanPHP\OODBBean){
                $errors[$attribute] = $attribute.' '.$model->{$attribute}.' sudah terdaftar.';
            }
        }
        if (array_key_exists('on', $rule)) {
            if ($model->getScenario() != $rule['on']) {
                $errors = [];
            }
        }

        return $errors;
    }
}