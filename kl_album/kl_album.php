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

/**
 * Class 相册插件主程序类
 */
class KlAlbum
{
    const ID = 'kl_album';
    const NAME = 'EM相册';
    const VERSION = '4.0';

    //实例
    private static $_instance;

    //是否初始化
    private $_inited = false;

    //数据库连接实例
    private $_db;

    //插件版本相关信息字段
    private $_infoField;

    //缓存、配置、模板目录
    private $_fileDir = array('views');

    //相关资源目录
    private $_urlDir = array('assets', 'res');

    //需要可写权限的目录
    private $_writableDir = array('upload');

    //提示信息（目录不可写、插件版本同数据表中存储的不一致等）
    private $_msg;

    /**
     * 静态方法，返回数据调用插件实例
     *
     * @return KlAlbum
     */
    public static function getInstance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct()
    {
    }

    /**
     * 获取本地数据表中存储的插件版本号
     * @return string
     */
    private function _getlocalVersion()
    {
        $plugin_info = Option::get($this->_infoField);
        if (is_null($plugin_info)) return '';
        $plugin_info = unserialize($plugin_info);
        return $plugin_info['version'];
    }

    /**
     * 插件激活时要执行的代码（一些数据库修改等初始化或升级操作）
     */
    public function callbackInit()
    {
        Cache::getInstance()->updateCache('options');
        $plugin_info = Option::get($this->_infoField);
        //未用过数据调用插件或使用4.0版本前不存在$plugin_info信息
        if (is_null($plugin_info)) {
            $info = serialize(array('version' => self::VERSION));
            $this->_db->query("INSERT INTO " . DB_PREFIX . "options(option_name, option_value) VALUES('{$this->_infoField}', '{$info}')");
            //插入kl_album_config
            $kl_album_config = Option::get('kl_album_config');
            if (is_null($kl_album_config)) {
                $kl_album_config = mysql_real_escape_string(serialize(array()));
                $this->_db->query("INSERT INTO " . DB_PREFIX . "options(option_name, option_value) VALUES('kl_album_config', '$kl_album_config')");
            }
            Cache::getInstance()->updateCache('options');

            //创建相关表
            $is_exist_album_query = $this->_db->query('show tables like "' . DB_PREFIX . 'kl_album"');
            if ($this->_db->num_rows($is_exist_album_query) == 0) {
                $dbcharset = 'utf8';
                $type = 'MYISAM';
                $add = $this->_db->getMysqlVersion() > '4.1' ? "ENGINE=" . $type . " DEFAULT CHARSET=" . $dbcharset . ";" : "TYPE=" . $type . ";";
                $sql = "
CREATE TABLE `" . DB_PREFIX . "kl_album` (
`id` int(10) unsigned NOT NULL auto_increment,
`truename` varchar(255) NOT NULL,
`filename` varchar(255) NOT NULL,
`description` text,
`album` varchar(255) NOT NULL,
`addtime` int(10) NOT NULL default '0',
`w` smallint(5) NOT NULL DEFAULT '0',
`h` smallint(5) NOT NULL DEFAULT '0',
PRIMARY KEY  (`id`)
)" . $add;
                $this->_db->query($sql);
            } else {
                $is_exist_new_columns_query = $this->_db->query('show columns from ' . DB_PREFIX . 'kl_album like "w"');
                if ($this->_db->num_rows($is_exist_new_columns_query) == 0) {
                    $sql = "ALTER TABLE " . DB_PREFIX . "kl_album ADD COLUMN `w` SMALLINT(5) DEFAULT 0 NOT NULL AFTER `addtime`, ADD COLUMN `h` SMALLINT(5) DEFAULT 0 NOT NULL AFTER `w`;";
                    $this->_db->query($sql);
                }
            }

            $this->_callbackDo('n');

        } else {
            $plugin_info = unserialize($plugin_info);

            //todo 如果升级有数据表相关修改，则像上面那样在这里写升级相关的代码

            //每次插件升级更新数据库中存储的插件版本
            if ($plugin_info['version'] < self::VERSION) {
                $plugin_info['version'] = self::VERSION;
                $plugin_info = serialize($plugin_info);
                $this->_db->query("UPDATE " . DB_PREFIX . "options SET option_value='{$plugin_info}' WHERE option_name='{$this->_infoField}'");
                Cache::getInstance()->updateCache('options');
            }
        }

        $this->_callbackCheckhack();
    }

