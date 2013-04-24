<?php
/**
 * This file is part of the Ecommerce-Payment package
 *
 * @package Ecommerce-Payment
 */

/**
 * DPS namespace
 */
namespace Heystack\Subsystem\Payment\DPS\PXFusion;

use Heystack\Subsystem\Core\Exception\ConfigurationException;
use Heystack\Subsystem\Ecommerce\Currency\CurrencyService;
use Heystack\Subsystem\Ecommerce\Transaction\Interfaces\TransactionInterface;
use Heystack\Subsystem\Payment\DPS\PXPost\Service as PXPostService;
use Heystack\Subsystem\Payment\DPS\Service as BaseService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 *
 *
 * @copyright  Heyday
 * @package    Ecommerce-Payment
 */
class Service extends BaseService
{

    /**
     * Config key for type
     */
    const CONFIG_TYPE = 'Type';

    /**
     * Config key for username
     */
    const CONFIG_USERNAME = 'Username';

    /**
     * Config key for password
     */
    const CONFIG_PASSWORD = 'Password';

    /**
     * Auth-Complete type. This should be used when you want to authorise an amount (or just $1) to either verify a card
     * or alternatively avoid holding credit card data on the server for a later transaction
     */
    const TYPE_AUTH_COMPLETE = 'Auth-Complete';

    /**
     * Purchase type. This type should be used when you want to immediately take money from the credit card.
     */
    const TYPE_PURCHASE = 'Purchase';

    /**
     * Txn type auth. This is txnType used in the soap call when using Auth-Complete
     */
    const TXN_TYPE_AUTH = 'Auth';

    /**
     * Txn type purchase. This is the txnType used in the soap call when using Purchase
     */
    const TXN_TYPE_PURCHASE = 'Purchase';

    /**
     * Auth stage for the Auth-Complete payment cycle
     */
    const STAGE_AUTH = 'Auth';

    /**
     * Complete stage for the Auth-Complete payment cycle
     */
    const STAGE_COMPLETE = 'Complete';

    /**
     * Holds the Event Dispatcher service
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $eventService;

    /**
     * Holds the Transaction object
     * @var \Heystack\Subsystem\Ecommerce\Transaction\Interfaces\TransactionInterface
     */
    protected $transaction;

    /**
     * Holds the currency service
     * @var \Heystack\Subsystem\Ecommerce\Currency\CurrencyService
     */
    protected $currencyService;

    /**
     * Holds the px post service for when using the auth complete cycle
     * @var \Heystack\Subsystem\Payment\DPS\PXPost\Service
     */
    protected $pxPostService;

    /**
     * Holds the data array which contains all the data specific to the payment
     * @var array
     */
    protected $data = array();

    /**
     * Hold the soap client used for connections with DPS
     * @var \SoapClient
     */
    protected $soapClient;

    /**
     * @var string
     */
    protected $stage = self::STAGE_AUTH;

    /**
     * This is the amount of money authorised in the Auth-Complete payment type
     * @var int
     */
    protected $authAmount = 1;

    /**
     * Default wsdl for Soap client
     * @var string
     */
    protected $wsdl = 'https://sec.paymentexpress.com/pxf/pxf.svc?wsdl';

    /**
     * List of messages for each status code
     * @var array
     */
    protected $statusMessages = array(
        0 => 'Approved',
        1 => 'Declined',
        2 => 'Declined due to temporary error, please retry',
        3 => 'There was an error with your transaction, please contact the site admin',
        4 => 'Transaction result cannot be determined at this time (re-run GetTransaction)',
        5 => 'Transaction did not proceed due to being attempted after timeout timestamp or having been cancelled by a CancelTransaction call',
        6 => 'No transaction found (SessionId query failed to return a transaction record - transaction not yet attempted)'
    );

    /**
     * @var array
     */
    protected $errorStatuses = array(
        2,
        3,
        4,
        5,
        6
    );

    /**
     * Creates the Service object
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface               $eventService
     * @param \Heystack\Subsystem\Ecommerce\Transaction\Interfaces\TransactionInterface $transaction
     * @param \Heystack\Subsystem\Payment\DPS\PXPost\Service                            $pxPostService
     */
    public function __construct(
        EventDispatcherInterface $eventService,
        TransactionInterface $transaction,
        CurrencyService $currencyService,
        PXPostService $pxPostService = null
    ) {
        $this->eventService = $eventService;
        $this->transaction = $transaction;

        if (!is_null($pxPostService)) {
            $this->pxPostService = $pxPostService;
        }
        $this->currencyService = $currencyService;
    }

