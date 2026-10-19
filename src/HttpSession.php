<?php
declare(strict_types=1);

namespace Signed;

/** PHP-Session per Cookie. Wird sofort wieder geschlossen, damit parallele Anfragen nicht warten. */
final class HttpSession implements Session
{
    private const LIFETIME = 60 * 60 * 24 * 30;

    private ?int $labelId;

    public function __construct()
    {
        $this->open();
        $this->labelId = isset($_SESSION['label_id']) ? (int) $_SESSION['label_id'] : null;
        session_write_close();
    }

    public function labelId(): ?int
    {
        return $this->labelId;
    }

    public function setLabelId(?int $id): void
    {
        $this->open();
        session_regenerate_id(true);
        if ($id === null) {
            unset($_SESSION['label_id']);
        } else {
            $_SESSION['label_id'] = $id;
        }
        session_write_close();
        $this->labelId = $id;
    }

    private function open(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        session_name('signed');
        session_set_cookie_params([
            'lifetime' => self::LIFETIME,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.gc_maxlifetime', (string) self::LIFETIME);
        ini_set('session.use_strict_mode', '1');
        session_start();
    }
}