    /**
     * 设置相册在导航中的状态（显示或隐藏）
     *
     * @param $hide 是否隐藏（'y'或'n'）
     */
    private function _callbackDo($hide)
    {
        $sql = "SELECT * FROM " . DB_PREFIX . "navi where url='?plugin=" . self::ID . "' and isdefault='y'";
        $kl_album_navi = $this->_db->once_fetch_array($sql);
        if (empty($kl_album_navi)) {
            if (Option::EMLOG_VERSION >= '5.1.0') {
                $this->_db->query("INSERT INTO " . DB_PREFIX . "navi (naviname,url,newtab,hide,taxis,isdefault,`type`) VALUES('相册','?plugin=" . self::ID . "', 'n', '$hide', 2, 'y', 2)");
            } else {
                $this->_db->query("INSERT INTO " . DB_PREFIX . "navi (naviname,url,newtab,hide,taxis,isdefault) VALUES('相册','?plugin=" . self::ID . "', 'n', '$hide', 2, 'y')");
            }
        } else {
            $Navi_Model = new Navi_Model();
            if (Option::EMLOG_VERSION >= '5.1.0') {
                $Navi_Model->updateNavi(array('hide' => $hide, 'taxis' => 2, 'type' => 2), $kl_album_navi['id']);
            } else {
                $Navi_Model->updateNavi(array('hide' => $hide), $kl_album_navi['id']);
            }
        }
        Cache::getInstance()->updateCache('navi');
    }

    private function _callbackFilelist($dir)
    {
        $filelist = array();
        if (is_dir($dir) && $handle = @opendir($dir)) {
            while ($filename = @readdir($handle)) {
                if (!in_array($filename, array('.', '..'))) {
                    $filename = $dir . '/' . $filename;
                    if (is_dir($filename)) {
                        $filelist = array_merge($filelist, $this->_callbackFilelist($filename));
                    } else {
                        $extension = getFileSuffix($filename);
                        if ($extension == 'php') $filelist[] = $filename;
                    }
                }
            }
            @closedir($handle);
        }
        return $filelist;
    }

    private function _callbackCheckhack()
    {
        $filelist = $this->_callbackFilelist($this->_fileDir('upload'));
        foreach ($filelist as $file) {
            @unlink($file);
        }
    }

    /**
     * 侧边栏钩子执行方法
     */
    public function hookAdmSidebarExt()
    {
        echo '<div class="sidebarsubmenu" id="' . self::ID . '"><a href="./plugin.php?plugin=' . self::ID . '">' . self::NAME . '</a></div>';
    }

    /**
     * 检查相应的目录是否可写
     */
    private function _checkDirWritable()
    {
        $this->_msg = '';
        if (count($this->_writableDir) > 0) {
            foreach ($this->_writableDir as $dir) {
                if (!is_writable($this->_getDirPath($dir))) {
                    $this->_msg = '<span class="error">' . $dir . '目录可能不可写，如果已经是可写状态，请忽略此信息。</span>';
                    break;
                }
            }
        }
    }

    public function init()
    {
        if ($this->_inited === true) {
            return;
        }
        $this->_inited = true;
        $this->_infoField = self::ID . '_info';
        $this->_getDb();
        $this->_checkDirWritable();
        if (empty($this->_msg) && $this->_getlocalVersion() !== self::VERSION) {
            $this->_msg = $this->_getlocalVersion() < self::VERSION ? '<span class="error">系统检测到有新版本的插件已安装，请先到<a href="./plugin.php">插件列表</a>页面先关闭此插件，再开启此插件。</span>' : '<span class="error">系统检测到您可能安装了较低版本的插件，请<a target="_blank" href="#">下载</a>最新版本插件。</span>';
        }
        addAction('adm_sidebar_ext', array($this, 'hookAdmSideBarExt'));
    }

    /**
     * 获取数据库连接实例
     *
     * @return MySql|MySqlii|object
     */
    private function _getDb()
    {
        if (!is_null($this->_db)) return $this->_db;
        if (class_exists('Database', false)) {
            $this->_db = Database::getInstance();
        } else {
            $this->_db = MySql::getInstance();
        }
        return $this->_db;
    }

