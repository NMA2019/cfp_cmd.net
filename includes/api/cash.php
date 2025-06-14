<?php
header('Content-Type: application/json');
require_once __DIR__.'/../includes/db_connect.php';

// Helper function to respond with JSON
function respond($status, $data = null, $error = null) {
    http_response_code($status);
    echo json_encode([
        'status' => $status,
        'data' => $data,
        'error' => $error
    ]);
    exit;
}

// Authentication check
if (!isset($_SESSION['user_id'])) {
    respond(401, null, 'Unauthorized access');
}

// Check user role permissions
$allowed_roles = ['super_admin', 'admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    respond(403, null, 'Forbidden - Insufficient permissions');
}

// GET - List cash transactions or get single transaction
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Check if requesting a specific transaction
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("
                SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as handler_name
                FROM cash c
                JOIN users u ON c.handled_by = u.id
                WHERE c.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $transaction = $stmt->fetch();
            
            if ($transaction) {
                respond(200, $transaction);
            } else {
                respond(404, null, 'Transaction not found');
            }
        } 
        // List all transactions with pagination
        else {
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = 10;
            $offset = ($page - 1) * $limit;
            
            // Count total records
            $countStmt = $pdo->query("SELECT COUNT(*) FROM cash");
            $total = $countStmt->fetchColumn();
            
            // Get paginated data
            $stmt = $pdo->prepare("
                SELECT c.id, c.transaction_type, c.amount, c.description, c.reference,
                       c.transaction_date, CONCAT(u.first_name, ' ', u.last_name) as handler_name
                FROM cash c
                JOIN users u ON c.handled_by = u.id
                ORDER BY c.transaction_date DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            $transactions = $stmt->fetchAll();
            
            // Calculate current balance
            $balanceStmt = $pdo->query("
                SELECT 
                    SUM(CASE WHEN transaction_type = 'entree' THEN amount ELSE 0 END) as total_income,
                    SUM(CASE WHEN transaction_type = 'sortie' THEN amount ELSE 0 END) as total_expenses
                FROM cash
            ");
            $balance = $balanceStmt->fetch();
            $current_balance = ($balance['total_income'] ?? 0) - ($balance['total_expenses'] ?? 0);
            
            respond(200, [
                'transactions' => $transactions,
                'current_balance' => $current_balance,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);
        }
    } catch (PDOException $e) {
        logEvent("Database error: " . $e->getMessage());
        respond(500, null, 'Database error occurred');
    }
}

// POST - Create new cash transaction
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    $required = ['transaction_type', 'amount', 'description'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            respond(400, null, "Missing required field: $field");
        }
    }
    
    // Validate transaction type
    if (!in_array($data['transaction_type'], ['entree', 'sortie'])) {
        respond(400, null, "Invalid transaction type");
    }
    
    // Validate amount
    if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
        respond(400, null, "Amount must be a positive number");
    }
    
    try {
        // Generate reference (example: CASH-YYYYMMDD-XXXX)
        $reference = 'CASH-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Create transaction
        $stmt = $pdo->prepare("
            INSERT INTO cash (transaction_type, amount, payment_id, description, reference, handled_by, transaction_date)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['transaction_type'],
            $data['amount'],
            $data['payment_id'] ?? null,
            $data['description'],
            $reference,
            $_SESSION['user_id']
        ]);
        
        // Return created transaction
        $transactionId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("
            SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as handler_name
            FROM cash c
            JOIN users u ON c.handled_by = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();
        
        respond(201, $transaction);
    } catch (PDOException $e) {
        logEvent("Error creating transaction: " . $e->getMessage());
        respond(500, null, 'Error creating transaction record');
    }
}

// PUT - Update cash transaction (only certain fields can be updated)
elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        respond(400, null, 'Transaction ID is required');
    }
    
    try {
        // Only description and payment_id can be updated after creation
        $updates = [];
        $params = [];
        
        if (isset($data['description'])) {
            $updates[] = "description = ?";
            $params[] = $data['description'];
        }
        
        if (isset($data['payment_id'])) {
            $updates[] = "payment_id = ?";
            $params[] = $data['payment_id'];
        }
        
        if (empty($updates)) {
            respond(400, null, 'No valid fields to update');
        }
        
        $params[] = $data['id'];
        
        $sql = "UPDATE cash SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Return updated transaction
        $stmt = $pdo->prepare("
            SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as handler_name
            FROM cash c
            JOIN users u ON c.handled_by = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$data['id']]);
        $transaction = $stmt->fetch();
        
        respond(200, $transaction);
    } catch (PDOException $e) {
        logEvent("Error updating transaction: " . $e->getMessage());
        respond(500, null, 'Error updating transaction record');
    }
}

// DELETE - Remove cash transaction (only super admin can delete)
elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if ($_SESSION['role'] !== 'super_admin') {
        respond(403, null, 'Forbidden - Only super admin can delete transactions');
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        respond(400, null, 'Transaction ID is required');
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM cash WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        if ($stmt->rowCount() > 0) {
            respond(200, ['message' => 'Transaction deleted successfully']);
        } else {
            respond(404, null, 'Transaction not found');
        }
    } catch (PDOException $e) {
        logEvent("Error deleting transaction: " . $e->getMessage());
        respond(500, null, 'Error deleting transaction record');
    }
}

// Search transactions
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['search'])) {
    $searchTerm = '%' . $_GET['search'] . '%';
    
    try {
        $stmt = $pdo->prepare("
            SELECT c.id, c.transaction_type, c.amount, c.description, c.reference,
                   c.transaction_date, CONCAT(u.first_name, ' ', u.last_name) as handler_name
            FROM cash c
            JOIN users u ON c.handled_by = u.id
            WHERE c.description LIKE ? OR c.reference LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?
            ORDER BY c.transaction_date DESC
            LIMIT 50
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $results = $stmt->fetchAll();
        
        respond(200, $results);
    } catch (PDOException $e) {
        logEvent("Search error: " . $e->getMessage());
        respond(500, null, 'Search failed');
    }
}

// Get cash balance
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['balance'])) {
    try {
        $stmt = $pdo->query("
            SELECT 
                SUM(CASE WHEN transaction_type = 'entree' THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN transaction_type = 'sortie' THEN amount ELSE 0 END) as total_expenses
            FROM cash
        ");
        $balance = $stmt->fetch();
        $current_balance = ($balance['total_income'] ?? 0) - ($balance['total_expenses'] ?? 0);
        
        respond(200, ['balance' => $current_balance]);
    } catch (PDOException $e) {
        logEvent("Balance calculation error: " . $e->getMessage());
        respond(500, null, 'Error calculating balance');
    }
}

respond(405, null, 'Method not allowed');
?>