**[English Version](./README.en_US.md)**

# 禅道 Webhook 的 RocketChat 集成支持

禅道（这里仅限于社区版）本身只支持有限的即时聊天工具（IM）对接：

- [倍洽 BearyChat](http://bearychat.com/)
- [钉钉](https://www.dingtalk.com/)
- [企业微信](https://work.weixin.qq.com/)
- [飞书](https://www.feishu.cn/)

如果是在物理隔绝的内网部署 IM，那么要么需要花大价钱做上面工具的私有化部署（还不一定有），要么部署其它可以独立私有化部署的 IM 工具，比如本文要讲到的 **[RocketChat](https://www.rocket.chat/)**。

在开发工作中及时收到任务或缺陷的消息还是很重要的。于是就引出了如何将禅道和 RocketChat 对接起来的需求，这样在禅道里的重要操作，都能即时通知到 RocketChat 聊天工具里。

因为 RocketChat 本身支持集成 Webhook ，这样我们其实基于禅道 Webhook 功能添加一个 RocketChat 渠道就可以了。


## 0. 太长不看版

本项目代码就是基于禅道社区版 21.7.5 版本的 **Webhook** 模块，添加了 RocketChat Webhook 的支持，可以直接使用。备份好原版 Webhook 目录，用本项目代码替换即可。如果禅道版本不同，请自行修改代码。


## 1. 开发、测试环境

这里为了方便，我们使用 Docker 运行一个 RocketChat 和一个禅道，版本都取此文写作时的版本：

- RocketChat: https://hub.docker.com/r/rocketchat/rocket.chat **7.10.0**
- 禅道: https://hub.docker.com/r/easysoft/zentao **21.7.5**

### 1.1. RocketChat

RocketChat 的 `docker-compose.yml` 可以在官方 GitHub 仓库里找到：https://github.com/RocketChat/rocketchat-compose/blob/main/compose.yml ，相关文档：https://docs.rocket.chat/v1/docs/deploy-with-docker-docker-compose 。运行起来以后我们需要去 [RocketChat 官网](https://cloud.rocket.chat/home)注册激活一下，按照文档操作即可，这里就不赘述了。

![:/a672e278050949c680ae7a72321db27f](./docs/images/7b3f6c904007310adf60c63b81ea7ee5.png)

运行起来的 RocketChat 可以通过浏览器访问 http://127.0.0.1:3000 ：

![:/a5d8b173d6c94632a33ebb9e084723fc](./docs/images/847881283882431588eafd9b24f99f34.png)

#### RocketChat 管理员创建方法

参考[官方文档](https://github.com/sporqist/Rocket.Chat_docs/blob/main/setup-and-configure/accessing-your-workspace/admin-account-creation.md)，在 `docker-compose.yml` 里 `rocketchat` 的 `environment` 里添加：

```yaml
      INITIAL_USER: yes
      ADMIN_USERNAME: admin
      ADMIN_NAME: Admin
      ADMIN_EMAIL: admin@localhost.com
      ADMIN_PASS: password
```


### 1.2. 禅道

禅道的 docker 运行命令，注意这里使用了之前启动 RocketChat 容器组时自动创建的 bridge 网络 `rocketchat_default`，这是为了后面从禅道容器连接 RocketChat 容器方便：
```bash
docker run -it -d \
    --name zentao \
    -v $PWD/data:/data \
    -p 80:80 \
    -e MYSQL_INTERNAL=true \
	--network rocketchat_default \
    easysoft/zentao:21.7.5
```

运行起来并安装完成后，进入后台，查看**通知设置**，我们可以看到当前支持的 **Webhook**：

![:/8f68346c66ee429498052e8d27a34819](./docs/images/5f1dddfe637f8952faff1dab9d30ff34.png)

因为要修改禅道的 Webhook 实现代码，所以我们还需要获取禅道社区版的源代码：

```bash
git clone --branch zentaopms_21.7.5_20250911 https://github.com/easysoft/zentaopms.git
```

然后就可以基于这个源码项目进行修改了。只不过这样修改完成后的代码我们还需要自行打包 docker 镜像。

另一种比较取巧的办法是将 docker 容器里的 webhook 模块代码拷贝出来到宿主机，然后把它用 docker 目录映射的方式映射到容器里，这样我们的修改直接就可以生效：

```bash
# 拷贝 webhook 目录到宿主机
docker cp zentao:/apps/zentao/module/webhook ./

# 停掉、删除旧容器
docker stop zentao && docker rm zentao

# 映射 webhook 目录，启动新容器
docker run -it -d \
    --name zentao \
    -v $PWD/data:/data \
	-v $PWD/webhook:/apps/zentao/module/webhook \
    -p 80:80 \
    -e MYSQL_INTERNAL=true \
	--network rocketchat_default \
    easysoft/zentao:21.7.5

```

为了方便我这里就使用后一种方法。


## 2. 在 RocketChat 里添加 Webhook

### 支持中文用户名称和频道名称

**管理** >> **通用** >> **UTF8**，改成如下内容即可：
```
[\u4e00-\u9fa50-9a-zA-Z-_.]+
```

![:/f1aa36d48c744af2911659429d894c20](./docs/images/a8f5d993d54441f1250f63e75d290d83.png)

### 创建 Webhook

![:/5c9689fcc5c6464fb6f4de79be8e73d6](./docs/images/17d80eb5493e993081d1d8532ab5a12e.png)

![:/ccaf2d779b044a7a9c5a2b323546fbae](./docs/images/bf6e6ce6ed1a83fb4f1b4cb3ea4f7679.png)

注意，这里的频道必须先创建好，如果使用其它用户作为 Webhook 发送者身份，也需要该用户事先存在。然后将 `Allow to overwrite destination channel in the body parameters` 开关打开，这样我们可以通过 Webhook 的参数将消息发送到指定的其它频道或用户。

![:/e5d21e01fae64be88e0778b4fad2baf1](./docs/images/0fe35d193388dc39f21167eda00a699d.png)

保存之后，这个 Webhook 就可以用了。注意这里 URL 的 IP 使用了宿主机的 IP，而不是 127.0.0.1，因为如果后面从禅道的容器发送请求给 RocketChat 服务器，用 127.0.0.1 是无法送达的。

![:/ea3e94a4fc1f43e88bee3608bb570ed6](./docs/images/dd2d17129363a96a880fa9cd2df91292.png)

直接使用提供的示例就能测试：

![:/99fa1e9a395a4acea804e2a82953beba](./docs/images/9da1fb60a0bf617d8e79d825437fbcc9.png)


## 3. 修改禅道

现在我们的目标就是把 RocketChat 的 Webhook 集成到禅道里。

### 3.1. 修改禅道 Webhook 模块

#### 3.1.1. 修改 `webhook\config\formdata.php`

在 `$config->webhook->form->create['feishuAppSecret']` 之后添加：
```php
$config->webhook->form->create['rocketChannel']    = array('type' => 'string', 'control' => 'text',      'required' => false, 'default' => '', 'filter'  => 'trim');
```

在 `$config->webhook->form->edit['feishuAppSecret']` 之后添加：

```php
$config->webhook->form->edit['rocketChannel']    = array('type' => 'string', 'control' => 'text',      'required' => false, 'default' => '', 'filter'  => 'trim');
```

#### 3.1.2. 修改 `webhook\js\create.js`

将下面的代码
```js
    $('#type').change(function()
    {
        var type = $(this).val();
        $('#sendTypeTR').toggle(type != 'dinggroup' && type != 'dinguser' && type != 'wechatuser' && type != 'wechatgroup' && type != 'feishuuser' && type != 'feishugroup');
        $('#secretTR').toggle(type == 'dinggroup' || type == 'feishugroup');
        $('#urlTR').toggle(type != 'dinguser' && type != 'wechatuser' && type != 'feishuuser');
        $('.dinguserTR').toggle(type == 'dinguser');
        $('.wechatTR').toggle(type == 'wechatuser');
        $('.feishuTR').toggle(type == 'feishuuser');
        $('#paramsTR').toggle(type != 'bearychat' && type != 'dinggroup' && type != 'dinguser' && type != 'wechatuser' && type != 'wechatgroup' && type != 'feishuuser' && type != 'feishugroup');
        $('#urlNote').html(urlNote[type]);
    });
```

修改为（添加了 `rocketchannel`）：
```js
    $('#type').change(function()
    {
        var type = $(this).val();
        $('#sendTypeTR').toggle(type != 'dinggroup' && type != 'dinguser' && type != 'wechatuser' && type != 'wechatgroup' && type != 'feishuuser' && type != 'feishugroup' && type != 'rocketchannel');
        $('#secretTR').toggle(type == 'dinggroup' || type == 'feishugroup');
        $('#urlTR').toggle(type != 'dinguser' && type != 'wechatuser' && type != 'feishuuser');
        $('.dinguserTR').toggle(type == 'dinguser');
        $('.wechatTR').toggle(type == 'wechatuser');
        $('.feishuTR').toggle(type == 'feishuuser');
		$('.rocketTR').toggle(type == 'rocketchannel');
        $('#paramsTR').toggle(type != 'bearychat' && type != 'dinggroup' && type != 'dinguser' && type != 'wechatuser' && type != 'wechatgroup' && type != 'feishuuser' && type != 'feishugroup' && type != 'rocketchannel');
        $('#urlNote').html(urlNote[type]);
    });
```

#### 3.1.3. 修改 `webhook\js\create.ui.js`

将代码修改为（添加了 `rocketchannel`）：
```js
window.changeType = function()
{
    var type = $('[name=type]').val();
    $('#sendTypeTR').toggle(type != 'dinggroup' && type != 'dinguser' && type != 'wechatuser' && type != 'wechatgroup' && type != 'feishuuser' && type != 'feishugroup' && type != 'rocketchannel');
    $('#secretTR').toggle(type == 'dinggroup' || type == 'feishugroup');
    $('#urlTR').toggle(type != 'dinguser' && type != 'wechatuser' && type != 'feishuuser');
    $('.dinguserTR').toggle(type == 'dinguser');
    $('.wechatTR').toggle(type == 'wechatuser');
    $('.feishuTR').toggle(type == 'feishuuser');
	$('.rocketTR').toggle(type == 'rocketchannel');
    $('#paramsTR').toggle(type != 'bearychat' && type != 'dinggroup' && type != 'dinguser' && type != 'wechatuser' && type != 'wechatgroup' && type != 'feishuuser' && type != 'feishugroup' && type != 'rocketchannel');
    $('#urlNote').html(urlNote[type]);
}
changeType();
```

#### 3.1.4. 修改 `webhook\lang\zh-cn.php`

添加下面内容：

```php
$lang->webhook->typeList['rocketchannel'] = 'RocketChat 频道';
$lang->webhook->rocketChannel = 'RocketChat 频道';
$lang->webhook->note->rocketChannel = '填写 RocketChat 频道名称，不带 # 前缀，多个频道用半角逗号“,”分隔。';
$lang->webhook->note->typeList['rocketchannel'] = '请在 RocketChat 中添加一个集成（Integrations），并将其 webhook 填写到此处。';
```

其它语言可以自己添加。

#### 3.1.5. 修改 `webhook\ui\create.html.php`

在 `$formItems[] = formRow` 这行上面添加：

```php
    if($field == 'rocketChannel') {
        $title = $lang->webhook->rocketChannel;
        $rowClass = 'rocketTR';
        $required = true;
        $notice = $lang->webhook->note->rocketChannel;
    }
```

#### 3.1.6. 修改 `webhook\ui\edit.html.php`

修改 `if($field == 'params'` 这行，添加 `rocketchannel` 条件：

```php
if($field == 'params' && str_contains('|bearychat|dinggroup|dinguser|wechatgroup|wechatuser|feishuuser|feishugroup|rocketchannel|', "|{$webhook->type}|")) continue;
```

在 `$formItems[] = formRow` 这行上面添加：

```php
    if($field == 'rocketChannel') {
        $title = $lang->webhook->rocketChannel;
        $rowClass = 'rocketTR';
        $required = true;
        $notice = $lang->webhook->note->rocketChannel;
        $default = $webhook->params;
    }
```

#### 3.1.7. 修改 `webhook\view\create.html.php`

修改添加 `rocketTR` 部分：

```php
        <tr class='feishuTR'>
          <th><?php echo $lang->webhook->feishuAppSecret;?></th>
          <td class='required'><?php echo html::input('feishuAppSecret', '', "class='form-control'");?></td>
        </tr>
        <tr class="rocketTR">
          <th><?php echo $lang->webhook->rocketChannel;?></th>
          <td class='required'><?php echo html::input('rocketChannel', '', "class='form-control'")?></td>
          <td id='rocketNote'><?php echo $lang->webhook->note->rocketChannel ?></td>
        </tr>
```

#### 3.1.8. 修改 `webhook\view\edit.html.php`

在 `feishuuser` 相关代码后面修改添加 `rocketchannel` 部分：

```php
        <?php if($webhook->type == 'feishuuser'):?>
        <?php $secret = json_decode($webhook->secret);?>
        <tr class="feishuTR">
          <th><?php echo $lang->webhook->feishuAppId;?></th>
          <td class='required'><?php echo html::input('feishuAppId', $secret->appId, "class='form-control'")?></td>
        </tr>
        <tr class="feishuTR">
          <th><?php echo $lang->webhook->feishuAppSecret;?></th>
          <td class='required'><?php echo html::input('feishuAppSecret', $secret->appSecret, "class='form-control'")?></td>
          <td></td>
        </tr>
        <?php endif;?>
        <?php if($webhook->type == 'rocketchannel'):?>
        <tr class="rocketTR">
          <th><?php echo $lang->webhook->rocketChannel;?></th>
          <td><?php echo html::input('rocketChannel', $webhook->params, "class='form-control'")?></td>
          <td><?php echo $lang->webhook->note->rocketChannel ?></td>
        </tr>
        <?php endif;?>
```

找到代码中两处 `if(strpos(` 的地方，分别添加 `rocketchannel` 条件：

```php
<?php if(strpos("dinggroup|dinguser|wechatgroup|wechatuser|feishuuser|feishugroup|rocketchannel", $webhook->type) === false):?>
```

```php
<?php if(strpos(',bearychat,dinggroup,dinguser,wechatgroup,wechatuser,feishuuser,feishugroup,rocketchannel,', ",$webhook->type,") === false):?>
```


#### 3.1.9. 修改 `webhook\model.php`

##### 修改 `public function create($webhook)` 方法：

```php
    public function create($webhook)
    {
        $webhook->createdBy   = $this->app->user->account;
        $webhook->createdDate = helper::now();
        $webhook->domain      = trim($webhook->domain, '/');
        $webhook->params      = $this->post->params ? implode(',', $this->post->params) . ',text' : 'text';

        if($webhook->type == 'dinguser')
        {
            $webhook = $this->webhookTao->getDingdingSecret($webhook);
        }
        elseif($webhook->type == 'wechatuser')
        {
            $webhook = $this->webhookTao->getWeixinSecret($webhook);
        }
        elseif($webhook->type == 'feishuuser')
        {
            $webhook = $this->webhookTao->getFeishuSecret($webhook);
        }
        elseif($webhook->type == 'rocketchannel')
        {
            $webhook->params = $webhook->rocketChannel;
        }

        if(dao::isError()) return false;

        $this->dao->insert(TABLE_WEBHOOK)->data($webhook, 'agentId,appKey,appSecret,wechatCorpId,wechatCorpSecret,wechatAgentId,feishuAppId,feishuAppSecret,rocketChannel')
            ->batchCheck($this->config->webhook->create->requiredFields, 'notempty')
            ->autoCheck()
            ->exec();

        return $this->dao->lastInsertID();
    }
```

##### 修改 `public function update($id, $webhook)` 方法：

```php
    public function update($id, $webhook)
    {
        $webhook->editedBy   = $this->app->user->account;
        $webhook->editedDate = helper::now();
        $webhook->domain     = trim($webhook->domain, '/');
        $webhook->params     = $this->post->params ? implode(',', $this->post->params) : 'text';
        if(!str_contains($webhook->params, 'text')) $webhook->params .= ',text';

        if($webhook->type == 'dinguser')
        {
            $webhook = $this->webhookTao->getDingdingSecret($webhook);
        }
        elseif($webhook->type == 'wechatuser')
        {
            $webhook = $this->webhookTao->getWeixinSecret($webhook);
        }
        elseif($webhook->type == 'feishuuser')
        {
            $webhook = $this->webhookTao->getFeishuSecret($webhook);
        }
        elseif($webhook->type == 'rocketchannel')
        {
            $webhook->params = $webhook->rocketChannel;
        }

        $this->dao->update(TABLE_WEBHOOK)->data($webhook, 'agentId,appKey,appSecret,wechatCorpId,wechatCorpSecret,wechatAgentId,feishuAppId,feishuAppSecret,rocketChannel')
            ->batchCheck($this->config->webhook->edit->requiredFields, 'notempty')
            ->autoCheck()
            ->where('id')->eq($id)
            ->exec();
        return !dao::isError();
    }
```

##### 修改 `public function getDataByType($webhook, $action, $title, $text, $mobile, $email, $objectType, $objectID)` 方法：

```php
    public function getDataByType($webhook, $action, $title, $text, $mobile, $email, $objectType, $objectID)
    {
        if($webhook->type == 'dinggroup' or $webhook->type == 'dinguser')
        {
            $data = $this->getDingdingData($title, $text, $webhook->type == 'dinguser' ? '' : $mobile);
        }
        elseif($webhook->type == 'bearychat')
        {
            $data = $this->getBearychatData($text, $mobile, $email, $objectType, $objectID);
        }
        elseif($webhook->type == 'wechatgroup' or $webhook->type == 'wechatuser')
        {
            $data = $this->getWeixinData($text, $mobile);
        }
        elseif($webhook->type == 'feishuuser' or $webhook->type == 'feishugroup')
        {
            $data = $this->getFeishuData($title, $text);
        }
        elseif($webhook->type == 'rocketchannel') {
            $data = new stdclass();
            $data->text = $text;
            $data->channel = $webhook->params;
        }
        else
        {
            $data = new stdclass();
            foreach(explode(',', $webhook->params) as $param) $data->$param = $action->$param;
        }

        return json_encode($data);
    }
```

##### 修改 `public function fetchHook($webhook, $sendData, $actionID = 0, $appendUser = '')` 方法：

```php
    public function fetchHook($webhook, $sendData, $actionID = 0, $appendUser = '')
    {
        if(!extension_loaded('curl')) return print(helper::jsonEncode($this->lang->webhook->error->curl));

        if(in_array($webhook->type, array('dinguser', 'wechatuser', 'feishuuser'))) return $this->sendToUser($webhook, $sendData, $actionID, $appendUser);

        $contentType = "Content-Type: {$webhook->contentType};charset=utf-8";
        if($webhook->type == 'dinggroup' or $webhook->type == 'wechatgroup' or $webhook->type == 'feishugroup' or $webhook->type == 'rocketchannel') $contentType = "Content-Type: application/json";
        $header[] = $contentType;

        $url = $webhook->url;
        if($webhook->type == 'dinggroup' and $webhook->secret)
        {
            $timestamp = time() * 1000;
            $sign = $timestamp . "\n" . $webhook->secret;
            $sign = urlencode(base64_encode(hash_hmac('sha256', $sign, $webhook->secret, true)));
            $url .= "&timestamp={$timestamp}&sign={$sign}";
        }
        if($webhook->type == 'feishugroup' and $webhook->secret)
        {
            $timestamp = time();
            $sign = $timestamp . "\n" . $webhook->secret;
            $sign = base64_encode(hash_hmac('sha256', '', $sign, true));

            $content = json_decode($sendData);
            $content->timestamp = $timestamp;
            $content->sign      = $sign;
            $sendData = json_encode($content);
        }

        //针对 rocketchannel 的特殊处理, 需要分别发送给不同的 channel
        if($webhook->type == 'rocketchannel') {
            $ch = $this->curlInit($url, $header);
        
            $tmpdata = json_decode($sendData);

            if(strlen($tmpdata->channel) > 0) {
                if(stripos($tmpdata->channel, ",") > 0) {
                    $channels = explode(",", $tmpdata->channel);
                } else {
                    $channels = array($tmpdata->channel);
                }
                foreach($channels as $channel) {
                    $tmpdata->channel = trim($channel);
                    $data = json_encode($tmpdata);

                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

                    $result   = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $error    = curl_error($ch);
                    curl_close($ch);
                    if($error)  return $error;
                }
            }

            if($result) return $result;
            return $httpCode;
        } else {
            $ch = $this->curlInit($url, $header);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $sendData);

            $result   = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if($error)  return $error;
            if($result) return $result;
            return $httpCode;
        }
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
```


### 3.2. 添加 RocketChat Webhook 到禅道

![:/a6090c1c863d47cda8ed5b3338c7937f](./docs/images/0a31014f2c856232b308b722645feea3.png)

然后我们随便找一个 bug 或者 任务，添加一条备注：

![:/94e24a937b8549bcb8d9872b24ea087f](./docs/images/6f3c2adfde929bacc3622848e46d3c6d.png)

可以在 RocketChat 里设置好的频道里接收到相关提醒消息了：

![:/29b6636940df447e8814e45443d776c2](./docs/images/fb9a005f2c4513db2a854e4ef0d57fc8.png)

![:/f44a78c5da094052a7118f3f090b5aef](./docs/images/5e3b0ef3b51d51c82b0e833d0ab9544f.png)


## 4. 直接通知到用户

仅仅通知到频道，跟企业微信、钉钉等通知到群组消息是类似的。更直接的通知到用户个人账号，在某些比较大的项目组应该是更合适的做法，毕竟太多频道、群组消息会干扰到很多人。所以这里我们参考企业微信、钉钉等绑定用户的方式，来实现 RocketChat 的账号和禅道账号绑定。

为了安全起见，我们建立一个独立的用户来执行前面 admin 账号的工作，这在生产环境是必要的安全隔离措施。

### 4.1. 在 RocketChat 里添加一个用户 `zentao`

![:/f6aface352cb4601b6adfff573dea0b0](./docs/images/558ff04bd993d36976196db319d5819a.png)

### 4.2. 修改 RocketChat Webhook 配置

使用 `zentao` 用户发送消息：

![:/209eaffd031044dfa70bd4c48a476bb7](./docs/images/4ad8aaaebd10d691e51580eb231d2df9.png)

### 4.3. 获取 RocketChat 用户的 API 访问令牌

![:/1d9a531375d44f42a7920e7993f575e9](./docs/images/934f9f42d2687787e13d37af30c12c35.png)

![:/bd78c9d4ddd3478899ba729d14c817c7](./docs/images/7c3c6283d9b9b95c405310bae4ceadaf.png)

![:/0e41c31a545d4a5abd5edb4634e88508](./docs/images/9ac046bd86f5f5a5027d69f481454f61.png)

这里我们要用到的 API 是：https://developer.rocket.chat/apidocs/get-users-list

测试一下令牌是否可用：

```
curl -H 'X-Auth-Token: z6pD9UOCSWJ83lnMMO4ZqzuN4IaKh-I2b0NMZPyvoCB' -H 'X-User-Id: PF2faXWRNfTZbJqY5' 'http://127.0.0.1:3000/api/v1/users.info?userId=zentao'

# 返回：
{"user":{"_id":"PF2faXWRNfTZbJqY5","username":"zentao","type":"user","status":"away","active":true,"name":"禅道","utcOffset":8,"canViewAllInfo":false},"success":true}%
```

```
curl -H 'X-Auth-Token: z6pD9UOCSWJ83lnMMO4ZqzuN4IaKh-I2b0NMZPyvoCB' -H 'X-User-Id: PF2faXWRNfTZbJqY5' 'http://127.0.0.1:3000/api/v1/users.list'

# 返回：
{"users":[{"_id":"693048466f442bace501954a","name":"Admin","username":"admin","status":"offline","active":true,"type":"user","nameInsensitive":"admin"},{"_id":"rocket.cat","name":"Rocket.Cat","username":"rocket.cat","status":"online","active":true,"type":"bot","avatarETag":"KMQbXSF5e5AaH3LK4","nameInsensitive":"rocket.cat"},{"_id":"PF2faXWRNfTZbJqY5","username":"zentao","type":"user","status":"away","active":true,"name":"禅道","nameInsensitive":"禅道"}],"count":3,"offset":0,"total":3,"success":true}
```

### 4.4. 修改禅道代码

具体代码就直接体现在项目代码里了，这里就不一一列出了。

- 在项目里添加了 `rocketchatapi.class.php` 文件，实现了与 RocketChat 的 API 集成；
- 在 `control.php` 中对 `rocketchannel` 类型的 webhook 进行了处理；
- 在 `model.php` 中增加了对 `rocketchannel` 类型的 webhook 支持和 `sendToUser` 的调用；
- 在 `tao.php` 中添加了 `getRocketChatSecret` 方法，用于获取 RocketChat 的认证信息和 API URL；
- 在 `zen.php` 中的 `getResponse` 方法中增加了对 `rocketchannel` 类型的处理逻辑，用于向指定用户发送消息。


### 4.5. 测试

在禅道的 Webhook 界面，点击之前创建的 Hook 条目右侧操作栏里面的链接图标：

![:/2225474bf10443deb6e71ae981c6f66a](./docs/images/030db0d5-01f6-4edf-8bf1-d14dda124a88.png)

可以对已有的禅道用户和 RocketChat 用户进行绑定：

![:/09c49f8027d54e6c8b4b0cc8c0216cb9](./docs/images/9281b46c-aa57-40e1-b94b-26fc83895d6e.png)

然后随便找一个 bug 或者 任务，添加一条备注，看看是否可以发送到指定的用户：

![:/1cdf0202f8274fd9baeff73a6403cd9f](./docs/images/d3744671-59ad-4e4e-9c56-7e7f6841c9e7.png)


## 5. 总结

通过以上步骤，我们成功地将禅道与 RocketChat 集成，实现了在禅道中创建任务或 bug 时，自动发送消息到指定的 RocketChat 频道或用户。这样，我们就可以在 RocketChat 中实时接收禅道中的任务和 bug 更新，提高团队协作效率。