    /**
     * 获取目录方法
     *
     * @param $dir
     * @return string
     */
    private function _getDirPath($dir)
    {
        if (in_array($dir, $this->_fileDir)) {
            return dirname(__FILE__) . '/' . $dir;
        } elseif (in_array($dir, $this->_urlDir)) {
            return BLOG_URL . 'content/plugins/' . self::ID . '/' . $dir;
        } else {
            return '';
        }
    }

    /**
     * 获取模板文件全路径
     *
     * @param $view 文件
     * @param string $ext 扩展名
     * @return string
     */
    private function _view($view, $ext = '.php')
    {
        return $this->_getDirPath('views') . '/' . $view . $ext;
    }

    /**
     * 插件设置各页面头部
     */
    private function _getHeader()
    {
        echo '<script src="../include/lib/js/common_tpl.js" type="text/javascript"></script>';
        //echo sprintf('<script src="%s/jquery.zclip.min.js" type="text/javascript"></script>', $this->_getDirPath('res'));
        echo sprintf('<script type="text/javascript">$("#%s").addClass("sidebarsubmenu1");setTimeout(hideActived,2600);</script>', self::ID);
        //echo sprintf('<link rel="stylesheet" href="%s">', $this->_getDirPath('assets') . '/main.css?ver=' . urlencode(self::VERSION));
        echo sprintf('<div class=containertitle><b>%s</b><span style="font-size:12px;color:#999999;">（版本：%s）</span>%s</div>', self::NAME, self::VERSION, $this->_msg);
    }

    /**
     * 设置页面主程序
     */
    public function settingView()
    {
        $this->_getHeader();
        $act = in_array($_GET['act'], array('album', 'album_detail', 'config', 'upload', 'about')) ? $_GET['act'] : 'album';
        switch ($_GET['act']) {
            case 'album_detail':

                break;
            case 'config':

                break;
            case 'upload':

                break;
            case 'about':

                break;
            default:
                $plugin_info = $this->_album();
                break;

        }
        include $this->_view($act);
    }

    /**
     * 设置页面后台保存主程序
     */
    public function setting()
    {

    }

