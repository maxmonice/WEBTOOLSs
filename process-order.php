<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'activity-logger.php';

// Handle order creation from frontend
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $rawInput = file_get_contents('php://input');
    error_log("Raw input received: " . $rawInput);
    
    if (empty($rawInput)) {
        error_log("No input data received");
        echo json_encode(['success' => false, 'message' => 'No data received']);
        exit;
    }
    
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }
    
    error_log("Decoded data: " . print_r($data, true));
    
    if ($data['action'] === 'create_order') {
        // Try database connection first
        $pdo = null;
        $databaseAvailable = false;
        
        try {
            $host     = '127.0.0.1';
            $dbname   = 'lukes_seafood';
            $username = 'root';
            $password = '';
            
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            $databaseAvailable = true;
            error_log("Database connection successful");
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            $databaseAvailable = false;
        }
        
        try {
            if ($databaseAvailable && $pdo) {
                $items = $data['items'] ?? [];
                $address = $data['address'] ?? '';
                $paymentMethod = $data['paymentMethod'] ?? '';
                $paymentDetails = $data['paymentDetails'] ?? [];
                $total = $data['total'] ?? 0;
                $userEmail = $data['userEmail'] ?? '';
                $userName = $data['userName'] ?? '';
                
                // Validate required fields
                if (empty($items) || empty($address) || empty($paymentMethod)) {
                    echo json_encode(['success' => false, 'message' => 'Missing required order information']);
                    exit;
                }
                
                // Insert order into database using existing table structure
                $stmt = $pdo->prepare("
                    INSERT INTO orders (
                        user_id, status, total_amount, address, payment_method, notes, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                // Store items and payment details in notes field as JSON
                $orderNotes = json_encode([
                    'items' => $items,
                    'payment_details' => $paymentDetails,
                    'user_email' => $userEmail,
                    'user_name' => $userName,
                    'shipping' => $data['shipping'] ?? 0,
                    'subtotal' => $data['subtotal'] ?? 0
                ]);
                
                $result = $stmt->execute([
                    null, // user_id (null for guest orders)
                    'pending',
                    $total,
                    $address,
                    $paymentMethod,
                    $orderNotes
                ]);
                
                if ($result) {
                    // Log order placement activity
                    $orderDetails = $data['items'] ?? [];
                    $itemCount = count($orderDetails);
                    $totalAmount = $data['total'] ?? 0;
                    logActivity('order_placed', "Customer placed order with {$itemCount} items totaling ₱{$totalAmount}", $userEmail, $userName);
                    
                    echo json_encode(['success' => true, 'message' => 'Order created successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to insert order']);
                }
                exit;
            } else {
                // Fallback: Store order in file if database is not available
                $orderFile = 'orders_backup.json';
                $orders = [];
                
                if (file_exists($orderFile)) {
                    $orders = json_decode(file_get_contents($orderFile), true) ?: [];
                }
                
                $orderData = [
                    'id' => count($orders) + 1,
                    'items' => $data['items'] ?? [],
                    'address' => $data['address'] ?? '',
                    'payment_method' => $data['paymentMethod'] ?? '',
                    'payment_details' => $data['paymentDetails'] ?? [],
                    'subtotal' => $data['subtotal'] ?? 0,
                    'shipping' => $data['shipping'] ?? 0,
                    'total' => $data['total'] ?? 0,
                    'user_email' => $data['userEmail'] ?? '',
                    'user_name' => $data['userName'] ?? '',
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s'),
                    'stored_in_file' => true
                ];
                
                $orders[] = $orderData;
                
                if (file_put_contents($orderFile, json_encode($orders, JSON_PRETTY_PRINT))) {
                    echo json_encode(['success' => true, 'message' => 'Order saved successfully (database unavailable - stored in backup file)']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save order']);
                }
                exit;
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
}

// If not a valid request
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
