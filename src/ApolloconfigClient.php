<?php

namespace Wgqi1126\ApolloconfigPhpClient;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;

class ApolloconfigClient
{
    public array $config;
    protected Client $client;

    /**
     * 初始化 <br>
     * $config = [
     *  'config_server_url' => 'http://localhost:8080',
     *  'app_id' => '',
     *  'cluster_name' => 'default',
     *  'ip' => '',
     *  'max_memory' => 100 * 1024 * 1024,
     * ]
     * @param array $config
     */
    public function __construct(array $config)
    {
        $config['config_server_url'] = $config['config_server_url'] ?? 'http://localhost:8080';
        $config['cluster_name'] = $config['cluster_name'] ?? 'default';
        $config['namespace_name'] = $config['namespace_name'] ?? 'application';
        $config['max_memory'] = $config['max_memory'] ?? 100 * 1024 * 1024;

        $this->config = $config;
        $this->client = new Client([
            'base_uri' => $config['config_server_url'],
            'http_errors' => false,
        ]);

        try {
            ini_set('memory_limit', $config['max_memory']);
        } catch (Throwable $e) {
        }
    }

    /**
     * 获取配置<br>
     * 从缓存中获取配置，适合频率较高的配置拉取请求，如简单的每30秒轮询一次配置。<br>
     * 由于缓存最多会有一秒的延时，所以如果需要配合配置推送通知实现实时更新配置的话，请使用 <pre>ApolloconfigClient->getConfig()</pre>
     * @param string $namespaceName default: application
     * @return mixed 配置内容
     * @throws Exception
     */
    public function getConfigInCache(string $namespaceName = 'application')
    {
        $query = [];
        if ($this->config['ip'] ?? null) {
            $query['ip'] = $this->config['ip'];
        }
        $rs = $this->call("/configfiles/json/{$this->config['app_id']}/{$this->config['cluster_name']}/{$namespaceName}", $query);

        if ($rs['code'] != 200) {
            throw new Exception("Call apolloconfig server api error: http_code: {$rs['code']}, body: {$rs['body']}");
        }

        return json_decode($rs['body'], true);
    }

    /**
     * 获取配置<br>
     * 直接从数据库中获取配置，可以配合配置推送通知实现实时更新配置。
     * @param string $namespaceName default: application
     * @param string|null $releaseKey
     * @return mixed|null 配置的详情
     * [
     *   'appId' => '111',
     *   'cluster' => 'default',
     *   'namespaceName' => 'application',
     *   'configurations' => [
     *     'aa' => 'aa',
     *     'bb' => 'dddd',
     *   ],
     *   'releaseKey' => '20221206181028-e95f08813b790dfa',
     * ]
     * @throws Exception
     */
    public function getConfig(string $namespaceName = 'application', string $releaseKey = null)
    {
        $query = [];
        if ($this->config['ip'] ?? null) {
            $query['ip'] = $this->config['ip'];
        }
        if ($releaseKey) {
            $query['releaseKey'] = $releaseKey;
        }

        $rs = $this->call("/configs/{$this->config['app_id']}/{$this->config['cluster_name']}/{$namespaceName}", $query);

        if ($rs['code'] == 304) {
            return null;
        }

        if ($rs['code'] != 200) {
            throw new Exception("Call apolloconfig server api error: http_code: {$rs['code']}, body: {$rs['body']}");
        }

        return json_decode($rs['body'], true);
    }

    /**
     * 监听配置变化
     * @throws Exception
     * @package callable $callback function(array $changedNamespaceNames, array $details)
     */
    public function watch(callable $callback, array $namespaceName = ['application'])
    {
        $query = [
            'appId' => $this->config['app_id'],
            'cluster' => $this->config['cluster_name'],
        ];
        $notifications = [];
        foreach ($namespaceName as $name) {
            $notifications[$name] = ['namespaceName' => $name, 'notificationId' => -1];
        }
        while (true) {
            $query['notifications'] = json_encode(array_values($notifications));

            $rs = $this->call("/notifications/v2", $query, 120);

            if ($rs['code'] != 200) {
                throw new Exception("Call apolloconfig server api error: http_code: {$rs['code']}, body: {$rs['body']}");
            }
            $changes = json_decode($rs['body'], true);
            $changesNames = [];
            foreach ($changes as $v) {
                $changesNames[] = $v['namespaceName'];
                $notifications[$v['namespaceName']]['notificationId'] = $v['notificationId'];
            }

            $cbRs = $callback($changesNames, $changes);
            if ($cbRs === false) {
                break;
            }
        }
    }

    protected function call(string $uri, array $query = [], $timeout = 30)
    {
        $url = "{$uri}?" . http_build_query($query);
        $rs = $this->client->get($url, [
            'timeout' => $timeout,
        ]);
        return [
            'code' => $rs->getStatusCode(),
            'body' => $rs->getBody()->getContents(),
        ];
    }
}
