<?php
session_start();
require_once '../config/supabase.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$hour = intval(date('H'));
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

function fetchAPI($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code !== 200) return null;
    return json_decode($response, true);
}

$api_key = 'f3612b349931cf0327ecaa01b1437356';
$city    = 'Sheffield';
$lat     = 53.3811;
$lon     = -1.4701;

$weather_data  = null;
$hourly_data   = [];
$daily_data    = [];
$alerts_data   = [];
$overview_text = '';
$api_version   = '';

$onecall_url  = "https://api.openweathermap.org/data/3.0/onecall?lat={$lat}&lon={$lon}&units=metric&lang=en&appid={$api_key}";
$onecall_data = fetchAPI($onecall_url);

if ($onecall_data && isset($onecall_data['current'])) {
    $api_version  = '3.0';
    $weather_data = $onecall_data['current'];
    $hourly_data  = array_slice($onecall_data['hourly'] ?? [], 0, 12);
    $daily_data   = array_slice($onecall_data['daily']  ?? [], 0, 5);
    $alerts_data  = $onecall_data['alerts'] ?? [];

    $overview_url  = "https://api.openweathermap.org/data/3.0/onecall/overview?lat={$lat}&lon={$lon}&units=metric&appid={$api_key}";
    $overview_data = fetchAPI($overview_url);
    if ($overview_data && isset($overview_data['weather_overview'])) {
        $overview_text = $overview_data['weather_overview'];
    }
} else {
    $weather_url  = "https://api.openweathermap.org/data/2.5/weather?q={$city},GB&appid={$api_key}&units=metric";
    $weather_data = fetchAPI($weather_url);

    if ($weather_data && isset($weather_data['cod']) && $weather_data['cod'] != 200) {
        $weather_data = null;
    }

    $forecast_url  = "https://api.openweathermap.org/data/2.5/forecast?q={$city},GB&appid={$api_key}&units=metric&cnt=12";
    $forecast_data = fetchAPI($forecast_url);
    if ($forecast_data && isset($forecast_data['list'])) {
        $hourly_data = $forecast_data['list'];
    }

    $api_version = '2.5';
}

