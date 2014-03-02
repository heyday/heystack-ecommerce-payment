<?php

namespace Heystack\Payment\DPS;

use Heystack\Payment\Traits\PaymentConfigTrait;

use Heystack\Core\Exception\ConfigurationException;

abstract class Service
{

    use PaymentConfigTrait;

    /**
     * List of currencies supported by DPS
     * @var array
     */
    protected $supportedCurrencies = [
        'CAD', 'CHF', 'DKK', 'EUR',
        'FRF', 'GBP', 'HKD', 'JPY',
        'NZD', 'SGD', 'THB', 'USD',
        'ZAR', 'AUD', 'WST', 'VUV',
        'TOP', 'SBD', 'PGK', 'MYR',
        'KWD', 'FJD'
    ];

    /**
     * List of currencies which don't have cents
     * @var array
     */
    protected $currenciesWithoutCents = [
        'JPY'
    ];

    /**
     * If testing last request data is needed form soap calls thi should be set to true
     * @var bool
     */
    protected $testingMode = false;

    abstract public function getTransaction();

    /**
     * Set the testing mode
     * @param boolean $testingMode
     */
    public function setTestingMode($testingMode)
    {
        $this->testingMode = $testingMode;
    }

    /**
     * Get the testing mode
     * @return boolean
     */
    public function getTestingMode()
    {
        return $this->testingMode;
    }

    /**
     * Returns the currency code.
     * @return mixed
     * @throws ConfigurationException
     */
    protected function getCurrencyCode()
    {
        $currencyCode = $this->currencyService->getActiveCurrencyCode();

        if (!in_array($currencyCode, $this->supportedCurrencies)) {

            throw new ConfigurationException("The currency $currencyCode is not supported by DPS");

        }

        return $currencyCode;
    }

    protected function responseFromErrors($errors = null)
    {
        die();

    }

   /**
    * Returns the formatted payment amount
    * @return string Amount
    */
    protected function formatAmount($amount)
    {
        $currencyCode = $this->currencyService->getActiveCurrencyCode();

        if (!in_array($currencyCode, $this->currenciesWithoutCents)) {
            return number_format($amount, 2, '.', '');
        }

        return $amount;

    }

}