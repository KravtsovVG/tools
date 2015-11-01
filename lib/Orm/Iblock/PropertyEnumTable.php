<?php

namespace Mx\Tools\Orm\Iblock;

class PropertyEnumTable
{
    public static function getMap()
    {
        $map = array(
            'ID' => array(
                'data_type' => 'integer',
                'primary' => true,
                'autocomplete' => true,
                'title' => '������������� �������� �������� ���� "������"',
            ),
            'PROPERTY_ID' => array(
                'data_type' => 'integer',
                'title' => '������������� ��������',
            ),
            'PROPERTY' => array(
                'data_type' => 'Bitrix\Iblock\PropertyTable',
                'reference' => array('=this.IBLOCK_ELEMENT_ID' => 'ref.ID'),
            ),
            'VALUE' => array(
                'data_type' => 'string',
                'title' => '�������� ��������',
            ),
            'DEF' => array(
                'data_type' => 'boolean',
                'values' => array('N','Y'),
                'title' => '�� ���������',
            ),
            'SORT' => array(
                'data_type' => 'integer',
                'title' => '����������',
            ),
            'XML_ID' => array(
                'data_type' => 'string',
                'title' => '��� �������� ��������',
            ),
            'TMP_ID' => array(
                'data_type' => 'string',
                'title' => '',
            ),
        );
    }
}