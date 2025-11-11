<?php
// Include the autoloader which starts session and loads all classes
require_once 'ClassAutoLoad.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])){
    header('Location: signin.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']}",
        $conf['db_user'],
        $conf['db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection Failed: " . $e->getMessage());
}

// Fetch events for dropdown
$eventsStmt = $pdo->prepare("SELECT id, title, event_date, status FROM events WHERE user_id = ? ORDER BY created_at DESC");
$eventsStmt->execute([$userId]);
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

// --- SUMMARY METRICS ---
$totalEvents = $pdo->prepare("SELECT COUNT(*) FROM events WHERE user_id = ?");
$totalEvents->execute([$userId]);
$totalEvents = $totalEvents->fetchColumn();

$totalTicketsSold = $pdo->prepare("
    SELECT IFNULL(SUM(quantity),0)
    FROM event_attendees ea
    JOIN events e ON ea.event_id = e.id
    WHERE e.user_id = ? AND ea.status='going'
");
$totalTicketsSold->execute([$userId]);
$totalTicketsSold = $totalTicketsSold->fetchColumn();

$totalRevenue = $pdo->prepare("
    SELECT IFNULL(SUM(p.amount),0)
    FROM payments p
    JOIN events e ON p.event_id = e.id
    WHERE e.user_id = ? AND p.status='completed'
");
$totalRevenue->execute([$userId]);
$totalRevenue = $totalRevenue->fetchColumn();

$averageRating = $pdo->prepare("
    SELECT IFNULL(ROUND(AVG(f.rating),1),0)
    FROM feedback f
    JOIN events e ON f.event_id = e.id
    WHERE e.user_id = ?
");
$averageRating->execute([$userId]);
$averageRating = $averageRating->fetchColumn();

// Average ticket price
$avgTicketPrice = $pdo->prepare("
    SELECT IFNULL(ROUND(AVG(ticket_price),2),0)
    FROM events
    WHERE user_id = ? AND ticket_price > 0
");
$avgTicketPrice->execute([$userId]);
$avgTicketPrice = $avgTicketPrice->fetchColumn();

// Total attendees (unique users)
$totalAttendees = $pdo->prepare("
    SELECT COUNT(DISTINCT ea.user_id)
    FROM event_attendees ea
    JOIN events e ON ea.event_id = e.id
    WHERE e.user_id = ?
");
$totalAttendees->execute([$userId]);
$totalAttendees = $totalAttendees->fetchColumn();

// Conversion rate (tickets sold / total tickets)
$conversionRate = $pdo->prepare("
    SELECT 
        CASE 
            WHEN SUM(total_tickets) > 0 
            THEN ROUND((SUM(total_tickets - available_tickets) / SUM(total_tickets)) * 100, 1)
            ELSE 0 
        END as rate
    FROM events
    WHERE user_id = ?
");
$conversionRate->execute([$userId]);
$conversionRate = $conversionRate->fetchColumn();

// Total feedback count
$totalFeedback = $pdo->prepare("
    SELECT COUNT(*)
    FROM feedback f
    JOIN events e ON f.event_id = e.id
    WHERE e.user_id = ?
");
$totalFeedback->execute([$userId]);
$totalFeedback = $totalFeedback->fetchColumn();

// --- EVENT STATUS DISTRIBUTION ---
$eventStatusData = $pdo->prepare("
    SELECT status, COUNT(*) AS total
    FROM events
    WHERE user_id = ?
    GROUP BY status
");
$eventStatusData->execute([$userId]);
$eventStatusData = $eventStatusData->fetchAll(PDO::FETCH_ASSOC);

// --- RSVP BREAKDOWN ---
$rsvpData = $pdo->prepare("
    SELECT ea.status, COUNT(*) AS total
    FROM event_attendees ea
    JOIN events e ON ea.event_id = e.id
    WHERE e.user_id = ?
    GROUP BY ea.status
");
$rsvpData->execute([$userId]);
$rsvpData = $rsvpData->fetchAll(PDO::FETCH_ASSOC);

// --- REVENUE OVER TIME (last 30 days) ---
$revenueOverTime = $pdo->prepare("
    SELECT DATE(p.created_at) AS date, SUM(p.amount) AS total
    FROM payments p
    JOIN events e ON p.event_id = e.id
    WHERE e.user_id = ? AND p.status='completed' AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(p.created_at)
    ORDER BY date ASC
");
$revenueOverTime->execute([$userId]);
$revenueOverTime = $revenueOverTime->fetchAll(PDO::FETCH_ASSOC);

// --- TICKETS SOLD OVER TIME ---
$ticketsSoldOverTime = $pdo->prepare("
    SELECT DATE(ea.registered_at) AS date, SUM(ea.quantity) AS total
    FROM event_attendees ea
    JOIN events e ON ea.event_id = e.id
    WHERE e.user_id = ? AND ea.status='going' AND ea.registered_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(ea.registered_at)
    ORDER BY date ASC
");
$ticketsSoldOverTime->execute([$userId]);
$ticketsSoldOverTime = $ticketsSoldOverTime->fetchAll(PDO::FETCH_ASSOC);

// --- TOP EVENTS BY REVENUE ---
$topEvents = $pdo->prepare("
    SELECT e.title AS event_title, IFNULL(SUM(p.amount),0) AS revenue, COUNT(DISTINCT p.id) AS transactions
    FROM events e
    LEFT JOIN payments p ON e.id = p.event_id AND p.status='completed'
    WHERE e.user_id = ?
    GROUP BY e.id
    ORDER BY revenue DESC
    LIMIT 5
");
$topEvents->execute([$userId]);
$topEvents = $topEvents->fetchAll(PDO::FETCH_ASSOC);

// --- TOP EVENTS BY ATTENDANCE ---
$topEventsByAttendance = $pdo->prepare("
    SELECT e.title AS event_title, SUM(ea.quantity) AS attendees
    FROM events e
    LEFT JOIN event_attendees ea ON e.id = ea.event_id AND ea.status='going'
    WHERE e.user_id = ?
    GROUP BY e.id
    ORDER BY attendees DESC
    LIMIT 5
");
$topEventsByAttendance->execute([$userId]);
$topEventsByAttendance = $topEventsByAttendance->fetchAll(PDO::FETCH_ASSOC);

// --- RATING DISTRIBUTION ---
$ratingDist = $pdo->prepare("
    SELECT f.rating, COUNT(*) AS total
    FROM feedback f
    JOIN events e ON f.event_id = e.id
    WHERE e.user_id = ?
    GROUP BY f.rating
    ORDER BY f.rating ASC
");
$ratingDist->execute([$userId]);
$ratingDist = $ratingDist->fetchAll(PDO::FETCH_ASSOC);

// --- PAYMENT METHOD DISTRIBUTION ---
$paymentMethods = $pdo->prepare("
    SELECT p.payment_method, COUNT(*) AS total, SUM(p.amount) AS revenue
    FROM payments p
    JOIN events e ON p.event_id = e.id
    WHERE e.user_id = ? AND p.status='completed'
    GROUP BY p.payment_method
");
$paymentMethods->execute([$userId]);
$paymentMethods = $paymentMethods->fetchAll(PDO::FETCH_ASSOC);

// --- ATTENDEE CATEGORY BREAKDOWN ---
// Check if attendee_category table exists
$tableCheck = $pdo->query("SHOW TABLES LIKE 'attendee_category'")->rowCount();
$categoryBreakdown = [];

if($tableCheck > 0) {
    try {
        $categoryBreakdown = $pdo->prepare("
            SELECT ac.name, COUNT(*) AS total
            FROM event_attendees ea
            JOIN events e ON ea.event_id = e.id
            JOIN attendee_category ac ON ea.category_id = ac.id
            WHERE e.user_id = ?
            GROUP BY ac.id
            ORDER BY total DESC
        ");
        $categoryBreakdown->execute([$userId]);
        $categoryBreakdown = $categoryBreakdown->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $categoryBreakdown = [];
    }
}

// --- UPCOMING EVENTS ---
$upcomingEvents = $pdo->prepare("
    SELECT title, event_date, 
           (total_tickets - available_tickets) AS sold,
           total_tickets,
           ROUND(((total_tickets - available_tickets) / total_tickets) * 100, 1) AS fill_rate
    FROM events
    WHERE user_id = ? AND status IN ('upcoming', 'draft') AND event_date > NOW()
    ORDER BY event_date ASC
    LIMIT 5
");
$upcomingEvents->execute([$userId]);
$upcomingEvents = $upcomingEvents->fetchAll(PDO::FETCH_ASSOC);

// --- RECENT FEEDBACK ---
$recentFeedback = $pdo->prepare("
    SELECT e.title, f.rating, f.comment, f.created_at, u.fullname
    FROM feedback f
    JOIN events e ON f.event_id = e.id
    JOIN users u ON f.user_id = u.id
    WHERE e.user_id = ?
    ORDER BY f.created_at DESC
    LIMIT 5
");
$recentFeedback->execute([$userId]);
$recentFeedback = $recentFeedback->fetchAll(PDO::FETCH_ASSOC);

// Start HTML output using layout
$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<!-- Analytics Dashboard Content -->
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 30px;">
    <div class="container">
        <h1 style="font-size: 32px; font-weight: 700; margin-bottom: 5px;">📊 EventHub Analytics Dashboard</h1>
        <p style="font-size: 14px; margin: 0;">Comprehensive insights and performance metrics for your events</p>
    </div>
</div>

<!-- Add custom styles for analytics -->
<style>
.analytics-container { 
    padding: 0 20px; 
    max-width: 1400px; 
    margin: auto; 
    margin-bottom: 100px; 
}
.filter-section {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}
.filter-section label {
    font-weight: 600;
    color: #333;
}
.filter-section select {
    padding: 10px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s;
}
.filter-section select:focus {
    outline: none;
    border-color: #667eea;
}
.filter-section button {
    padding: 10px 20px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    transition: all 0.3s;
}
.filter-section button:hover {
    background: #5568d3;
    transform: translateY(-2px);
}
.analytics-metrics { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
    gap: 20px; 
    margin-bottom: 30px; 
}
.analytics-metric-card { 
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px; 
    padding: 25px; 
    color: white;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}
.analytics-metric-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}
.analytics-metric-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.2);
}
.analytics-metric-card h3 { 
    font-size: 14px; 
    font-weight: 500; 
    margin-bottom: 10px; 
    opacity: 0.9;
    position: relative;
}
.analytics-metric-card p { 
    font-size: 32px; 
    font-weight: 700;
    margin: 0;
    position: relative;
}
.metric-icon {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 40px;
    opacity: 0.3;
}
.analytics-charts { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); 
    gap: 25px;
    margin-bottom: 30px;
}
.analytics-chart-box { 
    background: white; 
    border-radius: 15px; 
    padding: 25px; 
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: all 0.3s;
}
.analytics-chart-box:hover {
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}
.analytics-chart-box h4 { 
    font-size: 18px;
    font-weight: 600;
    color: #333; 
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 3px solid #667eea;
}
.analytics-data-table {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.analytics-data-table h4 {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 3px solid #667eea;
}
.analytics-data-table table {
    width: 100%;
    border-collapse: collapse;
}
.analytics-data-table th {
    background: #f5f7fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #e0e0e0;
}
.analytics-data-table td {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
    color: #666;
}
.analytics-data-table tr:hover {
    background: #f9f9f9;
}
.feedback-item {
    padding: 15px;
    border-left: 4px solid #667eea;
    margin-bottom: 15px;
    background: #f9f9f9;
    border-radius: 8px;
}
.feedback-item .stars {
    color: #ffa500;
    font-size: 18px;
    margin-bottom: 8px;
}
.feedback-item .comment {
    color: #666;
    font-style: italic;
    margin-bottom: 8px;
}
.feedback-item .meta {
    font-size: 12px;
    color: #999;
}
@media (max-width: 768px) {
    .analytics-charts { grid-template-columns: 1fr; }
    .analytics-metrics { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="analytics-container">
    <!-- Event Filter -->
    <div class="filter-section">
        <label for="eventSelect">Filter by Event:</label>
        <select id="eventSelect">
            <option value="all">All Events</option>
            <?php foreach($events as $e): ?>
                <option value="<?= $e['id'] ?>">
                    <?= htmlspecialchars($e['title']) ?> 
                    (<?= date('M d, Y', strtotime($e['event_date'])) ?>) 
                    - <?= ucfirst($e['status']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button onclick="window.print()">🖨️ Print Report</button>
        <button onclick="exportData()">📥 Export Data</button>
    </div>

    <!-- KPI Metrics -->
    <div class="analytics-metrics">
        <div class="analytics-metric-card">
            <span class="metric-icon">🎫</span>
            <h3>Total Events</h3>
            <p id="totalEvents"><?= $totalEvents ?></p>
        </div>
        <div class="analytics-metric-card">
            <span class="metric-icon">🎟️</span>
            <h3>Tickets Sold</h3>
            <p id="totalTicketsSold"><?= number_format($totalTicketsSold) ?></p>
        </div>
        <div class="analytics-metric-card">
            <span class="metric-icon">💰</span>
            <h3>Total Revenue</h3>
            <p id="totalRevenue">Ksh <?= number_format($totalRevenue, 2) ?></p>
        </div>
        <div class="analytics-metric-card">
            <span class="metric-icon">⭐</span>
            <h3>Average Rating</h3>
            <p id="averageRating"><?= $averageRating ?>/5</p>
        </div>
        <div class="analytics-metric-card">
            <span class="metric-icon">👥</span>
            <h3>Total Attendees</h3>
            <p id="totalAttendees"><?= number_format($totalAttendees) ?></p>
        </div>
        <div class="analytics-metric-card">
            <span class="metric-icon">📈</span>
            <h3>Conversion Rate</h3>
            <p id="conversionRate"><?= $conversionRate ?>%</p>
        </div>
        <div class="analytics-metric-card">
            <span class="metric-icon">💵</span>
            <h3>Avg Ticket Price</h3>
            <p id="avgTicketPrice">Ksh <?= number_format($avgTicketPrice, 2) ?></p>
        </div>
        <div class="analytics-metric-card">
            <span class="metric-icon">💬</span>
            <h3>Total Feedback</h3>
            <p id="totalFeedback"><?= number_format($totalFeedback) ?></p>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="analytics-charts">
        <div class="analytics-chart-box">
            <h4>Event Status Distribution</h4>
            <canvas id="eventStatusChart"></canvas>
        </div>
        <div class="analytics-chart-box">
            <h4>RSVP Status Breakdown</h4>
            <canvas id="rsvpChart"></canvas>
        </div>
        <div class="analytics-chart-box">
            <h4>Revenue Trend (Last 30 Days)</h4>
            <canvas id="revenueChart"></canvas>
        </div>
        <div class="analytics-chart-box">
            <h4>Tickets Sold Over Time (Last 30 Days)</h4>
            <canvas id="ticketsChart"></canvas>
        </div>
        <div class="analytics-chart-box">
            <h4>Top 5 Events by Revenue</h4>
            <canvas id="topEventsChart"></canvas>
        </div>
        <div class="analytics-chart-box">
            <h4>Top 5 Events by Attendance</h4>
            <canvas id="topAttendanceChart"></canvas>
        </div>
        <div class="analytics-chart-box">
            <h4>Rating Distribution</h4>
            <canvas id="ratingChart"></canvas>
        </div>
        <div class="analytics-chart-box">
            <h4>Payment Methods</h4>
            <canvas id="paymentMethodChart"></canvas>
        </div>
        <?php if(count($categoryBreakdown) > 0): ?>
        <div class="analytics-chart-box">
            <h4>Attendee Category Distribution</h4>
            <canvas id="categoryChart"></canvas>
        </div>
        <?php endif; ?>
    </div>

    <!-- Upcoming Events Table -->
    <?php if(count($upcomingEvents) > 0): ?>
    <div class="analytics-data-table">
        <h4>Upcoming Events Performance</h4>
        <table>
            <thead>
                <tr>
                    <th>Event Name</th>
                    <th>Date</th>
                    <th>Tickets Sold</th>
                    <th>Total Capacity</th>
                    <th>Fill Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($upcomingEvents as $evt): ?>
                <tr>
                    <td><?= htmlspecialchars($evt['title']) ?></td>
                    <td><?= date('M d, Y h:i A', strtotime($evt['event_date'])) ?></td>
                    <td><?= $evt['sold'] ?></td>
                    <td><?= $evt['total_tickets'] ?></td>
                    <td><strong><?= $evt['fill_rate'] ?>%</strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Recent Feedback -->
    <?php if(count($recentFeedback) > 0): ?>
    <div class="analytics-data-table">
        <h4>Recent Customer Feedback</h4>
        <?php foreach($recentFeedback as $fb): ?>
        <div class="feedback-item">
            <div class="stars">
                <?php for($i = 0; $i < 5; $i++): ?>
                    <?= $i < $fb['rating'] ? '⭐' : '☆' ?>
                <?php endfor; ?>
            </div>
            <div class="comment">"<?= htmlspecialchars($fb['comment'] ?: 'No comment provided') ?>"</div>
            <div class="meta">
                <strong><?= htmlspecialchars($fb['fullname']) ?></strong> on 
                <strong><?= htmlspecialchars($fb['title']) ?></strong> - 
                <?= date('M d, Y', strtotime($fb['created_at'])) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initial Data from PHP
let eventStatusData = <?= json_encode($eventStatusData) ?>;
let rsvpData = <?= json_encode($rsvpData) ?>;
let revenueOverTime = <?= json_encode($revenueOverTime) ?>;
let ticketsSoldOverTime = <?= json_encode($ticketsSoldOverTime) ?>;
let topEvents = <?= json_encode($topEvents) ?>;
let topAttendance = <?= json_encode($topEventsByAttendance) ?>;
let ratingDist = <?= json_encode($ratingDist) ?>;
let paymentMethods = <?= json_encode($paymentMethods) ?>;
let categoryBreakdown = <?= json_encode($categoryBreakdown) ?>;

// Chart defaults
Chart.defaults.font.family = 'Inter';
Chart.defaults.font.size = 12;

// Event Status Chart
let eventStatusChart = new Chart(document.getElementById('eventStatusChart'), {
    type: 'doughnut',
    data: {
        labels: eventStatusData.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1)),
        datasets: [{
            data: eventStatusData.map(d => d.total),
            backgroundColor: ['#667eea','#50E3C2','#F5A623','#D0021B','#B8E986']
        }]
    },
    options: {
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// RSVP Chart
let rsvpChart = new Chart(document.getElementById('rsvpChart'), {
    type: 'pie',
    data: {
        labels: rsvpData.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1)),
        datasets: [{
            data: rsvpData.map(d => d.total),
            backgroundColor: ['#7B68EE','#00C49F','#FFBB28','#FF8042']
        }]
    },
    options: {
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Revenue Chart
let revenueChart = new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: revenueOverTime.map(d => d.date),
        datasets: [{
            label: 'Revenue (Ksh)',
            data: revenueOverTime.map(d => d.total),
            fill: true,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102,126,234,0.1)',
            tension: 0.4,
            pointBackgroundColor: '#667eea',
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Ksh ' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Tickets Sold Chart
let ticketsChart = new Chart(document.getElementById('ticketsChart'), {
    type: 'bar',
    data: {
        labels: ticketsSoldOverTime.map(d => d.date),
        datasets: [{
            label: 'Tickets Sold',
            data: ticketsSoldOverTime.map(d => d.total),
            backgroundColor: '#50E3C2',
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Top Events by Revenue
let topEventsChart = new Chart(document.getElementById('topEventsChart'), {
    type: 'bar',
    data: {
        labels: topEvents.map(d => d.event_title),
        datasets: [{
            label: 'Revenue (Ksh)',
            data: topEvents.map(d => d.revenue),
            backgroundColor: '#764ba2',
            borderRadius: 8
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            x: {
                ticks: {
                    callback: function(value) {
                        return 'Ksh ' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Top Events by Attendance
let topAttendanceChart = new Chart(document.getElementById('topAttendanceChart'), {
    type: 'bar',
    data: {
        labels: topAttendance.map(d => d.event_title),
        datasets: [{
            label: 'Attendees',
            data: topAttendance.map(d => d.attendees),
            backgroundColor: '#F5A623',
            borderRadius: 8
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
            legend: { display: false }
        }
    }
});

// Rating Distribution
let ratingChart = new Chart(document.getElementById('ratingChart'), {
    type: 'bar',
    data: {
        labels: ratingDist.map(d => d.rating + ' Stars'),
        datasets: [{
            label: 'Count',
            data: ratingDist.map(d => d.total),
            backgroundColor: ['#D0021B','#FF8042','#FFBB28','#00C49F','#7B68EE'],
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Payment Methods Chart
let paymentMethodChart = new Chart(document.getElementById('paymentMethodChart'), {
    type: 'doughnut',
    data: {
        labels: paymentMethods.map(d => d.payment_method),
        datasets: [{
            data: paymentMethods.map(d => d.total),
            backgroundColor: ['#667eea','#50E3C2','#F5A623','#764ba2']
        }]
    },
    options: {
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Category Breakdown Chart (only if data exists)
<?php if(count($categoryBreakdown) > 0): ?>
let categoryChart = new Chart(document.getElementById('categoryChart'), {
    type: 'polarArea',
    data: {
        labels: categoryBreakdown.map(d => d.name),
        datasets: [{
            data: categoryBreakdown.map(d => d.total),
            backgroundColor: ['#667eea','#50E3C2','#F5A623','#764ba2','#D0021B','#B8E986']
        }]
    },
    options: {
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
<?php endif; ?>

// Handle dropdown change
document.getElementById('eventSelect').addEventListener('change', function() {
    const eventId = this.value;
    fetch('get_event_data.php?event_id=' + eventId)
        .then(res => res.json())
        .then(data => {
            if(data.error){
                alert(data.error);
                return;
            }

            // Update KPIs
            document.getElementById('totalTicketsSold').textContent = data.tickets.toLocaleString();
            document.getElementById('totalRevenue').textContent = 'Ksh ' + parseFloat(data.revenue).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('averageRating').textContent = data.averageRating + '/5';
            
            if(data.totalAttendees !== undefined) {
                document.getElementById('totalAttendees').textContent = data.totalAttendees.toLocaleString();
            }

            // Update RSVP chart
            rsvpChart.data.labels = data.attendees.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1));
            rsvpChart.data.datasets[0].data = data.attendees.map(d => d.total);
            rsvpChart.update();

            // Update Rating chart
            ratingChart.data.labels = data.ratings.map(d => d.rating + ' Stars');
            ratingChart.data.datasets[0].data = data.ratings.map(d => d.total);
            ratingChart.update();
        })
        .catch(err => {
            console.error('Error fetching event data:', err);
            alert('Failed to load event data');
        });
});

// Export data function
function exportData() {
    const csvContent = generateCSVContent();
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'eventhub_analytics_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function generateCSVContent() {
    let csv = 'EventHub Analytics Report\n\n';
    csv += 'Generated on: ' + new Date().toLocaleString() + '\n\n';
    csv += 'Summary Metrics\n';
    csv += 'Metric,Value\n';
    csv += 'Total Events,' + document.getElementById('totalEvents').textContent + '\n';
    csv += 'Total Tickets Sold,' + document.getElementById('totalTicketsSold').textContent + '\n';
    csv += 'Total Revenue,' + document.getElementById('totalRevenue').textContent + '\n';
    csv += 'Average Rating,' + document.getElementById('averageRating').textContent + '\n';
    csv += 'Total Attendees,' + document.getElementById('totalAttendees').textContent + '\n';
    csv += 'Conversion Rate,' + document.getElementById('conversionRate').textContent + '\n';
    csv += 'Average Ticket Price,' + document.getElementById('avgTicketPrice').textContent + '\n';
    csv += 'Total Feedback,' + document.getElementById('totalFeedback').textContent + '\n';
    csv += '\n';
    
    csv += 'Top Events by Revenue\n';
    csv += 'Event,Revenue,Transactions\n';
    topEvents.forEach(e => {
        csv += '"' + e.event_title + '",Ksh ' + e.revenue + ',' + e.transactions + '\n';
    });
    csv += '\n';
    
    csv += 'Top Events by Attendance\n';
    csv += 'Event,Attendees\n';
    topAttendance.forEach(e => {
        csv += '"' + e.event_title + '",' + e.attendees + '\n';
    });
    
    return csv;
}
</script>

<?php
// Close the layout with footer
$LayoutObject->footer($conf);
?>