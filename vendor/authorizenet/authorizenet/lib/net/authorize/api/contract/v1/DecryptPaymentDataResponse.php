<?php
namespace net\authorize\api\contract\v1;

/**
 * Class representing DecryptPaymentDataResponse
 */
class DecryptPaymentDataResponse extends ANetApiResponseType
{

    /**
     * @property \net\authorize\api\contract\v1\CustomerAddressType $shippingInfo
     */
    private $shippingInfo = null;

    /**
     * @property \net\authorize\api\contract\v1\CustomerAddressType $billingInfo
     */
    private $billingInfo = null;

    /**
     * @property \net\authorize\api\contract\v1\CreditCardMaskedType $cardInfo
     */
    private $cardInfo = null;

    /**
     * @property \net\authorize\api\contract\v1\PaymentDetailsType $paymentDetails
     */
    private $paymentDetails = null;

    /**
     * Gets as shippingInfo
     *
     * @return \net\authorize\api\contract\v1\CustomerAddressType
     */
    public function getShippingInfo()
    {
        return $this->shippingInfo;
    }

    /**
     * Sets a new shippingInfo
     *
     * @param \net\authorize\api\contract\v1\CustomerAddressType $shippingInfo
     * @return self
     */
    public function setShippingInfo(\net\authorize\api\contract\v1\CustomerAddressType $shippingInfo)
    {
        $this->shippingInfo = $shippingInfo;
        return $this;
    }

    /**
     * Gets as billingInfo
     *
     * @return \net\authorize\api\contract\v1\CustomerAddressType
     */
    public function getBillingInfo()
    {
        return $this->billingInfo;
    }

    /**
     * Sets a new billingInfo
     *
     * @param \net\authorize\api\contract\v1\CustomerAddressType $billingInfo
     * @return self
     */
    public function setBillingInfo(\net\authorize\api\contract\v1\CustomerAddressType $billingInfo)
    {
        $this->billingInfo = $billingInfo;
        return $this;
    }

    /**
     * Gets as cardInfo
     *
     * @return \net\authorize\api\contract\v1\CreditCardMaskedType
     */
    public function getCardInfo()
    {
        return $this->cardInfo;
    }

    /**
     * Sets a new cardInfo
     *
     * @param \net\authorize\api\contract\v1\CreditCardMaskedType $cardInfo
     * @return self
     */
    public function setCardInfo(\net\authorize\api\contract\v1\CreditCardMaskedType $cardInfo)
    {
        $this->cardInfo = $cardInfo;
        return $this;
    }

    /**
     * Gets as paymentDetails
     *
     * @return \net\authorize\api\contract\v1\PaymentDetailsType
     */
    public function getPaymentDetails()
    {
        return $this->paymentDetails;
    }

    /**
     * Sets a new paymentDetails
     *
     * @param \net\authorize\api\contract\v1\PaymentDetailsType $paymentDetails
     * @return self
     */
    public function setPaymentDetails(\net\authorize\api\contract\v1\PaymentDetailsType $paymentDetails)
    {
        $this->paymentDetails = $paymentDetails;
        return $this;
    }


}

