<?php
/**
 * 相册配置页面的模板
 */
!defined('EMLOG_ROOT') && exit('access deined!');
?>
<div class="containertitle2">
    <a class="navi1" href="?plugin=<?php echo self::ID;?>">相册列表</a>
    <a class="navi3" href="?plugin=<?php echo self::ID;?>&act=config">相册配置</a>
    <a class="navi4" href="?plugin=<?php echo self::ID;?>&act=about" style="color:orange;">关于作者</a>
    <?php /*if (isset($_GET['active_del'])): */?><!--<span class="actived">删除成功</span><?php /*endif; */?>
    <?php /*if (isset($_GET['active_update'])): */?><span class="actived">更新缓存成功</span>--><?php /*endif; */?>
    <span style="float:right;">
        <form action="./plugin.php?plugin=kl_data_call&action=setting&update=true" method="POST" onsubmit="return confirm('确定要更新所有数据调用缓存吗？');">
            <input name="kl_data_call_do" class="copy" type="submit" value="一键更新所有数据调用缓存"/>
        </form>
    </span>
</div>