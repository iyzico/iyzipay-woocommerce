<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

class TlsVerifier {
	private $tlsUrl = 'https://api.iyzipay.com';

	public function verifyAndGetVersion() {
		$tlsVersion = get_option( 'iyziTLS' );

		if ( $tlsVersion != 1.2 ) {
			$result = $this->verifyTLS( $this->tlsUrl );
			if ( $result ) {
				$tlsVersion = 1.2;
				$this->updateTlsVersionOption( $tlsVersion );
			}
		}

		return $tlsVersion;
	}

	private function verifyTLS( $url ) {
		$curl = curl_init();
		curl_setopt_array( $curl, [
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL            => $url,
		] );
		$response = curl_exec( $curl );
		curl_close( $curl );

		return $response;
	}

	private function updateTlsVersionOption( $version ) {
		if ( get_option( 'iyziTLS' ) ) {
			update_option( 'iyziTLS', $version );
		} else {
			add_option( 'iyziTLS', $version, '', false );
		}
	}
}