    /**
     * @return TransactionInterface
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * Defines an array of required parameters used in setConfig
     * @return array
     */
    protected function getRequiredConfig()
    {
        return array(
            self::CONFIG_TYPE,
            self::CONFIG_USERNAME,
            self::CONFIG_PASSWORD
        );
    }

    /**
     * Defines an array of required parameters used in setConfig
     * @return array
     */
    protected function getAllowedConfig()
    {
        return array(
            self::CONFIG_TYPE,
            self::CONFIG_USERNAME,
            self::CONFIG_PASSWORD
        );
    }

    /**
     * Gets the allowed options for the additional configuration
     * @return array
     */
    public function getAllowedAdditionalConfig()
    {
        return array(
            'enableAddBillCard',
            'avsAction',
            'avsPostCode',
            'avsStreetAddress',
            'billingId',
            'token billing',
            'dateStart',
            'enableAvsData',
            'enablePaxInfo',
            'merchantReference',
            'paxDateDepart',
            'paxName',
            'paxOrigin',
            'paxTicketNumber',
            'paxTravelAgentInfo',
            'timeout',
            'txnData1',
            'txnData2',
            'txnData3'
        );
    }

    /**
     * @return array
     */
    protected function getRequiredAdditionalConfig()
    {
        return array();
    }

    /**
     * @param array $config
     * @return array
     */
    protected function validateConfig(array $config)
    {
        $errors = array();

        if (isset($config[self::CONFIG_TYPE]) && !in_array(
            $config[self::CONFIG_TYPE],
            array(
                self::TYPE_AUTH_COMPLETE,
                self::TYPE_PURCHASE
            )
        )
        ) {
            $errors[] = "{$config[self::CONFIG_TYPE]} is not a valid 'Type' for this payment handler";
        }

        return $errors;
    }

    /**
     * Returns an array of required parameters used in setConfig
     * @return array
     */
    protected function getRequiredUserConfig()
    {
        return array();
    }

    /**
     * Returns an array of allowed config parameters
     * @return array
     */
    protected function getAllowedUserConfig()
    {
        return array();
    }

    /**
     * Validates config
     */
    protected function validateUserConfig(array $config)
    {
        return array();
    }

    /**
     * @return bool
     */
    protected function validateAdditionalConfig(array $config)
    {
        return array();
    }

    /**
     * @return bool
     */
    public function getType()
    {
        return isset($this->config[self::CONFIG_TYPE]) ? $this->config[self::CONFIG_TYPE] : false;
    }

    /**
     * @param $type
     */
    public function setType($type)
    {
        $this->config[self::CONFIG_TYPE] = $type;

        $this->validateConfig($this->config);
    }

    /**
     * @return string
     */
    public function getReturnUrl()
    {
        $returnUrl = \EcommerceInputController::$url_segment . '/process/' . InputProcessor::IDENTIFIER;
        switch ($this->config[self::CONFIG_TYPE]) {
            case self::TYPE_AUTH_COMPLETE:
                $returnUrl .= '/check/auth';
                break;
            case self::TYPE_PURCHASE:
                $returnUrl .= '/check/purchase';
                break;
        }

        return \Director::absoluteURL($returnUrl);
    }

    /**
     * @return string
     */
    public function getTxnType()
    {

        if ($this->getType() == self::TYPE_AUTH_COMPLETE && $this->getStage() == self::STAGE_AUTH) {
            return self::TXN_TYPE_AUTH;
        }

        return self::TXN_TYPE_PURCHASE;

    }