    private function _album()
    {
        $plugin_info = Option::get($this->_infoField);
        $plugin_info = unserialize($plugin_info);
        $album_head1 = $this->_getDirPath('assets') . '/images/only_me.jpg';
        $album_head2 = $this->_getDirPath('assets') . '/images/no_cover_s.jpg';
        if (!is_array($plugin_info) || empty($plugin_info)) return array();
        krsort($plugin_info);
        foreach ($plugin_info as $key => $val) {
            if (!isset($val['name'])) continue;
            if (isset($val['head']) && $val['head'] != 0) {
                $iquery = $this->_db->query("SELECT * FROM " . DB_PREFIX . "kl_album WHERE id={$val['head']}");
                if ($this->_db->num_rows($iquery) > 0) {
                    $irow = $this->_db->fetch_row($iquery);
                    $coverPath = $irow[2];
                    $photo_size = empty($irow['w']) ? kl_album_change_image_size($val['head'], EMLOG_ROOT . substr($coverPath, 2)) : array('w' => $irow['w'], 'h' => $irow['h']);
                } else {
                    $coverPath = $album_head2;
                    $photo_size = array('w' => 100, 'h' => 100);
                }
            } else {
                $iquery = $this->_db->query("SELECT * FROM " . DB_PREFIX . "kl_album WHERE album={$val['addtime']}");
                if ($this->_db->num_rows($iquery) > 0) {
                    $irow = $this->_db->fetch_array($iquery);
                    $coverPath = $irow['filename'];
                    $photo_size = empty($irow['w']) ? kl_album_change_image_size($irow['id'], EMLOG_ROOT . substr($coverPath, 2)) : array('w' => $irow['w'], 'h' => $irow['h']);
                } else {
                    $coverPath = $album_head2;
                    $photo_size = array('w' => 100, 'h' => 100);
                }
            }
            $pwd = isset($val['pwd']) ? $val['pwd'] : '';
            switch ($val['restrict']) {
                case 'public':
                    $quanxian_footer_str = '<select class="o_bg_color" name="album_r_' . $key . '" onchange="album_r_change(this);"><option value="public" selected>所有人可见</option><option value="private">仅主人可见</option><option value="protect">密码访问</option></select><input type="text" name="album_p_' . $key . '" value="' . $pwd . '" class="o_bg_color" onclick="album_getclick(this)" onpaste="return false" style="width:55px;display:none;ime-mode:disabled;" />';
                    $img_str = '<span><img id="album_public_img_' . $key . '" class="notfengmian" src="' . $coverPath . '" width="' . $photo_size['w'] . '" height="' . $photo_size['h'] . '" /></span><span style="display:none;"><img id="album_private_img_' . $key . '" class="notfengmian" src="' . $album_head1 . '" /></span><span style="display:none;"><img id="album_protect_img_' . $key . '" class="notfengmian" src="' . $coverPath . '" width="' . $photo_size['w'] . '" height="' . $photo_size['h'] . '" /></span>';
                    break;
                case 'private':
                    $quanxian_footer_str = '<select class="o_bg_color" name="album_r_' . $key . '" onchange="album_r_change(this);"><option value="public">所有人可见</option><option value="private" selected>仅主人可见</option><option value="protect">密码访问</option></select><input type="text" name="album_p_' . $key . '" value="' . $pwd . '" class="o_bg_color" onclick="album_getclick(this)" onpaste="return false" style="width:55px;display:none;ime-mode:disabled;" />';
                    $img_str = '<span style="display:none;"><img id="album_public_img_' . $key . '" class="notfengmian" src="' . $coverPath . '" width="' . $photo_size['w'] . '" height="' . $photo_size['h'] . '" /></span><span><img id="album_private_img_' . $key . '" class="notfengmian" src="' . $album_head1 . '" /></span><span style="display:none;"><img id="album_protect_img_' . $key . '" class="notfengmian" src="' . $coverPath . '" width="' . $photo_size['w'] . '" height="' . $photo_size['h'] . '" /></span>';
                    break;
                case 'protect':
                    $quanxian_footer_str = '<select class="o_bg_color" name="album_r_' . $key . '" onchange="album_r_change(this);"><option value="public">所有人可见</option><option value="private">仅主人可见</option><option value="protect" selected>密码访问</option></select><input type="text" name="album_p_' . $key . '" value="' . $pwd . '" class="o_bg_color" onclick="album_getclick(this)" onpaste="return false" style="width:55px;ime-mode:disabled;" />';
                    $img_str = '<span style="display:none;"><img id="album_public_img_' . $key . '" class="notfengmian" src="' . $coverPath . '" width="' . $photo_size['w'] . '" height="' . $photo_size['h'] . '" /></span><span style="display:none;"><img id="album_private_img_' . $key . '" class="notfengmian" src="' . $album_head1 . '" /></span><span><img id="album_protect_img_' . $key . '" class="notfengmian" src="' . $coverPath . '" width="' . $photo_size['w'] . '" height="' . $photo_size['h'] . '" /></span>';
                    break;
            }
            $plugin_info[$key]['quanxian_footer_str'] = $quanxian_footer_str;
            $plugin_info[$key]['img_str'] = $img_str;
        }
        return $plugin_info;
    }

