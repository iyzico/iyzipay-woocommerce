<?php

namespace Iyzico\IyzipayWoocommerce\Checkout;

use Iyzipay\Model\CheckoutFormInitialize;

class CheckoutView
{
    private const LOADING_ID = 'loadingBar';
    private const INFO_BOX_ID = 'infoBox';
    private const CHECKOUT_FORM_ID = 'iyzipay-checkout-form';

    private CheckoutSettings $checkoutSettings;
    /**
     * @var int
     */
    private $orderId = 0;

    public function __construct()
    {
        $this->checkoutSettings = new CheckoutSettings();
    }

    /**
     * @param int $orderId
     * @return void
     */
    public function setOrderId($orderId): void
    {
        $this->orderId = (int) $orderId;
    }

    public function renderCheckoutForm(CheckoutFormInitialize $checkoutFormInitialize): void
    {
        if ($checkoutFormInitialize->getStatus() === 'success') {
            $this->renderInfoBox();

            $className = $this->checkoutSettings->findByKey('form_class') ?? 'popup';

            $allowed_html = [
                'div' => ['id' => [], 'class' => [], 'style' => []],
                'script' => ['type' => [], 'src' => []],
                'style' => [],
                'p' => ['class' => []],
                'strong' => [],
            ];

            printf(
                '<div id="%s" class="%s" style="display:none">%s</div>',
                esc_attr(self::CHECKOUT_FORM_ID),
                esc_attr($className),
                wp_kses($checkoutFormInitialize->getCheckoutFormContent(), $allowed_html)
            );

            $this->renderUiControlScript();
        } else {
            echo esc_html($checkoutFormInitialize->getErrorMessage());
        }
    }

    /**
     * @return void
     */
    private function renderInfoBox(): void
    {
        $paymentValue = $this->checkoutSettings->findByKey('payment_checkout_value');
        printf(
            '<p id="%s" style="display:none">%s</p>',
            esc_attr(self::INFO_BOX_ID),
            esc_html($paymentValue)
        );
    }

    /**
     * @return void
     */
    private function renderUiControlScript(): void
    {
        $ajaxUrl = admin_url('admin-ajax.php');
        $nonce   = wp_create_nonce('iyzico_iframe_loaded');
        ?>
        <script type="text/javascript">
            var iyzicoOrderId = <?php echo (int) $this->orderId; ?>;
            var iyzicoIframeAjaxUrl = "<?php echo esc_url($ajaxUrl); ?>";
            var iyzicoIframeNonce = "<?php echo esc_js($nonce); ?>";

            var iyzicoNotifyIframeLoaded = function () {
                if (!iyzicoOrderId || !iyzicoIframeAjaxUrl) {
                    return;
                }
                if (window.iyzicoIframeLoadedNotified) {
                    return;
                }
                window.iyzicoIframeLoadedNotified = true;

                try {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', iyzicoIframeAjaxUrl, true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
                    xhr.send(
                        'action=iyzico_iframe_loaded'
                        + '&order_id=' + encodeURIComponent(iyzicoOrderId)
                        + '&nonce=' + encodeURIComponent(iyzicoIframeNonce)
                    );
                } catch (e) {
                    // sessizce yut
                }
            };

            var checkIyziInit = function () {
                if (typeof iyziInit !== 'undefined') {
                    document.getElementById('<?php echo esc_js(self::LOADING_ID); ?>').style.display = 'none';
                    document.getElementById('<?php echo esc_js(self::INFO_BOX_ID); ?>').style.display = 'block';
                    document.getElementById('<?php echo esc_js(self::CHECKOUT_FORM_ID); ?>').style.display = 'block';
                    iyzicoNotifyIframeLoaded();
                    return;
                }
                // Henüz yüklenmediyse tekrar dene
                setTimeout(checkIyziInit, 100);
            };

            // Sayfa yüklendiğinde kontrol etmeye başla
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', checkIyziInit);
            } else {
                checkIyziInit();
            }
        </script>
        <?php
    }

    /**
     * @return void
     */
    public function renderLoadingHtml(): void
    {
        printf(
            '<div id="%s">
                <div class="loading"></div>
                <div class="brand">
                    <p>iyzico</p>
                </div>
            </div>',
            esc_attr(self::LOADING_ID)
        );
    }
}
