<?php namespace Academe\SagePay\Psr7\Request;

/**
 * The transaction value object to send a transaction to SagePay.
 * TODO: look at all the other transaction types - they are very different messages.
 *   Maybe change this one to a Payment only, with an abstract class to hold
 *   the constants and shared request message functionality?
 */

use Exception;
use UnexpectedValueException;

use ReflectionClass;

use Academe\SagePay\Psr7\Model\Auth;
use Academe\SagePay\Psr7\PaymentMethod\PaymentMethodInterface;
use Academe\SagePay\Psr7\Money\AmountInterface;
use Academe\SagePay\Psr7\Model\AddressInterface;
use Academe\SagePay\Psr7\Model\ShippingDetails;
use Academe\SagePay\Psr7\Model\Address;
use Academe\SagePay\Psr7\Model\PersonInterface;
use Academe\SagePay\Psr7\Model\Endpoint;

class Transaction extends AbstractRequest
{
    protected $resource_path = ['transactions'];

    protected $auth;

    // Minimum mandatory data (constructor).
    protected $transactionType;
    protected $paymentMethod;
    protected $vendorTxCode;
    protected $amount;
    protected $description;
    protected $billingAddress;
    protected $customer;

    // Optional or overridable data (setters).
    protected $entryMethod;
    protected $recurringIndicator;
    protected $giftAid = false;
    protected $applyAvsCvcCheck;
    protected $apply3DSecure;
    protected $shippingAddress;
    protected $shippingRecipient;

    /**
     * @var string The prefix is added to the name fields of the customer.
     */
    protected $customerFieldsPrefix = 'customer';

    /**
     * @var string The prefix is added to the name fields when sending to Sage Pay
     */
    protected $shippingNameFieldPrefix = 'recipient';

    /**
     * @var string The prefix added to address name fields
     */
    protected $shippingAddressFieldPrefix = 'shipping';

    /**
     * Valid values for enumerated input types.
     */

    const TRANSACTION_TYPE_PAYMENT                  = 'Payment';

    const ENTRY_METHOD_ECOMMERCE                    = 'Ecommerce';
    const ENTRY_METHOD_MAILORDER                    = 'MailOrder';
    const ENTRY_METHOD_TELEPHONEORDER               = 'TelephoneOrder';

    const RECURRING_INDICATOR_RECURRING             = 'Recurring';
    const RECURRING_INDICATOR_INSTALMENT            = 'Instalment';

    const APPLY_AVS_CVC_CHECK_USEMSPSETTING         = 'UseMSPSetting';
    const APPLY_AVS_CVC_CHECK_FORCE                 = 'Force';
    const APPLY_AVS_CVC_CHECK_DISABLE               = 'Disable';
    const APPLY_AVS_CVC_CHECK_FORCEIGNORINGRULES    = 'ForceIgnoringRules';

    // The numeric values are the Sage Pay Direct equivalents.
    const APPLY_3D_SECURE_USEMSPSETTING             = 'UseMSPSetting'; // 0
    const APPLY_3D_SECURE_FORCE                     = 'Force'; // 1
    const APPLY_3D_SECURE_DISABLE                   = 'Disable'; // 2
    const APPLY_3D_SECURE_FORCEIGNORINGRULES        = 'ForceIgnoringRules'; // 3

    public function __construct(
        Endpoint $endpoint,
        Auth $auth,
        $transactionType,
        PaymentMethodInterface $paymentMethod,
        $vendorTxCode,
        AmountInterface $amount,
        $description,
        AddressInterface $billingAddress,
        PersonInterface $customer,
        AddressInterface $shippingAddress = null,
        PersonInterface $shippingRecipient = null
        // TODO: Why isn't this a shipping address and shipping person? Split it up and remove ShippingDetails.
        //ShippingDetails $shippingDetails = null
    ) {
        $this->endpoint = $endpoint;
        $this->auth = $auth;
        $this->description = $description;

        // Some simple normalisation.
        $transactionType = ucfirst(strtolower($transactionType));

        // Is the transaction type one we are expecting?
        $transactionTypeValue = $this->constantValue('TRANSACTION_TYPE', $transactionType);
        if ( ! $transactionTypeValue) {
            throw new UnexpectedValueException(sprintf('Unknown transaction type "%s".', (string)$transactionType));
        }

        $this->transactionType = $transactionTypeValue;

        $this->paymentMethod = $paymentMethod;
        $this->vendorTxCode = $vendorTxCode;
        $this->amount = $amount;
        $this->billingAddress = $billingAddress->withFieldPrefix('');
        $this->customer = $customer->withFieldPrefix($this->customerFieldsPrefix);

        $this->shippingAddress = $shippingAddress->withFieldPrefix($this->shippingAddressFieldPrefix);
        $this->shippingRecipient = $shippingRecipient->withFieldPrefix($this->shippingNameFieldPrefix);
    }

    public function withEntryMethod($entryMethod)
    {
        // Get the value from the class constants.
        $value = $this->constantValue('ENTRY_METHOD', $entryMethod);

        if ( ! $value) {
            throw new UnexpectedValueException(sprintf(
                'Unknown entryMethod "%s"; require one of %s',
                (string)$entryMethod,
                implode(', ', static::getEntryMethods())
            ));
        }

        $copy = clone $this;
        $copy->entryMethod = $value;
        return $copy;
    }

