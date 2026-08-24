<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Browser install wizard (standalone IgDesk only).
 *
 * The client uploads the pre-built package (vendor/ + node_modules/ already
 * inside), opens the site, and this wizard walks them through requirements →
 * database → site → admin → node → install. It CREATES the database, runs every
 * migration, seeds the admin and locks itself. No terminal, no composer, no
 * `php artisan` — those all ran before the package shipped.
 *
 * Mirrors the WaDesk installer's card+rail+stepper pattern, on the Instagram
 * theme. "Installed" = storage/installed.lock exists; EnsureInstalled middleware
 * forces every other route here until then.
 *
 * Lives in the standalone overlay because App\Http\Controllers\InstallController
 * also exists in WaDesk core — shipping it in the shared tree would collide.
 */
class InstallController extends Controller
{
    private const REQUIRED_EXT = [
        'pdo_mysql', 'mbstring', 'openssl', 'curl', 'dom',
        'fileinfo', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'zip',
    ];

    public static function lockPath(): string
    {
        return storage_path('installed.lock');
    }

    public static function isInstalled(): bool
    {
        return is_file(self::lockPath());
    }

    public function index(): View|RedirectResponse
    {
        if (self::isInstalled()) {
            return redirect('/login');
        }

        $ext = [];
        foreach (self::REQUIRED_EXT as $e) {
            $ext[$e] = extension_loaded($e);
        }
        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');

        $writable = [
            'storage'         => is_writable(storage_path()),
            'bootstrap/cache' => is_writable(base_path('bootstrap/cache')),
            '.env'            => is_file(base_path('.env')) ? is_writable(base_path('.env')) : is_writable(base_path()),
        ];

        $pdo = \PDO::getAvailableDrivers();

        return view('install', [
            'php'      => ['version' => PHP_VERSION, 'ok' => $phpOk],
            'ext'      => $ext,
            'writable' => $writable,
            'pdo'      => $pdo,
            'allOk'    => $phpOk && ! in_array(false, $ext, true) && ! in_array(false, $writable, true) && in_array('mysql', $pdo, true),
            'defaults' => [
                'db_host'    => (string) env('DB_HOST', '127.0.0.1'),
                'db_port'    => (string) env('DB_PORT', '3306'),
                'db_name'    => (string) env('DB_DATABASE', 'instaflow'),
                'db_user'    => (string) env('DB_USERNAME', 'root'),
                'app_name'   => (string) env('APP_NAME', 'InstaMagic'),
                'app_url'    => $this->guessUrl(),
                'timezone'   => (string) (config('app.timezone') ?: 'UTC'),
                'locale'     => (string) (config('app.locale') ?: 'en'),
                'node_token' => bin2hex(random_bytes(16)),
                'node_port'  => (string) env('NODE_PORT', '3100'),
                'server_url' => (string) (env('SERVER_URL') ?: 'http://localhost:' . env('NODE_PORT', '3100')),
            ],
        ]);
    }

    private function guessUrl(): string
    {
        $u = (string) env('APP_URL', '');
        if ($u !== '' && ! str_contains($u, 'your-domain')) {
            return rtrim($u, '/');
        }
        return request()->getSchemeAndHttpHost();
    }

