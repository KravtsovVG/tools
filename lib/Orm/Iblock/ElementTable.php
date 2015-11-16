<?php

namespace Maximaster\Tools\Orm\Iblock;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\DB\SqlExpression;
use Bitrix\Main\Entity;
use Maximaster\Tools\Helpers\IblockStructure;
use Maximaster\Tools\Interfaces\IblockElementTableInterface;
use Maximaster\Tools\Orm\Query;


class ElementTable extends \Bitrix\Iblock\ElementTable implements IblockElementTableInterface
{
    protected static $concatSeparator = '|<-separator->|';

    public static function getIblockId  ()
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
            $propValueShortcut              = "PROPERTY_{$propCode}_VALUE";
            $propValueDescriptionShortcut   = "PROPERTY_{$propCode}_DESCRIPTION";
            $concatSubquery                 = "GROUP_CONCAT(%s SEPARATOR '" .  static::$concatSeparator . "')";
            $propValueColumn                = $isMultiple || $isOldProps ? 'VALUE' : "PROPERTY_{$propId}";
            $valueReference = $valueEntity = $fieldReference = null;

            /**
             * ��� �� ������������� ������� 2.0 ������� ������ ���� ��������
             */
            if (!$isOldProps && !$isMultiple)
            {
                $map[ $singlePropsEntityName ] = new Entity\ReferenceField(
                    $singlePropsEntityName,
                    $singleProp,
                    array('=ref.IBLOCK_ELEMENT_ID' => 'this.ID'),
                    array('join_type' => 'LEFT')
                );

                $singlePropTableLinked = true;
            }

            $realValueStorage = !$isOldProps && !$isMultiple ?
                "{$singlePropsEntityName}.{$propValueColumn}" :
                "{$propTableEntityName}.{$propValueColumn}";

            switch ($prop['PROPERTY_TYPE'])
            {
                case 'E':

                    if ($isMultiple)
                    {
                        $valueEntity = new Entity\ExpressionField(
                            $propValueShortcut,
                            $isMultiple ? $concatSubquery : '%s',
                            $realValueStorage
                        );
                    }
                    else
                    {
                        $valueReference = array("=this.{$realValueStorage}" => 'ref.ID');
                        $entityName = '\\' . __CLASS__;
                        if ($prop['LINK_IBLOCK_ID'])
                        {
                            $entityName = self::compileEntity($prop['LINK_IBLOCK_ID'])->getDataClass();
                            $valueReference['=ref.IBLOCK_ID'] = new SqlExpression('?i', $prop['LINK_IBLOCK_ID']);
                        }

                        //TODO ����� �������� ����������� ������ ElementTable ��� �������� � ������� ��� ����
                        $valueEntity = new Entity\ReferenceField(
                            $propValueShortcut,
                            $entityName,
                            $valueReference
                        );
                    }

                    break;
                case 'G':
                    if ($isMultiple)
                    {
                        $valueEntity = new Entity\ExpressionField(
                            $propValueShortcut,
                            $isMultiple ? $concatSubquery : '%s',
                            $realValueStorage
                        );
                        $valueEntity->addFetchDataModifier(array(__CLASS__, 'multiValuesDataModifier'));
                    }
                    else
                    {
                        $valueReference = array("=this.{$realValueStorage}" => 'ref.ID');

                        if ($prop['LINK_IBLOCK_ID'])
                        {
                            $valueReference['=ref.IBLOCK_ID'] = new SqlExpression('?i', $prop['LINK_IBLOCK_ID']);
                        }

                        $valueEntity = new Entity\ReferenceField(
                            $propValueShortcut,
                            '\Bitrix\Iblock\SectionTable',
                            $valueReference
                        );
                    }

                    break;

                case 'L':

                    if ($isMultiple)
                    {
                        $valueEntity = new Entity\ExpressionField(
                            $propValueShortcut,
                            $isMultiple ? $concatSubquery : '%s',
                            $realValueStorage
                        );
                        $valueEntity->addFetchDataModifier(array(__CLASS__, 'multiValuesDataModifier'));
                    }
                    else
                    {
                        $valueReference = array(
                            '=this.ID' => 'ref.PROPERTY_ID',
                            "=this.{$realValueStorage}" => 'ref.ID'
                        );

                        $valueEntity = new Entity\ReferenceField(
                            $propValueShortcut,
                            '\Bitrix\Iblock\PropertyEnumerationTable',
                            $valueReference
                        );
                    }

                    break;
                case 'S': case 'N': default:
                $valueEntity = new Entity\ExpressionField(
                    $propValueShortcut,
                    $isMultiple ? $concatSubquery : '%s',
                    $realValueStorage
                );

                break;
            }

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
                 * ����������� ��� ������������� ��������
                 */
                if ($isMultiple) $valueEntity->addFetchDataModifier(array(__CLASS__, 'multiValuesDataModifier'));
                $map[ $propValueShortcut ] = $valueEntity;

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
                $map[ $propValueShortcut ] = $valueEntity;

                /**
                 * � ��� ��� ��������, ���� ��� ����
                 */
                if ($useDescription)
                {
                    $map[ $propValueDescriptionShortcut ] = new Entity\ExpressionField(
                        $propValueDescriptionShortcut,
                        '%s',
                        "{$singlePropsEntityName}.DESCRIPTION_{$propId}"
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
     * @param array        $prop
     * @param Entity\Field $tableField
     * @return Entity\ExpressionField
     */
    private static function linkPropertyValue(array $prop, Entity\Field &$tableField)
    {
        $propValueShortcut = "PROPERTY_{$prop['CODE']}_VALUE";
        $isMultiple = $prop['MULTIPLE'] == 'Y';
        $isOldProps = $prop['VERSION'] == 2;
        $concatSubquery = "GROUP_CONCAT(%s SEPARATOR '" .  static::$concatSeparator . "')";
        $propValueColumn = $isMultiple || $isOldProps ? 'VALUE' : "PROPERTY_{$prop['ID']}";

        $mapEntity = new Entity\ExpressionField(
            $propValueShortcut,
            $isMultiple ? $concatSubquery : '%s',
            "{$tableField->getName()}.{$propValueColumn}"
        );

        return $mapEntity;
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

    /**
     * @param $iblockId
     * @return Entity\Base
     * @throws ArgumentException
     */
    public static function compileEntity($iblockId)
    {
        $meta = IblockStructure::full($iblockId);
        if (!$meta)
        {
            throw new ArgumentException('������ �������������� ������������� ���������');
        }

        $entityName = "Iblock" . Entity\Base::snake2camel($iblockId) . "ElementTable";
        $fullEntityName = '\\' . __NAMESPACE__ . '\\' . $entityName;

        $code = "
            namespace "  . __NAMESPACE__ . ";
            class {$entityName} extends ElementTable {
                public static function getIblockId(){
                    return {$meta['iblock']['ID']};
                }
            }
        ";
        if (!class_exists($fullEntityName)) eval($code);

        return Entity\Base::getInstance($fullEntityName);
    }
}