<?php
/**
 * Barzahlen Payment Module (OXID eShop)
 *
 * @copyright   Copyright (c) 2014 Cash Payment Solutions GmbH (https://www.barzahlen.de)
 * @author      Alexander Diebler
 * @license     http://opensource.org/licenses/GPL-3.0  GNU General Public License, version 3 (GPL-3.0)
 */

require_once getShopBasePath() . 'modules/barzahlen/api/loader.php';

/**
 * Extends Order manager
 */
class barzahlen_order extends barzahlen_order_parent
{
    /**
     * Transaction status codes.
     */
    const STATE_PENDING = "pending";
    const STATE_CANCELED = "canceled";

    /**
     * Log file
     */
    const LOGFILE = "barzahlen.log";

    /**
     * Module identifier
     *
     * @var string
     */
    protected $_sModuleId = 'module:barzahlen';

    /**
     * Extends the order cancelation to cancel pending Barzahlen payment slips
     * at the same time.
     */
    public function cancelOrder()
    {
        parent::cancelOrder();

        if ($this->oxorder__oxpaymenttype->value == 'oxidbarzahlen' && $this->oxorder__bzstate->value == self::STATE_PENDING) {

            $sTransactionId = $this->oxorder__bztransaction->value;

            $oRequest = new Barzahlen_Request_Cancel($sTransactionId);
            $cancel = $this->_connectBarzahlenApi($oRequest);

            if ($cancel->isValid()) {
                $this->oxorder__bzstate = new oxField(self::STATE_CANCELED);
                $this->save();
            }
        }
    }

    /**
     * Extends the order deletion to cancel pending Barzahlen payment slips
     * at the same time.
     *
     * @param string $sOxId Ordering ID (default null)
     * @return bool
     */
    public function delete($sOxId = null)
    {
        if (!$sOxId) {
            $sOxId = $this->getId();
        }

        if (!$this->load($sOxId)) {
            return false;
        }

        if ($this->oxorder__oxpaymenttype->value == 'oxidbarzahlen' && $this->oxorder__bzstate->value == self::STATE_PENDING) {

            $sTransactionId = $this->oxorder__bztransaction->value;

            $oRequest = new Barzahlen_Request_Cancel($sTransactionId);
            $cancel = $this->_connectBarzahlenApi($oRequest);

            if ($cancel->isValid()) {
                $this->oxorder__bzstate = new oxField(self::STATE_CANCELED);
                $this->save();
            }
        }

        return parent::delete($sOxId);
    }

    /**
     * Performs the api request.
     *
     * @param Barzahlen_Request $oRequest request object
     */
    protected function _connectBarzahlenApi($oRequest)
    {
        $oApi = $this->_getBarzahlenApi();

        try {
            $oApi->handleRequest($oRequest);
        } catch (Exception $e) {
            oxUtils::getInstance()->writeToLog(date('c') . " API connection failed: " . $e . "\r\r", self::LOGFILE);
        }

        return $oRequest;
    }

    /**
     * Prepares a Barzahlen API object for the payment request.
     *
     * @return Barzahlen_Api
     */
    protected function _getBarzahlenApi()
    {
        $oxConfig = $this->getConfig();
        $sShopId = $oxConfig->getShopId();
        $sModule = $this->_sModuleId;

        $shopId = $oxConfig->getShopConfVar('bzShopId', $sShopId, $sModule);
        $paymentKey = $oxConfig->getShopConfVar('bzPaymentKey', $sShopId, $sModule);
        $sandbox = $oxConfig->getShopConfVar('bzSandbox', $sShopId, $sModule);
        $debug = $oxConfig->getShopConfVar('bzDebug', $sShopId, $sModule);

        $api = new Barzahlen_Api($shopId, $paymentKey, $sandbox);
        $api->setDebug($debug, self::LOGFILE);
        $api->setUserAgent('OXID v' . $oxConfig->getVersion() .  ' / Plugin v1.2.0');
        return $api;
    }
}
