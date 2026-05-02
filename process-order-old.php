<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
                // Create orders table if it doesn't exist
                $createTableSQL = "
                    CREATE TABLE IF NOT EXISTS orders (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        items JSON NOT NULL,
                        address TEXT NOT NULL,
                        payment_method VARCHAR(50) NOT NULL,
                        payment_details JSON,
                        subtotal DECIMAL(10,2) NOT NULL,
                        shipping DECIMAL(10,2) NOT NULL,
                        total DECIMAL(10,2) NOT NULL,
                        user_email VARCHAR(255),
                        user_name VARCHAR(255),
                        status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ";
                $pdo->exec($createTableSQL);
                
                $items = $data['items'] ?? [];
                $address = $data['address'] ?? '';
                $paymentMethod = $data['paymentMethod'] ?? '';
                $paymentDetails = $data['paymentDetails'] ?? [];
                $subtotal = $data['subtotal'] ?? 0;
                $shipping = $data['shipping'] ?? 0;
                $total = $data['total'] ?? 0;
                $userEmail = $data['userEmail'] ?? '';
                $userName = $data['userName'] ?? '';
                
                // Validate required fields
                if (empty($items) || empty($address) || empty($paymentMethod)) {
                    echo json_encode(['success' => false, 'message' => 'Missing required order information']);
                    exit;
                }
                
                // Insert order into database
                $stmt = $pdo->prepare("
                    INSERT INTO orders (
                        items, address, payment_method, payment_details, 
                        subtotal, shipping, total, user_email, user_name, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                
                $result = $stmt->execute([
                    json_encode($items), $address, $paymentMethod, json_encode($paymentDetails),
                    $subtotal, $shipping, $total, $userEmail, $userName
                ]);
                
                if ($result) {
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
        try {
            // Create orders table if it doesn't exist
            $createTableSQL = "
                CREATE TABLE IF NOT EXISTS orders (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    items JSON NOT NULL,
                    address TEXT NOT NULL,
                    payment_method VARCHAR(50) NOT NULL,
                    payment_details JSON,
                    subtotal DECIMAL(10,2) NOT NULL,
                    shipping DECIMAL(10,2) NOT NULL,
                    total DECIMAL(10,2) NOT NULL,
                    user_email VARCHAR(255),
                    user_name VARCHAR(255),
                    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ";
            $pdo->exec($createTableSQL);
            
            $items = $data['items'] ?? [];
            $address = $data['address'] ?? '';
            $paymentMethod = $data['paymentMethod'] ?? '';
            $paymentDetails = $data['paymentDetails'] ?? [];
            $subtotal = $data['subtotal'] ?? 0;
            $shipping = $data['shipping'] ?? 0;
            $total = $data['total'] ?? 0;
            $userEmail = $data['userEmail'] ?? '';
            $userName = $data['userName'] ?? '';
            
            // Validate required fields
            if (empty($items) || empty($address) || empty($paymentMethod)) {
                echo json_encode(['success' => false, 'message' => 'Missing required order information']);
                exit;
            }
            
            // Insert order into database
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    items, address, payment_method, payment_details, 
                    subtotal, shipping, total, user_email, user_name, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            
            $result = $stmt->execute([
                json_encode($items), $address, $paymentMethod, json_encode($paymentDetails),
                $subtotal, $shipping, $total, $userEmail, $userName
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Order created successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to insert order']);
            }
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
}

// If not a valid request
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
