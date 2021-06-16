<?php
  namespace M2\Ordertransfer\Controller\Adminhtml\Transfer;

  use DateTime;
  use DateTimeZone;
  use \Magento\Framework\App\ObjectManager;

  class Index extends \Magento\Backend\App\Action
  {

    private $_message = null;
    protected $resultPageFactory;

    public function __construct(
      \Magento\Backend\App\Action\Context $context,
      \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
      parent::__construct($context);
      $this->resultPageFactory = $resultPageFactory;

      $this->messageManager = ObjectManager::getInstance()->get(
        \Magento\Framework\Message\ManagerInterface::class
      );
    }
  
    public function execute() {
      $data = $this->getRequest()->getPost();
      $pre_owner = null;

      $order_number = trim($data['order']);
      $email = trim($data['email']);

      if(strlen($order_number) > 0 && strlen($email) > 0) {

        $this->customerRepo = ObjectManager::getInstance()->get(
          \Magento\Customer\Api\CustomerRepositoryInterface::class
        );
        $this->customerModel = ObjectManager::getInstance()->get(
          \Magento\Customer\Model\Customer::class
        );
        $this->orderInterface = ObjectManager::getInstance()->get(
          \Magento\Sales\Api\Data\OrderInterface::class
        );

        $ct = $this->customerRepo->get($email);
        $customer = $this->customerModel->load($ct->getId());
        $order = $this->orderInterface->loadByIncrementId($order_number);
        
        if($customer->getData() == null) {
          $this->_message = $email.' does not exist.';
        } else if($order->getData() == null) {
          $this->_message = $order_number.' does not exist.';
        } else if($order->getData()['customer_id'] == $customer->getId()) {
          $this->_message = 'Order '.$order_number.' is already associated to '.$email;
        } else if(!$customer->getDefaultBilling()) {
          $this->_message = $email.' does not have default billing address.';
        }

        if($this->_message) {
          $this->messageManager->addError(__($this->_message));
        } else {
          $pre_owner = $order->getCustomerEmail();

          try {
            $order->setCustomerId($customer->getId());
            $order->setCustomerFirstname($customer->getFirstname());
            $order->setCustomerLastname($customer->getLastname());
            $order->setCustomerEmail($customer->getEmail());
            $order->setCustomerGroupId($customer->getGroupId());
            $date = new DateTime("now", new DateTimeZone('America/Los_Angeles'));
            $order->addStatusHistoryComment('Order Transfer: From: '.$pre_owner.' - To: '.$email. '. Timestamp: '.$date->format('m/d/y g:i a')." Pacific");
            $order->save();

            $this->customerAddress = ObjectManager::getInstance()->get(
              \Magento\Customer\Model\AddressFactory::class
            );
            $this->repositoryAddress = ObjectManager::getInstance()->get(
              \Magento\Sales\Model\Order\AddressRepository::class
            );

            $address = $this->customerAddress->create()->load($customer->getDefaultBilling());
            $billingAddress = $this->repositoryAddress->get($order->getBillingAddress()->getId());
            $billingAddress->setPrefix($address->getPrefix())
            ->setFirstname($address->getFirstname())
            ->setLastname($address->getLastname())
            ->setEmail($customer->getEmail())
            ->setCompany($address->getCompany())
            ->setStreet($address->getStreet())
            ->setCity($address->getCity())
            ->setCountryId($address->getCountryId())
            ->setRegion($address->getRegion())
            ->setRegionId($address->getRegionId())
            ->setPostcode($address->getPostcode())
            ->setTelephone($address->getTelephone())
            ->setFax($address->getFax());
            $this->repositoryAddress->save($billingAddress);

            $this->messageManager->addSuccess(__("Order # ".$order_number." has been transferred to ".$email));
            
          } catch(Exception $e) {
            $this->messageManager->addError(__($e->getMessage()));
          }
        }

      }

      return $this->resultPageFactory->create();
    }
  
  }
?>