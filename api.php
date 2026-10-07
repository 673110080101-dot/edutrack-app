<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (!file_exists('config.php')) {
    echo json_encode(['status' => 'error', 'message' => 'ยังไม่ได้ทำการติดตั้งระบบ กรุณารัน install.php ก่อน']);
    exit;
}

require_once 'config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

// 1. ดึงข้อมูลรายการสั่งงานทั้งหมด
if ($action === 'get_assignments') {
    $stmt = $pdo->query("SELECT * FROM assignments ORDER BY id DESC");
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
    exit;
}

// 2. ระบบการอัปโหลดไฟล์จริงขึ้น Server
if ($action === 'upload_submission' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignment_id = $_POST['assignment_id'];
    $student_id = $_POST['student_id'];
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file']['tmp_name'];
        $file_name = time() . '_' . basename($_FILES['file']['name']);
        $target_dir = "uploads/";
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($file_tmp, $target_file)) {
            // บันทึกลง MySQL
            $stmt = $pdo->prepare("INSERT INTO submissions (assignment_id, student_id, file_name, file_path, status, submitted_at) VALUES (?, ?, ?, ?, 'ส่งตรงเวลา', NOW())");
            $stmt->execute([$assignment_id, $student_id, $_FILES['file']['name'], $target_file]);

            echo json_encode(['status' => 'success', 'message' => 'อัปโหลดไฟล์ขึ้น Server สำเร็จ']);
            exit;
        }
    }
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์']);
    exit;
}

echo json_encode(['status' => 'online', 'message' => 'EduTrack API System is Running']);