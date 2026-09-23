<?php
/**
 * Standalone self-check, no framework. Run: php tests/test-handle-api-request.php
 *
 * handle_api_request() treats only token-carrying POSTs as real callbacks and
 * redirects every other request (plain GET, tokenless POST) to the same URL
 * without wc-api. See includes/Checkout/CheckoutForm.php
 */

namespace {
    class WC_Payment_Gateway
    {
    }

    $GLOBALS['redirected_to'] = null;

    function wp_safe_redirect($url)
    {
        $GLOBALS['redirected_to'] = $url;
        throw new \RuntimeException('redirect:' . $url); // stands in for exit
    }

    function remove_query_arg($key, $url = null)
    {
        $url = $url ?? $_SERVER['REQUEST_URI'];
        [$path, $query] = array_pad(explode('?', $url, 2), 2, '');
        parse_str($query, $args);
        unset($args[$key]);

        return $args ? $path . '?' . http_build_query($args) : $path;
    }
}

namespace Iyzico\IyzipayWoocommerce\Checkout {

    require_once __DIR__ . '/../includes/Checkout/CheckoutForm.php';

    class FakeProcessor
    {
        public $called = 0;

        public function processCallback(): void
        {
            $this->called++;
            throw new \RuntimeException('processed'); // stands in for the exit that follows
        }
    }

    function run(string $method, array $post): array
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = '/checkout/order-received/42/?key=wc_order_ABC&wc-api=iyzipay';
        $_GET = ['wc-api' => 'iyzipay'];
        $_POST = $post;
        $GLOBALS['redirected_to'] = null;

        $form = (new \ReflectionClass(CheckoutForm::class))->newInstanceWithoutConstructor();
        $form->paymentProcessor = $processor = new FakeProcessor();

        try {
            $form->handle_api_request();
        } catch (\RuntimeException $e) {
            // the exit after wp_safe_redirect/processCallback
        }

        return [$processor->called, $GLOBALS['redirected_to']];
    }

    // Plain browser GET: no callback processing, no error, wc-api dropped.
    [$called, $to] = run('GET', []);
    assert($called === 0, 'GET must not be processed as a callback');
    assert($to === '/checkout/order-received/42/?key=wc_order_ABC', "unexpected url: $to");

    // Tokenless POST (session timeout, prefetch) is redirected the same way.
    [$called, $to] = run('POST', []);
    assert($called === 0, 'tokenless POST must not be processed as a callback');
    assert($to === '/checkout/order-received/42/?key=wc_order_ABC', "unexpected url: $to");

    // Real iyzico callback: processed, no redirect.
    [$called, $to] = run('POST', ['token' => 'abc123']);
    assert($called === 1, 'POST with a token must be processed');
    assert($to === null, 'POST with a token must not be redirected');

    // A wc-api request belonging to another plugin is not ours.
    $_GET = ['wc-api' => 'baska_eklenti'];
    $_POST = [];
    $form = (new \ReflectionClass(CheckoutForm::class))->newInstanceWithoutConstructor();
    $form->paymentProcessor = $p = new FakeProcessor();
    $form->handle_api_request();
    assert($p->called === 0, 'foreign wc-api request must not be processed');

    echo "OK\n";
}
