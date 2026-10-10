<?php
// ==========================================
// 1. เชื่อมต่อฐานข้อมูล MySQL
// ==========================================
$servername = "10.26.1.95";
$username_db = "admin_user";
$password_db = "123456";
$dbname = "my_database";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$selected_rfid = isset($_GET['rfid']) ? $_GET['rfid'] : '';

$users_data = [];
$profile_name = "";
$final_score = 0;

// ตัวแปรสำหรับกราฟ Weekly
$high_ans = $mod_ans = $low_ans = 0;
$labels_bar = []; $hrv_data = []; $cam_data = [];

// ตัวแปรสำหรับกราฟ Monthly
$m_high_ans = $m_mod_ans = $m_low_ans = 0;
$m_labels_line = []; $m_hrv_data = []; $m_cam_data = [];

if ($selected_rfid == '') {
    $sql_users = "SELECT r.rfid_uid, r.username, 
            (SELECT camera_state FROM user_logs WHERE rfid_uid = r.rfid_uid ORDER BY recorded_time DESC LIMIT 1) as latest_state,
            (SELECT recorded_time FROM user_logs WHERE rfid_uid = r.rfid_uid ORDER BY recorded_time DESC LIMIT 1) as latest_time,
            (SELECT final_score FROM summary_results WHERE rfid_uid = r.rfid_uid ORDER BY created_at DESC LIMIT 1) as latest_score
            FROM registered_users r";
    $result_users = $conn->query($sql_users);
    if ($result_users && $result_users->num_rows > 0) {
        while($row = $result_users->fetch_assoc()) { 
            $users_data[] = $row; 
        }
    }
} else {
    $sql_profile = "SELECT username FROM registered_users WHERE rfid_uid = '$selected_rfid' LIMIT 1";
    $res_profile = $conn->query($sql_profile);
    $profile_name = ($res_profile && $res_profile->num_rows > 0) ? $res_profile->fetch_assoc()['username'] : 'Unknown Employee';

    $sql_score = "SELECT final_score FROM summary_results WHERE rfid_uid = '$selected_rfid' ORDER BY created_at DESC LIMIT 1";
    $res_score = $conn->query($sql_score);
    $final_score = ($res_score && $res_score->num_rows > 0) ? floatval($res_score->fetch_assoc()['final_score']) : 0;

    // Weekly Data
    $sql_weekly = "SELECT COUNT(*) as total, 
                    SUM(CASE WHEN camera_state >= 1 THEN 1 ELSE 0 END) as burnout_count,
                    SUM(CASE WHEN camera_state = 0 THEN 1 ELSE 0 END) as neutral_count
                  FROM user_logs WHERE rfid_uid = '$selected_rfid' AND recorded_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $weekly = $conn->query($sql_weekly)->fetch_assoc();
    $total_weekly = $weekly['total'] > 0 ? $weekly['total'] : 1;
    $burnout_count = $weekly['burnout_count'] ?: 0;
    $neutral_count = $weekly['neutral_count'] ?: 0;
    $burnout_percent = round(($burnout_count / $total_weekly) * 100);
    $neutral_percent = round(($neutral_count / $total_weekly) * 100);

    $sql_survey = "SELECT SUM(CASE WHEN survey_answer >= 50 THEN 1 ELSE 0 END) as high_ans,
                    SUM(CASE WHEN survey_answer > 16.7 AND survey_answer < 50 THEN 1 ELSE 0 END) as mod_ans,
                    SUM(CASE WHEN survey_answer <= 16.7 THEN 1 ELSE 0 END) as low_ans
                  FROM user_logs WHERE rfid_uid = '$selected_rfid' AND recorded_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    if ($res_survey = $conn->query($sql_survey)) {
        $survey_data = $res_survey->fetch_assoc();
        $high_ans = $survey_data['high_ans'] ?: 0; $mod_ans = $survey_data['mod_ans'] ?: 0; $low_ans =$survey_data['low_ans'] ?: 0;
    }

    $sql_bar = "SELECT DATE_FORMAT(recorded_time, '%a %H:%i') as time_label, hrv, camera_state 
                FROM (SELECT recorded_time, hrv, camera_state FROM user_logs WHERE rfid_uid = '$selected_rfid' ORDER BY recorded_time DESC LIMIT 5) AS sub ORDER BY recorded_time ASC";
    if ($res_bar = $conn->query($sql_bar)) {
        while($row =$res_bar->fetch_assoc()) {
            $labels_bar[] =$row['time_label']; $hrv_data[] = floatval($row['hrv']); $cam_data[] = ($row['camera_state'] >= 1) ? 100 : 0;
        }
    }

    // Monthly Data
    $sql_monthly = "SELECT COUNT(*) as total, 
                    SUM(CASE WHEN camera_state >= 1 THEN 1 ELSE 0 END) as burnout_count,
                    SUM(CASE WHEN camera_state = 0 THEN 1 ELSE 0 END) as neutral_count
                  FROM user_logs WHERE rfid_uid = '$selected_rfid' AND recorded_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $monthly =$conn->query($sql_monthly)->fetch_assoc();$total_monthly = $monthly['total'] > 0 ?$monthly['total'] : 1;
    $m_burnout_count =$monthly['burnout_count'] ?: 0;
    $m_neutral_count =$monthly['neutral_count'] ?: 0;
    $m_burnout_percent = round(($m_burnout_count / $total_monthly) * 100);$m_neutral_percent = round(($m_neutral_count / $total_monthly) * 100);

    $sql_survey_m = "SELECT SUM(CASE WHEN survey_answer >= 50 THEN 1 ELSE 0 END) as high_ans,
                    SUM(CASE WHEN survey_answer > 16.7 AND survey_answer < 50 THEN 1 ELSE 0 END) as mod_ans,
                    SUM(CASE WHEN survey_answer <= 16.7 THEN 1 ELSE 0 END) as low_ans
                  FROM user_logs WHERE rfid_uid = '$selected_rfid' AND recorded_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    if ($res_survey_m = $conn->query($sql_survey_m)) {
        $survey_m_data =$res_survey_m->fetch_assoc();
        $m_high_ans =$survey_m_data['high_ans'] ?: 0; $m_mod_ans =$survey_m_data['mod_ans'] ?: 0; $m_low_ans =$survey_m_data['low_ans'] ?: 0;
    }

    $sql_line = "SELECT DATE_FORMAT(recorded_time, '%d %b') as time_label, hrv, camera_state 
                FROM (SELECT recorded_time, hrv, camera_state FROM user_logs WHERE rfid_uid = '$selected_rfid' ORDER BY recorded_time DESC LIMIT 30) AS sub ORDER BY recorded_time ASC";
    if ($res_line = $conn->query($sql_line)) {
        while($row = $res_line->fetch_assoc()) {$m_labels_line[] = $row['time_label'];$m_hrv_data[] = floatval($row['hrv']);$m_cam_data[] = ($row['camera_state'] >= 1) ? 100 : 0;
        }
    }
}$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Burnout Risk Report Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php if ($selected_rfid == ''): ?>
    <!-- VIEW 1: หน้า MAIN -->
    <div id="view-main" class="view active">
        <div class="main-container">
            <div class="header-top">
                <div>Date: <span id="currentDate"></span><br>Time: <span id="currentTime"></span></div>
            </div>
            <div class="main-title">Burnout Risk Report for<br>Employee Attendance</div>

            <div class="emp-grid">
                <?php if (count($users_data) > 0): ?>
                    <?php foreach ($users_data as$user): ?>
                        <?php 
                            $latest_time =$user['latest_time'] ? date("h:i A", strtotime($user['latest_time'])) : "--:-- AM";
                            $user_final_score = $user['latest_score'] ? floatval($user['latest_score']) : 0;
                            $is_burnout = ($user_final_score >= 50);
                        ?>
                        <div class="emp-card" onclick="window.location.href='index.php?rfid=<?php echo $user['rfid_uid']; ?>'">
                            <img src="https://via.placeholder.com/100" alt="Profile">
                            <div class="emp-info">
                                <?php if($is_burnout): ?>
                                    <div class="high-burnout-tag">High Risk: <?php echo $user_final_score; ?>%</div>
                                <?php else: ?>
                                    <div class="normal-tag">Normal: <?php echo $user_final_score; ?>%</div>
                                <?php endif; ?>
                                <p class="emp-name">Name: <?php echo htmlspecialchars($user['username']); ?></p>
                                <p class="emp-dept">RFID : <?php echo htmlspecialchars($user['rfid_uid']); ?></p>
                                <div class="arrival-tag">Arrival Time: <?php echo $latest_time; ?></div>
                            </div>
                            <button class="arrow-btn">></button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center;">ยังไม่มีผู้ลงทะเบียนในระบบ</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- VIEW 2: หน้า DASHBOARD WEEKLY -->
    <div id="view-weekly" class="view active">
        <div class="dashboard-layout">
            <div class="sidebar">
                <svg class="home-icon" onclick="window.location.href='index.php'" viewBox="0 0 24 24">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                </svg>
            </div>
            <div class="dashboard-content">
                <div class="dash-header">
                    <div class="dash-profile">
                        <img src="https://via.placeholder.com/50" alt="Profile">
                        <h2><?php echo htmlspecialchars($profile_name); ?>'s Report</h2>
                    </div>
                </div>

                <div class="dash-subheader">
                    <div class="time-badge">Weekly (Last 7 Days)</div>
                    <div class="nav-arrow" onclick="navigateTo('view-monthly')">&#8594;</div>
                </div>

                <div class="risk-banner <?php echo ($final_score >= 50) ? 'risk-red' : 'risk-green'; ?>">
                    Overall Burnout Risk: <?php echo $final_score; ?> %
                </div>

                <div class="grid-dashboard">
                    <!-- คอลัมน์ที่ 1: ตัวเลขสถิติ -->
                    <div class="stat-group">
                        <div class="dash-card stat-block">
                            <h3>Total of inspections</h3>
                            <div class="stat-value"><?php echo ($weekly['total'] > 0) ?$weekly['total'] : 0; ?> Time</div>
                        </div>
                        <div class="dash-card stat-block">
                            <h3>Neutral <?php echo $neutral_count; ?> Time</h3>
                            <div class="stat-value"><?php echo $neutral_percent; ?>%</div>
                        </div>
                        <div class="dash-card stat-block">
                            <h3>Burnout <?php echo $burnout_count; ?> Time</h3>
                            <div class="stat-value"><?php echo $burnout_percent; ?>%</div>
                        </div>
                    </div>

                    <!-- คอลัมน์ที่ 2: บังคับความสูง 100% -->
                    <div class="chart-group">
                        <div class="dash-card" style="height: 100%; display: flex; flex-direction: column; justify-content: space-evenly;">
                            <h3 style="text-align: center; margin-bottom: 5px;">Camera Burnout Detection</h3>
                            <div class="heatmap-legend" style="margin-top: 0; margin-bottom: 15px;">
                                <div class="legend-item"><div class="legend-color box-red"></div> Burnout</div>
                                <div class="legend-item"><div class="legend-color box-green"></div> Neutral</div>
                            </div>
                            <div class="donut-container" style="margin-bottom: 20px;"><canvas id="weeklyDonut1"></canvas></div>
                            
                            <hr style="border: none; border-top: 1px solid #eee; width: 100%; margin: 10px 0;">
                            
                            <h3 style="text-align: center; font-size: 14px; margin-bottom: 10px;">History (Last 5 Scans)</h3>
                            <div id="weeklyHeatmap" class="heatmap-weekly"></div>
                        </div>
                    </div>

                    <!-- คอลัมน์ที่ 3: Questionnaire และ HRV -->
                    <div class="chart-group">
                        <div class="dash-card">
                            <h3>Questionnaire Report</h3>
                            <div class="donut-container" style="height: 180px;"><canvas id="weeklyDonut2"></canvas></div>
                        </div>
                        <div class="dash-card">
                            <h3>HRV History (Last 5 Scans)</h3>
                            <div class="chart-container" style="height: 180px;"><canvas id="weeklyBar1"></canvas></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- VIEW 3: หน้า DASHBOARD MONTHLY -->
    <div id="view-monthly" class="view">
        <div class="dashboard-layout">
            <div class="sidebar">
                <svg class="home-icon" onclick="window.location.href='index.php'" viewBox="0 0 24 24">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                </svg>
            </div>
            <div class="dashboard-content">
                <div class="dash-header">
                    <div class="dash-profile">
                        <img src="https://via.placeholder.com/50" alt="Profile">
                        <h2><?php echo htmlspecialchars($profile_name); ?>'s Report</h2>
                    </div>
                </div>

                <div class="dash-subheader">
                    <div class="time-badge">Monthly (Last 30 Days)</div>
                    <div class="nav-arrow" onclick="navigateTo('view-weekly')">&#8592;</div>
                </div>

                <div class="risk-banner <?php echo ($final_score >= 50) ? 'risk-red' : 'risk-green'; ?>">
                    Overall Burnout Risk: <?php echo $final_score; ?> %
                </div>

                <div class="grid-dashboard">
                    <!-- คอลัมน์ที่ 1: ตัวเลขสถิติ -->
                    <div class="stat-group">
                        <div class="dash-card stat-block">
                            <h3>Total of inspections</h3>
                            <div class="stat-value"><?php echo ($monthly['total'] > 0) ?$monthly['total'] : 0; ?> Time</div>
                        </div>
                        <div class="dash-card stat-block">
                            <h3>Neutral <?php echo $m_neutral_count; ?> Time</h3>
                            <div class="stat-value"><?php echo $m_neutral_percent; ?>%</div>
                        </div>
                        <div class="dash-card stat-block">
                            <h3>Burnout <?php echo $m_burnout_count; ?> Time</h3>
                            <div class="stat-value"><?php echo $m_burnout_percent; ?>%</div>
                        </div>
                    </div>

                    <!-- คอลัมน์ที่ 2: บังคับความสูง 100% -->
                    <div class="chart-group">
                        <div class="dash-card" style="height: 100%; display: flex; flex-direction: column; justify-content: space-evenly;">
                            <h3 style="text-align: center; margin-bottom: 5px;">Camera Burnout Detection</h3>
                            <div class="heatmap-legend" style="margin-top: 0; margin-bottom: 15px;">
                                <div class="legend-item"><div class="legend-color box-red"></div> Burnout</div>
                                <div class="legend-item"><div class="legend-color box-green"></div> Neutral</div>
                            </div>
                            <div class="donut-container" style="margin-bottom: 20px;"><canvas id="monthlyDonut1"></canvas></div>
                            
                            <hr style="border: none; border-top: 1px solid #eee; width: 100%; margin: 10px 0;">
                            
                            <h3 style="text-align: center; font-size: 14px; margin-bottom: 10px;">History (Last 30 Scans)</h3>
                            <div id="monthlyHeatmap" class="heatmap-monthly"></div>
                        </div>
                    </div>

                    <!-- คอลัมน์ที่ 3: Questionnaire และ HRV -->
                    <div class="chart-group">
                        <div class="dash-card">
                            <h3>Questionnaire Report</h3>
                            <div class="donut-container" style="height: 180px;"><canvas id="monthlyDonut2"></canvas></div>
                        </div>
                        <div class="dash-card">
                            <h3>HRV History (Last 30 Scans)</h3>
                            <div class="chart-container" style="height: 180px;"><canvas id="monthlyLine1"></canvas></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function updateDateTime() {
            const dateEl = document.getElementById('currentDate');
            const timeEl = document.getElementById('currentTime');
            if(dateEl && timeEl) {
                const now = new Date();
                dateEl.innerText = now.toLocaleDateString('en-GB');
                timeEl.innerText = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
        }
        updateDateTime(); setInterval(updateDateTime, 1000);

        let chartsInitialized = false;
        function navigateTo(viewId) {
            document.querySelectorAll('.view').forEach(view => view.classList.remove('active'));
            document.getElementById(viewId).classList.add('active');
            window.scrollTo(0, 0);

            if (!chartsInitialized && (viewId === 'view-weekly' || viewId === 'view-monthly')) {
                initCharts();
                chartsInitialized = true;
            }
            setTimeout(() => { if (chartsInitialized) { for (let id in Chart.instances) Chart.instances[id].resize(); } }, 50);
        }

        <?php if ($selected_rfid != ''): ?>
            window.onload = function() {
                initCharts();
                chartsInitialized = true;
            };

            function initCharts() {
                Chart.defaults.font.family = "'Kanit', sans-serif";
                Chart.register(ChartDataLabels);
                Chart.defaults.set('plugins.datalabels', { display: false });

                const donutOptionsNoPercent = {
                    responsive: true, maintainAspectRatio: false, layout: { padding: 5 }, 
                    plugins: { legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 10 } }, datalabels: { display: true, color: '#ffffff', font: { weight: 'bold', size: 10 }, formatter: (val) => val } }
                };

                // =============== WEEKLY CHARTS ===============
                new Chart(document.getElementById('weeklyDonut1'), {
                    type: 'doughnut',
                    data: { labels: ['Burnout', 'Neutral'], datasets: [{ data: [<?php echo $burnout_percent; ?>, <?php echo$neutral_percent; ?>], backgroundColor: ['#cc0000', '#4caf50'], borderWidth: 0 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, datalabels: { display: true, color: '#ffffff', font: { weight: 'bold', size: 10 }, formatter: (val) => val + '%' } } }
                });

                new Chart(document.getElementById('weeklyDonut2'), {
                    type: 'doughnut',
                    data: { labels: ['High', 'Moderate', 'Low'], datasets: [{ data: [<?php echo $high_ans; ?>, <?php echo $mod_ans; ?>, <?php echo$low_ans; ?>], backgroundColor: ['#cc0000', '#fdd835', '#4caf50'], borderWidth: 0 }] },
                    options: donutOptionsNoPercent
                });

                const barLabels = <?php echo json_encode($labels_bar); ?>;
                const hrvData = <?php echo json_encode($hrv_data); ?>;
                const camData = <?php echo json_encode($cam_data); ?>;
                
                new Chart(document.getElementById('weeklyBar1'), {
                    type: 'bar',
                    data: { labels: barLabels, datasets: [{ label: 'HRV', data: hrvData, backgroundColor: ['#a984ed', '#7e5fcc', '#4c4298', '#262873', '#00134d'], borderRadius: 5 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
                });

                // =============== MONTHLY CHARTS ===============
                new Chart(document.getElementById('monthlyDonut1'), {
                    type: 'doughnut',
                    data: { labels: ['Burnout', 'Neutral'], datasets: [{ data: [<?php echo $m_burnout_percent; ?>, <?php echo$m_neutral_percent; ?>], backgroundColor: ['#cc0000', '#4caf50'], borderWidth: 0 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, datalabels: { display: true, color: '#ffffff', font: { weight: 'bold', size: 10 }, formatter: (val) => val + '%' } } }
                });

                new Chart(document.getElementById('monthlyDonut2'), {
                    type: 'doughnut',
                    data: { labels: ['High', 'Moderate', 'Low'], datasets: [{ data: [<?php echo $m_high_ans; ?>, <?php echo $m_mod_ans; ?>, <?php echo$m_low_ans; ?>], backgroundColor: ['#cc0000', '#fdd835', '#4caf50'], borderWidth: 0 }] },
                    options: donutOptionsNoPercent
                });

                const lineLabels = <?php echo json_encode($m_labels_line); ?>;
                const mHrvData = <?php echo json_encode($m_hrv_data); ?>;
                const mCamData = <?php echo json_encode($m_cam_data); ?>;

                new Chart(document.getElementById('monthlyLine1'), {
                    type: 'line',
                    data: { labels: lineLabels, datasets: [{ label: 'HRV', data: mHrvData, borderColor: '#0d47a1', backgroundColor: '#0d47a1', tension: 0.3, pointRadius: 3 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { autoSkip: true, maxTicksLimit: 10, font: { size: 10 } } }, y: { beginAtZero: true, max: 100 } } }
                });

                // =============== HEATMAP ===============
                // เพิ่มพารามิเตอร์ maxBoxes เพื่อกำหนดจำนวนกล่องสูงสุดที่ต้องแสดง
                function renderHeatmap(containerId, dataArray, labelsArray, maxBoxes) {
                    const container = document.getElementById(containerId);
                    if (!container) return;
                    container.innerHTML = ''; 

                    // 1. วาดกล่องที่มีข้อมูลจริงก่อน (สีแดง/สีเขียว)
                    dataArray.forEach((val, index) => {
                        const box = document.createElement('div');
                        box.className = 'heatmap-box';
                        if (val === 100) {
                            box.classList.add('box-red');
                            box.title = `${labelsArray[index]} : Burnout`; 
                        } else {
                            box.classList.add('box-green');
                            box.title = `${labelsArray[index]} : Neutral`; 
                        }
                        container.appendChild(box);
                    });

                    // 2. คำนวณว่าขาดอีกกี่กล่องถึงจะครบโควตา
                    const currentCount = dataArray.length;
                    if (currentCount < maxBoxes) {
                        const emptyCount = maxBoxes - currentCount;
                        // วาดกล่องสีเทาเปล่าๆ เติมเข้าไปจนครบ
                        for (let i = 0; i < emptyCount; i++) {
                            const emptyBox = document.createElement('div');
                            emptyBox.className = 'heatmap-box'; // คลาสนี้ใน CSS เป็นสีเทา (#ebedf0) อยู่แล้ว
                            emptyBox.title = 'Waiting for scan data...';
                            emptyBox.style.opacity = '0.5'; // ทำให้สีเทาดูจางลงนิดนึงเพื่อให้รู้ว่าเป็นช่องว่าง
                            container.appendChild(emptyBox);
                        }
                    }
                }
                
                // เรียกใช้งานโดยระบุจำนวนกล่องที่ต้องการ: หน้า Weekly = 5, หน้า Monthly = 30
                renderHeatmap('weeklyHeatmap', camData, barLabels, 5);
                renderHeatmap('monthlyHeatmap', mCamData, lineLabels, 30);
            }
        <?php endif; ?>
    </script>
</body>
</html>