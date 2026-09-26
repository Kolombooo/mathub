<?php

final class Auth
{
    public static function ensureUserExists(): void
    {
        if (!is_dir(Config::dataDir())) {
            mkdir(Config::dataDir(), 0775, true);
        }
        if (!is_file(Config::usersFile())) {
            // Default credentials; teacher should change the password after first login.
            $default = [
                'username' => 'teacher',
                'password_hash' => password_hash('changeme', PASSWORD_DEFAULT),
            ];
            file_put_contents(Config::usersFile(), json_encode($default, JSON_PRETTY_PRINT));
        }
    }

    private static function loadUser(): array
    {
        self::ensureUserExists();
        return json_decode((string)file_get_contents(Config::usersFile()), true) ?: [];
    }

    public static function attempt(string $username, string $password): bool
    {
        $user = self::loadUser();
        if (!hash_equals($user['username'] ?? '', $username)) {
            return false;
        }
        if (!password_verify($password, $user['password_hash'] ?? '')) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $user['username'];
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['authenticated']);
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: /admin/login');
            exit;
        }
    }

    public static function changePassword(string $currentPassword, string $newPassword): bool
    {
        $user = self::loadUser();
        if (!password_verify($currentPassword, $user['password_hash'] ?? '')) {
            return false;
        }
        $user['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        file_put_contents(Config::usersFile(), json_encode($user, JSON_PRETTY_PRINT));
        return true;
    }
}
