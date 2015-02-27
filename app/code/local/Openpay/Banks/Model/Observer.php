<?php

class Openpay_Banks_Model_Observer{

    public function checkoutOnepageControllerSuccessAction($order_ids){

        if(Mage::getConfig()->getModuleConfig('Openpay_Banks')->is('active', 'true')){
            $order_ids_list = $order_ids->getOrderIds();
            $order_id  = $order_ids_list[0];
            $order = Mage::getModel('sales/order')->load($order_id);

            $code = Mage::getModel('Openpay_Banks_Model_Method_Banks')->getCode();

            if($order->getPayment()->getMethod() == $code){
                $args = array(
                    'order' => $order->getIncrementId(),
                    'id' => $order->getPayment()->getOpenpayPaymentId(),
                );

                $this->_redirect('print', 'payments', $code, $args);
            }
        }

        return $this;

    }

    protected function _redirect($action, $controller = null, $module = null, array $params = null)
    {
        $response = Mage::app()->getResponse();

        $response->setRedirect(Mage::helper('adminhtml')->getUrl($module.'/'.$controller.'/'.$action, $params));

    }
}
