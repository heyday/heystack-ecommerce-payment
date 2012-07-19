<?php

namespace Heystack\Subsystem\Payment\Interfaces;


interface PaymentHandlerInterface 
{
    public function setConfig(array $config);
    public function getConfig();
    public function executePayment();
}