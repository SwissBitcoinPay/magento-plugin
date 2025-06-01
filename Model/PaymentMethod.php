<?php
namespace SwissBitcoinPay\SbpPayment\Model;

use Magento\Payment\Model\Method\AbstractMethod;
use Psr\Log\LoggerInterface;

class PaymentMethod extends AbstractMethod
{

    protected $_code = 'sbp_payment';

    protected $_isGateway = true;
    protected $_canCapture = true;
    protected $_canRefund = true;
    protected $_canVoid = true;
    protected $_canCancel = true;
    protected $_canUseCheckout = true;
    protected $_canUseInternal = true;
    protected $_canFetchTransactionInfo = true;
    protected $_canAuthorize = true;
    
    protected $logger;
    protected $encryptor;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            null,
            null,
            $data
        );

        $this->logger = $logger;
        $this->encryptor = $encryptor;
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
       // $this->logger->info('isAvailable called for the plugin');
        //return parent::isAvailable($quote);
        return true; //parent::isAvailable($quote) && $this->getConfigData('enabled');
    }

    public function canUseForCurrency($currencyCode)
    {
        return true;
    }
}
