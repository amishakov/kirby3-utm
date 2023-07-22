<?php

declare(strict_types=1);

namespace Bnomei;

use DeviceDetector\DeviceDetector;
use Exception;
use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Kirby\Database\Database;
use Kirby\Filesystem\F;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;

final class Utm
{
    /** @var Database */
    private $_database;

    /** @var array $options */
    private $options;

    public function __construct(array $options = [])
    {
        $defaults = [
            'debug' => option('debug'),
            'enabled' => option('bnomei.utm.enabled'),
            'file' => option('bnomei.utm.sqlite.file'),
            'ip' => null, // INTERNAL: only used in testing via $options overwrite
            'ipstack_access_key' => option('bnomei.utm.ipstack.access_key'),
            'ipstack_https' => option('bnomei.utm.ipstack.https') ? 'https' : 'http',
            'ipstack_expire' => option('bnomei.utm.ipstack.expire'),
            'stats_range' => option('bnomei.utm.stats.range'),
            'ratelimit_enabled' => option('bnomei.utm.ratelimit.enabled'),
            'ratelimit_expire' => option('bnomei.utm.ratelimit.duration'),
            'ratelimit_trials' => option('bnomei.utm.ratelimit.trials'),
        ];
        $this->options = array_merge($defaults, $options);

        foreach ($this->options as $key => $call) {
            if (!is_string($call) && is_callable($call) && in_array($key, ['ip', 'ipstack_access_key', 'enabled', 'file'])) {
                $this->options[$key] = $call();
            }
        }

        if ($this->option('debug')) {
            try {
                kirby()->cache('bnomei.utm')->flush();
            } catch (Exception $e) {
                //
            }
        }

        // db is lazy loaded on first call
    }

    /**
     * @param string|null $key
     * @return array|mixed
     */
    public function option(?string $key = null)
    {
        if ($key) {
            return A::get($this->options, $key);
        }
        return $this->options;
    }

    public function databaseFile(): string
    {
        return $this->option('file');
    }

    public function database(): Database
    {
        // lazy load the db as late, so its not loaded if disabled
        if (!$this->_database) {
            $target = $this->databaseFile();
            if (!F::exists($target)) {
                $db = new \SQLite3($target);
                $db->exec("CREATE TABLE IF NOT EXISTS utm (ID INTEGER PRIMARY KEY AUTOINCREMENT, page_id TEXT NOT NULL, utm_source TEXT, utm_medium TEXT, utm_campaign TEXT, utm_term TEXT, utm_content TEXT, visited_at DATETIME DEFAULT CURRENT_TIMESTAMP, iphash TEXT, country_name TEXT, city TEXT, user_agent TEXT)");
                $db->close();
            }

            $this->_database = new Database([
                'type' => 'sqlite',
                'database' => $target,
            ]);
        }
        return $this->_database;
    }

    public function track(string $id, array $params): bool
    {
        if ($this->option('enabled') !== true) {
            return false;
        }

        $useragent = A::get($_SERVER, "HTTP_USER_AGENT", '');
        $device = new DeviceDetector($useragent);
        $device->discardBotInformation();
        $device->parse();
        if ($device->isBot() || (new CrawlerDetect())->isCrawler($useragent)) {
            return false;
        }

        $ip = $this->option('ip') ?? kirby()->visitor()->ip();
        $iphash = sha1(__DIR__ . $ip);

        // check rate limit
        if ($this->ratelimit($iphash) === false) {
            return false;
        }

        $params = $this->sanitize($params);

        if (count($params) === 0) {
            return false; // no UTM params at all
        }

        $ipdata = $this->ipstack($ip, $iphash);
        $generated = [
            'visited_at' => time(),
            'iphash' => $iphash,
            'country' => A::get($ipdata, 'country_name', ''),
            'city' => A::get($ipdata, 'city', ''),
            'useragent' => $this->useragent(),
        ];

        // allow generated to be overwritten by input params (for testing etc)
        $params = array_merge($generated, $params);

        // retrieve again after merging
        $utm_source = A::get($params, 'utm_source', '');
        $utm_medium = A::get($params, 'utm_medium', '');
        $utm_campaign = A::get($params, 'utm_campaign', '');
        $utm_term = A::get($params, 'utm_term', '');
        $utm_content = A::get($params, 'utm_content', '');
        $visited_at = A::get($params, 'visited_at', '');
        if (is_int($visited_at)) {
            $visited_at = date('Y-m-d H:i:s', $visited_at);
        }
        $iphash = A::get($params, 'iphash', '');
        $country = A::get($params, 'country', '');
        $city = A::get($params, 'city', '');
        $useragent = A::get($params, 'useragent', '');

        $this->database()->query("INSERT INTO utm (page_id, utm_source, utm_medium, utm_campaign, utm_term, utm_content, visited_at, iphash, country_name, city, user_agent) VALUES ('$id', '$utm_source', '$utm_medium', '$utm_campaign', '$utm_term', '$utm_content', '$visited_at', '$iphash', '$country', '$city', '$useragent')");

        kirby()->cache('bnomei.utm.queries')->flush();

        return true;
    }

