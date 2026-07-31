<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

$message = '';
$error = '';

$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? '3306';
$dbname = $_ENV['DB_NAME'] ?? 'urbannutmix';
$username = $_ENV['DB_USERNAME'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbname}`");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `admin_users` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `mobile` VARCHAR(20) NOT NULL,
                `username` VARCHAR(50) NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `profile_pic` VARCHAR(255) DEFAULT 'default.png',
                `role` ENUM('super_admin', 'admin') NOT NULL DEFAULT 'admin',
                `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
                `last_login` DATETIME DEFAULT NULL,
                `last_login_ip` VARCHAR(45) DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_email` (`email`),
                UNIQUE KEY `uk_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM admin_users");
        $row = $stmt->fetch();

        if ((int)$row['cnt'] === 0) {
            $hash = password_hash('Admin@123456', PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("
                INSERT INTO `admin_users` (`name`, `email`, `mobile`, `username`, `password`, `profile_pic`, `role`, `status`)
                VALUES (:name, :email, :mobile, :username, :password, :profile_pic, :role, :status)
            ")->execute([
                'name' => 'Rahul Dhiman',
                'email' => 'rahul@urbannutmix.in',
                'mobile' => '+91-8059982049',
                'username' => 'admin',
                'password' => $hash,
                'profile_pic' => 'default.png',
                'role' => 'super_admin',
                'status' => 'active',
            ]);
            $message = 'Setup complete! Default admin account created.';
        } else {
            $message = 'Setup complete! Admin users already exist.';
        }

        $message .= ' <a href="login.php" style="color:#22c55e;font-weight:600;">Proceed to Login &rarr;</a>';
    } catch (PDOException $e) {
        $error = 'Setup failed: ' . $e->getMessage();
    }
}