    public static function getEntryMethods()
    {
        return static::constantList('ENTRY_METHOD');
    }

    public function withRecurringIndicator($recurringIndicator)
    {
        // Get the value from the class constants.
        $value = $this->constantValue('RECURRING_INDICATOR', $recurringIndicator);

        if ( ! $value) {
            throw new UnexpectedValueException(sprintf(
                'Unknown recurringIndicator "%s"; require one of %s',
                (string)$recurringIndicator,
                implode(', ', static::getRecurringIndicators())
            ));
        }

        $copy = clone $this;
        $copy->recurringIndicator = $value;
        return $copy;
    }

    public static function getRecurringIndicators()
    {
        return static::constantList('RECURRING_INDICATOR');
    }

    public function withGiftAid($giftAid)
    {
        $copy = clone $this;
        $copy->giftAid = ! empty($giftAid);
        return $copy;
    }

    public function withApplyAvsCvcCheck($applyAvsCvcCheck)
    {
        // Get the value from the class constants.
        $value = $this->constantValue('APPLY_AVS_CVC_CHECK', $applyAvsCvcCheck);

        if ( ! $value) {
            throw new UnexpectedValueException(sprintf(
                'Unknown applyAvsCvcCheck "%s"; require one of %s',
                (string)$applyAvsCvcCheck,
                implode(', ', staticgetApplyAvsCvcChecks())
            ));
        }

        $copy = clone $this;
        $copy->applyAvsCvcCheck = $value;
        return $copy;
    }

    public static function getApplyAvsCvcChecks()
    {
        return static::constantList('APPLY_AVS_CVC_CHECK');
    }

    public function withApply3DSecure($apply3DSecure)
    {
        // Get the value from the class constants.
        $value = $this->constantValue('APPLY_3D_SECURE', $apply3DSecure);

        if ( ! $value) {
            throw new UnexpectedValueException(sprintf(
                'Unknown apply3DSecure "%s"; require one of %s',
                (string)$apply3DSecure,
                implode(', ', static::getApply3DSecures())
            ));
        }

        $copy = clone $this;
        $copy->apply3DSecure = $value;
        return $copy;
    }

    public static function getApply3DSecures()
    {
        return static::constantList('APPLY_3D_SECURE');
    }

    public function withShippingDetails(ShippingDetails $shippingDetails)
    {
        $copy = clone $this;
        $copy->shippingDetails = $shippingDetails;
        return $copy;
    }

    public function withDescription($description)
    {
        $copy = clone $this;
        $copy->description = $description;
        return $copy;
    }

    /**
     * Get the message body data for serializing.
     */
    public function jsonSerialize()
    {
        $result = [
            'transactionType' => $this->transactionType,
            'paymentMethod' => $this->paymentMethod,
            'vendorTxCode' => $this->vendorTxCode,
            'amount' => $this->amount->getAmount(),
            'currency' => $this->amount->getCurrencyCode(),
            'description' => $this->description,
            'billingAddress' => $this->billingAddress,
        ];

        // The customer details 
        $result = array_merge($result, $this->customer->jsonSerialize());

        $shippingDetails = [];

        if ( ! empty($this->shippingAddress)) {
            $shippingDetails = array_merge($shippingDetails, $this->shippingAddress->jsonSerialize());
        }

        if ( ! empty($this->shippingRecipient)) {
            // We only want the names from the recipient details.
            $shippingDetails = array_merge($shippingDetails, $this->shippingRecipient->getNamesBody());
        }

        // If there are shipping details, then merge this in:
        if ( ! empty($shippingAddress)) {
            $result['shippingDetails'] = $shippingDetails;
        }

        // Add remaining optional parameters.

        if ( ! empty($this->entryMethod)) {
            $result['entryMethod'] = $this->entryMethod;
        }

        if ( ! empty($this->recurringIndicator)) {
            $result['recurringIndicator'] = $this->recurringIndicator;
        }

        if ( ! empty($this->giftAid)) {
            $result['giftAid'] = $this->giftAid;
        }

        if ( ! empty($this->applyAvsCvcCheck)) {
            $result['applyAvsCvcCheck'] = $this->applyAvsCvcCheck;
        }

        if ( ! empty($this->apply3DSecure)) {
            $result['apply3DSecure'] = $this->apply3DSecure;
        }

        return $result;
    }

    /**
     * Get an array of constants in this [late-bound] class, with an optional prefix.
     */
    public static function constantList($prefix = null)
    {
        $reflection = new ReflectionClass(__CLASS__);
        $constants = $reflection->getConstants();

        if (isset($prefix)) {
            $result = [];
            $prefix = strtoupper($prefix);
            foreach($constants as $key => $value) {
                if (strpos($key, $prefix) === 0) {
                    $result[$key] = $value;
                }
            }
            return $result;
        } else {
            return $constants;
        }
    }

    /**
     * Get a class constant value based on suffix and prefix.
     * Returns null if not found.
     */
    public static function constantValue($prefix, $suffix)
    {
        $name = strtoupper($prefix . '_' . $suffix);

        if (defined("static::$name")) {
            return constant("static::$name");
        }
    }

    public function getHeaders()
    {
        return $this->getBasicAuthHeaders();
    } 
}