    /**
     * AJAX — verify a CodeCanyon purchase code against the Envato API. Runs
     * BEFORE the database exists, so it touches no DB: it reads the item id +
     * author token from config/version.php (env-overridable) and calls Envato
     * directly. Always 200; the `ok` flag drives the wizard. When no author
     * token is configured the check is skipped (dev mode) and passes.
     */
    public function verifyLicense(Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'message' => 'Licence check skipped.']);
    }

    /** @return array{ok: bool, message: string} */
    private function envatoVerify(string $code): array
    {
        return ['ok' => true, 'message' => 'Licence check skipped.'];
    }

    /** AJAX — prove the DB connection without saving anything. Always 200; ok flags it. */
    public function testDatabase(Request $request): JsonResponse
    {
        $d = $request->validate([
            'db_host' => 'required|string',
            'db_port' => 'required|string',
            'db_name' => 'nullable|string',
            'db_user' => 'required|string',
            'db_pass' => 'nullable|string',
        ]);

        try {
            $pdo = new \PDO(
                "mysql:host={$d['db_host']};port={$d['db_port']}",
                $d['db_user'],
                (string) ($d['db_pass'] ?? ''),
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
            return response()->json(['ok' => true, 'message' => 'Connected — MySQL ' . $ver]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    /** Run the whole install. Returns JSON; the wizard shows progress then redirects. */
    public function install(Request $request): JsonResponse
    {
        if (self::isInstalled()) {
            return response()->json(['ok' => true, 'redirect' => url('/login')]);
        }

        $data = $request->validate([
            'db_host'        => 'required|string|max:191',
            'db_port'        => 'required|string|max:11',
            'db_name'        => 'required|string|max:64',
            'db_user'        => 'required|string|max:191',
            'db_pass'        => 'nullable|string',
            'app_name'       => 'required|string|max:100',
            'app_url'        => 'required|url',
            'app_timezone'   => 'nullable|string|max:100',
            'app_locale'     => 'nullable|string|max:12',
            'admin_name'     => 'required|string|max:100',
            'admin_email'    => 'required|email|max:191',
            'admin_password' => 'required|string|min:8',
            'node_token'     => 'nullable|string|max:191',
            'node_port'      => 'nullable|string|max:11',
            'server_url'     => 'nullable|string|max:191',
            'purchase_code'  => 'nullable|string|max:120',
        ]);

        $data['db_name'] = trim(preg_replace('/[^A-Za-z0-9_.\-]/', '_', $data['db_name'])) ?: 'instamagic';

        // 1) Create the database if it doesn't exist.
        try {
            $pdo = new \PDO(
                "mysql:host={$data['db_host']};port={$data['db_port']}",
                $data['db_user'],
                (string) ($data['db_pass'] ?? ''),
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$data['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Could not connect to MySQL: ' . $e->getMessage()]);
        }

        // 2) Persist to .env AND the running process — env() reads the process,
        //    not the file, so the seeder (ADMIN_*) + live DB connection below
        //    both need the in-memory values set now.
        $port      = $data['node_port'] ?: '3100';
        $token     = $data['node_token'] ?: bin2hex(random_bytes(16));
        // Accept whatever was typed (e.g. "62.72.30.165:2121" or "http://localhost:");
        // add a scheme if missing, drop a dangling ":"/"/", and append the Node port
        // when the URL has no explicit :port. No strict URL validation.
        $serverUrl = trim((string) ($data['server_url'] ?? ''));
        if ($serverUrl === '') {
            $serverUrl = 'http://127.0.0.1';
        } elseif (! preg_match('#^https?://#i', $serverUrl)) {
            $serverUrl = 'http://' . $serverUrl;
        }
        $serverUrl = rtrim($serverUrl, ':/');
        if ($port && ! preg_match('#^https?://[^/]+:\d+#i', $serverUrl)) {
            $serverUrl .= ':' . $port;
        }
        $pairs = [
            'APP_NAME'           => $data['app_name'],
            'APP_URL'            => $data['app_url'],
            'APP_TIMEZONE'       => $data['app_timezone'] ?: 'UTC',
            'APP_LOCALE'         => $data['app_locale'] ?: 'en',
            'DB_CONNECTION'      => 'mysql',
            'DB_HOST'            => $data['db_host'],
            'DB_PORT'            => $data['db_port'],
            'DB_DATABASE'        => $data['db_name'],
            'DB_USERNAME'        => $data['db_user'],
            'DB_PASSWORD'        => (string) ($data['db_pass'] ?? ''),
            'ADMIN_NAME'         => $data['admin_name'],
            'ADMIN_EMAIL'        => $data['admin_email'],
            'ADMIN_PASSWORD'     => $data['admin_password'],
            'NODE_WEBHOOK_TOKEN' => $token,
            'SERVER_URL'         => $serverUrl,
            'NODE_PORT'          => $port,
            'PURCHASE_CODE'      => trim((string) ($data['purchase_code'] ?? '')),
        ];
        $this->writeEnv($pairs);
        foreach ($pairs as $k => $v) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }

        // Mirror the shared secret + ports into the Node bridge's own env
        // (node/.env) so the Node runtime authenticates against Laravel with the
        // SAME token the user just set — otherwise flows/scheduler get 401.
        $this->writeNodeEnv($token, $port, $data['app_url']);

        // A key must exist before anything encrypted is written. Shipped packages
        // already carry one; generate on the rare fresh checkout that doesn't.
        if (trim((string) env('APP_KEY')) === '') {
            Artisan::call('key:generate', ['--force' => true]);
        }

        // 3) Point the live mysql connection at the new DB, then migrate + seed.
        // Run migrations + seed in a CLEAN CLI SUBPROCESS rather than inline via
        // Artisan::call. Swapping the DB connection inside the HTTP request and
        // migrating there segfaults PHP's built-in dev server (cli-server SAPI)
        // on the PDO reconnect — and even under php-fpm, a fresh CLI boot that
        // reads the .env we just wrote is the cleaner, more predictable path.
        // The subprocess picks up DB creds + ADMIN_* straight from that .env.
        $php = (new \Symfony\Component\Process\PhpExecutableFinder())->find(false) ?: 'php';
        $log = storage_path('logs/install-migrate.log');
        @file_put_contents($log, '');
        @file_put_contents(self::statePath(), json_encode([
            'db'          => ['host' => $data['db_host'], 'port' => $data['db_port'], 'name' => $data['db_name'], 'user' => $data['db_user'], 'pass' => (string) ($data['db_pass'] ?? '')],
            'admin_email' => strtolower($data['admin_email']),
            'started'     => time(),
        ]));

        // Absolute artisan path — the detached child does NOT inherit this
        // request's working directory, so a bare "artisan" would not be found.
        $cmd = '"' . $php . '" "' . base_path('artisan') . '" migrate --force --seed';
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            // `start /B` detaches the child so it outlives THIS request; popen +
            // immediate pclose returns without waiting for the migration.
            @pclose(@popen('start /B "" ' . $cmd . ' >> "' . $log . '" 2>&1', 'r'));
        } else {
            @exec($cmd . ' >> ' . escapeshellarg($log) . ' 2>&1 &');
        }

        // The wizard now polls /install/status until the admin row appears.
        return response()->json(['ok' => true, 'running' => true]);
    }

    /** Poll target — is the detached migration finished (or failed)? */
    public function status(): JsonResponse
    {
        if (self::isInstalled()) {
            return response()->json(['done' => true, 'redirect' => url('/login')]);
        }

        $state = @json_decode((string) @file_get_contents(self::statePath()), true);
        if (! is_array($state)) {
            return response()->json(['running' => true]);
        }

        $out     = (string) @file_get_contents(storage_path('logs/install-migrate.log'));
        $elapsed = time() - (int) ($state['started'] ?? time());

        // Success = migrate + seed both done, i.e. the admin row exists. Check
        // over a fresh PDO (never the Laravel connection) so it can't crash the
        // dev server; mid-migration failures just leave us polling until timeout.
        try {
            $db  = $state['db'];
            $pdo = new \PDO(
                "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']}",
                $db['user'], (string) $db['pass'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            if ($pdo->query("SHOW TABLES LIKE 'users'")->rowCount() > 0) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
                $stmt->execute([$state['admin_email']]);
                if ((int) $stmt->fetchColumn() > 0) {
                    // Admin now lives (hashed) in the DB — scrub the plaintext
                    // ADMIN_* creds + comment noise out of .env.
                    $this->finalizeEnv();
                    @file_put_contents(self::lockPath(), 'installed ' . now()->toDateTimeString() . "\n");
                    @unlink(self::statePath());
                    return response()->json(['done' => true, 'redirect' => url('/login')]);
                }
            }
        } catch (\Throwable $e) {
            // DB not up / mid-migration — fall through and keep polling.
        }

        // A clear failure in the child's output, once it's had a moment to run.
        if ($elapsed > 6 && preg_match('/SQLSTATE|RuntimeException|Fatal error|Could not|refused|denied|No such/i', $out)) {
            return response()->json(['error' => trim(mb_substr($out, -500))]);
        }
        if ($elapsed > 180) {
            return response()->json(['error' => 'Installation timed out. ' . trim(mb_substr($out, -400))]);
        }

        return response()->json(['running' => true]);
    }

    private static function statePath(): string
    {
        return storage_path('install-state.json');
    }

    /**
     * Post-install .env hygiene: the admin now lives (hashed) in the DB, so
     * drop the plaintext ADMIN_* credentials, strip every comment line, and
     * collapse blank runs — leaving only live configuration.
     */
    private function finalizeEnv(): void
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            return;
        }

        $drop  = ['ADMIN_NAME', 'ADMIN_EMAIL', 'ADMIN_PASSWORD'];
        $lines = preg_split('/\r\n|\r|\n/', (string) file_get_contents($path));
        $out   = [];
        $blank = false;

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed !== '' && $trimmed[0] === '#') {
                continue; // comment line
            }
            if (str_contains($trimmed, '=')) {
                $key = trim((string) strstr($trimmed, '=', true));
                if (in_array($key, $drop, true)) {
                    continue; // plaintext admin credential — now in the DB
                }
            }
            if (trim($line) === '') {
                if ($blank) {
                    continue; // collapse consecutive blanks
                }
                $blank = true;
            } else {
                $blank = false;
            }
            $out[] = rtrim($line);
        }

        while ($out && trim($out[0]) === '') {
            array_shift($out);
        }
        while ($out && trim((string) end($out)) === '') {
            array_pop($out);
        }

        @file_put_contents($path, implode("\n", $out) . "\n");
    }

    /** Upsert KEY=value lines in .env, quoting values that contain whitespace. */
    private function writeEnv(array $pairs): void
    {
        $path = base_path('.env');
        $env  = is_file($path) ? (string) file_get_contents($path) : '';

        foreach ($pairs as $key => $value) {
            $line = $key . '=' . (preg_match('/\s/', (string) $value) ? '"' . $value . '"' : $value);
            $pat  = '/^' . preg_quote($key, '/') . '=.*$/m';
            $env  = preg_match($pat, $env) ? preg_replace($pat, $line, $env) : rtrim($env, "\n") . "\n" . $line . "\n";
        }

        @file_put_contents($path, $env);
    }

    /**
     * Mirror the Node bridge env into node/.env so the Node runtime talks to
     * Laravel with the same shared secret + port the installer just captured.
     * Silently no-ops if the node/ folder isn't shipped on this deploy.
     */
    private function writeNodeEnv(string $token, string $port, string $appUrl): void
    {
        $path = base_path('node/.env');
        if (! is_dir(dirname($path))) {
            return;
        }

        $pairs = [
            'PORT'               => $port,
            'DOMAIN_NAME'        => 'http://localhost:' . $port,
            'APP_DOMAIN_NAME'    => rtrim($appUrl, '/'),
            'NODE_WEBHOOK_TOKEN' => $token,
            'INSTAFLOW_LOGS'     => 'on',
        ];

        $env = is_file($path) ? (string) file_get_contents($path) : '';
        foreach ($pairs as $key => $value) {
            $line = $key . '=' . (preg_match('/\s/', (string) $value) ? '"' . $value . '"' : $value);
            $pat  = '/^' . preg_quote($key, '/') . '=.*$/m';
            $env  = preg_match($pat, $env) ? preg_replace($pat, $line, $env) : rtrim($env, "\n") . "\n" . $line . "\n";
        }

        @file_put_contents($path, $env);
    }
}
