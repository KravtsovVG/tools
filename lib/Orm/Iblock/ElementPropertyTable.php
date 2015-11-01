<?php

namespace Mx\Tools\Orm\Iblock;

use Bitrix\Main\Entity;

class ElementPropertyTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'b_iblock_element_property';
    }

    public static function getMap()
    {
        $map = array(
            'ID' => array(
                'data_type' => 'integer',
                'primary' => true,
                'autocomplete' => true,
                'title' => '������������� �������� ��������',
            ),
            'IBLOCK_PROPERTY_ID' => array(
                'data_type' => 'integer',
                'title' => '������������� ��������',
            ),
            'IBLOCK_ELEMENT_ID' => array(
                'data_type' => 'integer',
                'title' => '������������� ��������',
            ),
            'PROPERTY' => array(
                'data_type' => 'Bitrix\Iblock\PropertyTable',
                'reference' => array('=this.IBLOCK_PROPERTY_ID' => 'ref.ID'),
            ),
            //TODO ������� ������ � ��� ���������, ������� ��������� � ����������� ���������
            //����� ���� ����������� ������� ����� �� �������� �������
            /*'ELEMENT' => array(
                'data_type' => 'Mx\Tools\Orm\Iblock\ElementTable',
                'reference' => array('=this.IBLOCK_ELEMENT_ID' => 'ref.ID'),
            ),*/
            'VALUE' => array(
                'data_type' => 'string',
                'title' => '�������� ��������',
            ),
            'VALUE_TYPE' => array(
                'data_type' => 'string',
                'title' => '��� ��������',
            ),
            'VALUE_ENUM' => array(
                'data_type' => 'integer',
                'title' => '�������� �������� ���� "������"',
            ),
            'VALUE_NUM' => array(
                'data_type' => 'float',
                'title' => '�������� �������� ��������',
            ),
            'DESCRIPTION' => array(
                'data_type' => 'string',
                'title' => '�������� �������� ��������',
            ),
        );

        return $map;
    }
}