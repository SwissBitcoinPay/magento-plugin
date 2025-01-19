<?php

namespace SwissBitcoinPay\SbpPayment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use SwissBitcoinPay\SbpPayment\Helper\Data as SbpHelper;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\UrlInterface;

class Redirect extends Action
{
    protected $quoteRepository;
    protected $_storeManager;
    protected $logger;
    protected $quoteIdMaskFactory;
    protected $sbpHelper;
    protected $curl;
    protected $resultFactory;
    protected $_url;

    public function __construct(
        Context $context,
        CartRepositoryInterface $quoteRepository,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        SbpHelper $sbpHelper,
        Curl $curl,
        ResultFactory $resultFactory,
        UrlInterface $url
    ) {
        parent::__construct($context);
        $this->sbpHelper = $sbpHelper;
        $this->logger = $logger;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteRepository = $quoteRepository;
        $this->_storeManager = $storeManager;
        $this->curl = $curl;
        $this->resultFactory = $resultFactory;
        $this->_url = $url;
    }

    public function execute()
    {
        try {
            $post = $this->getRequest()->getPostValue();
            
            if (!isset($post['quote_id'])) {
                throw new LocalizedException(__('Missing quote ID'));
            }

            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($post['quote_id'], 'masked_id');
            $quoteId = $quoteIdMask->getQuoteId();            
            $quote = $this->quoteRepository->get($quoteId);
            
            if (!$quote->getId()) {
                throw new LocalizedException(__('Unable to retrieve the quote.'));
            }

            $buyerEmail = $quote->getCustomerEmail();
            $buyerName = ($quote->getCustomerFirstname() ?? '') . ' ' . ($quote->getCustomerLastname() ?? '');
            if ($buyerName == ' ') {
                $billingAddress = $quote->getBillingAddress();
                $buyerName = $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname();
                $buyerEmail = $billingAddress->getEmail();

                $quote->setCustomerEmail($buyerEmail);
                $quote->setCustomerFirstname($billingAddress->getFirstname());
                $quote->setCustomerLastname($billingAddress->getLastname());

                $this->quoteRepository->save($quote);
            }
                
            $paymentData = [
                'Description' => 'From ' . $this->_storeManager->getStore()->getName(),
                'BuyerName' => $buyerName,
                'CartID' => $post['quote_id'],
                'CurrencyCode' => $post['currency'],
                'Amount' => $post['amount'],
                'BuyerEmail' => $buyerEmail,
                'Lang' => 'fr',
                'RedirectionURL' => $this->_url->getUrl('checkout/onepage/success'),
                'WebHookURL' => $this->_url->getUrl('sbppayment/payment/callback')
            ];

            $settings = [
                'ApiUrl' => $this->sbpHelper->getApiUrl(),
                'ApiKey' => $this->sbpHelper->getApiKey(),
                'AcceptOnChain' => $this->sbpHelper->isOnChainPaymentsAccepted()
            ];

            $checkoutUrl = $this->createInvoice($settings, $paymentData);

            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            return $resultJson->setData([
                'success' => true,
                'redirect_url' => $checkoutUrl
            ]);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while processing your request.'));
        }
    }

    private function createInvoice($settings, $paymentData) {
        try {
            $invoice = [
                'title' => $paymentData['Description'],
                'description' => $paymentData['BuyerName'] . ' | Quote : ' . $paymentData['CartID'],
                'unit' => $paymentData['CurrencyCode'],
                'amount' => $paymentData['Amount'],
                'email' => $paymentData['BuyerEmail'],
                'emailLanguage' => $paymentData['Lang'],
                'redirectAfterPaid' => $paymentData['RedirectionURL'],
                'webhook' => $paymentData['WebHookURL'],
                'delay' => 60,
                'onChain' => $settings['AcceptOnChain'],
                'extra' => [
                    'customNote' => 'Quote ' . $paymentData['CartID'],
                    'cartID' => $paymentData['CartID']
                ]
            ];
            $invoiceJson = json_encode($invoice, JSON_UNESCAPED_UNICODE);

            $headers = [
                'Content-Type: application/json',
                'api-key: ' . $settings['ApiKey']
            ];

            $url = rtrim($settings['ApiUrl'], '/') . '/';
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->addHeader('Content-Type', 'application/json');
            $this->curl->addHeader('api-key', $settings['ApiKey']);
            $this->curl->post($url . "checkout", $invoiceJson);

            $response = $this->curl->getBody();
            $httpCode = $this->curl->getStatus();

            if ($httpCode !== 200 && $httpCode !== 201) {
                throw new LocalizedException(__('HTTP request failed with code %1', $httpCode));
            }

            $jsonRep = json_decode($response, true);
            if (!isset($jsonRep['checkoutUrl'])) {
                throw new LocalizedException(__('Unexpected API response.'));
            }

            return $jsonRep['checkoutUrl'];
        } catch (\Exception $e) {
            throw new LocalizedException(__('Invoice creation failed: %1', $e->getMessage()));
        }
    }
}
