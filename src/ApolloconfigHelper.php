<?php

namespace Wgqi1126\ApolloconfigPhpClient;

use Exception;

class ApolloconfigHelper
{
    const FILE_TYPE_JSON = 'json';
    const FILE_TYPE_PHP = 'php';

    protected ApolloconfigClient $client;
    protected bool $openLog = false;

    public function __construct(array $config)
    {
        $this->client = new ApolloconfigClient($config);
    }

    /**
     * 监控配置变化，并保存
     * @param array $namespaceName
     * @param string $savePath
     * @param string|callable $fileType
     * @return void
     * @throws Exception
     */
    public function watchAndSave(array $namespaceName = ['application'], string $savePath = './data', $fileType = 'php')
    {
        if (!file_exists($savePath)) {
            mkdir($savePath, 0755, true);
        }
        $this->log("watch: " . json_encode($namespaceName));
        $this->client->watch(function ($changes) use ($savePath, $fileType) {
            foreach ($changes as $v) {
                $conf0 = $this->client->getConfig($v);
                $conf = $conf0['configurations'];
                unset($conf0['configurations']);
                if (is_callable($fileType)) {
                    list($file, $cnt) = $fileType($v, $conf);
                } else {
                    switch ($fileType) {
                        case self::FILE_TYPE_JSON:
                            $file = $v . '.json';
                            $cnt = json_encode($conf, JSON_UNESCAPED_UNICODE);
                            break;
                        case self::FILE_TYPE_PHP:
                            $file = $v . '.php';
                            $cnt = "<?php\nreturn " . var_export($conf, true) . ';';
                            break;
                        default:
                            throw new Exception("File type '{$fileType}' not supported");
                    }
                }
                $filename = $savePath . '/' . $file;

                $this->log("{$v} changed file={$filename}, info=" . json_encode($conf0, JSON_UNESCAPED_UNICODE));
                file_put_contents($filename, $cnt);
            }
        }, $namespaceName);
    }

    public function log($msg)
    {
        if ($this->openLog) {
            echo date('[Y-m-d H:i:s]') . ' ' . $msg . "\n";
        }
    }

    public function openLog($open = true)
    {
        $this->openLog = $open;
    }
}
