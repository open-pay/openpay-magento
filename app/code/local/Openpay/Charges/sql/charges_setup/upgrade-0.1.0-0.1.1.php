<?php

$installer = new Mage_Customer_Model_Entity_Setup('core_setup');
$installer->startSetup();


$vCustomerEntityType = $installer->getEntityTypeId('customer');
$vCustAttributeSetId = $installer->getDefaultAttributeSetId($vCustomerEntityType);
$vCustAttributeGroupId = $installer->getDefaultAttributeGroupId($vCustomerEntityType, $vCustAttributeSetId);

$installer->addAttribute('customer', 'openpay_user_id', array(
    'label' => 'OpenPay User Id',
    'input' => 'text',
    'type'  => 'varchar',
    'forms' => array(/*'customer_account_edit','customer_account_create',*/'adminhtml_customer'/*,'checkout_register'*/),
    'required' => 0,
    'global' => 1,
    'visible' => 1,
    'user_defined' => 1,
    'visible_on_front' => 1
));

$installer->addAttributeToGroup($vCustomerEntityType, $vCustAttributeSetId, $vCustAttributeGroupId, 'mobile', 0);

$oAttribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'openpay_user_id');
$oAttribute->setData('used_in_forms', array(/*'customer_account_edit','customer_account_create',*/'adminhtml_customer'/*,'checkout_register'*/));
$oAttribute->save();
$installer->endSetup();