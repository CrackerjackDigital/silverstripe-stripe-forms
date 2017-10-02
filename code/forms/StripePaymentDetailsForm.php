<?php

use \Stripe\Stripe as Stripe;
use \Stripe\Customer as StripeCustomer;

/**
 * A form that allows the user to enter their card details and
 * save them "into" stripe.
 *
 * The end user enters their credit card details and when the
 * form is submitted, they are pushed to Stripe via the API.
 * The form is then pre-populated with summary details of the
 * saved payment details.
 *
 * By default this form uses the default Stripe JS, if you wish
 * to overwrite this functionality (to perform custom operations)
 * then make sure you disable the use_custom_js config variable.
 *
 * @package stripe-forms
 * @subpackage forms
 * @author Mo <morven@ilateral.co.uk>
 */
class StripePaymentDetailsForm extends Form
{

    /**
     * Config variable to specify if we want to use custom JS.
     * Enabling this will disable all requirements calls
     *
     * @var Boolean
     * @config
     */
    private static $use_custom_js = false;

    public function __construct($controller, $name = "StripePaymentDetailsForm")
    {
        $publish_key = StripeForms::publish_key();

        parent::__construct(
            $controller,
            $name,

            // Fields
            FieldList::create(
                HiddenField::create("StripeToken"),
                HiddenField::create("CardType"),
                TextField::create("CardNumber")
                    ->setAttribute("name", "")
                    ->setAttribute("data-stripe", _t('StripePaymentDetailsForm.CardNumber.Label', 'number')),
                TextField::create("ExpirationMonth", _t( 'StripePaymentDetailsForm.ExpirationMonth.Label', "Expiration Month (MM)"))
                    ->setAttribute("name", "")
                    ->setAttribute("data-stripe", "exp_month"),
                TextField::create("ExpirationYear", _t( 'StripePaymentDetailsForm.ExpirationYear.Label', "Expiration Year (YYYY)"))
                    ->setAttribute("name", "")
                    ->setAttribute("data-stripe", "exp_year"),
                TextField::create("CVC")
                    ->setAttribute("name", "")
                    ->setAttribute("data-stripe", "cvc"),
                TextField::create("BillingZip", _t( 'StripePaymentDetailsForm.BillingZipPostal.Label', "Billing Zip/Post Code"))
                    ->setAttribute("name", "")
                    ->setAttribute("data-stripe", "address_zip")
            ),

            // Actions
            FieldList::create(
                FormAction::create("doSavePaymentDetails", "Save")
                    ->addExtraClass("submit")
            )
        );

        if (!StripeForms::config()->use_custom_js) {
            Requirements::javascript("https://js.stripe.com/v2/");
            Requirements::javascriptTemplate(
                "stripe-forms/javascript/StripePaymentDetailsForm.js",
                array(
                    "PublishKey" => $publish_key,
                    "FormName" => $this->FormName()
                )
            );
        }

        $card_details = $this->get_card_details();

        if ($card_details) {
            $this
                ->Fields()
                ->replaceField(
                    "CardType",
                    ReadonlyField::create("CardType")
                );

            $this->loadDataFrom($card_details);
        }
    }

    /**
     * Get the card details from stripe for the current user
     *
     * @return ArrayData | null
     */
    protected function get_card_details()
    {
        $member = Member::currentUser();
        $customer = $member->getStripeCustomer();

        if ($customer && !$customer->deleted && count($customer->sources->data)) {
            $stripe_card = $customer->sources->data[0];

            return new ArrayData(array(
                "CardType" => $stripe_card->brand,
                "CardNumber" => str_pad($stripe_card->last4, 16, "*", STR_PAD_LEFT),
                "ExpirationMonth" => $stripe_card->exp_month,
                "ExpirationYear" => $stripe_card->exp_year,
                "BillingZip" => $stripe_card->address_zip
            ));
        }
    }

    /**
     * Save stripe payment details against a customer
     *
     * @return SS_HTTPResponse
     */
    public function doSavePaymentDetails($data)
    {
        $token = $data["StripeToken"];

        if ($token) {
            $member = Member::currentUser();

            try {
                $member->saveStripeCustomer();

                $this->extend("onSuccessfulSavePaymentDetails", $data);

                $this->sessionMessage(
                    _t("StripeForms.UpdatedDetails", "Updated payment details"),
                    "good"
                );
            } catch (Exception $e) {
                $this->sessionMessage($e->getmessage(),"bad");
            }
        }

        return $this->controller->redirectBack();
    }

}