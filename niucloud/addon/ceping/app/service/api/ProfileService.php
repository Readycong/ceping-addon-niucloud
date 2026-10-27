<?php

namespace addon\ceping\app\service\api;

use addon\ceping\app\model\MemberProfile;
use addon\ceping\app\service\core\ProfileService as CoreProfileService;
use core\base\BaseApiService;
use core\exception\ApiException;

/**
 * 站内资料（确定性默认昵称/头像，可改）
 */
class ProfileService extends BaseApiService
{
    public function get(): array
    {
        $core = new CoreProfileService();
        $seed = $core->memberSeed($this->member_id);
        $defaults = [
            'nickname'   => $core->defaultNickname($seed),
            'avatar_key' => $core->defaultAvatarKey($seed),
        ];

        $profile = (new MemberProfile())->where('site_id', $this->site_id)
            ->where('member_id', $this->member_id)->findOrEmpty();
        $custom = $profile->isEmpty() ? [] : [
            'nickname'   => $profile->nickname,
            'avatar_key' => $profile->avatar_key,
        ];

        // 新用户无资料行时 $custom 为空，必须用 ?? 避免 PHP8 Undefined array key
        return [
            'nickname'     => !empty($custom['nickname']) ? $custom['nickname'] : $defaults['nickname'],
            'avatar_key'   => !empty($custom['avatar_key']) ? $custom['avatar_key'] : $defaults['avatar_key'],
            'nickname_custom' => !empty($custom['nickname']),
            'avatar_presets' => $core->avatarPresets(),
        ];
    }

    public function update(?string $nickname, ?string $avatar_key): array
    {
        if ($nickname !== null) {
            $nickname = trim($nickname);
            $len = mb_strlen($nickname);
            if ($len < 2 || $len > 10) throw new ApiException('CEPING_NICKNAME_LENGTH');
        }
        if ($avatar_key !== null && $avatar_key !== '') {
            $valid = array_column((new CoreProfileService())->avatarPresets(), 'key');
            if (!in_array($avatar_key, $valid)) throw new ApiException('CEPING_AVATAR_INVALID');
        }
        $profile = (new MemberProfile())->where('site_id', $this->site_id)
            ->where('member_id', $this->member_id)->findOrEmpty();
        $data = array_filter([
            'nickname'   => $nickname,
            'avatar_key' => $avatar_key,
        ], fn($v) => $v !== null);
        if ($profile->isEmpty()) {
            (new MemberProfile())->create(array_merge([
                'site_id'   => $this->site_id,
                'member_id' => $this->member_id,
            ], $data));
        } else {
            $profile->save($data);
        }
        return $this->get();
    }
}
