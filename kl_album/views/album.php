<?php
/**
 * 相册列表页面模板
 */
!defined('EMLOG_ROOT') && exit('access deined!');
?>
<div class="containertitle2">
    <a class="navi3" href="?plugin=<?php echo self::ID;?>">相册列表</a>
    <a class="navi4" href="?plugin=<?php echo self::ID;?>&act=config">相册配置</a>
    <a class="navi4" href="?plugin=<?php echo self::ID;?>&act=about" style="color:orange;">关于作者</a>
    <?php /*if (isset($_GET['active_del'])): */?><!--<span class="actived">删除成功</span><?php /*endif; */?>
    <?php /*if (isset($_GET['active_update'])): */?><span class="actived">更新缓存成功</span>--><?php /*endif; */?>
</div>

<link href="<?php echo $this->_getDirPath('assets');?>/css/album.css?v=<?php echo urlencode(self::VERSION);?>" type="text/css" rel="stylesheet">
<style type="text/css">
    .lanniu {
        -moz-background-clip: border;
        -moz-background-inline-policy: continuous;
        -moz-background-origin: padding;
        background: transparent url(<?php echo $this->_getDirPath('assets');?>/images/lanniu.jpg) no-repeat scroll 0 0;
        border: medium none;
        display: inline;
        height: 21px;
        line-height: 21px;
        margin-right: 10px;
        text-align: center;
        width: 61px;
    }
</style>
<script type="text/javascript" src="<?php echo $this->_getDirPath('res');?>/jqueryui//jquery.ui.core.min.js"></script>
<script type="text/javascript" src="<?php echo $this->_getDirPath('res');?>/jqueryui/jquery.ui.widget.min.js"></script>
<script type="text/javascript" src="<?php echo $this->_getDirPath('res');?>/jqueryui/jquery.ui.mouse.min.js"></script>
<script type="text/javascript" src="<?php echo $this->_getDirPath('res');?>/jqueryui/jquery.ui.sortable.min.js"></script>
<script type="text/javascript" src="<?php echo $this->_getDirPath('assets');?>/js/album.js?v=<?php echo urlencode(self::VERSION);?>"></script>
<div id="content">
    <div style="height:30px;">
        <span style="float:left;">
            <input id="xinjianxiangce" type="button" value="新建相册" class="lanniu" />
            <input id="baocunpaixu" type="button" value="保存排序" class="lanniu" />
        </span>
    </div>
    <div id="gallery">
        <ul id="kl_album_ul">
            <?php
            if(empty($plugin_info)) {
                echo '<li>还未创建相册</li>';
            }else{
                foreach($plugin_info as $key => $val){
                    ?>
                    <li>
                        <table height="100%" width="100%" border="0" style="background:#FFF;border:1px solid #CCC;">
                            <tr>
                                <td width="5"><div style="background:#996600;height:140px;width:5px;cursor:move;"></div></td>
                                <td width="110" height="140" rowspan="2" align="center"><a href="./plugin.php?plugin=kl_album&kl_album_action=display&album=<?php echo $val['addtime'];?>"><?php echo $val['img_str'];?></a></td>
                                <td vlign="top">
                                    <table border="0" width="100%" height="100%" style="border:1px solid #CCC;">
                                        <tr>
                                            <td width="40" height="35"><nobr>相册名称：</nobr><input type="hidden" name="sort[]" value="<?php echo $val['addtime'];?>" /></td>
                                            <td><input name="album_n_<?php echo $key;?>" type="text" value="<?php echo $val['name'];?>" class="o_bg_color" onclick="album_getclick(this)" /></td>
                                        </tr>
                                        <tr>
                                            <td height="35" <nobr>相册描述：</nobr></td>
                                            <td><input name="album_d_<?php echo $key;?>" type="text" value="<?php echo $val['description'];?>" class="o_bg_color" onclick="album_getclick(this)" /></td>
                                        </tr>
                                        <tr>
                                            <td height="35" <nobr>访问权限：</nobr></td>
                                            <td><?php echo $val['quanxian_footer_str'];?></td>
                                        </tr>
                                        <tr>
                                            <td height="30"><mobr>操　　作：</td>
                                            <td><input id="album_edit_<?php echo $key;?>" type="button" value="保存" class="lanniu" onclick="album_edit(<?php echo $key;?>)" /><input id="shanchu_<?php echo $key;?>" type="button" value="删除" class="lanniu" onclick="album_del(<?php echo $val['addtime'];?>)" /></td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </li>
                <?php
                }
            }
            ?>
        </ul>
    </div>
</div>