    public function count(string $query = 'SELECT count(*) AS count FROM utm'): int
    {
        if ($this->option('enabled') !== true) {
            return 0;
        }

        $key = md5($query) . '-count';
        $cache = kirby()->cache('bnomei.utm.queries');
        if ($data = $cache->get($key)) {
            return $data;
        }

        $count = intval($this->database()->query($query)->first()->count);
        $cache->set($key, $count);

        return $count;
    }

    public function useragent(): string
    {
        $ua = strtolower(A::get($_SERVER, "HTTP_USER_AGENT", ''));
        $isMob = is_numeric(strpos($ua, "mobile"));
        if ($isMob) {
            return 'mobile';
        }
        $isTab = is_numeric(strpos($ua, "tablet"));
        if ($isTab) {
            return 'tablet';
        }
        // $isDesk = !$isMob && !$isTab;

        return 'desktop';
    }

    public function ipstack(string $ip, string $iphash = null): array
    {
        $key = $this->option('ipstack_access_key');

        // ip could be empty on unittests
        if (empty($ip) || empty($key) || $this->option('enabled') !== true) {
            return [];
        }

        $cache = kirby()->cache('bnomei.utm.ipstack');
        $iphash ??= sha1(__DIR__ . $ip);
        if ($data = $cache->get($iphash)) {
            return $data;
        }

        $https = $this->option('ipstack_https');
        $url = $https . "://api.ipstack.com/" . $ip . "/?access_key=" . $key;
        try {
            $response = Remote::get($url);
            $ipdata = $response->code() === 200 ?
                @json_decode($response->content(), true) :
                null;
        } catch (\Exception $e) {
            $ipdata = [
                'ip' => $ip,
                'hostname' => $ip,
            ];
        }

        unset($ipdata['ip']); // remove the plain ip
        unset($ipdata['hostname']); // remove the plain host
        $cache->set($iphash, $ipdata, intval($this->option('ipstack_expire')));

        return $ipdata;
    }

    /** @var Utm */
    private static $singleton;

    /**
     * @param array $options
     * @return Utm
     */
    public static function singleton(array $options = [])
    {
        if (!self::$singleton) {
            self::$singleton = new self($options);
        }

        return self::$singleton;
    }

    private function sanitize(array $params)
    {
        $params = array_map(fn ($param) => \SQLite3::escapeString(strip_tags($param ?? '')), $params);
        return array_filter($params, fn ($param) => !empty($param));
    }

    private function ratelimit(string $iphash): bool
    {
        if ($this->option('ratelimit_enabled') !== true) {
            return true;
        }

        $cache = kirby()->cache('bnomei.utm.ratelimit');
        $key = $iphash;
        $limit = $cache->get($key);

        // none yet or time passed
        if (!$limit ||
            $limit['time'] + $this->option('ratelimit_expire') * 60 < time()) {
            $cache->set($key, [
                'time' => time(),
                'trials' => 1,
            ]);
            return true;
        }

        // below trial limit
        if ($limit['trials'] < $this->option('ratelimit_trials')) {
            $cache->set($key, [
                'time' => time(),
                'trials' => $limit['trials'] + 1,
            ], intval($this->option('ratelimit_expire')));
            return true;
        }

        return false; // limit reached
    }

    public static function sqliteDateRange(int $begin = 7, int $end = 0, string $column = 'visited_at'): string
    {
        return " $column >= datetime('now', '-$begin days', 'localtime') AND $column <= datetime('now', '-$end days', 'localtime')";
    }

    public static function percentChange($recent, $compare): int
    {
        return $compare > 0 ? intval(round($recent / $compare * 100.0 - 100.0)) : intval(round($recent * 100.0 - 100.0));
    }
}
