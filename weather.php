<?php
/*
Plugin Name: TechPlay Simple Weather Widget
Description: A simple weather widget that uses OpenWeatherMap API to display current weather
Version: 1.2
Author: NoxWon
*/

// 관리자 메뉴에 설정 페이지 추가
function simple_weather_widget_menu() {
    add_menu_page(
        'Simple Weather Widget Settings',
        'Weather Widget',
        'manage_options',
        'simple-weather-widget-settings',
        'simple_weather_widget_settings_page',
        'dashicons-admin-generic'
    );
}
add_action('admin_menu', 'simple_weather_widget_menu');

// 설정 페이지의 내용
function simple_weather_widget_settings_page() {
    ?>
    <div class="wrap">
        <h2>Simple Weather Widget Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('simple-weather-widget-settings-group');
            do_settings_sections('simple-weather-widget-settings-group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">OpenWeatherMap API Key</th>
                    <td><input type="text" name="weather_api_key" value="<?php echo esc_attr(get_option('weather_api_key')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Timezone</th>
                    <td><input type="text" name="weather_timezone" value="<?php echo esc_attr(get_option('weather_timezone')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Default City</th>
                    <td><input type="text" name="weather_default_city" value="<?php echo esc_attr(get_option('weather_default_city')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Style Sheet</th>
                    <td>
                        <select name="weather_style_sheet">
                            <option value="1" <?php selected(get_option('weather_style_sheet'), '1'); ?>>Style 1 (Default)</option>
                            <option value="2" <?php selected(get_option('weather_style_sheet'), '2'); ?>>Style 2 (Custom)</option>
                            <option value="3" <?php selected(get_option('weather_style_sheet'), '3'); ?>>Style 3 (Predefine)</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <p>숏코드 사용 방법:</p>
        <ul>
            <li>[simple_weather_widget]: 기본 설정으로 날씨 정보 표시</li>
            <li>[simple_weather_widget show_sunrise="true"]: 일출 정보 포함하여 표시</li>
            <li>[simple_weather_widget show_humidity="true" show_sunrise="true" show_sunset="true" show_wind="true"]: 전체 날씨 정보 표시</li>
        </ul>
    </div>
    <?php
}

// 설정 옵션 등록 및 관리
function simple_weather_widget_register_settings() {
    register_setting('simple-weather-widget-settings-group', 'weather_api_key');
    register_setting('simple-weather-widget-settings-group', 'weather_default_city');
    register_setting('simple-weather-widget-settings-group', 'weather_style_sheet');
    register_setting('simple-weather-widget-settings-group', 'weather_timezone');
}
add_action('admin_init', 'simple_weather_widget_register_settings');

// 스타일 및 스크립트 등록
function simple_weather_widget_enqueue_scripts() {
    //wp_enqueue_style('simple-weather-widget-style', plugins_url('/css/style.css', __FILE__));
    wp_enqueue_script('simple-weather-widget-script', plugins_url('/js/simple-weather-widget.js', __FILE__), array('jquery'), null, true);

    $style_sheet_option = get_option('weather_style_sheet', '1');
    switch ($style_sheet_option) {
        case '2':
            wp_enqueue_style('simple-weather-widget-style-custom', plugins_url('/css/style-custom.css', __FILE__));
            break;
        case '3':https://techplay.blog/
            wp_enqueue_style('simple-weather-widget-style-predefine', plugins_url('/css/style-predefine.css', __FILE__));
            break;
        default:
            wp_enqueue_style('simple-weather-widget-style', plugins_url('/css/style.css', __FILE__));
    }

    wp_localize_script('simple-weather-widget-script', 'weatherWidgetData', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('weather_nonce'),
        'default_city' => get_option('weather_default_city', 'Seoul'),
        'api_key' => get_option('weather_api_key')
    ));
}
add_action('wp_enqueue_scripts', 'simple_weather_widget_enqueue_scripts');

