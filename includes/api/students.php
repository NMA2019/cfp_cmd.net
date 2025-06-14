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
$allowed_roles = ['super_admin', 'admin', 'professeur'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    respond(403, null, 'Forbidden - Insufficient permissions');
}

// GET - List students or get single student
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Check if requesting a specific student
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("
                SELECT s.*, u.first_name, u.last_name, u.email, u.phone, u.address, u.photo as user_photo
                FROM students s
                JOIN users u ON s.user_id = u.id
                WHERE s.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $student = $stmt->fetch();
            
            if ($student) {
                // Calculate age
                $birthDate = new DateTime($student['date_of_birth']);
                $today = new DateTime();
                $age = $today->diff($birthDate)->y;
                $student['age'] = $age;
                
                respond(200, $student);
            } else {
                respond(404, null, 'Student not found');
            }
        } 
        // List all students with pagination
        else {
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = 10;
            $offset = ($page - 1) * $limit;
            
            // Count total records
            $countStmt = $pdo->query("SELECT COUNT(*) FROM students");
            $total = $countStmt->fetchColumn();
            
            // Get paginated data
            $stmt = $pdo->prepare("
                SELECT s.id, s.matricule, CONCAT(u.first_name, ' ', u.last_name) as full_name, 
                       s.date_of_birth, s.gender, s.status, f.name as formation, 
                       TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) as age
                FROM students s
                JOIN users u ON s.user_id = u.id
                JOIN formations f ON s.formation_id = f.id
                ORDER BY s.id DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            $students = $stmt->fetchAll();
            
            respond(200, [
                'students' => $students,
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

// POST - Create new student
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only admin roles can create students
    if (!in_array($_SESSION['role'], ['super_admin', 'admin'])) {
        respond(403, null, 'Forbidden - Insufficient permissions');
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    $required = ['first_name', 'last_name', 'email', 'password', 'date_of_birth', 'gender', 'formation_id'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            respond(400, null, "Missing required field: $field");
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        // Generate matricule (example: CMD-YYYY-MM-XXXX)
        $matricule = 'CMD-' . date('Y-m') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Create user first
        $userStmt = $pdo->prepare("
            INSERT INTO users (role_id, first_name, last_name, email, password, phone, address, photo)
            VALUES (4, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $photo = $data['photo'] ?? 'default.png';
        
        $userStmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $hashedPassword,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $photo
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Create student record
        $studentStmt = $pdo->prepare("
            INSERT INTO students (user_id, matricule, date_of_birth, gender, cin, niveau_scolaire, formation_id, status, photo)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'preinscrit', ?)
        ");
        
        $studentStmt->execute([
            $userId,
            $matricule,
            $data['date_of_birth'],
            $data['gender'],
            $data['cin'] ?? null,
            $data['niveau_scolaire'] ?? null,
            $data['formation_id'],
            $photo
        ]);
        
        $pdo->commit();
        
        // Return created student
        $studentId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM student_details WHERE id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        
        respond(201, $student);
    } catch (PDOException $e) {
        $pdo->rollBack();
        logEvent("Error creating student: " . $e->getMessage());
        respond(500, null, 'Error creating student record');
    }
}

// PUT - Update student
elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Only admin roles can update students
    if (!in_array($_SESSION['role'], ['super_admin', 'admin'])) {
        respond(403, null, 'Forbidden - Insufficient permissions');
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        respond(400, null, 'Student ID is required');
    }
    
    try {
        // Get student to update
        $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
        $stmt->execute([$data['id']]);
        $student = $stmt->fetch();
        
        if (!$student) {
            respond(404, null, 'Student not found');
        }
        
        $userId = $student['user_id'];
        
        // Update user info
        $userFields = ['first_name', 'last_name', 'email', 'phone', 'address', 'photo'];
        $userUpdates = [];
        $userParams = [];
        
        foreach ($userFields as $field) {
            if (isset($data[$field])) {
                $userUpdates[] = "$field = ?";
                $userParams[] = $data[$field];
            }
        }
        
        if (!empty($userUpdates)) {
            $userParams[] = $userId;
            $userSql = "UPDATE users SET " . implode(', ', $userUpdates) . " WHERE id = ?";
            $userStmt = $pdo->prepare($userSql);
            $userStmt->execute($userParams);
        }
        
        // Update student info
        $studentFields = ['date_of_birth', 'gender', 'cin', 'niveau_scolaire', 'formation_id', 'status', 'photo'];
        $studentUpdates = [];
        $studentParams = [];
        
        foreach ($studentFields as $field) {
            if (isset($data[$field])) {
                $studentUpdates[] = "$field = ?";
                $studentParams[] = $data[$field];
            }
        }
        
        if (!empty($studentUpdates)) {
            $studentParams[] = $data['id'];
            $studentSql = "UPDATE students SET " . implode(', ', $studentUpdates) . " WHERE id = ?";
            $studentStmt = $pdo->prepare($studentSql);
            $studentStmt->execute($studentParams);
        }
        
        // Return updated student
        $stmt = $pdo->prepare("SELECT * FROM student_details WHERE id = ?");
        $stmt->execute([$data['id']]);
        $updatedStudent = $stmt->fetch();
        
        respond(200, $updatedStudent);
    } catch (PDOException $e) {
        logEvent("Error updating student: " . $e->getMessage());
        respond(500, null, 'Error updating student record');
    }
}

// DELETE - Remove student
elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Only super admin can delete students
    if ($_SESSION['role'] !== 'super_admin') {
        respond(403, null, 'Forbidden - Only super admin can delete students');
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        respond(400, null, 'Student ID is required');
    }
    
    try {
        // Get student to delete (we need the associated user_id)
        $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
        $stmt->execute([$data['id']]);
        $student = $stmt->fetch();
        
        if (!$student) {
            respond(404, null, 'Student not found');
        }
        
        $userId = $student['user_id'];
        
        // Delete student record first (foreign key constraint is ON DELETE SET NULL for user_id)
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        // Delete associated user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        respond(200, ['message' => 'Student deleted successfully']);
    } catch (PDOException $e) {
        logEvent("Error deleting student: " . $e->getMessage());
        respond(500, null, 'Error deleting student record');
    }
}

// Search students
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['search'])) {
    $searchTerm = '%' . $_GET['search'] . '%';
    
    try {
        $stmt = $pdo->prepare("
            SELECT s.id, s.matricule, CONCAT(u.first_name, ' ', u.last_name) as full_name, 
                   s.date_of_birth, s.gender, s.status, f.name as formation
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN formations f ON s.formation_id = f.id
            WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR s.matricule LIKE ? OR f.name LIKE ?
            ORDER BY s.id DESC
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

respond(405, null, 'Method not allowed');
?>