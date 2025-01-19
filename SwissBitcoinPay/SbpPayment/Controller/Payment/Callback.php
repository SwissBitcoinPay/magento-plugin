<?php
namespace SwissBitcoinPay\SbpPayment\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Psr\Log\LoggerInterface;
use SwissBitcoinPay\SbpPayment\Helper\Data as SbpHelper;
use Exception;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Callback extends Action implements CsrfAwareActionInterface
{
    protected $quoteRepository;
    protected $orderRepository;
    protected $quoteManagement;
	protected $quoteIdMaskFactory;
    protected $logger;
    protected $sbpHelper;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        SbpHelper $sbpHelper,
		CartRepositoryInterface $quoteRepository,
        OrderRepository $orderRepository,
        QuoteManagement $quoteManagement,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        parent::__construct($context);
        $this->sbpHelper = $sbpHelper;
        $this->quoteRepository = $quoteRepository;
        $this->orderRepository = $orderRepository;
        $this->quoteManagement = $quoteManagement;
        $this->logger = $logger;
		$this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * Create CSRF validation exception
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Validate for CSRF
     *
     * @param RequestInterface $request
     * @return bool
     */
    public function validateForCsrf(RequestInterface $request): bool
    {
        return true;
    }

	public function execute()
	{
        $step = 0;
        $jsonStr = '';

        try {
			$swissBtcPaySig = $this->getRequest()->getHeader('SBP-SIG');
            if (empty($swissBtcPaySig)) {
				$this->logger->debug('Secret key not set', []);
				http_response_code(400);
				die('Secret key not set');
			}

            $step++;
            $jsonStr = file_get_contents('php://input');
			$jsonData = json_decode($jsonStr, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				$this->logger->debug("Invalid JSON payload", []);
				http_response_code(400);
				die('Invalid JSON payload');
			}

			/*$this->logger->debug('Callback', [
                'Secret Key' => $this->sbpHelper->getApiSecret(),
                'jsonData' => $jsonData,
				'swissBtcPaySig' => $swissBtcPaySig
            ]);
			$step++;
            $SwissSecret = explode('=', $swissBtcPaySig)[1];

			if (!$this->checkSecretKey($this->sbpHelper->getApiSecret(), $jsonStr, $SwissSecret)) {
				$this->logger->debug("Invalid signature");
				http_response_code(403);
				die('Invalid signature');
			}*/

            $step++;
            $isPaid = $jsonData['status'] === 'settled' ?? false;
            $isExpired = $jsonData['status'] === 'expired' ?? false;
        	$isUnconfirmed = $jsonData['status'] === 'unconfirmed' ?? false;
			if ($isUnconfirmed) {
				http_response_code(200);
				die('OK');				
			}
        	$cartID = $jsonData['extra']['cartID'];			
			
			$step++;
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartID, 'masked_id');
            $quote = $this->quoteRepository->get($quoteIdMask->getQuoteId());
            $quote->getPayment()->setMethod('sbp_payment');

            $order = $this->quoteManagement->submit($quote);
			$this->logger->debug('Order', [
                'order' => $order->getData()
            ]);
            if ($isPaid) {
                $order->setState(Order::STATE_PROCESSING)
                    ->setStatus(Order::STATE_PROCESSING);
            } elseif ($isExpired) {
                $order->setState(Order::STATE_CANCELED)
                    ->setStatus(Order::STATE_CANCELED);
            }

			$order->save();
            http_response_code(200);
            die('OK');
        } catch (Exception $e) {
            $this->logger->debug("Step: $step - $jsonStr - " . $e->getMessage(), []);
            http_response_code(500);
            die('Error processing webhook');
        }
	}

	private function checkSecretKey($key, $message, $signature)
	{
    	$hashBytes = hash_hmac('sha256', $message, $key, true);
    	$hashString = '';
    	foreach (str_split($hashBytes) as $byte) {
        	$hashString .= sprintf('%02x', ord($byte));
    	}
    	return hash_equals($hashString, $signature);
	}
}