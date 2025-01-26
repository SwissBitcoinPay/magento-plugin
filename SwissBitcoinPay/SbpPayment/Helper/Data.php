<?php
namespace SwissBitcoinPay\SbpPayment\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const CONFIG_PATH_API_URL = 'payment/sbp_payment/api_url';
    const CONFIG_PATH_API_KEY = 'payment/sbp_payment/api_key';
    const CONFIG_PATH_API_SECRET = 'payment/sbp_payment/api_secret';
    const CONFIG_PATH_ACCEPT_ONCHAIN = 'payment/sbp_payment/accept_onchain_payments';

    public function getApiUrl()
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_API_URL, ScopeInterface::SCOPE_STORE);
    }

    public function getApiKey()
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_API_KEY, ScopeInterface::SCOPE_STORE);
    }

    public function getApiSecret()
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_API_SECRET, ScopeInterface::SCOPE_STORE);
    }

    public function isOnChainPaymentsAccepted()
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_PATH_ACCEPT_ONCHAIN, ScopeInterface::SCOPE_STORE);
    }
}
