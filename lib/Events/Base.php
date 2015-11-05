<?php

namespace Mx\Tools\Events;

class Base
{
    /**
     * @var array ���������� ��� �������� ������, �������� ����� ������������ ������ ������ ����������� ������
     */
    private static $dataExchanger = array();

    /**
     * ��� ����������� ������� ������ �������� ���������� ������������ ������ ������. � �������� ����� ����� ������������
     * ��� ������, � � �������� �������� - �������� ������� ����������
     * @var array
     */
    protected static $sort = array();

    /**
     * �����, ������� �������� ������ ��������� ���������� ��� ���������� ��������
     * @return array
     */
    private static function sortList()
    {
        $methods = get_class_methods(get_called_class());
        $arSort = array();
        foreach ($methods as $method)
        {
            $sort = array_key_exists($method, static::$sort) ? static::$sort[ $method ] : 100;
            $arSort[ $method ] = $sort;
        }

        return $arSort;
    }

    /**
     * �������� ���������� ��� ���������� ��������
     * @param $method
     * @return mixed
     */
    public static function getSort($method)
    {
        $sortList = self::sortList();
        return $sortList[ $method ];
    }

    /**
     * ������������� ������ ��� ��������
     * @param $key
     * @param $value
     */
    protected static function setData($key, $value)
    {
        self::$dataExchanger[ $key ] = $value;
    }

    /**
     * �������� ����������� ������
     * @param $key
     * @return null
     */
    protected static function getData($key)
    {
        return isset(self::$dataExchanger[ $key ]) ? self::$dataExchanger[ $key ] : null;
    }

}