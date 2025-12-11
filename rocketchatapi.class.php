<?php
class rocketchatapi
{
    public  $apiUrl;
    private $rocketXUserId;
    private $rocketXAuthToken;
    private $errors = array();
    public $logPath;

    /**
     * Construct
     *
     * @param  string $appKey
     * @param  string $appSecret
     * @param  string $agentId
     * @param  string $apiUrl
     * @access public
     */
    public function __construct($rocketXUserId, $rocketXAuthToken, $apiUrl = '')
    {
        $this->logPath = dirname(dirname(dirname(__FILE__))) . '/tmp/log/webhook.log';
        file_put_contents($this->logPath, date('Y-m-d H:i:s') . " | rocketXUserId: " . $rocketXUserId . "; apiUrl: ". $apiUrl . "\n", FILE_APPEND);
        $this->rocketXUserId    = $rocketXUserId;
        $this->rocketXAuthToken = $rocketXAuthToken;
        $this->apiUrl   = $apiUrl;
    }

    /**
     * Get all users.
     *
     * @access public
     * @return array
     */
    public function getAllUsers()
    {
        $users = array();
        $headers = array('X-Auth-Token: ' . $this->rocketXAuthToken, 'X-User-Id: ' . $this->rocketXUserId);
        $response = $this->queryAPI($this->apiUrl . "users.list", $headers);

        if($this->isError()) return array('result' => 'fail', 'message' => $this->errors);

        foreach($response->users as $user) {
            $users[$user->name] = $user->username;
        }

        file_put_contents($this->logPath, date('Y-m-d H:i:s') . " | users: " . json_encode($users) . "\n", FILE_APPEND);

        return array('result' => 'success', 'data' => $users);
    }

    /**
     * Send message
     *
     * @param  string $userList
     * @param  string $message
     * @access public
     * @return array
     */
    public function send($userList, $message, $hookUrl)
    {
        file_put_contents($this->logPath, date('Y-m-d H:i:s') . " | send msg: " . $message . "\n", FILE_APPEND);
        $message = json_decode($message);

        if(!empty($userList)) {
            $header[] = "Content-Type: application/json; charset=utf-8";
            $openIdArr = explode(',', $userList);
            foreach($openIdArr as $channel) {
                $ch = $this->curlInit($hookUrl, $header);
                $message->channel = "@".$channel;
                $data = json_encode($message);
                file_put_contents($this->logPath, date('Y-m-d H:i:s') . " | send to: " . "@".$channel . "\n", FILE_APPEND);

                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

                $result   = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error    = curl_error($ch);
                curl_close($ch);
                file_put_contents($this->logPath, date('Y-m-d H:i:s') . " | result: " . $result . "\n", FILE_APPEND);
                if($error)  return array('result' => 'fail', 'message' => $error);
            }
        }

        if($result) {
            return array('result' => 'success', 'message' => $result);
        }
        return array('result' => 'success', 'message' => $httpCode);
    }

    private function curlInit($url, $header = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        return $ch;
    }

    /**
     * Query API.
     *
     * @param  string $url
     * @access public
     * @return string
     */
    public function queryAPI($url, $headers)
    {
        $response = common::http($url, null, null, $headers);
        $errors   = commonModel::$requestErrors;

        $response = json_decode($response);
        if(isset($response->success) and $response->success == true) return $response;

        if(empty($response)) $this->errors = $errors;
        if(isset($response->errcode)) $this->errors[$response->errcode] = "Errcode:{$response->errcode}, Errmsg:{$response->errmsg}";
        return false;
    }

    /**
     * Check for errors.
     *
     * @access public
     * @return bool
     */
    public function isError()
    {
        return !empty($this->errors);
    }

    /**
     * Get errors.
     *
     * @access public
     * @return array
     */
    public function getErrors()
    {
        $errors = $this->errors;
        $this->errors = array();

        return $errors;
    }
}
