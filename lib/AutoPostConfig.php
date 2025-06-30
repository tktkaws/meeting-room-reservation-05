<?php
class AutoPostConfig {
    private $configFile;
    private $config;
    
    public function __construct() {
        $this->configFile = __DIR__ . '/../config/auto_post_config.json';
        $this->loadConfig();
    }
    
    private function loadConfig() {
        if (!file_exists($this->configFile)) {
            $this->createDefaultConfig();
        }
        
        $json = file_get_contents($this->configFile);
        $this->config = json_decode($json, true);
        
        if ($this->config === null) {
            throw new Exception('設定ファイルのJSONが無効です');
        }
    }
    
    private function createDefaultConfig() {
        $defaultConfig = [
            'name' => '会議室予定自動投稿',
            'is_enabled' => true,
            'post_frequency' => 1440, // 1日1回（1440分）
            'post_time' => '08:50', // 日本時間08:50
            'webhook_url' => 'https://chat.googleapis.com/v1/spaces/AAQAW4CXATk/messages?key=AIzaSyDdI0hCZtE6vySjMm-WEfRq3CPzqKqqsHI&token=a_50V7YZ5Ix3hbh-sF-ez8apzMnrB_mbbxAaQDwB_ZQ',
            'last_post_datetime' => null,
            'created_at' => date('c'),
            'updated_at' => date('c')
        ];
        
        $this->saveConfig($defaultConfig);
    }
    
    private function saveConfig($config = null) {
        if ($config === null) {
            $config = $this->config;
        }
        
        $config['updated_at'] = date('c');
        
        // ディレクトリが存在しない場合は作成
        $dir = dirname($this->configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($this->configFile, $json) === false) {
            throw new Exception('設定ファイルの保存に失敗しました');
        }
        
        $this->config = $config;
    }
    
    public function get($key = null) {
        if ($key === null) {
            return $this->config;
        }
        
        return isset($this->config[$key]) ? $this->config[$key] : null;
    }
    
    public function set($key, $value) {
        $this->config[$key] = $value;
        $this->saveConfig();
    }
    
    public function update($data) {
        foreach ($data as $key => $value) {
            $this->config[$key] = $value;
        }
        $this->saveConfig();
    }
    
    public function isEnabled() {
        return $this->get('is_enabled') === true;
    }
    
    public function getFrequency() {
        return (int)$this->get('post_frequency');
    }
    
    public function getPostTime() {
        return $this->get('post_time');
    }
    
    // 後方互換性のため残す
    public function getTimeStart() {
        $postTime = $this->getPostTime();
        return $this->get('post_time_start') ?: $postTime;
    }
    
    public function getTimeEnd() {
        $postTime = $this->getPostTime();
        // 投稿時間の1分後を終了時間とする
        $endMinutes = (intval(substr($postTime, 0, 2)) * 60 + intval(substr($postTime, 3, 2))) + 1;
        $endHour = intval($endMinutes / 60);
        $endMin = $endMinutes % 60;
        $endTime = sprintf('%02d:%02d', $endHour, $endMin);
        return $this->get('post_time_end') ?: $endTime;
    }
    
    public function getWebhookUrl() {
        return $this->get('webhook_url');
    }
    
    public function getLastPostTime() {
        return $this->get('last_post_datetime');
    }
    
    public function updateLastPostTime($datetime = null) {
        if ($datetime === null) {
            $datetime = date('c');
        }
        $this->set('last_post_datetime', $datetime);
    }
    
    public function shouldPost() {
        // 有効無効チェック
        if (!$this->isEnabled()) {
            return false;
        }
        
        $currentTime = date('H:i');
        $postTime = $this->getPostTime();
        
        // 指定時刻チェック（1日1回の場合）
        if ($this->getFrequency() >= 1440) {
            // 指定時刻から5分以内かチェック
            $currentMinutes = date('H') * 60 + date('i');
            $postMinutes = intval(substr($postTime, 0, 2)) * 60 + intval(substr($postTime, 3, 2));
            
            if (abs($currentMinutes - $postMinutes) > 5) {
                return false;
            }
            
            // 今日既に投稿済みかチェック
            $lastPostTime = $this->getLastPostTime();
            if ($lastPostTime) {
                $lastPostDate = date('Y-m-d', strtotime($lastPostTime));
                $today = date('Y-m-d');
                
                if ($lastPostDate === $today) {
                    return false; // 今日既に投稿済み
                }
            }
            
            return true;
        }
        
        // 従来の頻度ベースのロジック（後方互換性）
        $startTime = $this->getTimeStart();
        $endTime = $this->getTimeEnd();
        
        if ($currentTime < $startTime || $currentTime > $endTime) {
            return false;
        }
        
        $lastPostTime = $this->getLastPostTime();
        if ($lastPostTime) {
            $lastPostTimestamp = strtotime($lastPostTime);
            $frequencyMinutes = $this->getFrequency();
            $nextPostTimestamp = $lastPostTimestamp + ($frequencyMinutes * 60);
            
            if (time() < $nextPostTimestamp) {
                return false;
            }
        }
        
        return true;
    }
    
    public function getNextPostTime() {
        $lastPostTime = $this->getLastPostTime();
        $postTime = $this->getPostTime();
        
        // 1日1回の場合
        if ($this->getFrequency() >= 1440) {
            if (!$lastPostTime) {
                return "今日 {$postTime} (初回投稿)";
            }
            
            $lastPostDate = date('Y-m-d', strtotime($lastPostTime));
            $today = date('Y-m-d');
            
            if ($lastPostDate === $today) {
                // 今日既に投稿済み
                $tomorrow = date('Y-m-d', strtotime('+1 day'));
                return "明日 {$tomorrow} {$postTime}";
            } else {
                // 今日まだ投稿していない
                return "今日 {$postTime}";
            }
        }
        
        // 従来の頻度ベースロジック
        if (!$lastPostTime) {
            return '即座に投稿可能';
        }
        
        $lastPostTimestamp = strtotime($lastPostTime);
        $frequencyMinutes = $this->getFrequency();
        $nextPostTimestamp = $lastPostTimestamp + ($frequencyMinutes * 60);
        
        if (time() >= $nextPostTimestamp) {
            return '即座に投稿可能';
        }
        
        return date('Y-m-d H:i:s', $nextPostTimestamp);
    }
}
?>