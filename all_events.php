<?php
require 'ClassAutoLoad.php';

// Fetch all published events with pagination
$page = (int)($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;

// Search and filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$location = $_GET['location'] ?? '';
$sort = $_GET['sort'] ?? 'date_asc';

try {
    $dsn = "mysql:host={$conf['db_host']};port={$conf['db_port']};dbname={$conf['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Build base count query
    $count_query = "
        SELECT COUNT(DISTINCT e.id)
        FROM events e 
        LEFT JOIN event_categories ec ON e.id = ec.event_id
        LEFT JOIN attendee_categories ac ON ec.category_id = ac.id
        WHERE e.status IN ('upcoming', 'ongoing')
    ";

    // Build data query - UPDATED ATTENDEE COUNT
    $data_query = "
        SELECT e.*, u.fullname as organizer_name,
               COALESCE(SUM(ea.quantity), 0) as attendee_count,
               GROUP_CONCAT(DISTINCT ac.name SEPARATOR ', ') as category_names
        FROM events e 
        LEFT JOIN users u ON e.user_id = u.id 
        LEFT JOIN event_attendees ea ON e.id = ea.event_id AND ea.status = 'going'
        LEFT JOIN event_categories ec ON e.id = ec.event_id
        LEFT JOIN attendee_categories ac ON ec.category_id = ac.id
        WHERE e.status IN ('upcoming', 'ongoing')
    ";

    $params = [];
    $conditions = [];

    // Add search conditions
    if (!empty($search)) {
        $conditions[] = "(e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    // Add category filter
    if (!empty($category)) {
        $conditions[] = "ac.name = ?";
        $params[] = $category;
    }

    // Add location filter
    if (!empty($location)) {
        $conditions[] = "e.location LIKE ?";
        $params[] = "%$location%";
    }

    // Add conditions to both queries
    if (!empty($conditions)) {
        $where_clause = " AND " . implode(" AND ", $conditions);
        $count_query .= $where_clause;
        $data_query .= $where_clause;
    }

    // Get total count
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_events = $stmt->fetchColumn();
    $total_pages = $total_events > 0 ? ceil($total_events / $limit) : 1;

    // Ensure page is within valid range
    if ($page < 1) $page = 1;
    if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

    // Add sorting
    $order_by = " ORDER BY ";
    switch ($sort) {
        case 'date_desc':
            $order_by .= "e.event_date DESC";
            break;
        case 'popular':
            $order_by .= "attendee_count DESC";
            break;
        case 'date_asc':
        default:
            $order_by .= "e.event_date ASC";
            break;
    }

    // Complete data query with grouping, sorting and pagination
    $data_query .= " GROUP BY e.id " . $order_by . " LIMIT ? OFFSET ?";

    // Get events for current page
    $data_params = $params;
    $data_params[] = $limit;
    $data_params[] = $offset;

    $stmt = $pdo->prepare($data_query);

    // Bind parameters with proper types
    $param_index = 1;
    foreach ($data_params as $param) {
        if ($param_index == count($data_params) - 1 || $param_index == count($data_params)) {
            // Bind limit and offset as integers
            $stmt->bindValue($param_index, $param, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($param_index, $param);
        }
        $param_index++;
    }

    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get categories for filter dropdown
    $stmt = $pdo->query("SELECT DISTINCT name FROM attendee_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $events = [];
    $total_pages = 1;
    $categories = [];
    $total_events = 0;
    $FlashMessageObject->setMsg('msg', 'Error loading events: ' . $e->getMessage(), 'danger');
}

$LayoutObject->head($conf);
$LayoutObject->header($conf);
?>

<style>
    /* Events Page Specific Styles */
    .events-hero {
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(124, 58, 237, 0.1) 100%);
        border-radius: 20px;
        padding: 3rem 2rem;
        margin-bottom: 2rem;
        text-align: center;
        animation: fadeInUp 0.8s ease-out;
    }

    .filter-card {
        background: white;
        border-radius: 20px;
        box-shadow: var(--shadow-medium);
        border: none;
        margin-bottom: 2rem;
        animation: slideInDown 0.6s ease-out;
    }

    .filter-card .card-body {
        padding: 2rem;
    }

    .stats-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 16px;
        padding: 1.5rem;
        text-align: center;
        animation: fadeInUp 0.8s ease-out 0.2s both;
    }

    .stats-number {
        font-size: 2.5rem;
        font-weight: bold;
        line-height: 1;
        margin-bottom: 0.5rem;
    }

    .stats-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }

    .event-grid {
        opacity: 0;
        animation: fadeIn 0.8s ease-out 0.4s forwards;
    }

    .event-card {
        background: white;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--shadow-soft);
        transition: var(--transition-smooth);
        border: none;
        height: 100%;
    }

    .event-card:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: var(--shadow-large);
    }

    .event-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
        transform: scaleX(0);
        transition: var(--transition-smooth);
        transform-origin: left;
        z-index: 2;
    }

    .event-card:hover::before {
        transform: scaleX(1);
    }

    .event-image {
        height: 200px;
        object-fit: cover;
        transition: var(--transition-smooth);
    }

    .event-card:hover .event-image {
        transform: scale(1.1);
    }

    .event-status-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        z-index: 3;
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
        border-radius: 50px;
        box-shadow: var(--shadow-soft);
        animation: pulse 2s infinite;
    }

    .event-meta {
        font-size: 0.875rem;
    }

    .event-meta i {
        width: 16px;
        text-align: center;
        margin-right: 0.5rem;
        color: var(--primary-color);
    }

    .category-tag {
        display: inline-block;
        background: rgba(79, 70, 229, 0.1);
        color: var(--primary-color);
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        margin: 0.1rem;
        transition: var(--transition-smooth);
    }

    .category-tag:hover {
        background: var(--primary-color);
        color: white;
        transform: scale(1.05);
    }

    .pagination-card {
        background: white;
        border-radius: 16px;
        box-shadow: var(--shadow-soft);
        padding: 2rem;
        margin-top: 3rem;
        animation: fadeInUp 0.8s ease-out;
    }

    .page-link {
        border: none;
        border-radius: 12px;
        margin: 0 0.25rem;
        color: #6b7280;
        font-weight: 500;
        transition: var(--transition-smooth);
    }

    .page-link:hover {
        background: var(--primary-color);
        color: white;
        transform: translateY(-2px);
    }

    .page-item.active .page-link {
        background: var(--gradient-primary);
        border: none;
        transform: scale(1.1);
    }

    .page-item.disabled .page-link {
        background: #f3f4f6;
        color: #9ca3af;
    }

    .sort-dropdown .dropdown-toggle {
        border-radius: 12px;
        padding: 0.5rem 1rem;
        border: 2px solid #e5e7eb;
        transition: var(--transition-smooth);
    }

    .sort-dropdown .dropdown-toggle:hover {
        border-color: var(--primary-color);
        transform: translateY(-2px);
    }

    .search-btn {
        border-radius: 12px;
        padding: 0.5rem 1.5rem;
        transition: var(--transition-bounce);
    }

    .search-btn:hover {
        transform: translateY(-3px) scale(1.05);
    }

    .empty-state {
        padding: 4rem 2rem;
        text-align: center;
        animation: fadeInUp 0.8s ease-out;
    }

    .empty-state-icon {
        font-size: 4rem;
        color: #d1d5db;
        margin-bottom: 1.5rem;
        animation: float 3s ease-in-out infinite;
    }

    .filter-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .filter-tag {
        background: rgba(79, 70, 229, 0.1);
        color: var(--primary-color);
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: var(--transition-smooth);
    }

    .filter-tag:hover {
        background: var(--primary-color);
        color: white;
        transform: scale(1.05);
    }

    .filter-tag .remove {
        cursor: pointer;
        font-weight: bold;
    }

    .loading-skeleton {
        animation: pulse 2s infinite;
    }

    @keyframes staggerFadeIn {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .event-item {
        animation: staggerFadeIn 0.6s ease-out forwards;
        opacity: 0;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .events-hero {
            padding: 2rem 1rem;
        }

        .stats-card {
            margin-bottom: 1rem;
        }

        .event-grid .col {
            animation-delay: calc(var(--index) * 0.1s) !important;
        }
    }
</style>

<div class="container mt-4">
    <!-- Hero Section -->
    <div class="events-hero">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <h1 class="display-5 fw-bold mb-3">Discover Amazing Events</h1>
                <p class="lead text-muted mb-4">
                    Find and join incredible events happening around you. From workshops to concerts, there's always something exciting to experience.
                </p>

                <!-- Quick Stats -->
                <div class="row justify-content-center g-4">
                    <div class="col-md-3 col-6">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $total_events; ?></div>
                            <div class="stats-label">Total Events</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo count($events); ?></div>
                            <div class="stats-label">Showing</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo count($categories); ?></div>
                            <div class="stats-label">Categories</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $total_pages; ?></div>
                            <div class="stats-label">Pages</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Header Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 animate__animated animate__fadeIn">
        <div>
            <h2 class="h3 mb-1">All Events</h2>
            <p class="text-muted mb-0">
                <?php if ($total_events > 0): ?>
                    Discover <?php echo $total_events; ?> amazing events waiting for you
                <?php else: ?>
                    No events found matching your criteria
                <?php endif; ?>
            </p>
        </div>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="add_event.php" class="btn btn-primary btn-create">
                <i class="fas fa-plus-circle me-2"></i>Create Event
            </a>
        <?php endif; ?>
    </div>

    <?php echo $FlashMessageObject->getMsg('msg'); ?>

    <!-- Enhanced Search and Filter Section -->
    <div class="filter-card">
        <div class="card-body">
            <h5 class="card-title mb-4">
                <i class="fas fa-search text-primary me-2"></i>Find Your Perfect Event
            </h5>

            <form method="GET" action="all_events.php" class="row g-3" id="searchForm">
                <div class="col-md-4">
                    <label for="search" class="form-label">
                        <i class="fas fa-search me-1"></i>Search Events
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" id="search" name="search"
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search by title, description, or location...">
                    </div>
                </div>

                <div class="col-md-3">
                    <label for="category" class="form-label">
                        <i class="fas fa-tags me-1"></i>Category
                    </label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>"
                                <?php echo $category == $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="location" class="form-label">
                        <i class="fas fa-map-marker-alt me-1"></i>Location
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-map-pin text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" id="location" name="location"
                            value="<?php echo htmlspecialchars($location); ?>"
                            placeholder="Filter by location...">
                    </div>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-grid w-100">
                        <button type="submit" class="btn btn-primary search-btn">
                            <i class="fas fa-search me-2"></i> Search
                        </button>
                    </div>
                </div>
            </form>

            <!-- Active Filters Display -->
            <?php if (!empty($search) || !empty($category) || !empty($location)): ?>
                <div class="mt-4">
                    <h6 class="mb-2">Active Filters:</h6>
                    <div class="filter-tags">
                        <?php if (!empty($search)): ?>
                            <span class="filter-tag">
                                Search: "<?php echo htmlspecialchars($search); ?>"
                                <span class="remove" onclick="removeFilter('search')">&times;</span>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($category)): ?>
                            <span class="filter-tag">
                                Category: <?php echo htmlspecialchars($category); ?>
                                <span class="remove" onclick="removeFilter('category')">&times;</span>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($location)): ?>
                            <span class="filter-tag">
                                Location: <?php echo htmlspecialchars($location); ?>
                                <span class="remove" onclick="removeFilter('location')">&times;</span>
                            </span>
                        <?php endif; ?>
                        <a href="all_events.php" class="filter-tag text-decoration-none" style="background: #ef4444; color: white;">
                            <i class="fas fa-times me-1"></i>Clear All
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Results Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 animate__animated animate__fadeIn">
        <div>
            <p class="text-muted mb-0">
                <?php if ($total_events > 0): ?>
                    Showing <strong><?php echo count($events); ?></strong> of <strong><?php echo $total_events; ?></strong> events
                    <?php if (!empty($search) || !empty($category) || !empty($location)): ?>
                        matching your criteria
                    <?php endif; ?>
                <?php else: ?>
                    No events found
                <?php endif; ?>
            </p>
        </div>

        <!-- Enhanced Sort Options -->
        <div class="dropdown sort-dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-sort me-2"></i>
                <?php
                $sort_labels = [
                    'date_asc' => 'Date (Earliest First)',
                    'date_desc' => 'Date (Latest First)',
                    'popular' => 'Most Popular'
                ];
                echo $sort_labels[$sort] ?? 'Sort By';
                ?>
            </button>
            <ul class="dropdown-menu">
                <li>
                    <a class="dropdown-item d-flex align-items-center <?php echo $sort == 'date_asc' ? 'active' : ''; ?>"
                        href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'date_asc'])); ?>">
                        <i class="fas fa-sort-amount-down-alt me-2"></i>
                        Date (Earliest First)
                    </a>
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center <?php echo $sort == 'date_desc' ? 'active' : ''; ?>"
                        href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'date_desc'])); ?>">
                        <i class="fas fa-sort-amount-down me-2"></i>
                        Date (Latest First)
                    </a>
                </li>
                <li>
                    <a class="dropdown-item d-flex align-items-center <?php echo $sort == 'popular' ? 'active' : ''; ?>"
                        href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'popular'])); ?>">
                        <i class="fas fa-fire me-2"></i>
                        Most Popular
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Events Grid -->
    <?php if (empty($events)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-calendar-times"></i>
            </div>
            <h3 class="mb-3">No Events Found</h3>
            <p class="text-muted mb-4">
                <?php if (!empty($search) || !empty($category) || !empty($location)): ?>
                    We couldn't find any events matching your search criteria. Try adjusting your filters or
                    <a href="all_events.php" class="text-decoration-none">clear all filters</a> to see more events.
                <?php else: ?>
                    There are no upcoming events at the moment. Be the first to create an event and get the party started!
                <?php endif; ?>
            </p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="add_event.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus-circle me-2"></i>Create Your First Event
                    </a>
                <?php else: ?>
                    <a href="signin.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In to Create Events
                    </a>
                <?php endif; ?>
                <a href="dashboard.php" class="btn btn-outline-primary btn-lg">
                    <i class="fas fa-home me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4 event-grid">
            <?php foreach ($events as $index => $event): ?>
                <div class="col event-item" style="--index: <?php echo $index; ?>; animation-delay: <?php echo $index * 0.1; ?>s">
                    <div class="card h-100 event-card">
                        <!-- Event Image -->
                        <?php if ($event['image']): ?>
                            <div class="position-relative overflow-hidden">
                                <img src="<?php echo htmlspecialchars($event['image']); ?>"
                                    class="card-img-top event-image"
                                    alt="<?php echo htmlspecialchars($event['title']); ?>">
                                <!-- Status Badge -->
                                <span class="event-status-badge badge bg-<?php
                                                                            echo $event['status'] == 'ongoing' ? 'primary' : 'success';
                                                                            ?>">
                                    <i class="fas fa-<?php echo $event['status'] == 'ongoing' ? 'play-circle' : 'clock'; ?> me-1"></i>
                                    <?php echo ucfirst($event['status']); ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="card-img-top bg-gradient-primary d-flex align-items-center justify-content-center text-white position-relative"
                                style="height: 200px;">
                                <div class="text-center">
                                    <i class="fas fa-calendar-alt fa-3x mb-2 opacity-75"></i>
                                    <p class="mb-0 small fw-bold">EVENT</p>
                                </div>
                                <!-- Status Badge -->
                                <span class="event-status-badge badge bg-<?php
                                                                            echo $event['status'] == 'ongoing' ? 'primary' : 'success';
                                                                            ?>">
                                    <i class="fas fa-<?php echo $event['status'] == 'ongoing' ? 'play-circle' : 'clock'; ?> me-1"></i>
                                    <?php echo ucfirst($event['status']); ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <div class="card-body d-flex flex-column">
                            <!-- Event Title -->
                            <h5 class="card-title fw-bold text-dark mb-2 line-clamp-2" style="min-height: 3rem;">
                                <?php echo htmlspecialchars($event['title']); ?>
                            </h5>

                            <!-- Event Description -->
                            <p class="card-text text-muted flex-grow-1 mb-3 line-clamp-2">
                                <?php
                                $description = strip_tags($event['description']);
                                if (strlen($description) > 100) {
                                    $description = substr($description, 0, 100) . '...';
                                }
                                echo htmlspecialchars($description);
                                ?>
                            </p>

                            <!-- Event Metadata -->
                            <div class="event-meta small text-muted mb-3">
                                <!-- Location -->
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span class="text-truncate"><?php echo htmlspecialchars($event['location']); ?></span>
                                </div>

                                <!-- Date & Time -->
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span><?php echo date('M j, Y g:i A', strtotime($event['event_date'])); ?></span>
                                </div>

                                <!-- Organizer -->
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-user"></i>
                                    <span class="text-truncate">By <?php echo htmlspecialchars($event['organizer_name']); ?></span>
                                </div>

                                <!-- Ticket Price -->
                                <?php if (!empty($event['ticket_price'])): ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-ticket-alt"></i>
                                        <span>Price: KSh <?php echo number_format($event['ticket_price'], 2); ?></span>
                                    </div>
                                <?php endif; ?>

                                <!-- Categories -->
                                <?php if ($event['category_names']): ?>
                                    <div class="d-flex align-items-center mb-2 flex-wrap">
                                        <i class="fas fa-tags"></i>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php
                                            $categories = explode(', ', $event['category_names']);
                                            $display_categories = array_slice($categories, 0, 2);
                                            foreach ($display_categories as $cat): ?>
                                                <span class="category-tag"><?php echo htmlspecialchars(trim($cat)); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($categories) > 2): ?>
                                                <span class="category-tag">+<?php echo count($categories) - 2; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Attendees & Tickets -->
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-users"></i>
                                    <span>
                                        <?php echo $event['attendee_count']; ?> attending
                                        <?php if ($event['total_tickets'] > 0): ?>
                                            • <?php echo $event['available_tickets']; ?> tickets left
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Action Buttons & Time -->
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <div class="btn-group">
                                    <a href="event_details.php?id=<?php echo $event['id']; ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $event['user_id']): ?>
                                        <a href="edit_event.php?id=<?php echo $event['id']; ?>"
                                            class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <small class="text-<?php
                                                    $event_date = strtotime($event['event_date']);
                                                    $now = time();
                                                    $diff = $event_date - $now;

                                                    if ($diff < 0) {
                                                        echo 'success fw-bold"><i class="fas fa-play-circle me-1"></i>Live';
                                                    } elseif ($diff < 3600) {
                                                        echo 'danger fw-bold"><i class="fas fa-clock me-1"></i>' . ceil($diff / 60) . 'm';
                                                    } elseif ($diff < 86400) {
                                                        echo 'warning"><i class="fas fa-clock me-1"></i>' . ceil($diff / 3600) . 'h';
                                                    } else {
                                                        echo 'muted"><i class="fas fa-clock me-1"></i>' . ceil($diff / 86400) . 'd';
                                                    }
                                                    ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Enhanced Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class=" pagination-card">
                                    <nav aria-label="Event pagination">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                            <div>
                                                <small class="text-muted">
                                                    Page <strong><?php echo $page; ?></strong> of <strong><?php echo $total_pages; ?></strong>
                                                    • <strong><?php echo $total_events; ?></strong> total events
                                                </small>
                                            </div>

                                            <ul class="pagination mb-0">
                                                <!-- Previous Page -->
                                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                                    <a class="page-link"
                                                        href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                                        aria-label="Previous">
                                                        <i class="fas fa-chevron-left"></i>
                                                    </a>
                                                </li>

                                                <!-- First Page -->
                                                <?php if ($page > 3): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                                                    </li>
                                                    <?php if ($page > 4): ?>
                                                        <li class="page-item disabled">
                                                            <span class="page-link">...</span>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <!-- Page Numbers -->
                                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                        <a class="page-link"
                                                            href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                            <?php echo $i; ?>
                                                        </a>
                                                    </li>
                                                <?php endfor; ?>

                                                <!-- Last Page -->
                                                <?php if ($page < $total_pages - 2): ?>
                                                    <?php if ($page < $total_pages - 3): ?>
                                                        <li class="page-item disabled">
                                                            <span class="page-link">...</span>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                                            <?php echo $total_pages; ?>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>

                                                <!-- Next Page -->
                                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                                    <a class="page-link"
                                                        href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                                        aria-label="Next">
                                                        <i class="fas fa-chevron-right"></i>
                                                    </a>
                                                </li>
                                            </ul>

                                            <div class="d-flex align-items-center gap-2">
                                                <small class="text-muted">Go to page:</small>
                                                <input type="number" class="form-control form-control-sm"
                                                    style="width: 70px;" min="1" max="<?php echo $total_pages; ?>"
                                                    id="pageJump" placeholder="<?php echo $page; ?>">
                                                <button class="btn btn-sm btn-outline-primary" onclick="jumpToPage()">
                                                    Go
                                                </button>
                                            </div>
                                        </div>
                                    </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                        </div>

                        <script>
                            // Enhanced event interactions
                            document.addEventListener('DOMContentLoaded', function() {
                                console.log('Events page loaded with <?php echo count($events); ?> events');

                                // Add click animation to event cards
                                const eventCards = document.querySelectorAll('.event-card');
                                eventCards.forEach(card => {
                                    card.addEventListener('click', function(e) {
                                        // Don't trigger if clicking on buttons or links
                                        if (e.target.tagName === 'A' || e.target.closest('a') || e.target.tagName === 'BUTTON' || e.target.closest('button')) {
                                            return;
                                        }

                                        const viewLink = this.querySelector('a[href*="event_details.php"]');
                                        if (viewLink) {
                                            // Add click animation
                                            this.style.transform = 'scale(0.95)';
                                            setTimeout(() => {
                                                window.location.href = viewLink.href;
                                            }, 150);
                                        }
                                    });
                                });

                                // Filter removal functionality
                                window.removeFilter = function(filterName) {
                                    const url = new URL(window.location);
                                    url.searchParams.delete(filterName);
                                    window.location.href = url.toString();
                                };

                                // Page jump functionality
                                window.jumpToPage = function() {
                                    const pageInput = document.getElementById('pageJump');
                                    const page = parseInt(pageInput.value);
                                    const totalPages = <?php echo $total_pages; ?>;

                                    if (page >= 1 && page <= totalPages) {
                                        const url = new URL(window.location);
                                        url.searchParams.set('page', page);
                                        window.location.href = url.toString();
                                    } else {
                                        alert('Please enter a valid page number between 1 and ' + totalPages);
                                    }
                                };

                                // Enter key support for page jump
                                const pageJumpInput = document.getElementById('pageJump');
                                if (pageJumpInput) {
                                    pageJumpInput.addEventListener('keypress', function(e) {
                                        if (e.key === 'Enter') {
                                            jumpToPage();
                                        }
                                    });
                                }

                                // Add loading state to search form
                                const searchForm = document.getElementById('searchForm');
                                if (searchForm) {
                                    searchForm.addEventListener('submit', function() {
                                        const submitBtn = this.querySelector('button[type="submit"]');
                                        if (submitBtn) {
                                            const originalText = submitBtn.innerHTML;
                                            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Searching...';
                                            submitBtn.disabled = true;

                                            // Re-enable after 5 seconds (fallback)
                                            setTimeout(() => {
                                                submitBtn.innerHTML = originalText;
                                                submitBtn.disabled = false;
                                            }, 5000);
                                        }
                                    });
                                }

                                // Intersection Observer for animations
                                const observerOptions = {
                                    threshold: 0.1,
                                    rootMargin: '0px 0px -50px 0px'
                                };

                                const observer = new IntersectionObserver(function(entries) {
                                    entries.forEach(entry => {
                                        if (entry.isIntersecting) {
                                            entry.target.style.animationPlayState = 'running';
                                            observer.unobserve(entry.target);
                                        }
                                    });
                                }, observerOptions);

                                // Observe all animated elements
                                const animatedElements = document.querySelectorAll('.event-item');
                                animatedElements.forEach(element => {
                                    element.style.animationPlayState = 'paused';
                                    observer.observe(element);
                                });

                                // Add hover effects to category tags
                                const categoryTags = document.querySelectorAll('.category-tag');
                                categoryTags.forEach(tag => {
                                    tag.addEventListener('mouseenter', function() {
                                        this.style.transform = 'scale(1.05) translateY(-2px)';
                                    });
                                    tag.addEventListener('mouseleave', function() {
                                        this.style.transform = 'scale(1) translateY(0)';
                                    });
                                });
                            });

                            // Quick filter by category
                            function filterByCategory(category) {
                                const url = new URL(window.location);
                                url.searchParams.set('category', category);
                                window.location.href = url.toString();
                            }

                            // Quick filter by location
                            function filterByLocation(location) {
                                const url = new URL(window.location);
                                url.searchParams.set('location', location);
                                window.location.href = url.toString();
                            }
                        </script>

                        <?php
                        $LayoutObject->footer($conf);
                        ?>