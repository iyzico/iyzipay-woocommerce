<?php

namespace Iyzico\IyzipayWoocommerce\Checkout;

class CheckoutView {
	protected $checkoutSettings;

	public function __construct( CheckoutSettings $checkoutSettings ) {
		$this->checkoutSettings = $checkoutSettings;
	}

	public function renderCheckoutForm( $checkoutFormInitialize ) {
		$className = $this->checkoutSettings->findByKey( 'form_class' );
		$message   = '<p id="infoBox" style="display:none;">' . esc_html( $this->checkoutSettings->findByKey( 'payment_checkout_value' ) ) . '</p>';
		echo '<script>
                jQuery(window).on("load", function(){
                    document.getElementById("loadingBar").style.display="none";
                    document.getElementById("infoBox").style.display="block";
                    document.getElementById("iyzipay-checkout-form").style.display="block";
                });
              </script>';

		if ( $checkoutFormInitialize->getStatus() === "success" ) {
			echo $message;
			echo ' <div style="display:none" id="iyzipay-checkout-form" class="' . esc_attr( $className ) . '">' . $checkoutFormInitialize->getCheckoutFormContent() . '</div>';
		} else {
			echo esc_html( $checkoutFormInitialize->getErrorMessage() );
		}
	}

	public function renderLoadingHtml() {
		echo '<div id="loadingBar">
                <div class="loading"></div>
                <div class="brand">
                    <p>iyzico</p>
                </div>
              </div>';
	}
}