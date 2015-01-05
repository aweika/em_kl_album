jQuery(function ($) {
    $("#kl_album").addClass('sidebarsubmenu1');
    $('#xiangcepeizhi').click(function () {
        location.href = './plugin.php?plugin=kl_album&kl_album_action=config'
    });
    $('#xinjianxiangce').click(function () {
        if (confirm('确定要建立一个新相册？')) {
            $.get('../content/plugins/kl_album/kl_album_ajax_do.php?action=album_create&sid=' + Math.random(), {is_create: 'Y'}, function (result) {
                if ($.trim(result) == 'kl_album_successed') {
                    window.location.reload()
                } else {
                    alert('发生错误:' + result)
                }
            })
        }
    });
    $('#baocunpaixu').click(function () {
        var ids = '';
        $('div#gallery input[name^=sort]').each(function () {
            ids = ids + $(this).val() + ',';
        });
        if (ids == '') {
            alert('您貌似还木有创建相册哦')
        } else {
            $.post('../content/plugins/kl_album/kl_album_ajax_do.php?action=album_sort&sid=' + Math.random(), {ids: ids}, function (result) {
                if ($.trim(result) == 'kl_album_successed') {
                    alert('保存成功')
                } else {
                    alert('保存失败!' + result)
                }
            })
        }
    });
    $("#kl_album_ul").sortable({handle: 'div', placeholder: 'o_bg_color'}).end().disableSelection();
});
function album_getclick(el) {
    $(el).removeClass('o_bg_color').addClass('no_bg_color');
};
function album_edit(num) {
    if ($('select[name^=album_r_' + num + ']').val() == 'protect' && $.trim($('input[name^=album_p_' + num + ']').val()) == '') {
        alert('您选择了密码访问，密码不可以为空哦~')
    } else {
        if ($.trim($('input[name^=album_n_' + num + ']').val()) == '') {
            alert('相册名称不可以为空哦~')
        } else {
            $.getJSON('../content/plugins/kl_album/kl_album_ajax_do.php?action=album_edit&sid=' + Math.random(), {
                key: num,
                n: $('input[name^=album_n_' + num + ']').val(),
                d: $('input[name^=album_d_' + num + ']').val(),
                r: $('select[name^=album_r_' + num + ']').val(),
                p: $('input[name^=album_p_' + num + ']').val()
            }, function (result) {
                if (result[0] == 'Y') {
                    $('input[name^=album_n_' + num + '],input[name^=album_d_' + num + '],input[name^=album_p_' + num + ']').removeClass('no_bg_color').addClass('o_bg_color');
                    $('#album_public_img_' + num + ',#album_private_img_' + num + ',#album_protect_img_' + num).not($('#album_' + result[1] + '_img_' + num)).parent().hide();
                    $('#album_' + result[1] + '_img_' + num).parent().show()
                } else {
                    alert('保存失败：' + result)
                }
                ;
            });
        }
    }
}
function album_del(num) {
    if (confirm('删除相册将一并删除该相册内所有相片，确定要删除？')) {
        $.get('../content/plugins/kl_album/kl_album_ajax_do.php?action=album_del&sid=' + Math.random(), {album: num}, function (result) {
            if ($.trim(result) == 'kl_album_successed') {
                window.location.reload()
            } else {
                alert('发生错误:' + result)
            }
        })
    }
}
function album_r_change(obj) {
    if ($(obj).val() == 'protect') {
        $(obj).next().show()
    } else {
        $(obj).next().hide()
    }
}