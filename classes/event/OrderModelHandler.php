<?php namespace Lovata\OrdersShopaholic\Classes\Event;

use Lovata\Toolbox\Classes\Event\ModelHandler;
use Lovata\Toolbox\Classes\Helper\SendMailHelper;

use Lovata\Shopaholic\Models\Settings;
use Lovata\OrdersShopaholic\Models\Order;
use Lovata\OrdersShopaholic\Classes\Item\OrderItem;
use Lovata\OrdersShopaholic\Classes\Processor\OrderProcessor;
use Lovata\OrdersShopaholic\Classes\Store\OrderListStore;

/**
 * Class OrderModelHandler
 * @package Lovata\OrdersShopaholic\Classes\Event
 * @author  Andrey Kharanenka, a.khoronenko@lovata.com, LOVATA Group
 */
class OrderModelHandler extends ModelHandler
{
    /** @var  Order */
    protected $obElement;

    /** @var  OrderListStore */
    protected $obListStore;

    /**
     * OrderModelHandler constructor.
     */
    public function __construct()
    {
        $this->obListStore = OrderListStore::instance();
    }

    /**
     * Add listeners
     * @param \Illuminate\Events\Dispatcher $obEvent
     */
    public function subscribe($obEvent)
    {
        parent::subscribe($obEvent);

        $obEvent->listen(OrderProcessor::EVENT_ORDER_CREATED, function ($obOrder) {
            $this->obElement = $obOrder;

            $bSendEmail = Settings::getValue('send_email_after_creating_order');
            if (!$bSendEmail) {
                return;
            }

            $this->sendUserEmailAfterCreating();
            $this->sendManagerEmailAfterCreating();
        });
    }

    /**
     * Get model class name
     * @return string
     */
    protected function getModelClass()
    {
        return Order::class;
    }

    /**
     * Get item class name
     * @return string
     */
    protected function getItemClass()
    {
        return OrderItem::class;
    }

    /**
     * After save event handler
     */
    protected function afterSave()
    {
        parent::afterSave();

        $this->checkFieldChanges('user_id', $this->obListStore->user);

        $this->checkFieldChangesTwoParam('status_id', 'user_id', $this->obListStore->status);
        $this->checkFieldChangesTwoParam('shipping_type_id', 'user_id', $this->obListStore->shipping_type);
        $this->checkFieldChangesTwoParam('payment_method_id', 'user_id', $this->obListStore->payment_method);
    }

    /**
     * After delete event handler
     */
    protected function afterDelete()
    {
        parent::afterDelete();

        $this->obListStore->user->clear($this->obElement->user_id);

        $this->obListStore->status->clear($this->obElement->status_id);
        $this->obListStore->status->clear($this->obElement->status_id, $this->obElement->user_id);

        $this->obListStore->shipping_type->clear($this->obElement->shipping_type_id);
        $this->obListStore->shipping_type->clear($this->obElement->shipping_type_id, $this->obElement->user_id);

        $this->obListStore->payment_method->clear($this->obElement->payment_method_id);
        $this->obListStore->payment_method->clear($this->obElement->payment_method_id, $this->obElement->user_id);
    }

    /**
     * Send email to user, after order creating
     */
    protected function sendUserEmailAfterCreating()
    {
        //Get user email
        $arOrderProeprtyList = $this->obElement->property;
        if (empty($arOrderProeprtyList) || !isset($arOrderProeprtyList['email']) || empty($arOrderProeprtyList['email'])) {
            return;
        }

        $sEmail = $arOrderProeprtyList['email'];

        //Get mail data
        $arMailData = $this->getDefaultEmailData();

        //Get mail template
        $sMailTemplate = Settings::getValue('creating_order_mail_template', 'lovata.ordersshopaholic::mail.create_order_user');

        $obSendMailHelper = SendMailHelper::instance();
        $obSendMailHelper->send(
            $sMailTemplate,
            $sEmail,
            $arMailData,
            OrderProcessor::EVENT_ORDER_CREATED_USER_MAIL_DATA,
            true);
    }

    /**
     * Send email to manager, after order creating
     */
    protected function sendManagerEmailAfterCreating()
    {
        //Get email list
        $sEmailList = Settings::getValue('creating_order_manager_email_list');
        if (empty($sEmailList)) {
            return;
        }

        //Get mail data
        $arMailData = $this->getDefaultEmailData();

        //Get mail template
        $sMailTemplate = Settings::getValue('creating_order_manager_mail_template', 'lovata.ordersshopaholic::mail.create_order_manager');

        $obSendMailHelper = SendMailHelper::instance();
        $obSendMailHelper->send(
            $sMailTemplate,
            $sEmailList,
            $arMailData,
            OrderProcessor::EVENT_ORDER_CREATED_MANAGER_MAIL_DATA,
            true);
    }

    /**
     * Get default array data for template
     * @return array
     */
    protected function getDefaultEmailData()
    {
        $arResult = [
            'order'        => $this->obElement,
            'order_number' => $this->obElement->order_number,
            'site_url'     => config('app.url'),
        ];

        return $arResult;
    }
}