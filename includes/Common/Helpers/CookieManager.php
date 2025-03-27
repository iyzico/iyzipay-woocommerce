<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

class CookieManager
{
    public function setWooCommerceSessionCookie()
    {
        $wooCommerceCookieKey = $this->findWooCommerceCookieKey();

        if ($wooCommerceCookieKey && isset($_COOKIE[$wooCommerceCookieKey])) {
            $cookieValue = sanitize_text_field(wp_unslash($_COOKIE[$wooCommerceCookieKey]));

            $serverName = 'example.com'; // Varsayılan değer
            if (!empty($_SERVER['SERVER_NAME'])) {
                $serverName = sanitize_text_field(wp_unslash($_SERVER['SERVER_NAME']));
            }

            $this->setCookieSameSite(
                $wooCommerceCookieKey,
                $cookieValue,
                time() + 86400,
                "/",
                $serverName,
                true,
                true
            );
        }
    }

    private function findWooCommerceCookieKey()
    {
        $prefix = 'wp_woocommerce_session_';
        foreach ($_COOKIE as $name => $value) {
            if (stripos($name, $prefix) === 0) {
                return $name;
            }
        }

        return null;
    }

    private function setCookieSameSite($name, $value, $expire, $path, $domain, $secure, $httpOnly)
    {
        $options = [
            'expires' => $expire,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => 'None'
        ];

        if (PHP_VERSION_ID < 70300) {
            setcookie($name, $value, $expire, "$path; samesite=None", $domain, $secure, $httpOnly);
        } else {
            setcookie($name, $value, $options);
        }
    }
}