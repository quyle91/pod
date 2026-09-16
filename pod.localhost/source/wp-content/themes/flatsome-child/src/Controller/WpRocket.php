<?php

namespace FlatsomeChild\Controller;

class WpRocket {
    private static $instance = null;
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct() {
        add_action('init', function () {
            if (!isset($_GET['active_wprocket'])) {
                return;
            }

            if (!current_user_can('administrator')) {
                return;
            }

            // xoá tất cả option hiện tại
            global $wpdb;
            $sql = "DELETE FROM `{$wpdb->options}` WHERE `option_name` LIKE '%_rocket%';";
            $wpdb->query($sql);
            echo '---- DELETED RECORDS -------<br>';

            exit;
        });

        add_filter('transient_wp_rocket_customer_data', function ($value, $transient) {
            return (object) [
                'licence' => (object) [
                    'name' => 'xxx',
                ],
                'licence_account' => 'xxx',
                'licence_expiration' => strtotime('+100 years'),
                'license_class' => 'wpr-isValid',
                'has_one-com_account' => true,
            ];
        }, 10, 2);



        // see: function rocket_valid_key()
        add_filter('http_response', function ($response, $parsed_args, $url) {
            error_log(json_encode($url));
            // Chỉ xử lý khi URL khớp với endpoint của WP Rocket.
            if ($url !== 'https://api.wp-rocket.me/valid_key.php') {
                return $response;
            }

            // Ghi log toàn bộ phản hồi để debug.
            error_log('Response from license validation: ' . json_encode($response));

            // Kiểm tra xem phản hồi có phải là lỗi hoặc nội dung null.
            if (is_wp_error($response)) {
                error_log('License validation failed: WP_Error detected.');
                $response['body'] = json_encode([
                    'success' => false,
                    'data' => [
                        'reason' => 'CUSTOM_ERROR',
                    ],
                ]);
            } elseif (empty($response['body']) || json_decode($response['body']) === null) {
                error_log('License validation failed: Empty or null body.');
                $response['body'] = json_encode([
                    'success' => false,
                    'data' => [
                        'reason' => 'EMPTY_BODY',
                    ],
                ]);
            }

            // Nếu phản hồi thành công nhưng dữ liệu không hợp lệ, sửa lại body cho hợp lệ.
            $body = json_decode($response['body'], true);
            if (isset($body['success']) && !$body['success']) {
                error_log('License validation returned failure: ' . $body['data']['reason']);
                $response['body'] = json_encode([
                    'success' => true,
                    'data' => [
                        'consumer_key' => '12345678',
                        'consumer_email' => 'example@example.com',
                        'secret_key' => hash('crc32', 'example@example.com'),
                    ],
                ]);
            }

            return $response;
        }, 10, 3);
    }
}