    /**
     * @return \SoapClient
     */
    public function getSoapClient()
    {
        if (!$this->soapClient) {

            $this->soapClient = new \SoapClient(
                $this->getWsdl(),
                array(
                    'soap_version' => SOAP_1_1,
                    'trace'        => $this->getTestingMode()
                )
            );

        }

        return $this->soapClient;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getTransactionId()
    {
        $soapClient = $this->getSoapClient();

        $configuration = array(
            'username'   => $this->config[self::CONFIG_USERNAME],
            'password'   => $this->config[self::CONFIG_PASSWORD],
            'tranDetail' => array_merge(
                array(
                    'txnType'   => $this->getTxnType(),
                    'currency'  => $this->getCurrencyCode(),
                    'amount'    => $this->getAmount(),
                    'returnUrl' => $this->getReturnUrl()
                ),
                $this->getAdditionalConfig()
            )
        );

        $response = $soapClient->GetTransactionId($configuration);

        if (is_object($response) && $response->GetTransactionIdResult && $response->GetTransactionIdResult->success) {

            return $response->GetTransactionIdResult->sessionId;

        } else {

            throw new Exception($soapClient->__getLastResponse(), $response, $configuration);

        }
    }

    /**
     * @param $transactionID
     * @return PaymentResponse
     * @throws Exception
     */
    public function checkTransaction($transactionID)
    {
        $soapClient = $this->getSoapClient();

        $configuration = array(
            'username'      => $this->config[self::CONFIG_USERNAME],
            'password'      => $this->config[self::CONFIG_PASSWORD],
            'transactionId' => $transactionID
        );

        $response = $soapClient->GetTransaction($configuration);

        if (!is_object($response) || !isset($response->GetTransactionResult)) {
            throw new Exception($soapClient->__getLastResponse(), $response, $configuration);
        }

        $result = $response->GetTransactionResult;
        $result->statusCode = $result->status;

        if (in_array($result->statusCode, $this->errorStatuses)) {
            $result->status = 'Error';
        } else {
            if ($result->statusCode === 0) {
                $result->status = 'Accepted';
            } elseif ($result->statusCode === 1) {
                $result->status = 'Declined';
            }
        }

        return new PaymentResponse(
            json_decode(
                json_encode((array)$result),
                true
            )
        );
    }

    /**
     * @param $dpsTxnRef
     * @return array|bool|\Heystack\Subsystem\Payment\DPS\PXPost\PaymentResponse
     */
    public function completeTransaction($dpsTxnRef)
    {
        $this->setStage(self::STAGE_COMPLETE);

        if ($this->pxPostService instanceof PXPostService) {

            try {

                $this->pxPostService->setTxnType(PXPostService::TXN_TYPE_COMPLETE);
                $this->pxPostService->setAdditionalConfigByKey('DpsTxnRef', $dpsTxnRef);

                return $this->pxPostService->processComplete();

            } catch (\Exception $e) {

                return false;

            }

        }

        return false;
    }

    /**
     * Sets the stage of the Auth-Complete cycle
     * @param string $stage
     * @throws ConfigurationException
     */
    public function setStage($stage)
    {
        if (
            $this->getType() == self::TYPE_AUTH_COMPLETE
            && in_array(
                $stage,
                array(
                    self::STAGE_AUTH,
                    self::STAGE_COMPLETE
                )
            )
        ) {
            $this->stage = $stage;
        } else {
            throw new ConfigurationException('Auth and Complete are the only supported stages for the Auth-Complete cycle');
        }
    }

    /**
     *
     * @return string
     */
    public function getStage()
    {
        return $this->stage;
    }

    /**
     *
     * @return string
     */
    public function getAmount()
    {
        if ($this->getTxnType() == self::TXN_TYPE_AUTH) {

            if (in_array($this->currencyService->getActiveCurrencyCode(), $this->currenciesWithoutCents)) {

                return $this->authAmount;

            }

            return number_format($this->authAmount, 2);

        }

        if (in_array($this->currencyService->getActiveCurrencyCode(), $this->currenciesWithoutCents)) {

            return $this->transaction->getTotal();

        }

        return number_format($this->transaction->getTotal(), 2);
    }

    /**
     * Set the amount to authorise when using Auth-Complete
     * @param int $authAmount
     */
    public function setAuthAmount($authAmount)
    {
        $this->authAmount = $authAmount;
    }

    /**
     * Get the amount that should be authorised when using Auth-Complete
     * @return int
     */
    public function getAuthAmount()
    {
        return $this->authAmount;
    }

    /**
     * Set all status messages
     * @param array $statusMessages
     */
    public function setStatusMessages($statusMessages)
    {
        $this->statusMessages = $statusMessages;
    }

    /**
     * Get all status messages
     * @return array
     */
    public function getStatusMessages()
    {
        return $this->statusMessages;
    }

    /**
     * Set a particular status message by code
     * @param $code
     * @param $message
     */
    public function setStatusMessage($code, $message)
    {
        $this->statusMessages[$code] = $message;
    }

    /**
     * Get a particular status message by code
     * @param $code
     * @return bool
     */
    public function getStatusMessage($code)
    {
        return isset($this->statusMessages[$code]) ? $this->statusMessages[$code] : false;
    }

    /**
     * @param string $wsdl
     * @throws ConfigurationException
     * @return void
     */
    public function setWsdl($wsdl)
    {
        if (!\Director::is_absolute_url($wsdl)) {

            throw new ConfigurationException("Wsdl needs to be an absolute url");

        }

        $this->wsdl = $wsdl;
    }

    /**
     * Get the wsdl
     * @return string
     */
    public function getWsdl()
    {
        return $this->wsdl;
    }

}
