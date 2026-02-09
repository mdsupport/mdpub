<?php
/**
 * Core functionality to encapsulate $_SESSION.
 *
 * Copyright (C) 2015-2022 MD Support <mdsupport@users.sourceforge.net>
 *
 * @package   Mdpub
 * @author    MD Support <mdsupport@users.sourceforge.net>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */
namespace Mdsupport\Mdpub\DevObj;

class DevSession
{
    private array $cookieInit = [
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    // Prevent warnings
    private array $sessionInit = [
        '_flash' => [],
        'user' => [],
    ];
    protected bool $started = false;
    
    /**
     * Constructor by default starts the session.
     * @param array $cookieParams
     * @param array $opts - If 'start' => false, session is not started.
     */
    public function __construct(array $cookieParams = [], array $opts = []) {
        if ($opts['start'] ?? true) {
            $this->start($cookieParams);
        }
    }

    public function start(array $cookieParams = []): self
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return $this;
        }

        $cookieParams = array_merge(
            $this->cookieInit,
            [
                'secure'   => isset($_SERVER['HTTPS']),
            ],
            $cookieParams
        );
        session_set_cookie_params($cookieParams);

        session_start();
        foreach ($this->sessionInit as $key => $value) {
            $_SESSION[$key] = $value;
        }
        $this->started = true;
        return $this;
    }

    public function end(): void
    {
        if (!$this->started) {
            return;
        }

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();
        $this->started = false;
    }

    public function set(string $key, mixed $value): self
    {
        $this->ensureStarted();
        if (is_array($key)) {
            // Bulk set: merge array into session
            $_SESSION = array_replace_recursive($_SESSION, $key);
        } else {
            // Single set
            $_SESSION[$key] = $value;
        }
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        $this->ensureStarted();
        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): self
    {
        return $this->unset($key);
    }

    /* Explicit unset method */
    public function unset(string $key): self
    {
        $this->ensureStarted();
        unset($_SESSION[$key]);
        return $this;
    }

    public function regenerate(bool $deleteOld = true): self
    {
        $this->ensureStarted();
        session_regenerate_id($deleteOld);
        return $this;
    }

    protected function ensureStarted(): self
    {
        if (!$this->started) {
            $this->start();
        }
        return $this;
    }

    /* Magic accessors */

    public function __get(string $key): mixed
    {
        $this->ensureStarted();
        return $_SESSION[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        $this->ensureStarted();
        return isset($_SESSION[$key]);
    }

    public function __unset(string $key): void
    {
        $this->unset($key);
    }
    
    // Utility methods when a value should be used only once
    public function setFlash(string $key, mixed $value): self
    {
        $this->ensureStarted();
        $_SESSION['_flash'][$key] = $value;
    }
    
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
}
