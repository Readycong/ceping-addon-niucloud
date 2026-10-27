<?php

namespace addon\{{KEY}};

/**
 * 插件生命周期
 */
class Addon
{
    /**
     * 插件安装执行（SQL/菜单/任务自动装载完成后调用）
     */
    public function install()
    {
        return true;
    }

    /**
     * 插件卸载执行（删表删文件之前调用）
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * 插件升级执行
     */
    public function upgrade()
    {
        return true;
    }
}
