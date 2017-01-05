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

$installer->getConnection()->addColumn($installer->getTable('sales/order_payment'), 'openpay_3d_secure', array(
    'type'    => Varien_Db_Ddl_Table::TYPE_BOOLEAN,
    'comment' => 'If Openpay payment use 3D secure',
    'default' => false
));

$installer->getConnection()->addColumn($installer->getTable('sales/order_payment'), 'openpay_3d_secure_url', array(
    'type'    => Varien_Db_Ddl_Table::TYPE_TEXT,
    'comment' => 'Redirect URL for 3D secure',
    'length'  => '255',
    'nullable' => true
));

// ALTER TABLE `mg_sales_flat_order_payment` ADD `openpay_3d_secure` TINYINT(1) NOT NULL DEFAULT '0' COMMENT 'If Openpay payment use 3D secure' AFTER `openpay_barcode`, ADD `openpay_3d_secure_url` VARCHAR(255) NULL COMMENT 'Redirect URL for 3D secure' AFTER `openpay_3d_secure`; 

$installer->endSetup();