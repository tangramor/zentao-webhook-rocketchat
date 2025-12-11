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