// 날씨 데이터 가져오기 함수
function fetch_weather_data($city, $api_key) {
    // 캐시 키 생성
    $cache_key = 'weather_data_' . sanitize_key($city);
    $cached_data = get_transient($cache_key);

    // 캐시된 데이터가 있으면 반환
    if ($cached_data) {
        return $cached_data;
    }

    $api_url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city) . "&appid=" . $api_key . "&units=metric";
    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        return false; // Handle error
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // 데이터 캐싱 (예: 30분)
    set_transient($cache_key, $data, 30 * MINUTE_IN_SECONDS);

    return $data;
}

// Function to get weather icon
function get_weather_icon($weather_code) {
    $icon_map = array(
        '01d' => 'clear-day',
        '01n' => 'clear-night',
        '02d' => 'few-clouds',
        '02n' => 'few-clouds-night',
        '03d' => 'scattered-clouds',
        '03n' => 'scattered-clouds-night',
        '04d' => 'broken-clouds',
        '04n' => 'broken-clouds-night',
        '09d' => 'shower-rain',
        '09n' => 'shower-rain-night',
        '10d' => 'rain',
        '10n' => 'rain-night',
        '11d' => 'thunderstorm',
        '11n' => 'thunderstorm',
        '13d' => 'snow',
        '13n' => 'snow-night',
        '50d' => 'mist',
        '50n' => 'mist-night',
    );

    return isset($icon_map[$weather_code]) ? $icon_map[$weather_code] : 'default-icon';
}

// 보퍼트 풍력계급표 기준
function get_wind_icon($wind_speed) {
    if ($wind_speed < 0.5) {
        return 'wind-beaufort-0';
    } elseif ($wind_speed < 1.6) {
        return 'wind-beaufort-1';
    } elseif ($wind_speed < 3.4) {
        return 'wind-beaufort-2';
    } elseif ($wind_speed < 5.5) {
        return 'wind-beaufort-3';
    } elseif ($wind_speed < 8.0) {
        return 'wind-beaufort-4';
    } elseif ($wind_speed < 10.8) {
        return 'wind-beaufort-5';
    } elseif ($wind_speed < 13.9) {
        return 'wind-beaufort-6';
    } elseif ($wind_speed < 17.2) {
        return 'wind-beaufort-7';
    } elseif ($wind_speed < 20.8) {
        return 'wind-beaufort-8';
    } elseif ($wind_speed < 24.5) {
        return 'wind-beaufort-9';
    } elseif ($wind_speed < 28.5) {
        return 'wind-beaufort-10';
    } elseif ($wind_speed < 32.7) {
        return 'wind-beaufort-11';
    } else {
        return 'wind-beaufort-12';
    }
}

// AJAX 요청 처리 함수
function simple_weather_widget_ajax() {
    check_ajax_referer('weather_nonce', 'nonce');

    $city = sanitize_text_field($_POST['city']);
    $api_key = get_option('weather_api_key');
    $weather_data = fetch_weather_data($city, $api_key);

    if (!$weather_data) {
        wp_send_json_error('Unable to fetch weather data');
    }

    wp_send_json_success($weather_data);
}
add_action('wp_ajax_nopriv_fetch_weather', 'simple_weather_widget_ajax');
add_action('wp_ajax_fetch_weather', 'simple_weather_widget_ajax');

