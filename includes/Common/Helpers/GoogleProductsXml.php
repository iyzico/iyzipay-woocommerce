<?php

namespace Iyzico\IyzipayWoocommerce\Common\Helpers;

use Iyzico\IyzipayWoocommerce\Checkout\CheckoutSettings;

class GoogleProductsXml
{
    private $xmlContent;
    private $siteUrl;
    private $products;
    private $remotePostUrl;
    private $logger;
    protected $checkoutSettings;

    public function __construct()
    {
        // PHP 8.1+ compatibility: ensure get_site_url() returns string
        $site_url = get_site_url();
        $this->siteUrl = $site_url !== null && $site_url !== false ? (string) $site_url : '';
        $this->remotePostUrl = 'https://xml.iyzitest.com/save';
        $this->logger = new \Iyzico\IyzipayWoocommerce\Common\Helpers\Logger();
        $this->checkoutSettings = new CheckoutSettings();
        
        // API ortamını kontrol et
        $this->checkApiEnvironment();
    }

    /**
     * Check API environment and disable remote sending for sandbox
     */
    private function checkApiEnvironment()
    {
        $api_type = $this->checkoutSettings->findByKey('api_type');
        
        if ($api_type === 'https://sandbox-api.iyzipay.com') {
            $this->remotePostUrl = '';
        }
    }

    /**
     * Generate Google Products XML from WooCommerce products
     *
     * @return string XML content
     */
    public function generateXml()
    {   
        $this->products = $this->getWooCommerceProducts();
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
        $xml .= '    <channel>' . "\n";
        
        // PHP 8.1+ compatibility: ensure get_bloginfo returns string
        $blog_name = get_bloginfo('name');
        $blog_name = $blog_name !== false && $blog_name !== null ? (string) $blog_name : '';
        $xml .= '        <title>' . esc_html($blog_name) . ' - Google Products</title>' . "\n";
        
        // PHP 8.1+ compatibility: ensure siteUrl is string
        $site_url = $this->siteUrl !== null ? (string) $this->siteUrl : '';
        $xml .= '        <link>' . esc_url($site_url) . '</link>' . "\n";
        $xml .= '        <description>Google Products XML Feed for ' . esc_html($blog_name) . '</description>' . "\n";
        $xml .= '        <lastBuildDate>' . date('r') . '</lastBuildDate>' . "\n";
        
        foreach ($this->products as $product) {
            $xml .= $this->generateProductXml($product);
        }
        
        $xml .= '    </channel>' . "\n";
        $xml .= '</rss>';
        
        $this->xmlContent = $xml;
        
        $this->saveXmlFile();
        
        update_option('iyzico_google_products_xml_last_update', current_time('timestamp'));
        
        $next_send_time = get_option('iyzico_google_products_next_send_time', 0);
        
        if (!empty($this->remotePostUrl) && ($next_send_time == 0 || time() >= (int)$next_send_time)) {
            $this->sendToRemoteServer();
        }
        
        return $xml;
    }

