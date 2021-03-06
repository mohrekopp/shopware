<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Components\MultiEdit\Resource\Product;

/**
 * Grammar product resource. Will generate the grammar understood by the frontend lexer with all the supported columns
 *
 * Class Grammar
 */
class Grammar
{
    /**
     * Reference to an instance of the DqlHelper
     *
     * @var DqlHelper
     */
    protected $dqlHelper;

    /**
     * @var \Enlight_Event_EventManager
     */
    protected $eventManager;

    /**
     * @return DqlHelper
     */
    public function getDqlHelper()
    {
        return $this->dqlHelper;
    }

    /**
     * @return \Enlight_Event_EventManager
     */
    public function getEventManager()
    {
        return $this->eventManager;
    }

    /**
     * @param $dqlHelper DqlHelper
     * @param $eventManager \Enlight_Event_EventManager
     */
    public function __construct($dqlHelper, $eventManager)
    {
        $this->dqlHelper = $dqlHelper;
        $this->eventManager = $eventManager;
    }

    /**
     * Generates attributes from column names. Attributes have a name which is known to the lexer and some
     * rules regarding the supported operators.
     * Most operator rules can be generated from the table definition.
     *
     * @return array
     * @throws \RuntimeException When the column was not defined
     */
    public function generateAttributesFromColumns()
    {
        $columns = $this->getDqlHelper()->getAttributes();
        $columnInfo = array();

        foreach ($this->getDqlHelper()->getEntities() as $entity) {
            list($entity, $prefix) = $entity;
            $newMapping = array();
            $mappings = $this->getDqlHelper()->getEntityManager()->getClassMetadata($entity)->fieldMappings;
            foreach ($mappings as $key => $value) {
                $newMapping[strtoupper($prefix.'.'.$key)] = $value;
            }
            $columnInfo = array_merge($columnInfo, $newMapping);
        }

        $attributes = array();

        foreach ($columns as $column) {
            $mapping = $columnInfo[$column];
            $type = $mapping['type'];
            $formattedColumn = strtoupper($column);

            switch ($type) {
                case 'integer':
                case 'decimal':
                case 'float':
                    $attributes[$formattedColumn] = array('>', '>=', '<', '<=', '=', '!=', 'ISNULL');
                    break;
                case 'text':
                case 'string':
                    $attributes[$formattedColumn] = array('=', '~', '!~', 'IN', '!=', 'ISNULL');
                    break;
                case 'boolean':
                    $attributes[$formattedColumn] = array('ISTRUE', 'ISFALSE', 'ISNULL');
                    break;
                case 'date':
                    $attributes[$formattedColumn] = array('>', '>=', '<', '<=', '=', 'ISNULL');
                    break;
                case 'datetime':
                    $attributes[$formattedColumn] = array('>', '>=', '<', '<=', '=', 'ISNULL');
                    break;
                default:
                    // Allow custom types. If not event handles the unknown type
                    // an exception will be thrown
                    if ($event = $this->getEventManager()->notifyUntil(
                        'SwagMultiEdit_Product_Grammar_generateAttributesFromColumns_Type_'.ucfirst(strtolower($type)),
                        array(
                            'subject'   => $this,
                            'type' => $type,
                            'mapping'  => $mapping
                        )
                    )) {
                        $attributes[$formattedColumn] = $event->getReturn();
                    } else {
                        throw new \RuntimeException("Column with type {$type} was not configured, yet");
                    }

            }
        }

        return $attributes;
    }

    /**
     * Returns an array which represents the grammar of out product resource
     *
     * @return array
     */
    public function getGrammar()
    {
        $grammar = array(
            'nullaryOperators' => array(
                'HASIMAGE' => '',
                'HASNOIMAGE' => '',
                'ISMAIN' => '',
                'HASPROPERTIES' => '',
                'HASCONFIGURATOR' => '',
                'HASBLOCKPRICE' => ''
            ),
            'unaryOperators' => array(
                'ISTRUE' => '',
                'ISFALSE' => '',
                'ISNULL' => '',
            ),
            'binaryOperators' => array(
                'IN' => array('('),
                '>=' => array('/(^-{0,1}[0-9.]+$)/', '/"(.*?)"/'),
                '=' => array('/(^-{0,1}[0-9.]+$)/', '/"(.*?)"/'),
                '!=' => array('/(^-{0,1}[0-9.]+$)/', '/"(.*?)"/'),
                '!~' => array('/"(.*?)"/'),
                '~' => array('/"(.*?)"/'),
                '>' => array('/(^-{0,1}[0-9.]+$)/', '/"(.*?)"/'),
                '<=' => array('/(^-{0,1}[0-9.]+$)/', '/"(.*?)"/'),
                '<' => array('/(^-{0,1}[0-9.]+$)/', '/"(.*?)"/')
            ),
            'subOperators' => array( '(', ')' ),
            'boolOperators' => array( 'AND', 'OR' ),
            'values' => array( '/"(.*?)"/', '/^-{0,1}[0-9.]+$/'),
            'attributes' => $this->generateAttributesFromColumns(),
        );

        // Allow users to add own operators / rules
        $grammar = $this->getEventManager()->filter(
            'SwagMultiEdit_Product_Grammar_getGrammar_filterGrammar',
            $grammar,
            array('subject' => $this)
        );

        return $grammar;
    }
}
