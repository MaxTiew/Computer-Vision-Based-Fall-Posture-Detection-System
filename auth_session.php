<?php

const GOODLIFE_REMEMBER_COOKIE = 'goodlife_remember';
const GOODLIFE_REMEMBER_LIFETIME = 2592000;

function goodlife_cookie_is_secure(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function start_goodlife_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $remembered_browser = !empty($_COOKIE[GOODLIFE_REMEMBER_COOKIE]);
    session_set_cookie_params([
        'lifetime' => $remembered_browser ? GOODLIFE_REMEMBER_LIFETIME : 0,
        'path' => '/',
        'secure' => goodlife_cookie_is_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', (string) GOODLIFE_REMEMBER_LIFETIME);
    session_start();
}

function set_goodlife_remember_cookie(bool $enabled): void
{
    $options = [
        'expires' => $enabled ? time() + GOODLIFE_REMEMBER_LIFETIME : time() - 3600,
        'path' => '/',
        'secure' => goodlife_cookie_is_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    setcookie(GOODLIFE_REMEMBER_COOKIE, $enabled ? '1' : '', $options);
    if ($enabled) {
        $_COOKIE[GOODLIFE_REMEMBER_COOKIE] = '1';
    } else {
        unset($_COOKIE[GOODLIFE_REMEMBER_COOKIE]);
    }
}

function refresh_goodlife_session_cookie(bool $remember): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    setcookie(session_name(), session_id(), [
        'expires' => $remember ? time() + GOODLIFE_REMEMBER_LIFETIME : 0,
        'path' => '/',
        'secure' => goodlife_cookie_is_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function login_caregiver_session(string $caregiver_id, string $caregiver_name, bool $remember): void
{
    start_goodlife_session();
    session_regenerate_id(true);

    unset($_SESSION['password_reset_email']);
    $_SESSION['caregiverID'] = $caregiver_id;
    $_SESSION['caregiverName'] = $caregiver_name;
    $_SESSION['rememberMe'] = $remember;

    if ($remember) {
        unset($_SESSION['tabAuthToken']);
        set_goodlife_remember_cookie(true);
    } else {
        $_SESSION['tabAuthToken'] = bin2hex(random_bytes(32));
        set_goodlife_remember_cookie(false);
    }

    refresh_goodlife_session_cookie($remember);
}

function logout_caregiver_session(bool $redirect = true): void
{
    start_goodlife_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => goodlife_cookie_is_secure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    set_goodlife_remember_cookie(false);
    session_destroy();

    if ($redirect) {
        header('Location: login.php');
        exit();
    }
}

function current_tab_auth_token(): string
{
    return (!empty($_SESSION['rememberMe'])) ? '' : (string) ($_SESSION['tabAuthToken'] ?? '');
}

function current_request_tab_auth_token(): string
{
    if (isset($_POST['tab_auth'])) {
        return trim((string) $_POST['tab_auth']);
    }

    if (isset($_GET['tab_auth'])) {
        return trim((string) $_GET['tab_auth']);
    }

    if (isset($_SERVER['HTTP_X_TAB_AUTH'])) {
        return trim((string) $_SERVER['HTTP_X_TAB_AUTH']);
    }

    return '';
}

function build_internal_url_with_tab_auth(string $url): string
{
    $token = current_tab_auth_token();
    if ($token === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)) {
        return $url;
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return $url;
    }

    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query['tab_auth'] = $token;

    $rebuilt = '';
    if (isset($parts['scheme'])) {
        $rebuilt .= $parts['scheme'] . '://';
    }
    if (isset($parts['user'])) {
        $rebuilt .= $parts['user'];
        if (isset($parts['pass'])) {
            $rebuilt .= ':' . $parts['pass'];
        }
        $rebuilt .= '@';
    }
    if (isset($parts['host'])) {
        $rebuilt .= $parts['host'];
    }
    if (isset($parts['port'])) {
        $rebuilt .= ':' . $parts['port'];
    }
    $rebuilt .= $parts['path'] ?? '';
    $rebuilt .= '?' . http_build_query($query);
    if (isset($parts['fragment'])) {
        $rebuilt .= '#' . $parts['fragment'];
    }

    return $rebuilt;
}

function auth_redirect(string $url): void
{
    header('Location: ' . build_internal_url_with_tab_auth($url));
    exit();
}

function require_caregiver_auth(bool $json = false): void
{
    start_goodlife_session();

    if (!isset($_SESSION['caregiverID'])) {
        if ($json) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
            exit();
        }

        header('Location: login.php');
        exit();
    }

    $expected_token = current_tab_auth_token();
    if ($expected_token === '') {
        return;
    }

    $provided_token = current_request_tab_auth_token();
    if ($provided_token !== '' && hash_equals($expected_token, $provided_token)) {
        return;
    }

    logout_caregiver_session(false);

    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
        exit();
    }

    header('Location: login.php?session_expired=1');
    exit();
}

function auth_hidden_input_html(): string
{
    $token = current_tab_auth_token();
    if ($token === '') {
        return '';
    }

    return '<input type="hidden" name="tab_auth" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
}

function render_auth_client_script(): void
{
    $token = current_tab_auth_token();
    if ($token === '') {
        return;
    }

    $encoded_token = json_encode($token);
    echo <<<HTML
<script>
(function() {
    const tabAuthToken = {$encoded_token};
    if (!tabAuthToken) {
        return;
    }

    function isSameOriginUrl(url) {
        try {
            return new URL(url, window.location.href).origin === window.location.origin;
        } catch (error) {
            return false;
        }
    }

    function appendGoodLifeAuthUrl(url) {
        if (!url || typeof url !== 'string') {
            return url;
        }

        const trimmed = url.trim();
        if (trimmed === '' || trimmed.startsWith('#') || trimmed.startsWith('javascript:') || trimmed.startsWith('mailto:') || trimmed.startsWith('tel:')) {
            return url;
        }

        if (!isSameOriginUrl(trimmed)) {
            return url;
        }

        const parsed = new URL(trimmed, window.location.href);
        parsed.searchParams.set('tab_auth', tabAuthToken);
        return parsed.toString();
    }

    function updateLink(link) {
        const href = link.getAttribute('href');
        if (!href) {
            return;
        }

        link.href = appendGoodLifeAuthUrl(href);
    }

    function updateForm(form) {
        let hiddenInput = form.querySelector('input[name="tab_auth"]');
        if (!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'tab_auth';
            form.appendChild(hiddenInput);
        }
        hiddenInput.value = tabAuthToken;
    }

    function updateAllLinksAndForms() {
        document.querySelectorAll('a[href]').forEach(updateLink);
        document.querySelectorAll('form').forEach(updateForm);
    }

    const originalFetch = window.fetch ? window.fetch.bind(window) : null;
    if (originalFetch) {
        window.fetch = function(resource, init) {
            let requestUrl = '';
            if (resource instanceof Request) {
                requestUrl = resource.url;
            } else if (typeof resource === 'string') {
                requestUrl = resource;
            } else if (resource && typeof resource.url === 'string') {
                requestUrl = resource.url;
            }

            if (!requestUrl || !isSameOriginUrl(requestUrl)) {
                return originalFetch(resource, init);
            }

            const finalUrl = appendGoodLifeAuthUrl(requestUrl);
            const headers = new Headers();

            if (resource instanceof Request) {
                resource.headers.forEach((value, key) => headers.set(key, value));
            }
            if (init && init.headers) {
                new Headers(init.headers).forEach((value, key) => headers.set(key, value));
            }

            headers.set('X-Tab-Auth', tabAuthToken);

            if (resource instanceof Request) {
                const requestInit = {
                    method: init && init.method ? init.method : resource.method,
                    headers,
                    body: init && Object.prototype.hasOwnProperty.call(init, 'body') ? init.body : undefined,
                    cache: init && init.cache ? init.cache : resource.cache,
                    credentials: init && init.credentials ? init.credentials : resource.credentials,
                    integrity: init && init.integrity ? init.integrity : resource.integrity,
                    keepalive: init && init.keepalive ? init.keepalive : resource.keepalive,
                    mode: init && init.mode ? init.mode : resource.mode,
                    redirect: init && init.redirect ? init.redirect : resource.redirect,
                    referrer: init && init.referrer ? init.referrer : resource.referrer,
                    referrerPolicy: init && init.referrerPolicy ? init.referrerPolicy : resource.referrerPolicy,
                    signal: init && init.signal ? init.signal : resource.signal
                };

                return originalFetch(new Request(finalUrl, requestInit));
            }

            return originalFetch(finalUrl, Object.assign({}, init || {}, { headers }));
        };
    }

    window.GOODLIFE_TAB_AUTH = tabAuthToken;
    window.appendGoodLifeAuthUrl = appendGoodLifeAuthUrl;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateAllLinksAndForms);
    } else {
        updateAllLinksAndForms();
    }

    document.addEventListener('click', function(event) {
        const link = event.target.closest('a[href]');
        if (link) {
            updateLink(link);
        }
    }, true);

    document.addEventListener('submit', function(event) {
        const form = event.target;
        if (form instanceof HTMLFormElement) {
            updateForm(form);
        }
    }, true);
})();
</script>
HTML;
}
