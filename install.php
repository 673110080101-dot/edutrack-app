<?php
/**
 * EduTrack Automatic System Installer
 * ไฟล์ตัวช่วยติดตั้งระบบ EduTrack ลงบน Web Hosting และ MySQL Database
 */
session_start();

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';$success = '';

// ตรวจสอบว่าเคยติดตั้งไปแล้วหรือไม่
if (file_exists('config.php') && $step === 1) {$already_installed = true;
} else {
    $already_installed = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    $db_host = trim($_POST['db_host']);
    $db_name = trim($_POST['db_name']);
    $db_user = trim($_POST['db_user']);
    $db_pass = trim($_POST['db_pass']);
    
    $admin_name = trim($_POST['admin_name']);
    $admin_email = trim($_POST['admin_email']);
    $admin_password = trim($_POST['admin_password']);

    // 1. ทดสอบการเชื่อมต่อฐานข้อมูล PDO
    try {
        $dsn = "mysql:host=$db_host;charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user,$db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // สร้าง Database หากยังไม่มี
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $pdo->exec("USE `$db_name`;");

        // 2. สร้างโครงสร้างตาราง (Tables)
        
        // ตารางผู้ใช้งาน (Users)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) UNIQUE NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `role` ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
            `level` VARCHAR(50) DEFAULT 'ม.ต้น',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // ตารางรายวิชา (Subjects)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `subjects` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(50) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `level` VARCHAR(50) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // ตารางสั่งงาน (Assignments)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `assignments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `subject` VARCHAR(255) NOT NULL,
            `max_score` FLOAT NOT NULL DEFAULT 10,
            `due_date` DATETIME NOT NULL,
            `description` TEXT,
            `level` VARCHAR(50) NOT NULL,
            `created_by` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // ตารางการส่งงาน (Submissions)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `submissions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `assignment_id` INT NOT NULL,
            `student_id` INT NOT NULL,
            `file_name` VARCHAR(255),
            `file_path` VARCHAR(255),
            `status` ENUM('ส่งตรงเวลา', 'ส่งช้ากว่ากำหนด', 'ยังไม่ได้ส่ง') DEFAULT 'ส่งตรงเวลา',
            `score` FLOAT DEFAULT NULL,
            `feedback` TEXT,
            `submitted_at` DATETIME,
            `graded_at` DATETIME,
            FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 3. บันทึกบัญชีผู้ดูแลระบบ (Admin User)
        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
        $stmt =$pdo->prepare("INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES (?, ?, ?, 'admin') ON DUPLICATE KEY UPDATE `password` = VALUES(`password`)");
        $stmt->execute([$admin_name, $admin_email,$hashed_password]);

        // 4. สร้างโฟลเดอร์สำหรับเก็บไฟล์งานที่อัปโหลด
        if (!file_exists('uploads')) {
            mkdir('uploads', 0777, true);
        }

        // 5. สร้างไฟล์ config.php
        $config_content = "<?php\n"
            . "// EduTrack System Configuration\n"
            . "define('DB_HOST', '$db_host');\n"
            . "define('DB_NAME', '$db_name');\n"
            . "define('DB_USER', '$db_user');\n"
            . "define('DB_PASS', '$db_pass');\n\n"
            . "try {\n"
            . "    \$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [\n"
            . "        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n"
            . "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC\n"
            . "    ]);\n"
            . "} catch (PDOException \$e) {\n"
            . "    die('Database connection failed: ' . \$e->getMessage());\n"
            . "}\n";

        file_put_contents('config.php', $config_content);

        $step = 2; // ย้ายไปหน้าติดตั้งสำเร็จ

    } catch (PDOException $e) {
        $error = "เกิดข้อผิดพลาดในการติดตั้ง: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตั้งระบบ EduTrack - Installation Wizard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Prompt', sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-xl w-full bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
        <!-- Header -->
        <div class="bg-indigo-600 p-6 text-white text-center">
            <h1 class="text-2xl font-bold">EduTrack Installer</h1>
            <p class="text-indigo-200 text-sm mt-1">ระบบตั้งค่าและสร้างฐานข้อมูลอัตโนมัติบน Host จริง</p>
        </div>

        <div class="p-6">
            <?php if ($already_installed &&$step === 1): ?>
                <div class="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-xl mb-4 text-sm">
                    ⚠️ <b>แจ้งเตือน:</b> พบไฟล์ <code>config.php</code> อยู่ในระบบแล้ว หากคุณต้องการติดตั้งใหม่ ให้ลบไฟล์ <code>config.php</code> เดิมออกก่อน
                </div>
                <a href="index.html" class="block text-center bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-xl transition">
                    เข้าสู่หน้าเว็บไซต์หลัก
                </a>
            <?php elseif ($step === 1): ?>

                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 p-3 rounded-xl mb-4 text-xs">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4 text-xs">
                    <h2 class="font-bold text-slate-700 text-sm border-b pb-2">1. ตั้งค่าการเชื่อมต่อ MySQL Database</h2>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-600 mb-1">Database Host</label>
                            <input type="text" name="db_host" value="localhost" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-600 mb-1">Database Name</label>
                            <input type="text" name="db_name" placeholder="เช่น edutrack_db" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-600 mb-1">Database User</label>
                            <input type="text" name="db_user" placeholder="เช่น root หรือ db_user" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-600 mb-1">Database Password</label>
                            <input type="password" name="db_pass" placeholder="รหัสผ่านฐานข้อมูล" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                    </div>

                    <h2 class="font-bold text-slate-700 text-sm border-b pb-2 pt-2">2. ตั้งค่าบัญชีผู้ดูแลระบบ (Admin User)</h2>
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">ชื่อ-นามสกุล ผู้ดูแลระบบ</label>
                        <input type="text" name="admin_name" value="ผู้ดูแลระบบ (Admin)" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-600 mb-1">อีเมล Admin</label>
                            <input type="email" name="admin_email" value="admin@school.ac.th" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                        <div>
                            <label class="block font-medium text-slate-600 mb-1">รหัสผ่าน Admin</label>
                            <input type="password" name="admin_password" required placeholder="••••••••" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
                        </div>
                    </div>

                    <button type="submit" name="install" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 rounded-xl transition text-sm shadow mt-2">
                        🚀 เริ่มต้นการติดตั้งตารางและระบบ
                    </button>
                </form>

            <?php elseif ($step === 2): ?>
                <div class="text-center py-6 space-y-4">
                    <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-3xl mx-auto">
                        ✓
                    </div>
                    <h2 class="text-xl font-bold text-slate-800">ติดตั้งระบบ EduTrack เรียบร้อยแล้ว!</h2>
                    <p class="text-xs text-slate-500 max-w-md mx-auto">
                        ระบบสร้างตารางฐานข้อมูลและไฟล์ <code>config.php</code> สำเร็จเรียบร้อยแล้ว <br>
                        <strong class="text-red-600">ข้อแนะนำด้านความปลอดภัย:</strong> โปรดลบไฟล์ <code>install.php</code> ออกจาก Server เพื่อป้องกันผู้อื่นเข้ามารีเซ็ตระบบ
                    </p>
                    <div class="pt-4">
                        <a href="index.html" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-6 py-2.5 rounded-xl text-sm transition shadow">
                            ไปยังหน้าเข้าสู่ระบบ
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>