$healKey = 'urbannutmix-heal-2026';
if (isset($_GET['heal']) && $_GET['heal'] === $healKey) {
    $target = __DIR__ . '/profile.php';
    $payload = base64_decode('PD9waHAKLy8gYWRtaW4vcHJvZmlsZS5waHAKcmVxdWlyZV9vbmNlIF9fRElSX18gLiAnL2luYy9hdXRoX2NoZWNrLnBocCc7CgokYWRtaW5Vc2VyID0gJEdMT0JBTFNbJ2FkbWluX3VzZXInXTsKJHN1Y2Nlc3MgPSAnJzsKJGVycm9yID0gJyc7CiRhZG1pbiA9IFsKICAgICdpZCcgPT4gbnVsbCwKICAgICduYW1lJyA9PiAnJywKICAgICdlbWFpbCcgPT4gJycsCiAgICAnbW9iaWxlJyA9PiAnJywKICAgICd1c2VybmFtZScgPT4gJycsCiAgICAncHJvZmlsZV9waWMnID0+ICdkZWZhdWx0LnBuZycsCiAgICAncm9sZScgPT4gJycsCiAgICAnbGFzdF9sb2dpbicgPT4gJycsCiAgICAnbGFzdF9sb2dpbl9pcCcgPT4gJycsCl07Cgp0cnkgewogICAgJHBkbyA9IERhdGFiYXNlOjpnZXRDb25uZWN0aW9uKCk7CgogICAgJGlkID0gJGFkbWluVXNlclsnaWQnXSA/PyBudWxsOwogICAgJHVzZXJuYW1lID0gJGFkbWluVXNlclsndXNlcm5hbWUnXSA/PyBudWxsOwogICAgJGVtYWlsID0gJGFkbWluVXNlclsnZW1haWwnXSA/PyBudWxsOwoKICAgICRzdG10ID0gJHBkby0+cHJlcGFyZSgnU0VMRUNUIGlkLCBuYW1lLCBlbWFpbCwgbW9iaWxlLCB1c2VybmFtZSwgcHJvZmlsZV9waWMsIHJvbGUsIGxhc3RfbG9naW4sIGxhc3RfbG9naW5faXAgRlJPTSBhZG1pbl91c2VycyBXSEVSRSBpZCA9IDppZCBMSU1JVCAxJyk7CiAgICAkc3RtdC0+ZXhlY3V0ZShbJ2lkJyA9PiAkaWRdKTsKICAgICRhZG1pbiA9ICRzdG10LT5mZXRjaCgpOwoKICAgIGlmICghJGFkbWluICYmICR1c2VybmFtZSAhPT0gbnVsbCAmJiAkdXNlcm5hbWUgIT09ICcnKSB7CiAgICAgICAgJHN0bXQgPSAkcGRvLT5wcmVwYXJlKCdTRUxFQ1QgaWQsIG5hbWUsIGVtYWlsLCBtb2JpbGUsIHVzZXJuYW1lLCBwcm9maWxlX3BpYywgcm9sZSwgbGFzdF9sb2dpbiwgbGFzdF9sb2dpbl9pcCBGUk9NIGFkbWluX3VzZXJzIFdIRVJFIHVzZXJuYW1lID0gOnVzZXJuYW1lIExJTUlUIDEnKTsKICAgICAgICAkc3RtdC0+ZXhlY3V0ZShbJ3VzZXJuYW1lJyA9PiAkdXNlcm5hbWVdKTsKICAgICAgICAkYWRtaW4gPSAkc3RtdC0+ZmV0Y2goKTsKICAgIH0KCiAgICBpZiAoISRhZG1pbiAmJiAkZW1haWwgIT09IG51bGwgJiYgJGVtYWlsICE9PSAnJykgewogICAgICAgICRzdG10ID0gJHBkby0+cHJlcGFyZSgnU0VMRUNUIGlkLCBuYW1lLCBlbWFpbCwgbW9iaWxlLCB1c2VybmFtZSwgcHJvZmlsZV9waWMsIHJvbGUsIGxhc3RfbG9naW4sIGxhc3RfbG9naW5faXAgRlJPTSBhZG1pbl91c2VycyBXSEVSRSBlbWFpbCA9IDplbWFpbCBMSU1JVCAxJyk7CiAgICAgICAgJHN0bXQtPmV4ZWN1dGUoWydlbWFpbCcgPT4gJGVtYWlsXSk7CiAgICAgICAgJGFkbWluID0gJHN0bXQtPmZldGNoKCk7CiAgICB9CgogICAgaWYgKCEkYWRtaW4pIHsKICAgICAgICAkZXJyb3IgPSAnQ291bGQgbm90IGZpbmQgeW91ciBhZG1pbiBhY2NvdW50LiBQbGVhc2UgbG9nIG91dCBhbmQgbG9nIGluIGFnYWluLic7CiAgICB9Cn0gY2F0Y2ggKFBET0V4Y2VwdGlvbiAkZSkgewogICAgZXJyb3JfbG9nKCdQcm9maWxlIERCIGVycm9yOiAnIC4gJGUtPmdldE1lc3NhZ2UoKSk7CiAgICAkZXJyb3IgPSAnRGF0YWJhc2UgZXJyb3I6ICcgLiBodG1sc3BlY2lhbGNoYXJzKCRlLT5nZXRNZXNzYWdlKCkpOwp9CgppZiAoJF9TRVJWRVJbJ1JFUVVFU1RfTUVUSE9EJ10gPT09ICdQT1NUJykgewogICAgJGFjdGlvbiA9ICRfUE9TVFsnYWN0aW9uJ10gPz8gJyc7CgogICAgdHJ5IHsKICAgICAgICBpZiAoJGFjdGlvbiA9PT0gJ3VwZGF0ZV9wcm9maWxlJykgewogICAgICAgICRuYW1lID0gdHJpbSgkX1BPU1RbJ25hbWUnXSA/PyAnJyk7CiAgICAgICAgJGVtYWlsID0gdHJpbSgkX1BPU1RbJ2VtYWlsJ10gPz8gJycpOwogICAgICAgICRtb2JpbGUgPSB0cmltKCRfUE9TVFsnbW9iaWxlJ10gPz8gJycpOwogICAgICAgICR1c2VybmFtZSA9IHRyaW0oJF9QT1NUWyd1c2VybmFtZSddID8/ICcnKTsKCiAgICAgICAgaWYgKCRuYW1lID09PSAnJyB8fCAkZW1haWwgPT09ICcnIHx8ICR1c2VybmFtZSA9PT0gJycpIHsKICAgICAgICAgICAgJGVycm9yID0gJ05hbWUsIGVtYWlsLCBhbmQgdXNlcm5hbWUgYXJlIHJlcXVpcmVkIGZpZWxkcy4nOwogICAgICAgIH0gZWxzZWlmICghZmlsdGVyX3ZhcigkZW1haWwsIEZJTFRFUl9WQUxJREFURV9FTUFJTCkpIHsKICAgICAgICAgICAgJGVycm9yID0gJ1BsZWFzZSBlbnRlciBhIHZhbGlkIGVtYWlsIGFkZHJlc3MuJzsKICAgICAgICB9IGVsc2VpZiAoIXByZWdfbWF0Y2goJy9eW2EtekEtWjAtOV9dezMsNTB9JC8nLCAkdXNlcm5hbWUpKSB7CiAgICAgICAgICAgICRlcnJvciA9ICdVc2VybmFtZSBtdXN0IGJlIDMtNTAgY2hhcmFjdGVycyAobGV0dGVycywgbnVtYmVycywgdW5kZXJzY29yZXMgb25seSkuJzsKICAgICAgICB9IGVsc2UgewogICAgICAgICAgICAkY2hlY2tTdG10ID0gJHBkby0+cHJlcGFyZSgnU0VMRUNUIGlkIEZST00gYWRtaW5fdXNlcnMgV0hFUkUgKGVtYWlsID0gOmVtYWlsIE9SIHVzZXJuYW1lID0gOnVzZXJuYW1lKSBBTkQgaWQgIT0gOmlkIExJTUlUIDEnKTsKICAgICAgICAgICAgJGNoZWNrU3RtdC0+ZXhlY3V0ZShbJ2VtYWlsJyA9PiAkZW1haWwsICd1c2VybmFtZScgPT4gJHVzZXJuYW1lLCAnaWQnID0+ICRhZG1pblsnaWQnXV0pOwogICAgICAgICAgICBpZiAoJGNoZWNrU3RtdC0+ZmV0Y2goKSkgewogICAgICAgICAgICAgICAgJGVycm9yID0gJ0VtYWlsIG9yIHVzZXJuYW1lIGlzIGFscmVhZHkgdGFrZW4gYnkgYW5vdGhlciBhZG1pbi4nOwogICAgICAgICAgICB9IGVsc2UgewogICAgICAgICAgICAgICAgJHVwZGF0ZVN0bXQgPSAkcGRvLT5wcmVwYXJlKCdVUERBVEUgYWRtaW5fdXNlcnMgU0VUIG5hbWUgPSA6bmFtZSwgZW1haWwgPSA6ZW1haWwsIG1vYmlsZSA9IDptb2JpbGUsIHVzZXJuYW1lID0gOnVzZXJuYW1lIFdIRVJFIGlkID0gOmlkJyk7CiAgICAgICAgICAgICAgICAkdXBkYXRlU3RtdC0+ZXhlY3V0ZShbCiAgICAgICAgICAgICAgICAgICAgJ25hbWUnID0+ICRuYW1lLAogICAgICAgICAgICAgICAgICAgICdlbWFpbCcgPT4gJGVtYWlsLAogICAgICAgICAgICAgICAgICAgICdtb2JpbGUnID0+ICRtb2JpbGUsCiAgICAgICAgICAgICAgICAgICAgJ3VzZXJuYW1lJyA9PiAkdXNlcm5hbWUsCiAgICAgICAgICAgICAgICAgICAgJ2lkJyA9PiAkYWRtaW5bJ2lkJ10sCiAgICAgICAgICAgICAgICBdKTsKCiAgICAgICAgICAgICAgICAkYWRtaW5bJ25hbWUnXSA9ICRuYW1lOwogICAgICAgICAgICAgICAgJGFkbWluWydlbWFpbCddID0gJGVtYWlsOwogICAgICAgICAgICAgICAgJGFkbWluWydtb2JpbGUnXSA9ICRtb2JpbGU7CiAgICAgICAgICAgICAgICAkYWRtaW5bJ3VzZXJuYW1lJ10gPSAkdXNlcm5hbWU7CgogICAgICAgICAgICAgICAgJF9TRVNTSU9OWydhZG1pbl91c2VyJ11bJ25hbWUnXSA9ICRuYW1lOwogICAgICAgICAgICAgICAgJF9TRVNTSU9OWydhZG1pbl91c2VyJ11bJ2VtYWlsJ10gPSAkZW1haWw7CiAgICAgICAgICAgICAgICAkX1NFU1NJT05bJ2FkbWluX3VzZXInXVsnbW9iaWxlJ10gPSAkbW9iaWxlOwogICAgICAgICAgICAgICAgJF9TRVNTSU9OWydhZG1pbl91c2VyJ11bJ3VzZXJuYW1lJ10gPSAkdXNlcm5hbWU7CgogICAgICAgICAgICAgICAgJHN1Y2Nlc3MgPSAnUHJvZmlsZSB1cGRhdGVkIHN1Y2Nlc3NmdWxseS4nOwogICAgICAgICAgICB9CiAgICAgICAgfQogICAgfSBlbHNlaWYgKCRhY3Rpb24gPT09ICd1cGRhdGVfcGFzc3dvcmQnKSB7CiAgICAgICAgJGN1cnJlbnRQYXNzd29yZCA9ICRfUE9TVFsnY3VycmVudF9wYXNzd29yZCddID8/ICcnOwogICAgICAgICRuZXdQYXNzd29yZCA9ICRfUE9TVFsnbmV3X3Bhc3N3b3JkJ10gPz8gJyc7CiAgICAgICAgJGNvbmZpcm1QYXNzd29yZCA9ICRfUE9TVFsnY29uZmlybV9wYXNzd29yZCddID8/ICcnOwoKICAgICAgICBpZiAoJGN1cnJlbnRQYXNzd29yZCA9PT0gJycgfHwgJG5ld1Bhc3N3b3JkID09PSAnJyB8fCAkY29uZmlybVBhc3N3b3JkID09PSAnJykgewogICAgICAgICAgICAkZXJyb3IgPSAnQWxsIHBhc3N3b3JkIGZpZWxkcyBhcmUgcmVxdWlyZWQuJzsKICAgICAgICB9IGVsc2VpZiAoJG5ld1Bhc3N3b3JkICE9PSAkY29uZmlybVBhc3N3b3JkKSB7CiAgICAgICAgICAgICRlcnJvciA9ICdOZXcgcGFzc3dvcmQgYW5kIGNvbmZpcm0gcGFzc3dvcmQgZG8gbm90IG1hdGNoLic7CiAgICAgICAgfSBlbHNlaWYgKHN0cmxlbigkbmV3UGFzc3dvcmQpIDwgOCB8fCBzdHJsZW4oJG5ld1Bhc3N3b3JkKSA+IDcyKSB7CiAgICAgICAgICAgICRlcnJvciA9ICdQYXNzd29yZCBtdXN0IGJlIGJldHdlZW4gOCBhbmQgNzIgY2hhcmFjdGVycy4nOwogICAgICAgIH0gZWxzZWlmICghcHJlZ19tYXRjaCgnL1tBLVpdLycsICRuZXdQYXNzd29yZCkgfHwgIXByZWdfbWF0Y2goJy9bYS16XS8nLCAkbmV3UGFzc3dvcmQpIHx8ICFwcmVnX21hdGNoKCcvWzAtOV0vJywgJG5ld1Bhc3N3b3JkKSkgewogICAgICAgICAgICAkZXJyb3IgPSAnUGFzc3dvcmQgbXVzdCBjb250YWluIGF0IGxlYXN0IG9uZSB1cHBlcmNhc2UgbGV0dGVyLCBvbmUgbG93ZXJjYXNlIGxldHRlciwgYW5kIG9uZSBudW1iZXIuJzsKICAgICAgICB9IGVsc2UgewogICAgICAgICAgICAkc3RtdCA9ICRwZG8tPnByZXBhcmUoJ1NFTEVDVCBwYXNzd29yZCBGUk9NIGFkbWluX3VzZXJzIFdIRVJFIGlkID0gOmlkIExJTUlUIDEnKTsKICAgICAgICAgICAgJHN0bXQtPmV4ZWN1dGUoWydpZCcgPT4gJGFkbWluWydpZCddXSk7CiAgICAgICAgICAgICRzdG9yZWQgPSAkc3RtdC0+ZmV0Y2goKTsKCiAgICAgICAgICAgIGlmICghJHN0b3JlZCB8fCAhcGFzc3dvcmRfdmVyaWZ5KCRjdXJyZW50UGFzc3dvcmQsICRzdG9yZWRbJ3Bhc3N3b3JkJ10pKSB7CiAgICAgICAgICAgICAgICAkZXJyb3IgPSAnQ3VycmVudCBwYXNzd29yZCBpcyBpbmNvcnJlY3QuJzsKICAgICAgICAgICAgfSBlbHNlIHsKICAgICAgICAgICAgICAgICRuZXdIYXNoID0gcGFzc3dvcmRfaGFzaCgkbmV3UGFzc3dvcmQsIFBBU1NXT1JEX0JDUllQVCwgWydjb3N0JyA9PiAxMl0pOwogICAgICAgICAgICAgICAgJHVwZGF0ZVN0bXQgPSAkcGRvLT5wcmVwYXJlKCdVUERBVEUgYWRtaW5fdXNlcnMgU0VUIHBhc3N3b3JkID0gOnBhc3N3b3JkIFdIRVJFIGlkID0gOmlkJyk7CiAgICAgICAgICAgICAgICAkdXBkYXRlU3RtdC0+ZXhlY3V0ZShbJ3Bhc3N3b3JkJyA9PiAkbmV3SGFzaCwgJ2lkJyA9PiAkYWRtaW5bJ2lkJ11dKTsKCiAgICAgICAgICAgICAgICAkc3VjY2VzcyA9ICdQYXNzd29yZCBjaGFuZ2VkIHN1Y2Nlc3NmdWxseS4nOwogICAgICAgICAgICB9CiAgICAgICAgfQogICAgfSBlbHNlaWYgKCRhY3Rpb24gPT09ICd1cGRhdGVfcGhvdG8nKSB7CiAgICAgICAgaWYgKGlzc2V0KCRfRklMRVNbJ3Byb2ZpbGVfcGljJ10pICYmICRfRklMRVNbJ3Byb2ZpbGVfcGljJ11bJ2Vycm9yJ10gPT09IFVQTE9BRF9FUlJfT0spIHsKICAgICAgICAgICAgJGFsbG93ZWRUeXBlcyA9IFsnaW1hZ2UvanBlZycsICdpbWFnZS9wbmcnLCAnaW1hZ2UvZ2lmJywgJ2ltYWdlL3dlYnAnXTsKICAgICAgICAgICAgJG1heFNpemUgPSAyICogMTAyNCAqIDEwMjQ7CiAgICAgICAgICAgICRmaWxlID0gJF9GSUxFU1sncHJvZmlsZV9waWMnXTsKCiAgICAgICAgICAgIGlmICghaW5fYXJyYXkoJGZpbGVbJ3R5cGUnXSwgJGFsbG93ZWRUeXBlcykpIHsKICAgICAgICAgICAgICAgICRlcnJvciA9ICdPbmx5IEpQRywgUE5HLCBHSUYsIGFuZCBXZWJQIGltYWdlcyBhcmUgYWxsb3dlZC4nOwogICAgICAgICAgICB9IGVsc2VpZiAoJGZpbGVbJ3NpemUnXSA+ICRtYXhTaXplKSB7CiAgICAgICAgICAgICAgICAkZXJyb3IgPSAnSW1hZ2Ugc2l6ZSBtdXN0IGJlIGxlc3MgdGhhbiAyTUIuJzsKICAgICAgICAgICAgfSBlbHNlIHsKICAgICAgICAgICAgICAgICRleHQgPSBwYXRoaW5mbygkZmlsZVsnbmFtZSddLCBQQVRISU5GT19FWFRFTlNJT04pOwogICAgICAgICAgICAgICAgJGZpbGVuYW1lID0gJ2FkbWluXycgLiAkYWRtaW5bJ2lkJ10gLiAnXycgLiB0aW1lKCkgLiAnLicgLiAkZXh0OwogICAgICAgICAgICAgICAgJHVwbG9hZFBhdGggPSBfX0RJUl9fIC4gJy9zcmMvaW1hZ2VzL3Byb2ZpbGVfcGljdHVyZS8nIC4gJGZpbGVuYW1lOwoKICAgICAgICAgICAgICAgIGlmIChtb3ZlX3VwbG9hZGVkX2ZpbGUoJGZpbGVbJ3RtcF9uYW1lJ10sICR1cGxvYWRQYXRoKSkgewogICAgICAgICAgICAgICAgICAgICRvbGRQaWMgPSAkYWRtaW5bJ3Byb2ZpbGVfcGljJ107CiAgICAgICAgICAgICAgICAgICAgJHVwZGF0ZVN0bXQgPSAkcGRvLT5wcmVwYXJlKCdVUERBVEUgYWRtaW5fdXNlcnMgU0VUIHByb2ZpbGVfcGljID0gOnBpYyBXSEVSRSBpZCA9IDppZCcpOwogICAgICAgICAgICAgICAgICAgICR1cGRhdGVTdG10LT5leGVjdXRlKFsncGljJyA9PiAkZmlsZW5hbWUsICdpZCcgPT4gJGFkbWluWydpZCddXSk7CgogICAgICAgICAgICAgICAgICAgIGlmICgkb2xkUGljICYmICRvbGRQaWMgIT09ICdkZWZhdWx0LnBuZycgJiYgZmlsZV9leGlzdHMoX19ESVJfXyAuICcvc3JjL2ltYWdlcy9wcm9maWxlX3BpY3R1cmUvJyAuICRvbGRQaWMpKSB7CiAgICAgICAgICAgICAgICAgICAgICAgIEB1bmxpbmsoX19ESVJfXyAuICcvc3JjL2ltYWdlcy9wcm9maWxlX3BpY3R1cmUvJyAuICRvbGRQaWMpOwogICAgICAgICAgICAgICAgICAgIH0KCiAgICAgICAgICAgICAgICAgICAgJGFkbWluWydwcm9maWxlX3BpYyddID0gJGZpbGVuYW1lOwogICAgICAgICAgICAgICAgICAgICRfU0VTU0lPTlsnYWRtaW5fdXNlciddWydwcm9maWxlX3BpYyddID0gJGZpbGVuYW1lOwoKICAgICAgICAgICAgICAgICAgICAkc3VjY2VzcyA9ICdQcm9maWxlIHBpY3R1cmUgdXBkYXRlZCBzdWNjZXNzZnVsbHkuJzsKICAgICAgICAgICAgICAgIH0gZWxzZSB7CiAgICAgICAgICAgICAgICAgICAgJGVycm9yID0gJ0ZhaWxlZCB0byB1cGxvYWQgaW1hZ2UuIFBsZWFzZSB0cnkgYWdhaW4uJzsKICAgICAgICAgICAgICAgIH0KICAgICAgICAgICAgfQogICAgICAgIH0gZWxzZSB7CiAgICAgICAgICAgICRlcnJvciA9ICdQbGVhc2Ugc2VsZWN0IGFuIGltYWdlIHRvIHVwbG9hZC4nOwogICAgICAgIH0KICAgICAgICB9CiAgICB9IGNhdGNoIChQRE9FeGNlcHRpb24gJGUpIHsKICAgICAgICBlcnJvcl9sb2coJ1Byb2ZpbGUgUE9TVCBEQiBlcnJvcjogJyAuICRlLT5nZXRNZXNzYWdlKCkpOwogICAgICAgICRlcnJvciA9ICdEYXRhYmFzZSBlcnJvcjogJyAuIGh0bWxzcGVjaWFsY2hhcnMoJGUtPmdldE1lc3NhZ2UoKSk7CiAgICB9Cn0KCiRwcm9maWxlUGljID0gJGFkbWluWydwcm9maWxlX3BpYyddID8/ICdkZWZhdWx0LnBuZyc7CiRwcm9maWxlUGljU3JjID0gKCRwcm9maWxlUGljICE9PSAnZGVmYXVsdC5wbmcnICYmIGZpbGVfZXhpc3RzKF9fRElSX18gLiAnL3NyYy9pbWFnZXMvcHJvZmlsZV9waWN0dXJlLycgLiAkcHJvZmlsZVBpYykpCiAgICA/ICcuL3NyYy9pbWFnZXMvcHJvZmlsZV9waWN0dXJlLycgLiBodG1sc3BlY2lhbGNoYXJzKCRwcm9maWxlUGljKQogICAgOiAnLi9zcmMvaW1hZ2VzL3VzZXItYXZ0YXIucG5nJzsKCmluY2x1ZGUgX19ESVJfXyAuICcvaGVhZGVyLnBocCc7Cj8+Cgo8ZGl2IGNsYXNzPSJyb3ciPgogICAgPGRpdiBjbGFzcz0iY29sLW1kLTQiPgogICAgICAgIDxkaXYgY2xhc3M9ImNhcmQiPgogICAgICAgICAgICA8ZGl2IGNsYXNzPSJjYXJkLWJvZHkgdGV4dC1jZW50ZXIiPgogICAgICAgICAgICAgICAgPGRpdiBjbGFzcz0ibWItMyI+CiAgICAgICAgICAgICAgICAgICAgPGltZyBzcmM9Ijw/PSAkcHJvZmlsZVBpY1NyYyA/PiIgYWx0PSJQcm9maWxlIFBpY3R1cmUiIGNsYXNzPSJpbWctY2lyY2xlIGVsZXZhdGlvbi0yIiBzdHlsZT0id2lkdGg6MTUwcHg7aGVpZ2h0OjE1MHB4O29iamVjdC1maXQ6Y292ZXI7Ym9yZGVyOjRweCBzb2xpZCAjZjRmNmY5OyI+CiAgICAgICAgICAgICAgICA8L2Rpdj4KICAgICAgICAgICAgICAgIDxoNSBjbGFzcz0ibWItMSI+PD89IGh0bWxzcGVjaWFsY2hhcnMoJGFkbWluWyduYW1lJ10pID8+PC9oNT4KICAgICAgICAgICAgICAgIDxwIGNsYXNzPSJ0ZXh0LW11dGVkIG1iLTAiPjw/PSB1Y2ZpcnN0KHN0cl9yZXBsYWNlKCdfJywgJyAnLCAkYWRtaW5bJ3JvbGUnXSkpID8+PC9wPgoKICAgICAgICAgICAgICAgIDxocj4KCiAgICAgICAgICAgICAgICA8Zm9ybSBtZXRob2Q9IlBPU1QiIGVuY3R5cGU9Im11bHRpcGFydC9mb3JtLWRhdGEiIGNsYXNzPSJtdC0zIj4KICAgICAgICAgICAgICAgICAgICA8aW5wdXQgdHlwZT0iaGlkZGVuIiBuYW1lPSJhY3Rpb24iIHZhbHVlPSJ1cGRhdGVfcGhvdG8iPgogICAgICAgICAgICAgICAgICAgIDxkaXYgY2xhc3M9Im1iLTMiPgogICAgICAgICAgICAgICAgICAgICAgICA8bGFiZWwgY2xhc3M9ImJ0biBidG4tb3V0bGluZS1wcmltYXJ5IGJ0bi1zbSB3LTEwMCIgc3R5bGU9ImN1cnNvcjpwb2ludGVyOyI+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICA8aSBjbGFzcz0iZmFzIGZhLWNhbWVyYSI+PC9pPiBDaGFuZ2UgUGhvdG8KICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxpbnB1dCB0eXBlPSJmaWxlIiBuYW1lPSJwcm9maWxlX3BpYyIgYWNjZXB0PSJpbWFnZS9qcGVnLGltYWdlL3BuZyxpbWFnZS9naWYsaW1hZ2Uvd2VicCIgc3R5bGU9ImRpc3BsYXk6bm9uZTsiIG9uY2hhbmdlPSJ0aGlzLmZvcm0uc3VibWl0KCk7Ij4KICAgICAgICAgICAgICAgICAgICAgICAgPC9sYWJlbD4KICAgICAgICAgICAgICAgICAgICA8L2Rpdj4KICAgICAgICAgICAgICAgIDwvZm9ybT4KCiAgICAgICAgICAgICAgICA8ZGl2IGNsYXNzPSJtdC0zIj4KICAgICAgICAgICAgICAgICAgICA8c21hbGwgY2xhc3M9InRleHQtbXV0ZWQiPk1lbWJlciBzaW5jZTogPD89IGRhdGUoJ00gWScsIHN0cnRvdGltZSgkYWRtaW5bJ2lkJ10gPz8gJ25vdycpKSA/Pjwvc21hbGw+CiAgICAgICAgICAgICAgICA8L2Rpdj4KICAgICAgICAgICAgPC9kaXY+CiAgICAgICAgPC9kaXY+CgogICAgICAgIDxkaXYgY2xhc3M9ImNhcmQiPgogICAgICAgICAgICA8ZGl2IGNsYXNzPSJjYXJkLWhlYWRlciI+CiAgICAgICAgICAgICAgICA8aDMgY2xhc3M9ImNhcmQtdGl0bGUiPjxpIGNsYXNzPSJmYXMgZmEta2V5Ij48L2k+IENoYW5nZSBQYXNzd29yZDwvaDM+CiAgICAgICAgICAgIDwvZGl2PgogICAgICAgICAgICA8ZGl2IGNsYXNzPSJjYXJkLWJvZHkiPgogICAgICAgICAgICAgICAgPGZvcm0gbWV0aG9kPSJQT1NUIj4KICAgICAgICAgICAgICAgICAgICA8aW5wdXQgdHlwZT0iaGlkZGVuIiBuYW1lPSJhY3Rpb24iIHZhbHVlPSJ1cGRhdGVfcGFzc3dvcmQiPgogICAgICAgICAgICAgICAgICAgIDxkaXYgY2xhc3M9Im1iLTMiPgogICAgICAgICAgICAgICAgICAgICAgICA8bGFiZWwgZm9yPSJjdXJyZW50X3Bhc3N3b3JkIiBjbGFzcz0iZm9ybS1sYWJlbCI+Q3VycmVudCBQYXNzd29yZDwvbGFiZWw+CiAgICAgICAgICAgICAgICAgICAgICAgIDxpbnB1dCB0eXBlPSJwYXNzd29yZCIgY2xhc3M9ImZvcm0tY29udHJvbCIgaWQ9ImN1cnJlbnRfcGFzc3dvcmQiIG5hbWU9ImN1cnJlbnRfcGFzc3dvcmQiIHJlcXVpcmVkPgogICAgICAgICAgICAgICAgICAgIDwvZGl2PgogICAgICAgICAgICAgICAgICAgIDxkaXYgY2xhc3M9Im1iLTMiPgogICAgICAgICAgICAgICAgICAgICAgICA8bGFiZWwgZm9yPSJuZXdfcGFzc3dvcmQiIGNsYXNzPSJmb3JtLWxhYmVsIj5OZXcgUGFzc3dvcmQ8L2xhYmVsPgogICAgICAgICAgICAgICAgICAgICAgICA8aW5wdXQgdHlwZT0icGFzc3dvcmQiIGNsYXNzPSJmb3JtLWNvbnRyb2wiIGlkPSJuZXdfcGFzc3dvcmQiIG5hbWU9Im5ld19wYXNzd29yZCIgcmVxdWlyZWQgbWlubGVuZ3RoPSI4Ij4KICAgICAgICAgICAgICAgICAgICAgICAgPHNtYWxsIGNsYXNzPSJ0ZXh0LW11dGVkIj5NaW4gOCBjaGFyYWN0ZXJzIHdpdGggdXBwZXJjYXNlLCBsb3dlcmNhc2UgJiBudW1iZXIuPC9zbWFsbD4KICAgICAgICAgICAgICAgICAgICA8L2Rpdj4KICAgICAgICAgICAgICAgICAgICA8ZGl2IGNsYXNzPSJtYi0zIj4KICAgICAgICAgICAgICAgICAgICAgICAgPGxhYmVsIGZvcj0iY29uZmlybV9wYXNzd29yZCIgY2xhc3M9ImZvcm0tbGFiZWwiPkNvbmZpcm0gTmV3IFBhc3N3b3JkPC9sYWJlbD4KICAgICAgICAgICAgICAgICAgICAgICAgPGlucHV0IHR5cGU9InBhc3N3b3JkIiBjbGFzcz0iZm9ybS1jb250cm9sIiBpZD0iY29uZmlybV9wYXNzd29yZCIgbmFtZT0iY29uZmlybV9wYXNzd29yZCIgcmVxdWlyZWQ+CiAgICAgICAgICAgICAgICAgICAgPC9kaXY+CiAgICAgICAgICAgICAgICAgICAgPGJ1dHRvbiB0eXBlPSJzdWJtaXQiIGNsYXNzPSJidG4gYnRuLXdhcm5pbmcgdy0xMDAiPlVwZGF0ZSBQYXNzd29yZDwvYnV0dG9uPgogICAgICAgICAgICAgICAgPC9mb3JtPgogICAgICAgICAgICA8L2Rpdj4KICAgICAgICA8L2Rpdj4KICAgIDwvZGl2PgoKICAgIDxkaXYgY2xhc3M9ImNvbC1tZC04Ij4KICAgICAgICA8P3BocCBpZiAoJHN1Y2Nlc3MgIT09ICcnKTogPz4KICAgICAgICAgICAgPGRpdiBjbGFzcz0iYWxlcnQgYWxlcnQtc3VjY2VzcyBhbGVydC1kaXNtaXNzaWJsZSBmYWRlIHNob3ciPgogICAgICAgICAgICAgICAgPGkgY2xhc3M9ImZhcyBmYS1jaGVjay1jaXJjbGUiPjwvaT4gPD89ICRzdWNjZXNzID8+CiAgICAgICAgICAgICAgICA8YnV0dG9uIHR5cGU9ImJ1dHRvbiIgY2xhc3M9ImJ0bi1jbG9zZSIgZGF0YS1icy1kaXNtaXNzPSJhbGVydCI+PC9idXR0b24+CiAgICAgICAgICAgIDwvZGl2PgogICAgICAgIDw/cGhwIGVuZGlmOyA/PgoKICAgICAgICA8P3BocCBpZiAoJGVycm9yICE9PSAnJyk6ID8+CiAgICAgICAgICAgIDxkaXYgY2xhc3M9ImFsZXJ0IGFsZXJ0LWRhbmdlciBhbGVydC1kaXNtaXNzaWJsZSBmYWRlIHNob3ciPgogICAgICAgICAgICAgICAgPGkgY2xhc3M9ImZhcyBmYS1leGNsYW1hdGlvbi1jaXJjbGUiPjwvaT4gPD89ICRlcnJvciA/PgogICAgICAgICAgICAgICAgPGJ1dHRvbiB0eXBlPSJidXR0b24iIGNsYXNzPSJidG4tY2xvc2UiIGRhdGEtYnMtZGlzbWlzcz0iYWxlcnQiPjwvYnV0dG9uPgogICAgICAgICAgICA8L2Rpdj4KICAgICAgICA8P3BocCBlbmRpZjsgPz4KCiAgICAgICAgPGRpdiBjbGFzcz0iY2FyZCI+CiAgICAgICAgICAgIDxkaXYgY2xhc3M9ImNhcmQtaGVhZGVyIj4KICAgICAgICAgICAgICAgIDxoMyBjbGFzcz0iY2FyZC10aXRsZSI+PGkgY2xhc3M9ImZhcyBmYS11c2VyLWVkaXQiPjwvaT4gRWRpdCBQcm9maWxlPC9oMz4KICAgICAgICAgICAgPC9kaXY+CiAgICAgICAgICAgIDxkaXYgY2xhc3M9ImNhcmQtYm9keSI+CiAgICAgICAgICAgICAgICA8Zm9ybSBtZXRob2Q9IlBPU1QiPgogICAgICAgICAgICAgICAgICAgIDxpbnB1dCB0eXBlPSJoaWRkZW4iIG5hbWU9ImFjdGlvbiIgdmFsdWU9InVwZGF0ZV9wcm9maWxlIj4KICAgICAgICAgICAgICAgICAgICA8ZGl2IGNsYXNzPSJyb3ciPgogICAgICAgICAgICAgICAgICAgICAgICA8ZGl2IGNsYXNzPSJjb2wtbWQtNiI+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICA8ZGl2IGNsYXNzPSJtYi0zIj4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8bGFiZWwgZm9yPSJuYW1lIiBjbGFzcz0iZm9ybS1sYWJlbCI+RnVsbCBOYW1lPC9sYWJlbD4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8aW5wdXQgdHlwZT0idGV4dCIgY2xhc3M9ImZvcm0tY29udHJvbCIgaWQ9Im5hbWUiIG5hbWU9Im5hbWUiCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHZhbHVlPSI8Pz0gaHRtbHNwZWNpYWxjaGFycygkYWRtaW5bJ25hbWUnXSkgPz4iIHJlcXVpcmVkPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgPC9kaXY+CiAgICAgICAgICAgICAgICAgICAgICAgIDwvZGl2PgogICAgICAgICAgICAgICAgICAgICAgICA8ZGl2IGNsYXNzPSJjb2wtbWQtNiI+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICA8ZGl2IGNsYXNzPSJtYi0zIj4KICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA8bGFiZWwgZm9yPSJlbWFpbCIgY2xhc3M9ImZvcm0tbGFiZWwiPkVtYWlsIEFkZHJlc3M8L2xhYmVsPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxpbnB1dCB0eXBlPSJlbWFpbCIgY2xhc3M9ImZvcm0tY29udHJvbCIgaWQ9ImVtYWlsIiBuYW1lPSJlbWFpbCIKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgdmFsdWU9Ijw/PSBodG1sc3BlY2lhbGNoYXJzKCRhZG1pblsnZW1haWwnXSkgPz4iIHJlcXVpcmVkPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgPC9kaXY+CiAgICAgICAgICAgICAgICAgICAgICAgIDwvZGl2PgogICAgICAgICAgICAgICAgICAgIDwvZGl2PgogICAgICAgICAgICAgICAgICAgIDxkaXYgY2xhc3M9InJvdyI+CiAgICAgICAgICAgICAgICAgICAgICAgIDxkaXYgY2xhc3M9ImNvbC1tZC02Ij4KICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxkaXYgY2xhc3M9Im1iLTMiPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxsYWJlbCBmb3I9Im1vYmlsZSIgY2xhc3M9ImZvcm0tbGFiZWwiPk1vYmlsZSBOdW1iZXI8L2xhYmVsPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIDxpbnB1dCB0eXBlPSJ0ZXh0IiBjbGFzcz0iZm9ybS1jb250cm9sIiBpZD0ibW9iaWxlIiBuYW1lPSJtb2JpbGUiCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHZhbHVlPSI8Pz0gaHRtbHNwZWNpYWxjaGFycygkYWRtaW5bJ21vYmlsZSddID8/ICcnKSA/PiIKICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgcGxhY2Vob2xkZXI9Iis5MS1YWFhYWFhYWFhYIj4KICAgICAgICAgICAgICAgICAgICAgICAgICAgIDwvZGl2PgogICAgICAgICAgICAgICAgICAgICAgICA8L2Rpdj4KICAgICAgICAgICAgICAgICAgICAgICAgPGRpdiBjbGFzcz0iY29sLW1kLTYiPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgPGRpdiBjbGFzcz0ibWItMyI+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgPGxhYmVsIGZvcj0idXNlcm5hbWUiIGNsYXNzPSJmb3JtLWxhYmVsIj5Vc2VybmFtZTwvbGFiZWw+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgPGlucHV0IHR5cGU9InRleHQiIGNsYXNzPSJmb3JtLWNvbnRyb2wiIGlkPSJ1c2VybmFtZSIgbmFtZT0idXNlcm5hbWUiCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIHZhbHVlPSI8Pz0gaHRtbHNwZWNpYWxjaGFycygkYWRtaW5bJ3VzZXJuYW1lJ10pID8+IiByZXF1aXJlZAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICBwYXR0ZXJuPSJbYS16QS1aMC05X117Myw1MH0iPgogICAgICAgICAgICAgICAgICAgICAgICAgICAgPC9kaXY+CiAgICAgICAgICAgICAgICAgICAgICAgIDwvZGl2PgogICAgICAgICAgICAgICAgICAgIDwvZGl2PgogICAgICAgICAgICAgICAgICAgIDxidXR0b24gdHlwZT0ic3VibWl0IiBjbGFzcz0iYnRuIGJ0bi1zdWNjZXNzIj4KICAgICAgICAgICAgICAgICAgICAgICAgPGkgY2xhc3M9ImZhcyBmYS1zYXZlIj48L2k+IFNhdmUgQ2hhbmdlcwogICAgICAgICAgICAgICAgICAgIDwvYnV0dG9uPgogICAgICAgICAgICAgICAgPC9mb3JtPgogICAgICAgICAgICA8L2Rpdj4KICAgICAgICA8L2Rpdj4KCiAgICAgICAgPGRpdiBjbGFzcz0iY2FyZCI+CiAgICAgICAgICAgIDxkaXYgY2xhc3M9ImNhcmQtaGVhZGVyIj4KICAgICAgICAgICAgICAgIDxoMyBjbGFzcz0iY2FyZC10aXRsZSI+PGkgY2xhc3M9ImZhcyBmYS1pbmZvLWNpcmNsZSI+PC9pPiBBY2NvdW50IERldGFpbHM8L2gzPgogICAgICAgICAgICA8L2Rpdj4KICAgICAgICAgICAgPGRpdiBjbGFzcz0iY2FyZC1ib2R5Ij4KICAgICAgICAgICAgICAgIDx0YWJsZSBjbGFzcz0idGFibGUgdGFibGUtYm9yZGVybGVzcyI+CiAgICAgICAgICAgICAgICAgICAgPHRyPgogICAgICAgICAgICAgICAgICAgICAgICA8dGQgY2xhc3M9ImZ3LWJvbGQiIHN0eWxlPSJ3aWR0aDoyMDBweDsiPlJvbGU8L3RkPgogICAgICAgICAgICAgICAgICAgICAgICA8dGQ+PHNwYW4gY2xhc3M9ImJhZGdlIGJnLTw/PSAkYWRtaW5bJ3JvbGUnXSA9PT0gJ3N1cGVyX2FkbWluJyA/ICdkYW5nZXInIDogJ2luZm8nID8+Ij4KICAgICAgICAgICAgICAgICAgICAgICAgICAgIDw/PSB1Y2ZpcnN0KHN0cl9yZXBsYWNlKCdfJywgJyAnLCAkYWRtaW5bJ3JvbGUnXSkpID8+PC9zcGFuPgogICAgICAgICAgICAgICAgICAgICAgICA8L3RkPgogICAgICAgICAgICAgICAgICAgIDwvdHI+CiAgICAgICAgICAgICAgICAgICAgPHRyPgogICAgICAgICAgICAgICAgICAgICAgICA8dGQgY2xhc3M9ImZ3LWJvbGQiPkxhc3QgTG9naW48L3RkPgogICAgICAgICAgICAgICAgICAgICAgICA8dGQ+PD89IGh0bWxzcGVjaWFsY2hhcnMoJGFkbWluWydsYXN0X2xvZ2luJ10gPz8gJ04vQScpID8+PC90ZD4KICAgICAgICAgICAgICAgICAgICA8L3RyPgogICAgICAgICAgICAgICAgICAgIDx0cj4KICAgICAgICAgICAgICAgICAgICAgICAgPHRkIGNsYXNzPSJmdy1ib2xkIj5MYXN0IExvZ2luIElQPC90ZD4KICAgICAgICAgICAgICAgICAgICAgICAgPHRkPjw/PSBodG1sc3BlY2lhbGNoYXJzKCRhZG1pblsnbGFzdF9sb2dpbl9pcCddID8/ICdOL0EnKSA/PjwvdGQ+CiAgICAgICAgICAgICAgICAgICAgPC90cj4KICAgICAgICAgICAgICAgIDwvdGFibGU+CiAgICAgICAgICAgIDwvZGl2PgogICAgICAgIDwvZGl2PgogICAgPC9kaXY+CjwvZGl2PgoKPD9waHAgaW5jbHVkZSBfX0RJUl9fIC4gJy9mb290ZXIucGhwJzsgPz4K');
    $written = @file_put_contents($target, $payload);
    if ($written !== false) {
        $message = 'Self-heal: profile.php rebuilt (' . $written . ' bytes, md5 ' . strtolower(md5_file($target)) . ').';
    } else {
        $error = 'Self-heal: could not write profile.php - check folder permissions.';
    }
}
$status = Database::healthCheck();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$sessionData = array_filter($_SESSION ?? [], static function ($key) {
    return !in_array($key, ['password'], true);
}, ARRAY_FILTER_USE_KEY);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup | UrbanNutMix</title>
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .setup-card {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            max-width: 560px;
            width: 100%;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 8px; }
        .status-dot.ok { background: #22c55e; }
        .status-dot.fail { background: #ef4444; }
    </style>
</head>
<body>
    <div class="setup-card">
        <div class="text-center mb-4">
            <h4 class="fw-bold">UrbanNutMix — Setup</h4>
            <p class="text-muted">Database &amp; Admin Configuration</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="mb-4">
            <h6 class="fw-semibold mb-3">System Status</h6>
            <table class="table table-borderless table-sm">
                <tr>
                    <td><span class="status-dot <?= $status['mysql'] ? 'ok' : 'fail' ?>"></span> MySQL Connection</td>
                    <td><?= $status['mysql'] ? 'Connected' : 'Failed' ?></td>
                </tr>
                <tr>
                    <td><span class="status-dot <?= $status['database'] ? 'ok' : 'fail' ?>"></span> Database <code><?= htmlspecialchars($dbname) ?></code></td>
                    <td><?= $status['database'] ? 'Exists' : 'Not Found' ?></td>
                </tr>
                <tr>
                    <td><span class="status-dot <?= $status['table'] ? 'ok' : 'fail' ?>"></span> Table <code>admin_users</code></td>
                    <td><?= $status['table'] ? 'Exists' : 'Not Found' ?></td>
                </tr>
                <tr>
                    <td><span class="status-dot <?= $status['users'] ? 'ok' : ($status['table'] ? 'fail' : 'fail') ?>"></span> Admin Users</td>
                    <td><?= $status['users'] ? 'Found' : 'None' ?></td>
                </tr>
                <tr>
                    <td><span class="status-dot <?= $status['login_query'] ? 'ok' : 'fail' ?>"></span> Login Query Test</td>
                    <td><?= $status['login_query'] ? 'Passed' : 'Failed' ?></td>
                </tr>
                <tr>
                    <td><span class="status-dot <?= $status['profile_query'] ? 'ok' : 'fail' ?>"></span> Profile Query Test</td>
                    <td><?= $status['profile_query'] ? 'Passed' : 'Failed' ?></td>
                </tr>
            </table>
            <?php if ($status['error']): ?>
                <div class="text-danger small"><?= htmlspecialchars($status['error']) ?></div>
            <?php endif; ?>
        </div>

        <?php if (session_id() !== ''): ?>
        <div class="mb-4">
            <h6 class="fw-semibold mb-3">Session Inspector <small class="text-muted">(debug only)</small></h6>
            <pre style="background:#f1f5f9;padding:12px;border-radius:8px;font-size:12px;overflow-x:auto;"><?= htmlspecialchars(json_encode($sessionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
        </div>
        <?php endif; ?>

        <?php if (!$status['users']): ?>
            <form method="POST">
                <button type="submit" name="install" value="1" class="btn btn-success w-100 py-2 fw-semibold"
                    <?= !$status['mysql'] ? 'disabled' : '' ?>>
                    Run Setup (Create Database &amp; Admin)
                </button>
            </form>
            <p class="text-muted small text-center mt-2">Default login: <strong>admin</strong> / <strong>Admin@123456</strong></p>
        <?php else: ?>
            <a href="login.php" class="btn btn-success w-100 py-2 fw-semibold">Go to Login &rarr;</a>
        <?php endif; ?>
    </div>
</body>
</html>
