<?php

namespace addon\ceping\app\service\api;

use app\model\member\Member;
use app\service\api\login\LoginService;
use core\base\BaseApiService;
use core\exception\ApiException;

/**
 * 仅开发环境：模拟微信 openid 静默登录
 * 生产环境（app_debug=false）一律拒绝
 */
class DevAuthService extends BaseApiService
{
    public function mockLogin(string $openid = ''): array
    {
        if (!env('app_debug', false)) {
            throw new ApiException('CEPING_DEV_LOGIN_DISABLED');
        }
        $openid = trim($openid);
        if ($openid === '') $openid = 'mock_openid_ceping_dev';
        if (!preg_match('/^[a-zA-Z0-9_\-]{4,64}$/', $openid)) {
            throw new ApiException('CEPING_DEV_OPENID_INVALID');
        }

        $member = (new Member())->where('site_id', $this->site_id)
            ->where('weapp_openid', $openid)->findOrEmpty();

        if ($member->isEmpty()) {
            $member = (new Member())->create([
                'site_id'          => $this->site_id,
                'member_no'        => 'CEP' . date('ymd') . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT),
                'username'         => '',
                'mobile'           => '',
                'password'         => '',
                'nickname'         => '',
                'headimg'          => '',
                'member_level'     => 0,
                'member_label'     => '',
                'weapp_openid'     => $openid,
                'register_channel' => 'weapp',
                'register_type'    => 'weapp',
                'login_type'       => 'weapp',
                'login_channel'    => 'weapp',
                'login_ip'         => '127.0.0.1',
                'login_count'      => 1,
                'login_time'       => time(),
                'last_visit_time'  => time(),
                'sex'              => 0,
                'status'           => 1,
                'create_time'      => time(),
            ]);
        }

        $token = (new LoginService())->createToken($member);
        return [
            'token'       => $token['token'] ?? '',
            'expire_time' => $token['params']['exp'] ?? 0,
            'member_id'   => (int)$member->member_id,
            'openid'      => $openid,
            'mock'        => true,
        ];
    }
}
