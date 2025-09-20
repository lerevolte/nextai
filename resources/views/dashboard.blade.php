<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дашборд - AI Bot Service</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .dashboard {
            max-width: 1400px;
            margin: 0 auto;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .period-selector {
            display: inline-flex;
            background: #f3f4f6;
            border-radius: 10px;
            padding: 5px;
            margin-top: 20px;
        }

        .period-btn {
            padding: 10px 20px;
            border: none;
            background: transparent;
            border-radius: 7px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .period-btn.active {
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .metric-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }

        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .metric-label {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .metric-value {
            font-size: 36px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 10px;
        }

        .metric-trend {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .trend-up {
            background: #d1fae5;
            color: #065f46;
        }

        .trend-down {
            background: #fee2e2;
            color: #991b1b;
        }

        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .chart-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #374151;
        }

        .heatmap-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .ab-tests {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .test-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f9fafb;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }

        .test-item:hover {
            background: #f3f4f6;
            transform: translateX(5px);
        }

        .test-status {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #dbeafe;
            color: #1e40af;
        }

        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            transition: transform 0.3s;
            animation: pulse 2s infinite;
        }

        .refresh-btn:hover {
            transform: scale(1.1);
        }

        @keyframes pulse {
            0% { box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4); }
            50% { box-shadow: 0 10px 40px rgba(102, 126, 234, 0.6); }
            100% { box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4); }
        }

        .export-menu {
            position: absolute;
            top: 30px;
            right: 30px;
        }

        .export-btn {
            padding: 10px 20px;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .export-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .performance-indicator {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }

        .indicator-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #10b981;
            animation: blink 2s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="header">
            <div class="performance-indicator">
                <div class="indicator-dot"></div>
                <span style="font-size: 12px; color: #6b7280;">Система работает нормально</span>
            </div>
            
            <h1>Аналитика чат-ботов</h1>
            <p style="color: #6b7280;">Обновлено: <span id="lastUpdate">только что</span></p>
            
            <div class="period-selector">
                <button class="period-btn" data-period="7">7 дней</button>
                <button class="period-btn active" data-period="30">30 дней</button>
                <button class="period-btn" data-period="90">90 дней</button>
            </div>
            
            <div class="export-menu">
                <button class="export-btn" onclick="exportReport('pdf')">📄 PDF</button>
                <button class="export-btn" onclick="exportReport('excel')">📊 Excel</button>
                <button class="export-btn" onclick="exportReport('csv')">📋 CSV</button>
            </div>
        </div>

        <div class="metrics-grid">
            <div class="metric-card" onclick="showDetails('conversations')">
                <div class="metric-label">Всего диалогов</div>
                <div class="metric-value" data-metric="total_conversations">0</div>
                <div class="metric-trend trend-up">
                    ↑ <span data-trend="conversations">0</span>%
                </div>
            </div>
            
            <div class="metric-card" onclick="showDetails('users')">
                <div class="metric-label">Уникальных пользователей</div>
                <div class="metric-value" data-metric="unique_users">0</div>
                <div class="metric-trend trend-up">
                    ↑ <span data-trend="users">0</span>%
                </div>
            </div>
            
            <div class="metric-card" onclick="showDetails('response_time')">
                <div class="metric-label">Среднее время ответа</div>
                <div class="metric-value"><span data-metric="avg_response_time">0</span>с</div>
                <div class="metric-trend trend-down">
                    ↓ <span data-trend="response_time">0</span>%
                </div>
            </div>
            
            <div class="metric-card" onclick="showDetails('success_rate')">
                <div class="metric-label">Успешность</div>
                <div class="metric-value"><span data-metric="success_rate">0</span>%</div>
                <div class="metric-trend trend-up">
                    ↑ <span data-trend="success_rate">0</span>%
                </div>
            </div>
        </div>

        <div class="charts-row">
            <div class="chart-container">
                <h3 class="chart-title">📈 Динамика сообщений</h3>
                <canvas id="messagesChart" height="100"></canvas>
            </div>
            
            <div class="chart-container">
                <h3 class="chart-title">📊 Распределение по каналам</h3>
                <canvas id="channelsChart" height="100"></canvas>
            </div>
        </div>

        <div class="charts-row">
            <div class="heatmap-container">
                <h3 class="chart-title">🔥 Тепловая карта активности</h3>
                <div id="heatmap"></div>
            </div>
            
            <div class="chart-container">
                <h3 class="chart-title">⚡ Производительность ботов</h3>
                <canvas id="botsChart" height="100"></canvas>
            </div>
        </div>

        <div class="ab-tests">
            <h3 class="chart-title">🧪 Активные A/B тесты</h3>
            <div id="abTestsList">
                <div class="test-item">
                    <div>
                        <div style="font-weight: 600;">Тест промпта v2</div>
                        <div style="font-size: 14px; color: #6b7280; margin-top: 5px;">
                            Участников: 1,234 | Конверсия: +12.5%
                        </div>
                    </div>
                    <div class="test-status status-active">Активен</div>
                </div>
            </div>
        </div>

        <div class="refresh-btn" onclick="refreshData()">
            <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                <path d="M4 12a8 8 0 018-8V2.5L16 6l-4 3.5V8a6 6 0 100 12 6 6 0 006-6h1.5A7.5 7.5 0 1112 4.5V4a8 8 0 01-8 8z"/>
            </svg>
        </div>
    </div>

    <script>
        // Инициализация графиков
        document.addEventListener('DOMContentLoaded', function() {
            initCharts();
            loadDashboardData();
            startAutoRefresh();
        });

        function initCharts() {
            // График сообщений
            const messagesCtx = document.getElementById('messagesChart').getContext('2d');
            new Chart(messagesCtx, {
                type: 'line',
                data: {
                    labels: generateDateLabels(30),
                    datasets: [{
                        label: 'Сообщения пользователей',
                        data: generateRandomData(30, 100, 500),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Ответы ботов',
                        data: generateRandomData(30, 100, 500),
                        borderColor: '#764ba2',
                        backgroundColor: 'rgba(118, 75, 162, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // График каналов
            const channelsCtx = document.getElementById('channelsChart').getContext('2d');
            new Chart(channelsCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Web', 'Telegram', 'WhatsApp', 'VK'],
                    datasets: [{
                        data: [45, 25, 20, 10],
                        backgroundColor: [
                            '#667eea',
                            '#764ba2',
                            '#10b981',
                            '#f59e0b'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // График производительности ботов
            const botsCtx = document.getElementById('botsChart').getContext('2d');
            new Chart(botsCtx, {
                type: 'bar',
                data: {
                    labels: ['Bot 1', 'Bot 2', 'Bot 3', 'Bot 4'],
                    datasets: [{
                        label: 'Диалоги',
                        data: [234, 189, 156, 98],
                        backgroundColor: '#667eea'
                    }, {
                        label: 'Успешность %',
                        data: [92, 88, 95, 87],
                        backgroundColor: '#10b981'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Тепловая карта
            initHeatmap();
        }

        function initHeatmap() {
            const options = {
                series: generateHeatmapData(),
                chart: {
                    height: 250,
                    type: 'heatmap',
                    toolbar: {
                        show: false
                    }
                },
                dataLabels: {
                    enabled: false
                },
                colors: ["#667eea"],
                xaxis: {
                    categories: ['00:00', '03:00', '06:00', '09:00', '12:00', '15:00', '18:00', '21:00']
                },
                yaxis: {
                    categories: ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']
                }
            };

            const chart = new ApexCharts(document.querySelector("#heatmap"), options);
            chart.render();
        }

        function generateDateLabels(days) {
            const labels = [];
            for (let i = days - 1; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                labels.push(date.toLocaleDateString('ru-RU', { day: 'numeric', month: 'short' }));
            }
            return labels;
        }

        function generateRandomData(count, min, max) {
            return Array.from({ length: count }, () => 
                Math.floor(Math.random() * (max - min + 1)) + min
            );
        }

        function generateHeatmapData() {
            const series = [];
            const days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
            
            days.forEach(day => {
                const data = [];
                for (let i = 0; i < 8; i++) {
                    data.push({ x: i * 3 + ':00', y: Math.floor(Math.random() * 100) });
                }
                series.push({ name: day, data });
            });
            
            return series;
        }

        function loadDashboardData() {
            // Симуляция загрузки данных
            animateValue('total_conversations', 0, 3456, 2000);
            animateValue('unique_users', 0, 1234, 2000);
            animateValue('avg_response_time', 0, 1.8, 2000);
            animateValue('success_rate', 0, 94.5, 2000);
            
            updateTrends();
        }

        function animateValue(id, start, end, duration) {
            const element = document.querySelector(`[data-metric="${id}"]`);
            const range = end - start;
            const increment = range / (duration / 16);
            let current = start;
            
            const timer = setInterval(() => {
                current += increment;
                if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                    element.textContent = formatNumber(end);
                    clearInterval(timer);
                } else {
                    element.textContent = formatNumber(current);
                }
            }, 16);
        }

        function formatNumber(num) {
            if (num >= 1000) {
                return (num / 1000).toFixed(1) + 'k';
            }
            return num.toFixed(num % 1 !== 0 ? 1 : 0);
        }

        function updateTrends() {
            const trends = {
                conversations: 15.3,
                users: 8.7,
                response_time: -12.5,
                success_rate: 3.2
            };
            
            Object.entries(trends).forEach(([key, value]) => {
                const element = document.querySelector(`[data-trend="${key}"]`);
                if (element) {
                    element.textContent = Math.abs(value);
                    const card = element.closest('.metric-trend');
                    if (card) {
                        card.className = `metric-trend ${value > 0 ? 'trend-up' : 'trend-down'}`;
                        card.innerHTML = `${value > 0 ? '↑' : '↓'} <span data-trend="${key}">${Math.abs(value)}</span>%`;
                    }
                }
            });
        }

        function refreshData() {
            const btn = document.querySelector('.refresh-btn');
            btn.style.animation = 'none';
            setTimeout(() => {
                btn.style.animation = 'pulse 2s infinite';
            }, 100);
            
            loadDashboardData();
            updateLastUpdateTime();
        }

        function updateLastUpdateTime() {
            document.getElementById('lastUpdate').textContent = 'только что';
        }

        function startAutoRefresh() {
            setInterval(() => {
                refreshData();
            }, 60000); // Обновление каждую минуту
        }

        function exportReport(format) {
            console.log(`Экспорт отчета в формате ${format}`);
            // Здесь будет вызов API для генерации отчета
            alert(`Отчет в формате ${format.toUpperCase()} будет отправлен на вашу почту`);
        }

        function showDetails(metric) {
            console.log(`Показать детали для метрики: ${metric}`);
            // Здесь можно открыть модальное окно с детальной информацией
        }

        // Обработчики для переключения периода
        document.querySelectorAll('.period-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const period = this.dataset.period;
                console.log(`Загрузка данных за ${period} дней`);
                loadDashboardData();
            });
        });
    </script>
</body>
</html>