    /**
     * Generate XML for a single product
     *
     * @param WC_Product $product
     * @return string
     */
    private function generateProductXml($product)
    {
        if (!$product || !is_object($product)) {
            return '';
        }
        
        $xml = '        <item>' . "\n";
        
        // PHP 8.1+ compatibility: ensure get_id() returns string
        $product_id = $product->get_id();
        $product_id = $product_id !== null ? (string) $product_id : '';
        $xml .= '            <g:id>' . esc_html($product_id) . '</g:id>' . "\n";
        
        // PHP 8.1+ compatibility: ensure get_name() returns string
        $product_name = $product->get_name();
        $product_name = $product_name !== null ? (string) $product_name : '';
        $xml .= '            <g:title>' . $this->cleanXmlContent($product_name) . '</g:title>' . "\n";
        
        // PHP 8.1+ compatibility: ensure get_description() returns string before wp_strip_all_tags
        $product_description = $product->get_description();
        $product_description = $product_description !== null ? (string) $product_description : '';
        $product_description = !empty($product_description) ? wp_strip_all_tags($product_description) : '';
        $xml .= '            <g:description>' . $this->cleanXmlContent($product_description) . '</g:description>' . "\n";
        
        // PHP 8.1+ compatibility: ensure get_permalink() returns string
        $product_permalink = $product->get_permalink();
        $product_permalink = $product_permalink !== null && $product_permalink !== false ? (string) $product_permalink : '';
        $xml .= '            <g:link>' . esc_url($product_permalink) . '</g:link>' . "\n";
        
        // Image link
        $image_id = $product->get_image_id();
        if ($image_id) {
            $image_url = wp_get_attachment_url($image_id);
            // PHP 8.1+ compatibility: ensure image_url is not null
            if ($image_url !== null && $image_url !== false) {
                $xml .= '            <g:image_link>' . esc_url($image_url) . '</g:image_link>' . "\n";
            }
        }
        
        // Price
        $price = $product->get_price();
        if ($price !== null && $price !== false && $price !== '') {
            $currency = get_woocommerce_currency();
            // PHP 8.1+ compatibility: ensure currency is string
            $currency = $currency !== null && $currency !== false ? (string) $currency : '';
            $price_str = $price !== null ? (string) $price : '';
            $xml .= '            <g:price>' . esc_html($price_str . ' ' . $currency) . '</g:price>' . "\n";
        }
        
        // Sale price
        if ($product->is_on_sale()) {
            $sale_price = $product->get_sale_price();
            if ($sale_price !== null && $sale_price !== false && $sale_price !== '') {
                $currency = get_woocommerce_currency();
                // PHP 8.1+ compatibility: ensure currency is string
                $currency = $currency !== null && $currency !== false ? (string) $currency : '';
                $sale_price_str = $sale_price !== null ? (string) $sale_price : '';
                $xml .= '            <g:sale_price>' . esc_html($sale_price_str . ' ' . $currency) . '</g:sale_price>' . "\n";
            }
        }
        
        // Availability
        $availability = $product->is_in_stock() ? 'in_stock' : 'out_of_stock';
        // PHP 8.1+ compatibility: ensure availability is string
        $availability = (string) $availability;
        $xml .= '            <g:availability>' . esc_html($availability) . '</g:availability>' . "\n";
        
        // Condition
        $xml .= '            <g:condition>new</g:condition>' . "\n";
        
        // Brand
        $brand = $this->getProductBrand($product);
        if ($brand) {
            $xml .= '            <g:brand>' . $this->cleanXmlContent($brand) . '</g:brand>' . "\n";
        }
        
        // GTIN
        $gtin = $this->getProductGtin($product);
        if ($gtin) {
            $xml .= '            <g:gtin>' . $this->cleanXmlContent($gtin) . '</g:gtin>' . "\n";
        }
        
        // MPN
        $mpn = $this->getProductMpn($product);
        if ($mpn) {
            $xml .= '            <g:mpn>' . $this->cleanXmlContent($mpn) . '</g:mpn>' . "\n";
        }
        
        // Google Product Category
        $google_category = $this->getProductCategory($product);
        if ($google_category) {
            $xml .= '            <g:google_product_category>' . $this->cleanXmlContent($google_category) . '</g:google_product_category>' . "\n";
        }
        
        // Additional image links
        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $gallery_id) {
            $gallery_url = wp_get_attachment_url($gallery_id);
            // PHP 8.1+ compatibility: ensure gallery_url is not null
            if ($gallery_url !== null && $gallery_url !== false) {
                $xml .= '            <g:additional_image_link>' . esc_url($gallery_url) . '</g:additional_image_link>' . "\n";
            }
        }
        
        $xml .= '        </item>' . "\n";
        
