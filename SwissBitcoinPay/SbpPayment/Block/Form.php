<?php
namespace SwissBitcoinPay\SbpPayment\Block;

use PayFabric\Payment\Helper\Helper;
use Magento\Framework\View\Element\Template;
use Magento\Customer\Model\Session;

class Form extends Template
{
    private $session;

    public function __construct(
        Template\Context $context,
        Session $session,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->session = $session;
    }

}