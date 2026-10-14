<?php
require_once __DIR__ . '/../config/database.php';

class Auth {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance();
        if (session_status() === PHP_SESSION_NONE) {
            session_name('DOCUTRACK_SESSID');
            session_start();
        }
    }

    public function login(string $email, string $password): array {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        
        try {
            $lockStmt = $this->db->prepare("
                SELECT COUNT(*) FROM login_attempts 
                WHERE (email = ? OR ip_address = ?) 
                AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ");
            $lockStmt->execute([$email, $ip]);
            $failedCount = (int)$lockStmt->fetchColumn();

            if ($failedCount >= 5) {
                return [
                    'success' => false, 
                    'message' => 'Too many failed login attempts. For security, please wait 15 minutes before trying again.'
                ];
            }
        } catch (Throwable $e) {
            error_log('Login rate-limit check failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Login is temporarily unavailable. Please try again later.'];
        }

        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            
            try {
                $recordStmt = $this->db->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)");
                $recordStmt->execute([$email, $ip]);
                
                $this->db->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
            } catch (Throwable $e) {}

            usleep(300000); 

            $left = max(0, 4 - $failedCount); 
            $msg  = 'Invalid email or password.';
            if ($left > 0 && $left <= 2) {
                $msg .= " {$left} attempt" . ($left === 1 ? '' : 's') . ' left before a 15-minute lockout.';
            }
            return ['success' => false, 'message' => $msg];
        }

        
        try {
            $clearStmt = $this->db->prepare("DELETE FROM login_attempts WHERE email = ? OR ip_address = ?");
            $clearStmt->execute([$email, $ip]);
        } catch (Throwable $e) {}

        
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id']      = (int)$user['id'];
        $_SESSION['user_name']    = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_role']    = $user['role'];
        $_SESSION['user_dept']    = $user['department_id'];
        $_SESSION['logged_in_at']  = time();
        $_SESSION['last_activity'] = time();

        $this->logActivity((int)$user['id'], null, 'login', 'User logged in');

        return ['success' => true, 'user' => $user];
    }

    public function logout(): void {
        if (!empty($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
            try {
                $this->logActivity((int)$_SESSION['user_id'], null, 'logout', 'User logged out');
            } catch (Throwable $e) {
                
            }
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
    }

    
    public function isLoggedIn(bool $touch = true): bool {
        if (empty($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
            return false;
        }
        $now  = time();
        $last = (int)($_SESSION['last_activity'] ?? $_SESSION['logged_in_at'] ?? 0);
        if (empty($_SESSION['logged_in_at'])
            || ($now - (int)$_SESSION['logged_in_at'] > SESSION_ABSOLUTE_TIMEOUT)
            || ($now - $last > SESSION_TIMEOUT)) {
            $this->logout();
            return false;
        }
        if ($touch) $_SESSION['last_activity'] = $now;
        return true;
    }

    
    public function secondsLeft(): int {
        if (empty($_SESSION['user_id'])) return 0;
        $now  = time();
        $last = (int)($_SESSION['last_activity'] ?? $_SESSION['logged_in_at'] ?? $now);
        $idleLeft = SESSION_TIMEOUT - ($now - $last);
        $absLeft  = SESSION_ABSOLUTE_TIMEOUT - ($now - (int)($_SESSION['logged_in_at'] ?? $now));
        return max(0, min($idleLeft, $absLeft));
    }

    public function requireLogin(): void {
        if (!$this->isLoggedIn()) {
            header('Location: ' . APP_URL . '/login.php');
            exit;
        }
    }

    public function requireRole(array $roles): void {
        $this->requireLogin();
        $userRole = $_SESSION['user_role'] ?? '';
        if (!in_array($userRole, $roles, true)) {
            header('Location: ' . APP_URL . '/dashboard.php?error=unauthorized');
            exit;
        }
    }

    public function currentUser(): ?array {
        if (!$this->isLoggedIn()) return null;
        try {
            $stmt = $this->db->prepare("SELECT u.*, d.name AS department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
            $stmt->execute([(int)$_SESSION['user_id']]);
            return $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function logActivity($userId, ?int $documentId, string $action, string $description): void {
        if (empty($userId) || !is_numeric($userId)) return;
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $stmt = $this->db->prepare("INSERT INTO activity_logs (user_id, document_id, action, description, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([(int)$userId, $documentId, $action, $description, $ip]);
        } catch (Throwable $e) {
            
        }
    }
}
