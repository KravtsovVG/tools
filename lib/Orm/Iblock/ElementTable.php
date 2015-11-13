<?php

namespace Mx\Tools\Orm\Iblock;

use Bitrix\Main\DB\SqlExpression;
use Bitrix\Main\Entity;
use Mx\Tools\Helpers\IblockStructure;
use Mx\Tools\Interfaces\IblockElementTableInterface;
use Mx\Tools\Orm\Query;


class ElementTable extends \Bitrix\Iblock\ElementTable implements IblockElementTableInterface
{
    protected static $concatSeparator = '|<-separator->|';

    public static function getIblockId()
    {
        return null;
    }

    public static function getMap()
    {
        if (static::getIblockId() === null) return parent::getMap();

        $map = parent::getMap();

        foreach (self::getAdditionalMap() as $key => $mapItem)
        {
            $map[] = $mapItem;
        }

        return $map;
    }

    /**
     * �������� ������ �������������� �����, ������� ����� �������� � ��������
     *
     * @param null|int|string $iblockId ������������� ��������� ��� ��� ��������� ��� null. � ������ null ����� ���������
     * ������� �������� ������������� ��������� �� ��������
     * @return array ������ �������������� ����� ��� ��������
     * @throws \Bitrix\Main\ArgumentException
     */
    public static function getAdditionalMap($iblockId = null)
    {
        $map = array();
        $iblockId = $iblockId === null ? static::getIblockId() : $iblockId;

        $meta = IblockStructure::full($iblockId);

        $iblock = $meta['iblock'];
        $props = $meta['properties'];

        if (empty($props)) return $map;

        $isOldProps = $iblock['VERSION'] == 1;

        $singlePropTableLinked = false;
        $singlePropsEntityName = "PROPERTY_TABLE_IBLOCK_{$iblock['ID']}";

        if (!$isOldProps)
        {
            $singleProp = ElementPropSingleTable::getInstance($iblock['CODE'])->getEntity()->getDataClass();
            $multipleProp = ElementPropMultipleTable::getInstance($iblock['CODE'])->getEntity()->getDataClass();
        }
        else
        {
            $singleProp = ElementPropertyTable::getEntity()->getDataClass();
        }

        foreach ($props as $propCode => $prop)
        {
            if (is_numeric($propCode)) continue;

            $propId                 = $prop['ID'];
            $isMultiple             = $prop['MULTIPLE'] == 'Y';
            $useDescription         = $prop['WITH_DESCRIPTION'] == 'Y';
            $isNewMultiple          = $isMultiple && !$isOldProps;

            $propTableEntityName            = "PROPERTY_{$propCode}";
            $propValueEntityName            = "PROPERTY_{$propCode}";
            $propValueShortcut              = "PROPERTY_{$propCode}_VALUE";
            $propValueDescriptionShortcut   = "PROPERTY_{$propCode}_DESCRIPTION";
            $concatSubquery                 = "GROUP_CONCAT(%s SEPARATOR '" .  static::$concatSeparator . "')";
            $propValueColumn                = 'VALUE';

            /*switch ($prop['PROPERTY_TYPE'])
            {
                case 'N': case 'E': case 'G':   $valueColumn = 'VALUE_NUM';  break;
                case 'L': case 'S': default:    $valueColumn = 'VALUE';      break;
            }*/

            /**
             * ��� ���� �������, ����� ��������� 2.0
             */
            if ($isOldProps || $isMultiple)
            {
                /**
                 * ������� ������� �� ��������� ��������
                 */
                $map[ $propTableEntityName ] = new Entity\ReferenceField(
                    $propTableEntityName,
                    $isNewMultiple ? $multipleProp : $singleProp,
                    array(
                        '=ref.IBLOCK_ELEMENT_ID' => 'this.ID',
                        '=ref.IBLOCK_PROPERTY_ID' => new SqlExpression('?i', $propId)
                    ),
                    array('join_type' => 'LEFT')
                );

                /**
                 * ������ ������� ������ ��� �������� ��������
                 */
                $e = new Entity\ExpressionField(
                    $propValueShortcut,
                    $isMultiple ? $concatSubquery : '%s',
                    "{$propTableEntityName}.{$propValueColumn}"
                );

                /**
                 * ����������� ��� ������������� ��������
                 */
                if ($isMultiple) $e->addFetchDataModifier(array(__CLASS__, 'multiValuesDataModifier'));
                $map[ $propValueShortcut ] = $e;

                /**
                 * � ��� ��� ��������, ���� ��� ����
                 */
                if ($useDescription)
                {
                    $e = new Entity\ExpressionField(
                        $propValueDescriptionShortcut,
                        $isMultiple ? $concatSubquery : '%s',
                        "{$propTableEntityName}.DESCRIPTION"
                    );

                    if ($isMultiple) $e->addFetchDataModifier(array(__CLASS__, 'multiValuesDataModifier'));
                    $map[ $propValueDescriptionShortcut ] = $e;
                }
            }
            else
            {
                /**
                 * ��� �� ������������� ������� 2.0 ������� ������ ���� ��������
                 */
                if (!$singlePropTableLinked)
                {
                    $map[ $singlePropsEntityName ] = new Entity\ReferenceField(
                        $singlePropsEntityName,
                        $singleProp,
                        array('=ref.IBLOCK_ELEMENT_ID' => 'this.ID'),
                        array('join_type' => 'LEFT')
                    );

                    $singlePropTableLinked = true;
                }

                /**
                 * ������� ������� �� ��������� ��������. ��� ��� ����������, �� ��� �������������...
                 */
                $map[ $propTableEntityName ] = new Entity\ReferenceField(
                    $propTableEntityName,
                    $singleProp,
                    array('=ref.IBLOCK_ELEMENT_ID' => 'this.ID'),
                    array('join_type' => 'LEFT')
                );

                /**
                 * ������ ������� ������ ��� �������� ��������
                 */
                $map[ $propValueShortcut ] = new Entity\ExpressionField(
                    $propValueShortcut,
                    '%s',
                    "{$singlePropsEntityName}.PROPERTY_{$propId}"
                );

                /**
                 * � ��� ��� ��������, ���� ��� ����
                 */
                if ($useDescription)
                {
                    $map[ $propValueDescriptionShortcut ] = new Entity\ExpressionField(
                        $propValueDescriptionShortcut,
                        '%s',
                        "{$propTableEntityName}.DESCRIPTION_{$propId}"
                    );
                }
            }
        }

        /**
         * ������� DETAIL_PAGE_URL
         */
        $e = new Entity\ExpressionField('DETAIL_PAGE_URL', '%s', 'IBLOCK.DETAIL_PAGE_URL');
        $e->addFetchDataModifier(function($value, $query, $entry, $fieldName)
        {
            $search = array();
            $replace = array();
            foreach ($entry as $key => $val)
            {
                $search[] = "#{$key}#";
                $replace[] = $val;
            }
            return str_replace($search, $replace, $value);
        });
        $map['DETAIL_PAGE_URL'] = $e;

        return $map;
    }

    /**
     * ����������� ������ ��� ������������� �������. ��������� ������ �� ��������������� ��������� ������������� �������
     *
     * @param $value
     * @param $query
     * @param $entry
     * @param $fieldName
     * @return array
     */
    public static function multiValuesDataModifier($value, $query, $entry, $fieldName)
    {
        if (
            trim($value) == static::$concatSeparator
            || strpos($value, static::$concatSeparator) === false

        ) return array();

        return explode(static::$concatSeparator, $value);
    }

    /**
     * ������� ����������� ������� �� ����������������
     *
     * @return Query
     */
    public static function query()
    {
        return new Query(static::getEntity());
    }

    public static function add(array $data)
    {
        throw new \LogicException('����������� \\Bitrix\\Iblock\\ElementTable');
    }

    public static function update($primary, array $data)
    {
        throw new \LogicException('����������� \\Bitrix\\Iblock\\ElementTable');
    }

    public static function delete($primary)
    {
        throw new \LogicException('����������� \\Bitrix\\Iblock\\ElementTable');
    }
}