$news_url   = "https://www.gov.uk/api/search.json?filter_organisations=uk-health-security-agency&count=6&order=-public_timestamp&fields=title,description,public_timestamp,link";
$news_json  = fetchAPI($news_url);
$news_items = [];
if ($news_json && isset($news_json['results'])) {
    $news_items = $news_json['results'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link rel="stylesheet" href="../assets/css/user_dashboard.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <title>Dashboard — UKHSA Data Governance Portal</title>
</head>

<body>
    <?php include("navbar.php"); ?>

    <main class="dashboard-main">
        <div class="dashboard-container">

            <div class="welcome-card">
                <h1><?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h1>
                <p>Welcome to the <strong>UKHSA Data Governance Portal</strong>. Use the navigation to browse datasets, manage your access requests, and track permissions.</p>
                <div class="welcome-details">
                    <span class="detail-item"><strong>Role:</strong> <?php echo htmlspecialchars($_SESSION['role']); ?></span>
                    <span class="detail-item"><strong>Team:</strong> <?php echo htmlspecialchars($_SESSION['team']     ?? 'N/A'); ?></span>
                    <span class="detail-item"><strong>Job Type:</strong> <?php echo htmlspecialchars($_SESSION['job_type'] ?? 'N/A'); ?></span>
                </div>
            </div>

            <?php if (!empty($alerts_data)): ?>
                <div class="alerts-section">
                    <?php foreach ($alerts_data as $alert): ?>
                        <div class="alert-banner alert-weather">
                            <span class="material-icons">warning_amber</span>
                            <div class="alert-content">
                                <strong><?php echo htmlspecialchars($alert['event']); ?></strong>
                                <p><?php echo htmlspecialchars(substr($alert['description'], 0, 200)); ?>...</p>
                                <span class="alert-meta">
                                    Source: <?php echo htmlspecialchars($alert['sender_name'] ?? 'Met Office'); ?> |
                                    Until: <?php echo date('d M H:i', $alert['end']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($overview_text)): ?>
                <div class="overview-card">
                    <div class="overview-header">
                        <span class="material-icons">smart_toy</span>
                        <h2>AI Weather Summary</h2>
                        <span class="overview-badge">OpenWeather AI</span>
                    </div>
                    <p class="overview-text"><?php echo htmlspecialchars($overview_text); ?></p>
                </div>
            <?php endif; ?>

            <div class="widgets-grid">

                <div class="widget-card weather-card">
                    <div class="widget-header">
                        <span class="material-icons">wb_sunny</span>
                        <h2>Weather — <?php echo htmlspecialchars($city); ?></h2>
                        <span class="api-badge">API <?php echo $api_version; ?></span>
                    </div>

                    <?php
                    $temp       = null;
                    $feels_like = null;
                    $humidity   = null;
                    $wind_speed = null;
                    $visibility = null;
                    $icon       = null;
                    $desc       = null;
                    $pressure   = null;
                    $uvi        = null;
                    $sunrise    = null;
                    $sunset     = null;

                    if ($api_version === '3.0' && $weather_data) {
                        $temp       = round($weather_data['temp']);
                        $feels_like = round($weather_data['feels_like']);
                        $humidity   = $weather_data['humidity'];
                        $wind_speed = round($weather_data['wind_speed'] * 2.237);
                        $visibility = round(($weather_data['visibility'] ?? 10000) / 1000, 1);
                        $icon       = $weather_data['weather'][0]['icon'];
                        $desc       = ucfirst($weather_data['weather'][0]['description']);
                        $pressure   = $weather_data['pressure'];
                        $uvi        = $weather_data['uvi'] ?? null;
                        $sunrise    = isset($weather_data['sunrise']) ? date('H:i', $weather_data['sunrise']) : null;
                        $sunset     = isset($weather_data['sunset']) ? date('H:i', $weather_data['sunset']) : null;
                    } elseif ($api_version === '2.5' && $weather_data && isset($weather_data['main'])) {
                        $temp       = round($weather_data['main']['temp']);
                        $feels_like = round($weather_data['main']['feels_like']);
                        $humidity   = $weather_data['main']['humidity'];
                        $wind_speed = round($weather_data['wind']['speed'] * 2.237);
                        $visibility = round(($weather_data['visibility'] ?? 10000) / 1000, 1);
                        $icon       = $weather_data['weather'][0]['icon'];
                        $desc       = ucfirst($weather_data['weather'][0]['description']);
                        $pressure   = $weather_data['main']['pressure'];
                        $sunrise    = isset($weather_data['sys']['sunrise']) ? date('H:i', $weather_data['sys']['sunrise']) : null;
                        $sunset     = isset($weather_data['sys']['sunset'])  ? date('H:i', $weather_data['sys']['sunset'])  : null;
                    }
                    ?>

                    <?php if ($temp !== null): ?>
                        <div class="weather-content">
                            <div class="weather-main">
                                <img src="https://openweathermap.org/img/wn/<?php echo $icon; ?>@2x.png"
                                    alt="<?php echo htmlspecialchars($desc); ?>"
                                    class="weather-icon">
                                <div class="weather-temp">
                                    <span class="temp-value"><?php echo $temp; ?>°C</span>
                                    <span class="temp-desc"><?php echo $desc; ?></span>
                                </div>
                            </div>

                            <div class="weather-grid">
                                <div class="weather-detail">
                                    <span class="material-icons">thermostat</span>
                                    <div>
                                        <span class="detail-label">Feels Like</span>
                                        <span class="detail-value"><?php echo $feels_like; ?>°C</span>
                                    </div>
                                </div>
                                <div class="weather-detail">
                                    <span class="material-icons">water_drop</span>
                                    <div>
                                        <span class="detail-label">Humidity</span>
                                        <span class="detail-value"><?php echo $humidity; ?>%</span>
                                    </div>
                                </div>
                                <div class="weather-detail">
                                    <span class="material-icons">air</span>
                                    <div>
                                        <span class="detail-label">Wind</span>
                                        <span class="detail-value"><?php echo $wind_speed; ?> mph</span>
                                    </div>
                                </div>
                                <div class="weather-detail">
                                    <span class="material-icons">compress</span>
                                    <div>
                                        <span class="detail-label">Pressure</span>
                                        <span class="detail-value"><?php echo $pressure; ?> hPa</span>
                                    </div>
                                </div>
                                <div class="weather-detail">
                                    <span class="material-icons">visibility</span>
                                    <div>
                                        <span class="detail-label">Visibility</span>
                                        <span class="detail-value"><?php echo $visibility; ?> km</span>
                                    </div>
                                </div>
                                <?php if ($uvi !== null): ?>
                                    <div class="weather-detail">
                                        <span class="material-icons">wb_sunny</span>
                                        <div>
                                            <span class="detail-label">UV Index</span>
                                            <span class="detail-value"><?php echo round($uvi, 1); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($sunrise && $sunset): ?>
                                <div class="sun-times">
                                    <div class="sun-item">
                                        <span class="material-icons">wb_twilight</span>
                                        <span>Sunrise <?php echo $sunrise; ?></span>
                                    </div>
                                    <div class="sun-item">
                                        <span class="material-icons">nights_stay</span>
                                        <span>Sunset <?php echo $sunset; ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="weather-footer">
                                <span>Updated: <?php echo date('H:i'); ?></span>
                                <span>Source: OpenWeather</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="widget-empty">
                            <span class="material-icons">cloud_off</span>
                            <p>Weather data unavailable.</p>
                            <p class="widget-hint">Check your API key or internet connection</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="widget-card news-card">
                    <div class="widget-header">
                        <span class="material-icons">newspaper</span>
                        <h2>UKHSA Latest News</h2>
                    </div>

                    <?php if (!empty($news_items)): ?>
                        <div class="news-list">
                            <?php foreach ($news_items as $news): ?>
                                <a href="https://www.gov.uk<?php echo htmlspecialchars($news['link']); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="news-item">
                                    <div class="news-title">
                                        <?php echo htmlspecialchars($news['title']); ?>
                                    </div>
                                    <div class="news-desc">
                                        <?php echo htmlspecialchars($news['description'] ?? ''); ?>
                                    </div>
                                    <div class="news-date">
                                        <span class="material-icons">schedule</span>
                                        <?php
                                        if (isset($news['public_timestamp'])) {
                                            echo date('d M Y', strtotime($news['public_timestamp']));
                                        }
                                        ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="news-footer">
                            <a href="https://www.gov.uk/government/organisations/uk-health-security-agency"
                                target="_blank"
                                rel="noopener noreferrer">
                                View all UKHSA news →
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="widget-empty">
                            <span class="material-icons">rss_feed</span>
                            <p>Unable to load UKHSA news feed.</p>
                            <p class="widget-hint">Check your internet connection</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <?php if (!empty($hourly_data)): ?>
                <div class="forecast-section">
                    <div class="section-header">
                        <span class="material-icons">schedule</span>
                        <h2><?php echo $api_version === '3.0' ? 'Hourly Forecast' : '3-Hour Forecast'; ?></h2>
                    </div>
                    <div class="hourly-scroll">
                        <?php foreach ($hourly_data as $h): ?>
                            <?php
                            if ($api_version === '3.0') {
                                $h_time = date('H:i', $h['dt']);
                                $h_day  = date('D',   $h['dt']);
                                $h_temp = round($h['temp']);
                                $h_icon = $h['weather'][0]['icon'];
                                $h_desc = ucfirst($h['weather'][0]['description']);
                                $h_wind = round($h['wind_speed'] * 2.237);
                                $h_pop  = isset($h['pop']) ? round($h['pop'] * 100) : 0;
                            } else {
                                $h_time = date('H:i', $h['dt']);
                                $h_day  = date('D',   $h['dt']);
                                $h_temp = round($h['main']['temp']);
                                $h_icon = $h['weather'][0]['icon'];
                                $h_desc = ucfirst($h['weather'][0]['description']);
                                $h_wind = round($h['wind']['speed'] * 2.237);
                                $h_pop  = isset($h['pop']) ? round($h['pop'] * 100) : 0;
                            }
                            ?>
                            <div class="hourly-item" title="<?php echo $h_desc; ?>">
                                <span class="hourly-day"><?php echo $h_day; ?></span>
                                <span class="hourly-time"><?php echo $h_time; ?></span>
                                <img src="https://openweathermap.org/img/wn/<?php echo $h_icon; ?>.png"
                                    alt="<?php echo $h_desc; ?>"
                                    class="hourly-icon">
                                <span class="hourly-temp"><?php echo $h_temp; ?>°</span>
                                <?php if ($h_pop > 0): ?>
                                    <span class="hourly-rain">
                                        <span class="material-icons">water_drop</span>
                                        <?php echo $h_pop; ?>%
                                    </span>
                                <?php endif; ?>
                                <span class="hourly-wind">
                                    <span class="material-icons">air</span>
                                    <?php echo $h_wind; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($daily_data) && $api_version === '3.0'): ?>
                <div class="forecast-section">
                    <div class="section-header">
                        <span class="material-icons">calendar_today</span>
                        <h2>5-Day Forecast</h2>
                    </div>
                    <div class="daily-grid">
                        <?php foreach ($daily_data as $d): ?>
                            <?php
                            $d_day     = date('l',   $d['dt']);
                            $d_date    = date('d M', $d['dt']);
                            $d_icon    = $d['weather'][0]['icon'];
                            $d_desc    = ucfirst($d['weather'][0]['description']);
                            $d_max     = round($d['temp']['max']);
                            $d_min     = round($d['temp']['min']);
                            $d_pop     = isset($d['pop']) ? round($d['pop'] * 100) : 0;
                            $d_wind    = round($d['wind_speed'] * 2.237);
                            $d_summary = $d['summary'] ?? '';
                            ?>
                            <div class="daily-item" title="<?php echo htmlspecialchars($d_summary); ?>">
                                <div class="daily-day">
                                    <strong><?php echo $d_day; ?></strong>
                                    <span><?php echo $d_date; ?></span>
                                </div>
                                <img src="https://openweathermap.org/img/wn/<?php echo $d_icon; ?>.png"
                                    alt="<?php echo $d_desc; ?>"
                                    class="daily-icon">
                                <div class="daily-temps">
                                    <span class="daily-max"><?php echo $d_max; ?>°</span>
                                    <span class="daily-min"><?php echo $d_min; ?>°</span>
                                </div>
                                <span class="daily-desc"><?php echo $d_desc; ?></span>
                                <div class="daily-meta">
                                    <?php if ($d_pop > 0): ?>
                                        <span class="daily-rain">
                                            <span class="material-icons">water_drop</span>
                                            <?php echo $d_pop; ?>%
                                        </span>
                                    <?php endif; ?>
                                    <span class="daily-wind">
                                        <span class="material-icons">air</span>
                                        <?php echo $d_wind; ?> mph
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
</body>

</html>