// 숏코드 함수 (날씨 위젯 표시)
function simple_weather_widget_shortcode($atts) {
    // 사용자 정의 속성 설정
    $atts = shortcode_atts(array(
        'city' => get_option('weather_default_city', 'Seoul'),
        'show_temperature' => 'true',
        'show_humidity' => 'false',
        'show_sunrise' => 'false',
        'show_sunset' => 'false',
        'show_wind_speed' => 'false',     
        'show_wind_direction' => 'false', 
        'timezone' => 'UTC', // 기본값으로 'UTC' 설정
        //'show_wind' => 'false',
    ), $atts);

    // API 키를 관리자 설정에서 가져옴
    $api_key = get_option('weather_api_key');

    // API 키가 없는 경우 안내 메시지 출력
    if (empty($api_key)) {
        echo "<p>OpenWeatherMap API 키가 설정되지 않았습니다. <a href='https://openweathermap.org/home/sign_up' target='_blank'>여기</a>에서 가입하고 API 키를 설정해주세요.</p>";
        return;
    }

    // 날씨 데이터 가져오기
    $weather_data = fetch_weather_data($atts['city'], $api_key);

    ob_start();

    if ($weather_data && isset($weather_data['weather'][0])) {
        $weather_icon = get_weather_icon($weather_data['weather'][0]['icon']);
        $icon_url = plugins_url('/icons/' . $weather_icon . '.svg', __FILE__);

        echo '<div id="weather-widget">';
        echo '<div class="techplay-weather-icon weather"><img src="' . esc_url($icon_url) . '" alt="Weather Icon"></div>';
        echo '<div class="techplay-weather-city"><strong>' . esc_html($weather_data['name']) . '</strong></div>';

        // 온도 표시
        if ('true' === $atts['show_temperature']) {
            $temperature = round($weather_data['main']['temp'], 1);
            echo '<div class="techplay-weather-icon temp"><img src="' . plugins_url('/icons/thermometer.svg', __FILE__) . '" alt="Temperature Icon"> ' . esc_html($temperature) . '°C</div>';
        }        

        // 습도 표시    
        if ('true' === $atts['show_humidity']) {
            echo '<div class="techplay-weather-icon humidity"><img src="' . plugins_url('/icons/humidity.svg', __FILE__) . '" alt="Humidity Icon"> ' . esc_html($weather_data['main']['humidity']) . '%</div>';
        }

        // 타임존 설정
        $timezone = new DateTimeZone($atts['timezone']);

        // 일출 시간 계산
        if ('true' === $atts['show_sunrise']) {
            $sunrise = new DateTime('@' . $weather_data['sys']['sunrise']);
            $sunrise->setTimezone($timezone);
            echo '<div class="techplay-weather-icon sunrise"><img src="' . plugins_url('/icons/sunrise.svg', __FILE__) . '" alt="Sunrise Icon"> ' . esc_html($sunrise->format('H:i')) . '</div>';
        }

        // 일몰 시간 계산
        if ('true' === $atts['show_sunset']) {
            $sunset = new DateTime('@' . $weather_data['sys']['sunset']);
            $sunset->setTimezone($timezone);
            echo '<div class="techplay-weather-icon sunset"><img src="' . plugins_url('/icons/sunset.svg', __FILE__) . '" alt="Sunset Icon"> ' . esc_html($sunset->format('H:i')) . '</div>';
        }

        // 풍속 표시
        if ('true' === $atts['show_wind_speed']) {
            $wind_speed = $weather_data['wind']['speed'];
            $wind_icon = get_wind_icon($wind_speed);
            $wind_icon_url = plugins_url('/icons/' . $wind_icon . '.svg', __FILE__);

            echo '<div class="techplay-weather-icon wind"><img src="' . esc_url($wind_icon_url) . '" alt="Wind Icon"> ' . esc_html($wind_speed) . ' m/s</div>';
        }

        // 풍향 표시
        if ('true' === $atts['show_wind_direction']) {
            echo '<div class="techplay-weather-icon wind-direction"><img src="' . plugins_url('/icons/windsock.svg', __FILE__) . '" alt="Wind Direction Icon"> ' . esc_html($weather_data['wind']['deg']) . ' degrees</div>';
        }

        echo '</div>';
    } else {
        echo '<div id="weather-widget"><p>Weather information is currently unavailable.</p></div>';
    }

    return ob_get_clean();
}

add_shortcode('simple_weather_widget', 'simple_weather_widget_shortcode');
?>

