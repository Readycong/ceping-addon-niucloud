<?php

namespace addon\ceping\app\service\api;

use addon\ceping\app\model\PartnerInvite;
use addon\ceping\app\model\Record;
use addon\ceping\app\model\Test;
use addon\ceping\app\service\core\CepingConfigService;
use addon\ceping\app\service\core\ProfileService;
use addon\ceping\app\service\core\ScoringService;
use core\base\BaseApiService;
use core\exception\ApiException;
use think\facade\Db;

/**
 * #7 双人契合度：邀请/加入/配对结果
 */
class PartnerService extends BaseApiService
{
    const PARTNER_TEST_ID = 7;

    /**
     * A作答后创建邀请
     */
    public function invite(array $answers, string $source = 'home'): array
    {
        $test = $this->partnerTest();
        // 先校验A作答合法（暂不计分）
        (new ScoringService())->validateAnswers($test->toArray(), $answers);

        $cfg = (new CepingConfigService())->getConfig($this->site_id);

        $record = (new Record())->create([
            'site_id'   => $this->site_id,
            'member_id' => $this->member_id,
            'test_id'   => self::PARTNER_TEST_ID,
            'mode'      => 'partner_inviter',
            'answers'   => array_values($answers),
            'source'    => $source,
            'status'    => 'waiting_partner',
        ]);

        $invite = (new PartnerInvite())->create([
            'site_id'           => $this->site_id,
            'test_id'           => self::PARTNER_TEST_ID,
            'token'             => $this->genToken(),
            'inviter_member_id' => $this->member_id,
            'inviter_record_id' => (int)$record->id,
            'status'            => 'waiting',
            'expire_time'       => time() + (int)$cfg['invite_expire'],
        ]);
        $record->save(['invite_id' => (int)$invite->id]);

        return [
            'invite_id' => (int)$invite->id,
            'token'     => $invite->token,
            'expire_time' => (int)$invite->expire_time,
        ];
    }

    /**
     * B查看邀请
     */
    public function joinInfo(string $token): array
    {
        $invite = $this->findToken($token);
        $this->refreshStatus($invite);

        $test = $this->partnerTest();
        $profile_core = new ProfileService();
        $seed = $profile_core->memberSeed((int)$invite->inviter_member_id);
        $custom = Db::name('ceping_member_profile')->where('site_id', $this->site_id)
            ->where('member_id', $invite->inviter_member_id)->find() ?: [];

        $avatar_key = !empty($custom['avatar_key']) ? $custom['avatar_key'] : $profile_core->defaultAvatarKey($seed);
        $avatar_emoji = '🐻';
        foreach ($profile_core->avatarPresets() as $preset) {
            if ($preset['key'] === $avatar_key) {
                $avatar_emoji = $preset['emoji'];
                break;
            }
        }

        $result = null;
        $pair_score = null;
        $inviter_record_id = (int)$invite->inviter_record_id;
        if ($invite->status === 'paired') {
            $rec = (new Record())->find($inviter_record_id);
            $pair_score = $rec->total_score;
            foreach (($test->results ?: []) as $r) {
                if ($r['key'] === $rec->result_key) $result = $r;
            }
        }

        return [
            'status'        => $invite->status,
            'token'         => $token,
            'is_inviter'    => (int)$invite->inviter_member_id === $this->member_id,
            'test_id'       => self::PARTNER_TEST_ID,
            'display_name'  => $test->display_name,
            'cover'         => $test->cover,
            'theme_key'     => $test->theme_key,
            'question_count' => $test->question_count,
            'inviter'       => [
                'nickname'     => ($custom['nickname'] ?? '') ?: $profile_core->defaultNickname($seed),
                'avatar_key'   => $avatar_key,
                'avatar_emoji' => $avatar_emoji,
            ],
            'expire_time'   => (int)$invite->expire_time,
            'pair_score'    => $pair_score,
            'result'        => $result,
            'inviter_record_id' => $inviter_record_id,
            'my_record_id'  => $this->myInviteeRecordId((int)$invite->id),
        ];
    }

    /**
     * B提交作答：服务端计算契合度并回写双方记录
     */
    public function submit(string $token, array $answers): array
    {
        $invite = $this->findToken($token);
        $this->refreshStatus($invite);
        if ($invite->status !== 'waiting') throw new ApiException('CEPING_PARTNER_NOT_WAITING');
        if ((int)$invite->inviter_member_id === $this->member_id) throw new ApiException('CEPING_PARTNER_SAME_MEMBER');
        if ($this->myInviteeRecordId((int)$invite->id)) throw new ApiException('CEPING_PARTNER_ALREADY_JOINED');

        $test = $this->partnerTest();
        $inviter_record = (new Record())->find($invite->inviter_record_id);
        if ($inviter_record->isEmpty()) throw new ApiException('CEPING_RECORD_NOT_FOUND');

        $pair = (new ScoringService())->scorePartner(
            $test->toArray(),
            $inviter_record->answers ?: [],
            $answers
        );

        $invitee_record = (new Record())->create([
            'site_id'   => $this->site_id,
            'member_id' => $this->member_id,
            'test_id'   => self::PARTNER_TEST_ID,
            'mode'      => 'partner_invitee',
            'answers'   => array_values($answers),
            'score_detail' => $pair['score_detail'],
            'result_key'   => $pair['result_key'],
            'result_name'  => $pair['result_name'],
            'total_score'  => $pair['total_score'],
            'source'    => 'invite',
            'invite_id' => (int)$invite->id,
            'status'    => 'paired',
        ]);

        $inviter_record->save([
            'score_detail'      => $pair['score_detail'],
            'result_key'        => $pair['result_key'],
            'result_name'       => $pair['result_name'],
            'total_score'       => $pair['total_score'],
            'partner_record_id' => (int)$invitee_record->id,
            'status'            => 'paired',
        ]);

        $invite->save([
            'invitee_member_id' => $this->member_id,
            'invitee_record_id' => (int)$invitee_record->id,
            'status'            => 'paired',
            'paired_time'       => time(),
        ]);

        return (new RecordService())->detail((int)$invitee_record->id);
    }

    protected function myInviteeRecordId(int $invite_id): int
    {
        return (int)(new Record())->where('invite_id', $invite_id)
            ->where('mode', 'partner_invitee')->where('member_id', $this->member_id)->value('id');
    }

    protected function partnerTest(): Test
    {
        $test = (new Test())->where('site_id', $this->site_id)
            ->where('test_id', self::PARTNER_TEST_ID)->where('online_status', 1)->findOrEmpty();
        if ($test->isEmpty()) throw new ApiException('CEPING_TEST_NOT_FOUND');
        return $test;
    }

    protected function findToken(string $token): PartnerInvite
    {
        if (!preg_match('/^[a-f0-9]{16,64}$/', $token)) throw new ApiException('CEPING_PARTNER_TOKEN_INVALID');
        $invite = (new PartnerInvite())->where('site_id', $this->site_id)
            ->where('token', $token)->findOrEmpty();
        if ($invite->isEmpty()) throw new ApiException('CEPING_PARTNER_NOT_FOUND');
        return $invite;
    }

    protected function refreshStatus(PartnerInvite $invite): void
    {
        if ($invite->status === 'waiting' && (int)$invite->expire_time < time()) {
            $invite->save(['status' => 'expired']);
            Db::name('ceping_record')->where('id', $invite->inviter_record_id)->update(['status' => 'canceled']);
        }
    }

    protected function genToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
