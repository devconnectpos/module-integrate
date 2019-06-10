<?php
/**
 * Created by Hung Nguyen - hungnh@smartosc.com
 * Date: 03/12/2018
 * Time: 10:25
 */

namespace SM\Integrate\StoreCredit\Contract;

use Magento\Framework\ObjectManagerInterface;

/**
 * Class AbstractStoreCreditIntegrate
 *
 * @package SM\Integrate\StoreCredit\Contract
 */
abstract class AbstractStoreCreditIntegrate
{

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * AbstractStoreCreditIntegrate constructor.
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * Retrieve session object
     *
     * @return \Magento\Backend\Model\Session\Quote
     */
    protected function getSession()
    {
        return $this->objectManager->get('Magento\Backend\Model\Session\Quote');
    }

    /**
     * Retrieve quote object
     *
     * @return \Magento\Quote\Model\Quote
     */
    protected function getQuote()
    {
        return $this->getSession()->getQuote();
    }
}
