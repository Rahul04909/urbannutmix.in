<?php
/**
 * UrbanNutMix - Razorpay Payment Verification Endpoint
 * Features: Secure signature checking, updates payment logs, clears customer cart session on validation.
 */

require_once __DIR__ . '/admin/config/database.php';
require_once __DIR__ . '/admin/config/session.php';
Session::start();

use Razorpay\Api\Api;

$paymentId = trim($_POST['razorpay_payment_id'] ?? '');
$orderId = trim($_POST['razorpay_order_id'] ?? '');
$signature = trim($_POST['razorpay_signature'] ?? '');
$orderNumber = trim($_POST['order_number'] ?? '');

if ($paymentId === '' || $orderId === '' || $signature === '' || $orderNumber === '') {
    error_log("Razorpay verification failed: Missing request parameters.");
    Session::set('flash_error', 'Invalid payment callback parameters.');
    header('Location: checkout.php?payment_error=1');
    exit;
}

try {
    $pdo = Database::getConnection();

    // Fetch the pending order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = :num AND order_status = 'pending' LIMIT 1");
    $stmt->execute(['num' => $orderNumber]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order reference not found or already processed.");
    }

    // Verify signature via Razorpay API
    require_once __DIR__ . '/vendor/autoload.php';
    $api = new Api($_ENV['RAZORPAY_KEY_ID'], $_ENV['RAZORPAY_KEY_SECRET']);

    $attributes = [
        'razorpay_order_id' => $orderId,
        'razorpay_payment_id' => $paymentId,
        'razorpay_signature' => $signature
    ];

    $api->utility->verifyPaymentSignature($attributes);

    // Signature matches - Update order status to paid
    $updateStmt = $pdo->prepare(
        "UPDATE orders 
         SET payment_status = 'paid', razorpay_payment_id = :pay_id, razorpay_signature = :sig 
         WHERE id = :id"
    );
    $updateStmt->execute([
        'pay_id' => $paymentId,
        'sig' => $signature,
        'id' => $order['id']
    ]);

    // Clear guest cart session values
    unset($_SESSION['cart']);
    unset($_SESSION['coupon']);

    // Redirect to success confirmation page
    header("Location: order-complete.php?order_number=" . urlencode($orderNumber));
    exit;

} catch (\Throwable $e) {
    error_log("Razorpay signature verification failed: " . $e->getMessage());
    Session::set('flash_error', 'Payment verification failed: ' . $e->getMessage());
    header("Location: checkout.php?payment_error=1&order_number=" . urlencode($orderNumber));
    exit;
}
