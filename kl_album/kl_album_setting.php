<?php
/*
Plugin Name: EM相册
Version: 4.0
Plugin URL: https://github.com/aweika/em_kl_album
Description: 一款优秀的本地相册插件，并且支持将照片嵌入到文章内容中。
Author: 阿维卡
Author Email: kller@foxmail.com
Author URL: http://www.aweika.com
*/
!defined('EMLOG_ROOT') && exit('access deined!');


function plugin_setting_view()
{
    KlAlbum::getInstance()->settingView();
}

function plugin_setting()
{
    KlAlbum::getInstance()->setting();
}
