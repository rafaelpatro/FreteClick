<?php
class Frete_Click_Model_Carrier extends Frete_Click_Model_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'freteclick';

    protected $_allowedMethods = array();
    
    /**
     * (non-PHPdoc)
     * @see Mage_Shipping_Model_Carrier_Interface::getAllowedMethods()
     */
    public function getAllowedMethods()
    {
        return $this->_allowedMethods;
    }
    
    /**
     * (non-PHPdoc)
     * @see Mage_Shipping_Model_Carrier_Abstract::proccessAdditionalValidation()
     */
    public function proccessAdditionalValidation(Mage_Shipping_Model_Rate_Request $request)
    {
        Mage::log('Frete_Click_Model_Carrier::proccessAdditionalValidation');
        $requestPostcode = Mage::helper('freteclick')->formatZip($request->getDestPostcode());
        $address = Mage::getModel($this->getConfigData('address_model'))->load($requestPostcode);

        if (!$this->isValid($address)) {
            return false;
        }

        if (!$this->validateAllowedZips($requestPostcode)) {
            return false;
        }

        $this->setDestAddress($address);
        return $this;
    }

    /**
     * (non-PHPdoc)
     * @see Mage_Shipping_Model_Carrier_Abstract::collectRates()
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        Mage::log('Frete_Click_Model_Carrier::collectRates');
        $rateResult = Mage::getModel('shipping/rate_result');
        foreach ($this->getQuotes($request) as $quote) {
            if (empty($quote->getPrice())) {
                Mage::log('Empty price for ' . $quote->getMethod());
                continue;
            }
            if (!$quote->hasError()) {
                $method = Mage::getModel('shipping/rate_result_method');
                $method->setCarrier($this->getCarrierCode());
                $method->setCarrierTitle($this->getConfigData('title'));
                $method->setMethod($this->getCarrierCode().'_'.$quote->getMethod());
                $method->setMethodTitle($this->getMethodTitle($quote));
                $method->setPrice($this->getFinalPriceWithHandlingFee($quote->getPrice()));
                $method->setCost($quote->getPrice());
            } else {
                Mage::logException(Mage::exception('Mage_Core', $quote->getError()));
                $method = Mage::getModel('shipping/rate_result_error');
                $method->setCarrier($this->getCarrierCode());
                $method->setErrorMessage($this->getConfigData('specificerrmsg'));
                $method->setErrorMessage($quote->getError());
            }

            if ($quote->getQuoteId()) {
                $this->_getSession()->setFreteClickOrderId($quote->getQuoteId());
            }
            
            $rateResult->append($method);
        }
        
        return $rateResult;
    }
}