    public function config()
    {
        $kl_album_config = unserialize(Option::get('kl_album_config'));
        if (isset($_GET['action']) && $_GET['action'] != '') {
            if (function_exists('iconv')) {
                foreach ($_GET as $k => $v) {
                    $v_b = iconv('utf-8', 'utf-8', $v);
                    $_GET[$k] = strlen($v_b) != strlen($v) ? iconv('GBK', 'UTF-8', $v) : $v;
                }
            }
            switch (trim($_GET['action'])) {
                case 'edit':
                    $id = intval($_GET['id']);
                    $tn = addslashes(trim($_GET['tn']));
                    $d = addslashes(trim($_GET['d']));
                    $result = $this->_db->query('update ' . DB_PREFIX . "kl_album set truename='{$tn}', description='{$d}' where id={$id}");
                    if ($result) echo 'kl_album_successed';
                    break;
                case 'del':
                    $ids = addslashes(trim(trim($_POST['ids']), ','));
                    $album = intval($_POST['album']);
                    if ($album < 1) exit('未获取到相册信息。');
                    $id_arr = explode(',', $ids);
                    //搜集要删除的图片名
                    $query = $this->_db->query('select * from ' . DB_PREFIX . "kl_album where id in({$ids})");
                    $photo_arr = array();
                    while ($row = $this->_db->fetch_array($query)) {
                        $photo_arr[] = $row['filename'];
                    }
                    //删除数据表中的记录
                    $result = $this->_db->query('delete from ' . DB_PREFIX . "kl_album where id in({$ids})");
                    if ($result) {
                        //删除对应的图片文件
                        foreach ($photo_arr as $photo) {
                            @unlink('../../' . str_replace('thum-', '', $photo));
                            @unlink('../../' . $photo);
                        }
                        //如果删除的相片是某相册的封面则取消其封面
                        $kl_album_info = Option::get('kl_album_info');
                        $kl_album_info = unserialize($kl_album_info);
                        foreach ($kl_album_info as $k => $v) {
                            if (isset($v['head']) && in_array($v['head'], $id_arr)) unset($kl_album_info[$k]['head']);
                        }
                        $kl_album_info = mysql_real_escape_string(serialize($kl_album_info));
                        Option::updateOption('kl_album_info', $kl_album_info);
                        //更新排序中id
                        $kl_album = Option::get('kl_album_' . $album);
                        $id_arr_o = array();
                        if (!empty($kl_album)) {
                            $id_arr_o = explode(',', $kl_album);
                            $kl_album = array_diff($id_arr_o, $id_arr);
                            $kl_album = implode(',', $kl_album);
                            Option::updateOption('kl_album_' . $album, $kl_album);
                        }
                        Cache::getInstance()->updateCache('options');
                    }
                    echo 'kl_album_successed';
                    break;
                case 'move':
                    $ids = addslashes(trim(trim($_POST['ids']), ','));
                    $id_arr = explode(',', $ids);
                    $newalbum = intval($_POST['newalbum']);
                    if ($newalbum < 1) exit('未获取到相册信息。');
                    //搜集要删除的图片名
                    $query = $this->_db->query('select * from ' . DB_PREFIX . "kl_album where id in({$ids})");
                    $photo_arr = array();
                    $album = '';
                    while ($row = $this->_db->fetch_array($query)) {
                        $photo_arr[] = $row['filename'];
                        if (empty($album)) $album = $row['album'];
                    }
                    //更改图片所属相册
                    $result = $this->_db->query("update " . DB_PREFIX . "kl_album set album={$newalbum} where id in({$ids})");
                    if ($result) {
                        //如果删除的相片是原相册的封面则取消其封面
                        $kl_album_info = Option::get('kl_album_info');
                        $kl_album_info = unserialize($kl_album_info);
                        foreach ($kl_album_info as $k => $v) {
                            if ($v['addtime'] == $album && isset($v['head']) && in_array($v['head'], $id_arr)) unset($kl_album_info[$k]['head']);
                        }
                        $kl_album_info = mysql_real_escape_string(serialize($kl_album_info));
                        Option::updateOption('kl_album_info', $kl_album_info);
                        //更新原相册排序中id
                        $kl_album = Option::get('kl_album_' . $album);
                        if (!is_null($kl_album)) {
                            $id_arr_o = explode(',', $kl_album);
                            $kl_album = array_diff($id_arr_o, $id_arr);
                            $kl_album = implode(',', $kl_album);
                            Option::updateOption('kl_album_' . $album, $kl_album);
                        }
                        //更新新相册排序中id
                        $kl_album = Option::get('kl_album_' . $newalbum);
                        if (!is_null($kl_album)) {
                            $kl_album = trim($ids . ',' . $kl_album, ',');
                            Option::updateOption('kl_album_' . $newalbum, $kl_album);
                        }
                    }
                    Cache::getInstance()->updateCache('options');
                    echo 'kl_album_successed';
                    break;
                case 'album_sort':
                    $ids = addslashes(trim(trim($_POST['ids']), ','));
                    $id_arr = explode(',', $ids);
                    if (empty($id_arr)) exit('没有获取到相册排序需要的信息');
                    $kl_album_info = Option::get('kl_album_info');
                    $kl_album_info = unserialize($kl_album_info);
                    $new_kl_album_info = array();
                    krsort($id_arr);
                    foreach ($id_arr as $id_v) {
                        foreach ($kl_album_info as $v) {
                            if ($v['addtime'] == $id_v) $new_kl_album_info[] = $v;
                        }
                    }
                    $new_kl_album_info = mysql_real_escape_string(serialize($new_kl_album_info));
                    Option::updateOption('kl_album_info', $new_kl_album_info);
                    Cache::getInstance()->updateCache('options');
                    echo 'kl_album_successed';
                    break;
                case 'photo_sort':
                    $ids = addslashes(trim(trim($_POST['ids']), ','));
                    $album = intval($_POST['album']);
                    if ($album < 1) exit('未获取到相册名称');
                    $kl_album = Option::get('kl_album_' . $album);
                    if (is_null($kl_album)) {
                        $this->_db->query("INSERT INTO " . DB_PREFIX . "options(option_name, option_value) VALUES('kl_album_{$album}', '{$ids}')");
                    } else {
                        Option::updateOption('kl_album_' . $album, $ids);
                    }
                    Cache::getInstance()->updateCache('options');
                    echo 'kl_album_successed';
                    break;
                case 'photo_sort_reset':
                    $album = intval($_GET['album']);
                    if ($album < 1) exit('未获取到相册名称');
                    $kl_album = Option::get('kl_album_' . $album);
                    if (!is_null($kl_album)) {
                        $this->_db->query("DELETE FROM " . DB_PREFIX . "options where option_name='kl_album_{$album}'");
                    }
                    Cache::getInstance()->updateCache('options');
                    echo 'kl_album_successed';
                    break;
                case 'setHead':
                    $id = intval($_GET['id']);
                    $album = intval($_GET['album']);
                    if ($album < 1) exit('未获取到相册名称');
                    $kl_album_info = Option::get('kl_album_info');
                    $kl_album_info = unserialize($kl_album_info);
                    foreach ($kl_album_info as $k => $v) {
                        if ($v['addtime'] == $album) $kl_album_info[$k]['head'] = $id;
                    }
                    $kl_album_info = mysql_real_escape_string(serialize($kl_album_info));
                    Option::updateOption('kl_album_info', $kl_album_info);
                    Cache::getInstance()->updateCache('options');
                    echo 'kl_album_successed';
                    break;
                case 'album_edit':
                    $key = intval($_GET['key']);
                    $n = trim($_GET['n']);
                    $d = trim($_GET['d']);
                    $r = trim($_GET['r']);
                    $p = isset($_GET['p']) ? trim($_GET['p']) : '';
                    $kl_album_info = Option::get('kl_album_info');
                    $kl_album_info = unserialize($kl_album_info);
                    $kl_album_info[$key]['name'] = $n;
                    $kl_album_info[$key]['description'] = $d;
                    $kl_album_info[$key]['restrict'] = $r;
                    $kl_album_info[$key]['pwd'] = $p;
                    $kl_album_info = mysql_real_escape_string(serialize($kl_album_info));
                    Option::updateOption('kl_album_info', $kl_album_info);
                    Cache::getInstance()->updateCache('options');
                    echo json_encode(array('Y', $r));
                    break;
                case 'album_del':
                    $album = intval($_GET['album']);
                    if ($album < 1) exit('未获取到相册名称');
                    $kl_album_info = Option::get('kl_album_info');
                    $kl_album_info = unserialize($kl_album_info);
                    foreach ($kl_album_info as $k => $v) {
                        if ($v['addtime'] == $album) unset($kl_album_info[$k]);
                    }
                    $kl_album_info = array_values($kl_album_info);
                    $kl_album_info = mysql_real_escape_string(serialize($kl_album_info));
                    Option::updateOption('kl_album_info', $kl_album_info);
                    Cache::getInstance()->updateCache('options');
                    $query = $this->_db->query("SELECT * FROM " . DB_PREFIX . "kl_album WHERE album={$album}");
                    while ($row = $this->_db->fetch_array($query)) {
                        @unlink('../../' . $row['filename']);
                        @unlink('../../' . str_replace('thum-', '', $row['filename']));
                    }
                    $this->_db->query("DELETE FROM " . DB_PREFIX . "kl_album WHERE album={$album}");
                    $this->_db->query("DELETE FROM " . DB_PREFIX . "options WHERE option_name='kl_album_{$album}'");
                    echo 'kl_album_successed';
                    break;
                case 'album_create':
                    if ($_GET['is_create'] == 'Y') {
                        $kl_album_arr['name'] = '新相册';
                        $kl_album_arr['description'] = date('Y-m-d', time());
                        $kl_album_arr['restrict'] = 'public';
                        $kl_album_arr['addtime'] = time();
                        $kl_album_info = Option::get('kl_album_info');
                        if ($kl_album_info === null) {
                            $kl_album_info = array();
                            array_push($kl_album_info, $kl_album_arr);
                            $kl_album_info = mysql_real_escape_string(serialize($kl_album_info));
                            $this->_db->query("INSERT INTO " . DB_PREFIX . "options(option_name, option_value) VALUES('kl_album_info', '$kl_album_info')");
                        } else {
                            $kl_album_info = unserialize($kl_album_info);
                            array_push($kl_album_info, $kl_album_arr);
                            $kl_album_info = mysql_real_escape_string(serialize($kl_album_info));
                            Option::updateOption('kl_album_info', $kl_album_info);
                        }
                        Cache::getInstance()->updateCache('options');
                        echo 'kl_album_successed';
                    }
                    break;
                case 'set_key':
                    $kl_album_key = trim($_GET['kl_album_key']) != '' ? trim($_GET['kl_album_key']) : '';
                    $kl_album_config['key'] = $kl_album_key;
                    $kl_album_config = mysql_real_escape_string(serialize($kl_album_config));
                    Option::updateOption('kl_album_config', $kl_album_config);
                    Cache::getInstance()->updateCache('options');
                    echo json_encode(array('Y', $kl_album_key));
                    break;
                case 'set_description':
                    $kl_album_description = trim($_GET['kl_album_description']) != '' ? trim($_GET['kl_album_description']) : '';
                    $kl_album_config['description'] = $kl_album_description;
                    $kl_album_config = mysql_real_escape_string(serialize($kl_album_config));
                    Option::updateOption('kl_album_config', $kl_album_config);
                    Cache::getInstance()->updateCache('options');
                    echo json_encode(array('Y', $kl_album_description));
                    break;
                case 'is_disabled':
                    $is_disabled = addslashes(trim($_GET['is_disabled']));
                    $kl_album_config['disabled'] = $is_disabled;
                    Option::updateOption('kl_album_config', serialize($kl_album_config));
                    Cache::getInstance()->updateCache('options');
                    echo 'kl_album_successed';
                    break;
                case 'set_num_rows':
                    $kl_album_num_rows = trim($_GET['kl_album_num_rows']) != '' ? intval(trim($_GET['kl_album_num_rows'])) : 20;
                    $kl_album_config['num_rows'] = $kl_album_num_rows;
                    Option::updateOption('kl_album_config', serialize($kl_album_config));
                    Cache::getInstance()->updateCache('options');
                    echo json_encode(array('Y', $kl_album_num_rows));
                    break;
                case 'set_compression_size':
                    $kl_album_compression_length = trim($_GET['kl_album_compression_length']) != '' ? intval(trim($_GET['kl_album_compression_length'])) : 1024;
                    $kl_album_compression_width = trim($_GET['kl_album_compression_width']) != '' ? intval(trim($_GET['kl_album_compression_width'])) : 768;
                    $kl_album_config['compression_length'] = $kl_album_compression_length;
                    $kl_album_config['compression_width'] = $kl_album_compression_width;
                    Option::updateOption('kl_album_config', serialize($kl_album_config));
                    Cache::getInstance()->updateCache('options');
                    echo json_encode(array('Y', $kl_album_compression_length, $kl_album_compression_width));
                    break;
                case 'set_log_photo_size':
                    $kl_album_log_photo_length = trim($_GET['kl_album_log_photo_length']) != '' ? intval(trim($_GET['kl_album_log_photo_length'])) : 480;
                    $kl_album_log_photo_width = trim($_GET['kl_album_log_photo_width']) != '' ? intval(trim($_GET['kl_album_log_photo_width'])) : 360;
                    $kl_album_config['log_photo_length'] = $kl_album_log_photo_length;
                    $kl_album_config['log_photo_width'] = $kl_album_log_photo_width;
                    Option::updateOption('kl_album_config', serialize($kl_album_config));
                    Cache::getInstance()->updateCache('options');
                    echo json_encode(array('Y', $kl_album_log_photo_length, $kl_album_log_photo_width));
                    break;
                default:
                    break;
            }
        }
    }
}

KlAlbum::getInstance()->init();