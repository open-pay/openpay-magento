<?php
/** @var $this Openpay_Charges_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->addColumn($installer->getTable('sales/quote_payment'), 'openpay_token', array(
    'type'    => Varien_Db_Ddl_Table::TYPE_TEXT,
    'comment' => 'OpenPay Token',
    'length'  => '255'
));

$installer->getConnection()->addColumn($installer->getTable('sales/order_payment'), 'openpay_authorization', array(
    'type'    => Varien_Db_Ddl_Table::TYPE_NUMERIC,
    'comment' => 'OpenPay Authorization Code',
    'length' => 10
));

$installer->getConnection()->addColumn($installer->getTable('sales/order_payment'), 'openpay_creation_date', array(
    'type'    => Varien_Db_Ddl_Table::TYPE_TIMESTAMP,
    'comment' => 'OpenPay Transaction Date'
));

$installer->getConnection()->addColumn($installer->getTable('sales/order_payment'), 'openpay_payment_id', array(
    'type'    => Varien_Db_Ddl_Table::TYPE_TEXT,
    'comment' => 'OpenPay Payment Id',
    'length'  => '255'
));

$installer->endSetup();