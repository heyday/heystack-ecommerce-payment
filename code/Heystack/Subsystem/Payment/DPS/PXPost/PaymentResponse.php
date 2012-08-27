<?php
/**
 * This file is part of the Heystack package
 *
 * @package Ecommerce-Payment
 */

/**
 * DPS namespace
 */
namespace Heystack\Subsystem\Payment\DPS\PXPost;

use Heystack\Subsystem\Payment\DPS\PXPost\PaymentInterface;

use Heystack\Subsystem\Payment\Traits\PaymentTrait;
use Heystack\Subsystem\Payment\DPS\PaymentTrait as DPSPaymentTrait;
use Heystack\Subsystem\Payment\DPS\PXPost\PaymentTrait as PXPostPaymentTrait;

use Heystack\Subsystem\Core\Storage\StorableInterface;
use Heystack\Subsystem\Core\Storage\Backends\SilverStripeOrm\Backend;
use Heystack\Subsystem\Core\Storage\Traits\ParentReferenceTrait;

/**
 * Payment stores information about payments made with the PXPost method
 *
 * @copyright  Heyday
 * @author Glenn Bautista <glenn@heyday.co.nz>
 * @author Stevie Mayhew <stevie@heyday.co.nz>
 * @package Heystack
 *
 */
class PaymentResponse implements PaymentInterface, StorableInterface
{
    use PaymentTrait;
    use DPSPaymentTrait;
    use PXPostPaymentTrait;
    use ParentReferenceTrait;

    const IDENTIFIER = 'pxpostpayment';

    public function getStorableIdentifier()
    {

        return self::IDENTIFIER;

    }

    /**
     * Get the name of the schema this system relates to
     * @return string
     */
    public function getSchemaName()
    {

        return 'PXPostPayment';

    }

    public function getStorableData()
    {
        $data = array();

        $data['id'] = "PXPostPayment";

        $data['flat'] = array(
            'Status' => $this->getStatus(),
            'CurrencyCode' => $this->getCurrencyCode(),
            'Message' => $this->getMessage(),
            'Amount' => $this->getAmount(),
            'IP' => $this->getIP(),
            'TransactionType' => $this->getTransactionType(),
            'MerchantReference' => $this->getMerchantReference(),
            'TransactionReference' => $this->getTransactionReference(),
            'AuthCode' => $this->getAuthCode(),
            'XMLResponse' => $this->getXMLResponse(),
            'BillingID' => $this->getBillingID(),
            'HelpText' => $this->getHelpText(),
            'ResponseCode' => $this->getResponseCode(),
            'SettlementDate' => $this->getSettlementDate(),
            'ParentID' => $this->parentReference
        );

        $data['parent'] = true;

        $data['related'] = false;

        return $data;

    }

    /**
     * @todo document this
     * @return string
     */
    public function getStorableBackendIdentifiers()
    {
        return array(
            Backend::IDENTIFIER
        );
    }
}