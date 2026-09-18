<?php if (!defined('ABSPATH')) exit;
$rest_url = get_rest_url('waterprize/v1/analytics');
$nonce = wp_create_nonce('wp_rest');
?>
<div class="wrap">
    <h1>📊 Аналитика</h1>

    <!-- Filters -->
    <div class="wpz-card wpz-analytics-filters">
        <div class="wpz-filter-row">
            <div class="wpz-filter-group">
                <label>График:</label>
                <select id="wpz-chart-type">
                    <option value="registrations" <?php selected($chart, 'registrations'); ?>>Регистрации</option>
                    <option value="points" <?php selected($chart, 'points'); ?>>Баллы</option>
                    <option value="scans" <?php selected($chart, 'scans'); ?>>Сканирования</option>
                </select>
            </div>
            <div class="wpz-filter-group">
                <label>Период:</label>
                <div class="wpz-period-btns">
                    <button type="button" class="button wpz-period-btn <?php echo $period === 'day' ? 'button-primary' : ''; ?>" data-period="day">День</button>
                    <button type="button" class="button wpz-period-btn <?php echo $period === 'week' ? 'button-primary' : ''; ?>" data-period="week">Неделя</button>
                    <button type="button" class="button wpz-period-btn <?php echo $period === 'month' ? 'button-primary' : ''; ?>" data-period="month">Месяц</button>
                    <button type="button" class="button wpz-period-btn <?php echo $period === 'year' ? 'button-primary' : ''; ?>" data-period="year">Год</button>
                </div>
            </div>
            <div class="wpz-filter-group">
                <label>С:</label>
                <input type="date" id="wpz-date-from" value="<?php echo esc_attr($from); ?>">
            </div>
            <div class="wpz-filter-group">
                <label>По:</label>
                <input type="date" id="wpz-date-to" value="<?php echo esc_attr($to); ?>">
            </div>
            <div class="wpz-filter-group">
                <button type="button" class="button button-primary" id="wpz-apply-filter">Применить</button>
            </div>
        </div>
    </div>

    <!-- Chart -->
    <div class="wpz-card wpz-analytics-chart-card">
        <h2 id="wpz-chart-title">Регистрации</h2>
        <div style="height:350px;position:relative;">
            <canvas id="wpz-analytics-chart"></canvas>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="wpz-stats-grid" id="wpz-analytics-stats"></div>
</div>

<script>
var WPZ_Analytics = {
    restUrl: <?php echo json_encode($rest_url); ?>,
    nonce: <?php echo json_encode($nonce); ?>,
    chart: <?php echo json_encode($chart); ?>,
    period: <?php echo json_encode($period); ?>,
    data: {
        registrations: <?php echo json_encode($reg_chart); ?>,
        points: <?php echo json_encode($pts_chart); ?>,
        scans: <?php echo json_encode($scans_chart); ?>
    }
};
</script>
