<?php

class Openpay_Charges_Model_Resource_Mysql4_Setup extends Mage_Core_Model_Resource_Setup{
    /**
     * Initialize resource configurations, setup connection, etc
     *
     * @param string $resourceName the setup resource name
     */
    public function __construct($resourceName)
    {
        $config = Mage::getConfig();
        $this->_resourceName = $resourceName;
        $this->_resourceConfig = $config->getResourceConfig($resourceName);
        $connection = $config->getResourceConnectionConfig($resourceName);
        if ($connection) {
            $this->_connectionConfig = $connection;
        } else {
            $this->_connectionConfig = $config->getResourceConnectionConfig(self::DEFAULT_SETUP_CONNECTION);
        }

        $modName = (string)$this->_resourceConfig->setup->module;
        $this->_moduleConfig = $config->getModuleConfig($modName);
        $connection = Mage::getSingleton('core/resource')->getConnection($this->_resourceName);
        /**
         * If module setup configuration wasn't loaded
         */
        if (!$connection) {
            $connection = Mage::getSingleton('core/resource')->getConnection($this->_resourceName);
        }
        $this->_conn = $connection;
    }

}
