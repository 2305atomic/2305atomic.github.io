<?php
require_once 'config.php';
checkAdminLogin();

// Get export type and filters
$export_type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';

if (!in_array($export_type, ['products', 'orders', 'customers', 'reviews'])) {
    $_SESSION['admin_error'] = 'Invalid export type.';
    redirect(ADMIN_URL);
}

try {
    $pdo = getDBConnection();
    $data = [];
    $filename = '';
    
    switch ($export_type) {
        case 'products':
            $stmt = $pdo->query("
                SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                ORDER BY p.created_at DESC
            ");
            $data = $stmt->fetchAll();
            $filename = 'products_export_' . date('Y-m-d_H-i-s');
            break;
            
        case 'orders':
            $stmt = $pdo->query("
                SELECT o.*, u.first_name, u.last_name, u.email 
                FROM orders o 
                LEFT JOIN users u ON o.user_id = u.id 
                ORDER BY o.created_at DESC
            ");
            $data = $stmt->fetchAll();
            $filename = 'orders_export_' . date('Y-m-d_H-i-s');
            break;
            
        case 'customers':
            $stmt = $pdo->query("
                SELECT u.*, COUNT(o.id) as total_orders, SUM(o.total_amount) as total_spent
                FROM users u 
                LEFT JOIN orders o ON u.id = o.user_id 
                GROUP BY u.id 
                ORDER BY u.created_at DESC
            ");
            $data = $stmt->fetchAll();
            $filename = 'customers_export_' . date('Y-m-d_H-i-s');
            break;
            
        case 'reviews':
            $stmt = $pdo->query("
                SELECT r.*, p.name as product_name, u.first_name, u.last_name 
                FROM reviews r 
                LEFT JOIN products p ON r.product_id = p.id 
                LEFT JOIN users u ON r.user_id = u.id 
                ORDER BY r.created_at DESC
            ");
            $data = $stmt->fetchAll();
            $filename = 'reviews_export_' . date('Y-m-d_H-i-s');
            break;
    }
    
    if (empty($data)) {
        $_SESSION['admin_error'] = 'No data found to export.';
        redirect(ADMIN_URL . '/' . $export_type . '.php');
    }
    
    // Export as CSV
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    // Export as Excel (basic HTML table)
    if ($format === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        
        echo '<table border="1">';
        echo '<tr>';
        foreach (array_keys($data[0]) as $header) {
            echo '<th>' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr>';
        
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . htmlspecialchars($cell) . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
        exit;
    }
    
} catch (Exception $e) {
    $_SESSION['admin_error'] = 'Export failed: ' . $e->getMessage();
    redirect(ADMIN_URL);
}
?>
