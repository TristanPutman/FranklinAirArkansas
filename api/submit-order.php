<?php
// Order submission endpoint — handles wizard form POST
header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/pricing.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/email.php';

// --- 1. Sanitize all POST inputs ---
$input = sanitize($_POST);

// --- 2. Extract fields ---
$serviceType     = $input['service_type'] ?? '';
$sqft            = max(0, intval($input['sqft'] ?? 0));
$rush            = !empty($input['rush']);
$customerName    = $input['customer_name'] ?? '';
$customerEmail   = $input['customer_email'] ?? '';
$customerPhone   = $input['customer_phone'] ?? '';
$customerCompany = $input['customer_company'] ?? '';
$notes           = $input['notes'] ?? '';

// --- 3. Validate required fields ---
$errors = [];
if (empty($serviceType))    $errors[] = 'Service type is required.';
if (empty($customerName))   $errors[] = 'Name is required.';
if (empty($customerEmail))  $errors[] = 'Email is required.';
if (!empty($customerEmail) && !validateEmail($customerEmail)) $errors[] = 'A valid email is required.';
if (empty($customerPhone))  $errors[] = 'Phone number is required.';
if (!empty($customerPhone) && !validatePhone($customerPhone)) $errors[] = 'A valid phone number is required.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// --- 4. Validate service type ---
$pricingTable = getPricingTable();
if (!isset($pricingTable[$serviceType])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid service type.']);
    exit;
}

$serviceLabel = $pricingTable[$serviceType]['label'];

// --- 5. Calculate price (server-authoritative, sqft-based) ---
$priceResult = calculatePrice($serviceType, $sqft, $rush);
$priceCents  = $priceResult['total_cents'];

// --- 6. Commercial: price is 0 (quote required) ---
if ($serviceType === 'commercial') {
    $priceCents = 0;
}

// --- 7. Build project_data from POST fields ---
$projectFields = [
    'project_type', 'address_street', 'address_city', 'address_state', 'address_zip',
    'sqft', 'year_built', 'front_door_faces',
    'floor_material', 'roof_ceiling_material', 'roofing_type', 'roof_insulation',
    'wall_material', 'wall_thickness', 'wall_insulation_type', 'wall_insulation',
    'siding_type', 'glass_u_value', 'glass_shgc', 'exterior_door',
    'energy_code',
];

$projectData = [];
foreach ($projectFields as $field) {
    $value = $input[$field] ?? '';
    if ($value !== '' && $value !== null) {
        $projectData[$field] = $value;
    }
}

// --- 8. Database operations ---
try {
    $db = getDB();
    $isNewUser = false;
    $tempPassword = null;

    // a. Check if user exists by email
    $stmt = $db->prepare('SELECT id, name FROM users WHERE email = ?');
    $stmt->execute([$customerEmail]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        // b. Use existing user ID; update phone if provided
        $userId = $existingUser['id'];
        if (!empty($customerPhone)) {
            $stmt = $db->prepare('UPDATE users SET phone = ? WHERE id = ?');
            $stmt->execute([$customerPhone, $userId]);
        }
    } else {
        // c. Create new user account
        $tempPassword = generatePassword();
        $userId = authRegister($customerName, $customerEmail, $tempPassword, $customerPhone, $customerCompany);
        $isNewUser = true;
    }

    // d. Generate order number
    $orderNumber = generateOrderNumber();

    // e. Insert order
    $stmt = $db->prepare(
        'INSERT INTO orders (user_id, order_number, status, service_type, num_systems, rush, price_cents, project_data, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        $orderNumber,
        'pending',
        $serviceLabel,
        1, // num_systems kept as 1 for schema compatibility
        $rush ? 1 : 0,
        $priceCents,
        json_encode($projectData),
        $notes,
    ]);

    // f. Get the new order ID
    $orderId = $db->lastInsertId();

    // g. Handle file uploads (multi-file: floor_plans)
    if (!empty($_FILES['floor_plans']) && is_array($_FILES['floor_plans']['name'])) {
        $fileCount = count($_FILES['floor_plans']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            // Skip empty file slots
            if ($_FILES['floor_plans']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            // Restructure into individual file array
            $file = [
                'name'     => $_FILES['floor_plans']['name'][$i],
                'type'     => $_FILES['floor_plans']['type'][$i],
                'tmp_name' => $_FILES['floor_plans']['tmp_name'][$i],
                'error'    => $_FILES['floor_plans']['error'][$i],
                'size'     => $_FILES['floor_plans']['size'][$i],
            ];

            // Validate file
            $validationResult = validateFileUpload($file);
            if ($validationResult !== true) {
                // Log validation error but continue processing order
                error_log("File upload validation failed for order {$orderNumber}: {$validationResult}");
                continue;
            }

            // Upload file
            $uploadResult = handleFileUpload($file, $orderId);
            if ($uploadResult === false) {
                error_log("File upload failed for order {$orderNumber}: {$file['name']}");
                continue;
            }

            // Insert file record
            $stmt = $db->prepare(
                'INSERT INTO order_files (order_id, uploaded_by, file_name, file_path, file_type, file_size)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $orderId,
                $userId,
                $uploadResult['file_name'],
                $uploadResult['file_path'],
                'floor_plan',
                $uploadResult['file_size'],
            ]);
        }
    }

    // h. Log status change
    logStatusChange($orderId, null, 'pending', $userId, 'Order submitted via website');

    // i. Build order and customer arrays for email
    $order = [
        'id'           => $orderId,
        'order_number' => $orderNumber,
        'service_type' => $serviceLabel,
        'sqft'         => $sqft,
        'rush'         => $rush,
        'price_cents'  => $priceCents,
        'project_data' => $projectData,
        'notes'        => $notes,
    ];

    $customer = [
        'name'    => $customerName,
        'email'   => $customerEmail,
        'phone'   => $customerPhone,
        'company' => $customerCompany,
    ];

    // j. Send admin notification
    sendOrderNotificationToAdmin($order, $customer);

    // k. Send customer confirmation
    sendOrderConfirmationToCustomer($order, $customer);

    // l. If new user, send welcome email with temporary password
    if ($isNewUser && $tempPassword) {
        $welcomeHtml = '<div style="font-family:Arial,sans-serif;max-width:500px;margin:0 auto;padding:20px;">'
            . '<h2 style="color:#1B2A4A;">Welcome to Franklin Air Arkansas</h2>'
            . '<p>Your account has been created so you can track your order online.</p>'
            . '<p><strong>Email:</strong> ' . htmlspecialchars($customerEmail) . '<br>'
            . '<strong>Temporary Password:</strong> ' . htmlspecialchars($tempPassword) . '</p>'
            . '<p>Log in at <a href="https://franklinairarkansas.com/portal/login.php">franklinairarkansas.com/portal/login.php</a> to view your orders.</p>'
            . '<p>We recommend changing your password after your first login.</p>'
            . '<p style="color:#8a8580;font-size:13px;">Franklin Air Arkansas</p>'
            . '</div>';

        sendEmail($customerEmail, 'Your Franklin Air Arkansas Account', $welcomeHtml);
    }

    // --- 9. Return success ---
    echo json_encode([
        'success'      => true,
        'order_number' => $orderNumber,
        'email'        => $customerEmail,
        'service_type' => $serviceLabel,
        'total'        => $priceCents,
    ]);

} catch (Exception $e) {
    // --- 10. Handle errors ---
    error_log("Order submission error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Something went wrong processing your order. Please call us at (479) 207-2454.',
    ]);
}
