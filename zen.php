<?php
/**
 * The zen file of webhook module of ZenTaoPMS.
 *
 * @copyright   Copyright 2009-2023 禅道软件（青岛）有限公司(ZenTao Software (Qingdao) Co., Ltd. www.zentao.net)
 * @license     ZPL(http://zpl.pub/page/zplv12.html) or AGPL(https://www.gnu.org/licenses/agpl-3.0.en.html)
 * @author      Sun Guangming<sunguangming@easysoft.ltd>
 * @package     webhook
 * @link        https://www.zentao.net
 */
class webhookZen extends webhook
{
    /**
     * 通过API获取人员列表。
     * Get users by API.
     *
     * @param  object $webhook
     * @access public
     * @return array
     */
    protected function getResponse($webhook)
    {
        file_put_contents('/tmp/webhook.log', date('Y-m-d H:i:s') . " | getResponse - webhook->type: " . $webhook->type . "\n", FILE_APPEND);

        $response = array();
        $selectedDepts = $this->cookie->selectedDepts ? $this->cookie->selectedDepts : '';
        if($webhook->type == 'dinguser')
        {
            $this->app->loadClass('dingapi', true);
            $dingapi  = new dingapi($webhook->secret->appKey, $webhook->secret->appSecret, $webhook->secret->agentId);
            $response = $dingapi->getUsers($selectedDepts);
        }
        elseif($webhook->type == 'wechatuser')
        {
            $this->app->loadClass('wechatapi', true);
            $wechatApi = new wechatapi($webhook->secret->appKey, $webhook->secret->appSecret, $webhook->secret->agentId);
            $response  = $wechatApi->getAllUsers();
        }
        elseif($webhook->type == 'feishuuser')
        {
            $this->app->loadClass('feishuapi', true);
            $feishuApi = new feishuapi($webhook->secret->appId, $webhook->secret->appSecret);
            $response  = $feishuApi->getAllUsers($selectedDepts);
        }
        elseif($webhook->type == 'rocketchannel')
        {
            include_once 'rocketchatapi.class.php';
            //$this->app->loadClass('rocketchatapi', true);
            file_put_contents('/tmp/webhook.log', date('Y-m-d H:i:s') . " | webhook: " . json_encode($webhook) . "\n", FILE_APPEND);
            $rocketChatApi = new rocketchatapi($webhook->secret->appId, $webhook->secret->appSecret, $webhook->secret->apiUrl);
            $response  = $rocketChatApi->getAllUsers();
        }

        $this->view->selectedDepts = $selectedDepts;
        return $response;
    }

    /**
     * 获取已绑定的open_id键值对，并追加未查询出的open_id。
     * Get bound open_id and name pairs, and append no fetch oauth users.
     *
     * @param  object $webhook
     * @param  array $users
     * @param  array $boundUsers
     * @param  array $oauthUsers
     * @access public
     * @return array
     */
    public function getBoundUseridPairs($webhook, $users, $boundUsers, $oauthUsers)
    {
        $boundUseridPairs = array();
        $useridPairs      = array_flip($oauthUsers);
        $noFetchOauth     = array();
        foreach($users as $user)
        {
            if(isset($boundUsers[$user->account]))  $userid = $boundUsers[$user->account];
            if(isset($oauthUsers[$user->realname])) $userid = $oauthUsers[$user->realname];
            if(!isset($userid)) continue;
            if(!isset($useridPairs[$userid])) $noFetchOauth[$userid] = $userid;
            $boundUseridPairs[$userid] = zget($useridPairs, $userid);
        }

        if($noFetchOauth)
        {
            if($webhook->type == 'dinguser')
            {
                $this->app->loadClass('dingapi', true);
                $dingapi = new dingapi($webhook->secret->appKey, $webhook->secret->appSecret, $webhook->secret->agentId);
                foreach($dingapi->batchGetUsers($noFetchOauth) as $userid => $name) $boundUseridPairs[$userid] = $name;
            }
            elseif($webhook->type == 'feishuuser')
            {
                $this->app->loadClass('feishuapi', true);
                $feishuApi = new feishuapi($webhook->secret->appId, $webhook->secret->appSecret);
                foreach($feishuApi->batchGetUsers($noFetchOauth) as $openid => $name) $boundUseridPairs[$openid] = $name;
            }
        }

        return $boundUseridPairs;
    }
}
