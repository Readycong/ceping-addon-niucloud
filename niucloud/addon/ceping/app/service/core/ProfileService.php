<?php

namespace addon\ceping\app\service\core;

use core\base\BaseCoreService;
use think\facade\Db;

/**
 * 确定性昵称/头像生成（纯函数，跨端一致规则见PRD §5.2）
 */
class ProfileService extends BaseCoreService
{
    const ADJ = ['透明', '潮湿', '野生', '半熟', '漂浮', '静音', '微醺', '松弛', '偏蓝', '温热', '反光', '失重', '限量', '慢热', '多云', '匿名'];
    const ANIMAL = ['水豚', '橘猫', '章鱼', '树懒', '河豚', '海鸥', '柴犬', '水母', '袋鼠', '企鹅', '浣熊', '刺猬', '海獭', '飞蛾', '柴鱼', '鸵鸟'];
    const AVATAR_COUNT = 24;

    public function defaultNickname(string $seed): string
    {
        $hash = hash('sha256', $seed . 'salt_v1');
        $adj_idx = hexdec(substr($hash, 0, 4)) % count(self::ADJ);
        $animal_idx = hexdec(substr($hash, 4, 4)) % count(self::ANIMAL);
        return self::ADJ[$adj_idx] . '的' . self::ANIMAL[$animal_idx];
    }

    public function defaultAvatarKey(string $seed): string
    {
        $hash = hash('sha256', $seed . 'salt_av');
        return 'avatar_' . ((hexdec(substr($hash, 0, 4)) % self::AVATAR_COUNT) + 1);
    }

    /**
     * 取会员标识种子（优先小程序openid）
     */
    public function memberSeed(int $member_id): string
    {
        $openid = Db::name('member')->where('member_id', $member_id)->value('weapp_openid');
        if (!empty($openid)) return (string)$openid;
        return 'member_' . $member_id;
    }

    public function avatarPresets(): array
    {
        $list = [];
        for ($i = 1; $i <= self::AVATAR_COUNT; $i++) {
            $list[] = ['key' => 'avatar_' . $i, 'emoji' => $this->emojiOf($i)];
        }
        return $list;
    }

    protected function emojiOf(int $i): string
    {
        $map = ['🐻','🐱','🦊','🐼','🐨','🐰','🐯','🦁','🐮','🐷','🐸','🐵','🦄','🐙','🦋','🐬','🐳','🦉','🐧','🦔','🦦','🐢','🐝','🪼'];
        return $map[$i - 1] ?? '⭐';
    }
}