        return $xml;
    }

    /**
     * Get WooCommerce products
     *
     * @return array
     */
    private function getWooCommerceProducts()
    {
        $products = array();
        
        // First try with WP_Query to get all published products
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        );
        
        $query = new \WP_Query($args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                
                if ($product && $product->is_visible()) {
                    $products[] = $product;
                }
            }
        }
        
        wp_reset_postdata();
        
        // If no products found with WP_Query, try wc_get_products as fallback
        if (empty($products)) {
            
            $wc_args = array(
                'status' => 'publish',
                'limit' => -1,
                'type' => array('simple', 'variable', 'grouped', 'external')
            );
            
            $wc_products = wc_get_products($wc_args);
            
            foreach ($wc_products as $product) {
                if ($product && $product->is_visible()) {
                    $products[] = $product;
                }
            }
        }
        
        return (array) $products;
    }

    /**
     * Get product brand
     *
     * @param WC_Product $product
     * @return string|null
     */
    private function getProductBrand($product)
    {
        try {
            // Try to get brand from product attributes
            $attributes = $product->get_attributes();
            
            if (isset($attributes['brand']) && is_object($attributes['brand'])) {
                $options = $attributes['brand']->get_options();
                if (!empty($options) && is_array($options)) {
                    return is_string($options[0]) ? $options[0] : null;
                }
            }
            
            // Try to get brand from custom field
            $brand = get_post_meta($product->get_id(), '_brand', true);
            if ($brand) {
                return is_string($brand) ? $brand : null;
            }
            
            // Try to get brand from taxonomy
            $brand_terms = get_the_terms($product->get_id(), 'product_brand');
            if ($brand_terms && !is_wp_error($brand_terms) && is_array($brand_terms)) {
                return is_string($brand_terms[0]->name) ? $brand_terms[0]->name : null;
            }
        } catch (Exception $e) {
            $this->logger->error('Iyzico Google XML: Error getting brand for product ' . $product->get_id() . ': ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Get product GTIN
     *
     * @param WC_Product $product
     * @return string|null
     */
    private function getProductGtin($product)
    {
        try {
            // Try to get GTIN from SKU if it's a valid GTIN
            $sku = $product->get_sku();
            if ($sku && $this->isValidGtin($sku)) {
                return is_string($sku) ? $sku : null;
            }
            
            // Try to get GTIN from custom field
            $gtin = get_post_meta($product->get_id(), '_gtin', true);
            if ($gtin) {
                return is_string($gtin) ? $gtin : null;
            }
        } catch (Exception $e) {
            $this->logger->error('Iyzico Google XML: Error getting GTIN for product ' . $product->get_id() . ': ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Get product MPN
     *
     * @param WC_Product $product
     * @return string|null
     */
    private function getProductMpn($product)
    {
        try {
            // Try to get MPN from custom field
            $mpn = get_post_meta($product->get_id(), '_mpn', true);
            if ($mpn) {
                return is_string($mpn) ? $mpn : null;
            }
            
            // Use SKU as MPN if no dedicated MPN field
            $sku = $product->get_sku();
            if ($sku) {
                return is_string($sku) ? $sku : null;
            }
        } catch (Exception $e) {
            $this->logger->error('Iyzico Google XML: Error getting MPN for product ' . $product->get_id() . ': ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Get Google Product Category
     *
     * @param WC_Product $product
     * @return string|null
     */
    private function getProductCategory($product)
    {
        try {
            // Try to get Google category from custom field
            $google_category = get_post_meta($product->get_id(), '_google_product_category', true);
            if ($google_category) {
                return is_string($google_category) ? $google_category : null;
            }
            
            // Map WooCommerce categories to Google categories (basic mapping)
            $categories = get_the_terms($product->get_id(), 'product_cat');
            if ($categories && !is_wp_error($categories) && is_array($categories)) {
                // Basic category mapping - you can expand this
                $category_map = array(
                    'electronics' => 'Electronics',
                    'clothing' => 'Apparel & Accessories',
                    'books' => 'Media > Books',
                    'toys' => 'Toys & Games',
                    'home' => 'Home & Garden',
                    'sports' => 'Sporting Goods'
                );
                
                foreach ($categories as $category) {
                    $slug = $category->slug;
                    if (isset($category_map[$slug])) {
                        return is_string($category_map[$slug]) ? $category_map[$slug] : null;
                    }
                }
            }
        } catch (Exception $e) {
            $this->logger->error('Iyzico Google XML: Error getting Google category for product ' . $product->get_id() . ': ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Clean content for XML compatibility
     *
     * @param string $content
     * @return string
     */
    private function cleanXmlContent($content)
    {
        // PHP 7.4 & 8+ compatibility: ensure $content is a string
        if ($content === null || $content === false) {
            return '';
        }
        
        // Convert to string if not already
        $content = (string) $content;
        
        // Empty string check
        if (empty($content)) {
            return '';
        }
        
        // HTML entities'leri decode et
        $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = $decoded !== false ? $decoded : $content;
        
        // HTML taglarını temizle - ensure content is string before wp_strip_all_tags
        $content = wp_strip_all_tags($content);
        
        // PHP 8.1+ compatibility: ensure $content is still a string after processing
        $content = $content !== null && $content !== false ? (string) $content : '';
        
        // XML'de sorun çıkarabilecek karakterleri escape et
        $content = str_replace(
            ['&', '<', '>', '"', "'"],
            ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'],
            $content
        );
        
        // Non-breaking space ve diğer özel karakterleri temizle
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
        $content = str_replace(['&nbsp;', '&NBSP;'], ' ', $content);
        
        // Çoklu boşlukları tek boşluğa çevir
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Başındaki ve sonundaki boşlukları temizle
        $content = trim($content);
        
        return $content;
    }

    /**
     * Check if a string is a valid GTIN
     *
     * @param string $gtin
     * @return bool
     */
    private function isValidGtin($gtin)
    {
        // Basic GTIN validation (8, 12, 13, 14 digits)
        return preg_match('/^\d{8}$|^\d{12}$|^\d{13}$|^\d{14}$/', $gtin);
    }

    /**
     * Save XML content to file
     */
    private function saveXmlFile()
    {
        $upload_dir = wp_upload_dir();
        $xml_dir = $upload_dir['basedir'] . '/iyzico-google-products/';
        
        // Create directory if it doesn't exist
        if (!file_exists($xml_dir)) {
            wp_mkdir_p($xml_dir);
        }
        
        $xml_file = $xml_dir . 'google-products.xml';
        $result = file_put_contents($xml_file, $this->xmlContent);
        if ($result === false) {
            $this->logger->error('Iyzico Google XML: Failed to write XML file to ' . $xml_file);
        }
        
        // Update option with file URL
        $xml_url = $upload_dir['baseurl'] . '/iyzico-google-products/google-products.xml';
        update_option('iyzico_google_products_xml_url', $xml_url);
    }

    /**
     * Send XML URL to remote server
     */
    private function sendToRemoteServer()
    {
        if (empty($this->remotePostUrl)) {
            $this->logger->error('Iyzico Google XML: Remote post URL is empty, cannot send');
            return;
        }
        
        $xml_url = get_option('iyzico_google_products_xml_url', '');
        if (empty($xml_url)) {
            $this->logger->error('Iyzico Google XML: XML URL is empty, cannot send');
            return;
        }
    
        $retry_data = get_option('iyzico_google_products_retry_data', array());
        $retry_count = isset($retry_data['count']) ? (int)$retry_data['count'] : 0;
        $last_try_time = isset($retry_data['last_try']) ? (int)$retry_data['last_try'] : 0;
        $max_retries = 3;
        $retry_interval = 15 * 60; // 15 dakika

        $last_sent = get_option('iyzico_google_products_last_sent', null);
        $is_first_setup = empty($last_sent);
        
        if (!$is_first_setup && $retry_count >= $max_retries && (time() - $last_try_time) < $retry_interval) {
            $this->logger->error('Iyzico Google XML: Max retry reached, will not try again until next XML generation.');
            return;
        }

        if (!$is_first_setup && $retry_count > 0 && (time() - $last_try_time) < $retry_interval) {
            return;
        }

        $previous_update_timestamp = get_option('iyzico_google_products_last_sent', null);
        $previous_update = null;
        if (!empty($previous_update_timestamp) && is_numeric($previous_update_timestamp)) {
            $previous_update = (int)$previous_update_timestamp;
        }

        $last_update = time();
        $xml_last_update_timestamp = get_option('iyzico_google_products_xml_last_update', null);
        $xml_last_update = null;
        if (!empty($xml_last_update_timestamp) && is_numeric($xml_last_update_timestamp)) {
            $xml_last_update = (int)$xml_last_update_timestamp;
        }

        $data = array(
            'site_url' => !empty($this->siteUrl) ? $this->siteUrl : '',
            'xml_url' => $xml_url,
            'last_update' => $last_update,
            'previous_update' => $previous_update,
            'xml_last_update' => $xml_last_update
        );
        
        $args = array(
            'body' => json_encode($data),
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json'
            )
        );
        
        $response = wp_remote_post($this->remotePostUrl, $args);
        
        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($code !== 200) {
                $retry_count++;
                update_option('iyzico_google_products_retry_data', array(
                    'count' => $retry_count,
                    'last_try' => time()
                ));
            } else {
                update_option('iyzico_google_products_last_sent', current_time('timestamp'));
                delete_option('iyzico_google_products_retry_data');
                $days = 2 + rand(0, 5);
                $next_send_time = time() + ($days * 24 * 60 * 60);
                update_option('iyzico_google_products_next_send_time', $next_send_time);
            }
        } else {
            $this->logger->error('Iyzico Google XML: wp_remote_post error: ' . $response->get_error_message());
            $retry_count++;
            update_option('iyzico_google_products_retry_data', array(
                'count' => $retry_count,
                'last_try' => time()
            ));
        }
    }

    /**
     * Get XML file URL
     *
     * @return string
     */
    public function getXmlUrl()
    {
        return get_option('iyzico_google_products_xml_url', '');
    }

    /**
     * Schedule XML generation
     */
    public function scheduleXmlGeneration()
    {
        if (!wp_next_scheduled('iyzico_generate_google_products_xml')) {
            wp_schedule_event(time(), 'daily', 'iyzico_generate_google_products_xml');
        }
    }

    /**
     * Unschedule XML generation
     */
    public function unscheduleXmlGeneration()
    {
        wp_clear_scheduled_hook('iyzico_generate_google_products_xml');
    }
} 