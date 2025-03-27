<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

class TlsVerifier
{
    private $tlsUrl = 'https://api.iyzipay.com';

    public function verifyAndGetVersion()
    {
        $tlsVersion = get_option('iyziTLS');

        if ($tlsVersion != 1.2) {
            $result = $this->verifyTLS($this->tlsUrl);
            if ($result) {
                $tlsVersion = 1.2;
                $this->updateTlsVersionOption($tlsVersion);
            }
        }

        return $tlsVersion;
    }

    private function verifyTLS($url)
    {
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'sslverify' => true
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        return wp_remote_retrieve_body($response);
    }

    private function updateTlsVersionOption($version)
    {
        if (get_option('iyziTLS')) {
            update_option('iyziTLS', $version);
        } else {
            add_option('iyziTLS', $version, '', false);
        }
    }
}