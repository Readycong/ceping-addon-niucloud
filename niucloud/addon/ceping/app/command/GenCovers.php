<?php

namespace addon\ceping\app\command;

use addon\ceping\app\model\Test;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/**
 * 生成题库写实封面图
 * php think ceping:gen-covers [--site=1000] [--id=1,2] [--force]
 */
class GenCovers extends Command
{
    const API = 'https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image';

    // 每个选题的封面画面（不含文字/logo，写实摄影风）
    const PROMPTS = [
        1  => 'cinematic portrait of a young asian woman split into four contrasting moods and lighting tones, film grain, moody dark background, realistic photography',
        2  => 'two pairs of hands gently intertwined over a warm cafe table at dusk, shallow depth of field, film photography, cozy warm tones',
        3  => 'four silk fabrics in blue gold orange green flowing over dark surface, dramatic side light, luxury editorial still life photography',
        4  => 'misty ancient forest with a faint glowing portal between trees, cinematic atmospheric photography, dark teal tones',
        5  => 'modern office desk at night with laptop coffee and scattered sticky notes, neon city light through window, realistic photography',
        6  => 'collection of elegant perfume bottles with soft mist and flowers on dark marble, editorial fragrance photography, warm spotlight',
        7  => 'two silhouettes sitting close together on a rooftop watching sunset city skyline, cinematic film photography, warm orange tones',
        8  => 'person standing at crossroads of a rainy neon street at night, reflection puddles, cinematic moody photography',
        9  => 'smartphone on a table showing colorful abstract social feed bubbles, warm indoor light, realistic lifestyle photography',
        10 => 'old photo album and vintage toys on wooden floor with sunlight window shadows, nostalgic film photography',
        11 => 'pink heart shaped balloons over a moody city night scene, cinematic bokeh, film photography',
        12 => 'nine geometric crystal shapes arranged in a circle on dark silk with candle light, mysterious still life photography',
        13 => 'coffee cup and unfinished checklist on a desk, late afternoon sunlight, realistic documentary photography',
        14 => 'starry night sky above mountains with a single glowing lantern, mystical long exposure photography, deep blue',
        15 => 'two friends laughing under a single umbrella in summer rain, candid street photography, warm film tones',
        16 => 'calm person meditating beside a lake at dawn with soft fog, serene realistic photography, muted blue tones',
        17 => 'minimal art gallery interior with a single visitor facing a large abstract painting, soft natural light photography',
        18 => 'two diverging paths in a foggy autumn forest, cinematic photography, muted gold and grey',
        19 => 'empty subway station at night with one seated person under warm light, lonely cinematic photography',
        20 => 'sunrise over mountain trail with a backpacker rising from rocks, hopeful cinematic photography, golden light',
        21 => 'glowing laptop and headphones in dark bedroom, screen light on face silhouette, realistic night photography',
        22 => 'messy bed with phone and snacks at sunday noon, cozy lazy realistic lifestyle photography, warm tones',
        23 => 'single rose and folded love letter on vintage chair, romantic soft window light film photography',
        24 => 'colorful shopping bags and receipt on a floor with warm sunlight, playful editorial still life photography',
        25 => 'open vintage suitcase with travel items on wooden floor, wanderlust film photography, warm afternoon light',
        26 => 'moonlit bedroom window with soft curtains and warm bedside lamp, peaceful realistic photography, deep blue',
        27 => 'spicy hotpot with red broth steam rising, fresh ingredients around, dramatic dark food photography',
        28 => 'iced drink glasses with condensation on dark bar counter, neon reflections, atmospheric beverage photography',
        29 => 'fluffy cat sleeping on a cozy blanket with morning sunlight, warm realistic pet photography',
        30 => 'dim bedroom lit by monitor glow, figurines and manga on shelves, otaku aesthetic realistic photography',
        31 => 'stack of books and glowing tablet on a desk at night, anxious mood, cinematic realistic photography',
        32 => 'crowded party with one person quietly stepping out to balcony at night, cinematic contrast photography',
        33 => 'person listening to headphones by rainy window in evening, melancholic cinematic photography, blue tones',
        34 => 'vintage cassette tape and headphones on sunlit wooden desk, nostalgic film photography',
        35 => 'glowing mythical beast eyes in a misty bamboo forest night, cinematic fantasy realistic photography',
        36 => 'wall of analog clocks at different times with dramatic shadow, conceptual still life photography',
        37 => 'two debaters as chess pieces facing each other on a dramatic board, conceptual photography, dark moody',
        38 => 'long dining table full of dishes and wine glasses with warm candle light, editorial dinner photography',
        39 => 'graduation cap thrown against summer sunset sky, hopeful cinematic photography, golden hour',
        40 => 'person relaxing in a hammock between palm trees at golden hour, carefree film photography, warm tones',
        41 => 'person inside a cocoon made of glowing phone screens in dark room, conceptual realistic photography',
        42 => 'colorful alien plants inside a glass terrarium with laboratory light, surreal macro photography, dark bg',
        43 => 'coins and banknotes arranged on dark stone with dramatic light, conceptual finance still life photography',
        44 => 'running shoes on wet asphalt at dawn with city lights, energetic sports photography, cool blue tones',
        45 => 'tv remote snacks and blanket on sofa in dark living room with screen glow, cozy realistic photography',
        46 => 'nine empty chairs around a wooden conference table, dramatic window light, corporate still life photography',
        47 => 'person holding a small umbrella over someone else in rain, soft emotional film photography',
        48 => 'open door revealing a glowing unknown world from a dark room, cinematic curiosity photography',
        49 => 'phone screen glowing in hand at 3am in dark bedroom, insomnia realistic photography, cold blue light',
        50 => 'tarot cards and candle on velvet cloth at midnight, mysterious warm low key still life photography',
    ];

    protected function configure()
    {
        $this->setName('ceping:gen-covers')
            ->addOption('site', null, Option::VALUE_OPTIONAL, '站点id', 1000)
            ->addOption('id', null, Option::VALUE_OPTIONAL, '指定test_id,逗号分隔', '')
            ->addOption('force', null, Option::VALUE_NONE, '覆盖已有封面')
            ->setDescription('为爱测评题库生成写实摄影封面图');
    }

    protected function execute(Input $input, Output $output)
    {
        $site_id = (int)$input->getOption('site');
        $force = (bool)$input->getOption('force');
        $only = array_filter(array_map('intval', explode(',', (string)$input->getOption('id'))));

        $dir = public_path() . 'addon' . DIRECTORY_SEPARATOR . 'ceping' . DIRECTORY_SEPARATOR . 'covers';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $query = (new Test())->where('site_id', $site_id);
        if (!empty($only)) $query->whereIn('test_id', $only);
        $tests = $query->field('id,test_id,display_name,cover')->order('test_id')->select();

        $ok = 0; $skip = 0; $fail = 0;
        foreach ($tests as $test) {
            $tid = (int)$test['test_id'];
            $file = $dir . DIRECTORY_SEPARATOR . sprintf('%02d.jpg', $tid);
            if (!$force && $test['cover'] && is_file($file)) {
                $output->writeln("[跳过] #$tid 已有封面");
                $skip++;
                continue;
            }
            $prompt = self::PROMPTS[$tid] ?? 'moody cinematic editorial photography, dark background, film grain';
            $url = self::API . '?prompt=' . rawurlencode($prompt) . '&image_size=landscape_16_9';
            $output->write("[生成] #$tid {$test['display_name']} ... ");
            try {
                $binary = $this->fetchImage($url);
                if (strlen($binary) < 1024) throw new \RuntimeException('返回内容过小');
                file_put_contents($file, $binary);
                $cover = '/addon/ceping/covers/' . sprintf('%02d.jpg', $tid);
                $test->save(['cover' => $cover]);
                $output->writeln('<info>OK ' . round(strlen($binary) / 1024) . 'KB</info>');
                $ok++;
            } catch (\Throwable $e) {
                $output->writeln('<error>FAIL ' . $e->getMessage() . '</error>');
                $fail++;
            }
            usleep(300000);
        }
        $output->writeln("完成: 成功$ok 跳过$skip 失败$fail");
        return $fail > 0 ? 1 : 0;
    }

    protected function fetchImage(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => ['Accept: image/*,application/json'],
        ]);
        $body = curl_exec($ch);
        $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($errno) throw new \RuntimeException($err);

        if (str_contains($ctype, 'application/json')) {
            $json = json_decode($body, true);
            $img_url = $json['data']['url'] ?? $json['url'] ?? $json['data'] ?? '';
            if (is_array($img_url)) $img_url = $img_url[0]['url'] ?? '';
            if (!$img_url) throw new \RuntimeException('JSON无图片地址: ' . substr($body, 0, 200));
            $ch2 = curl_init($img_url);
            curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 90, CURLOPT_SSL_VERIFYPEER => false]);
            $bin = curl_exec($ch2);
            $e2 = curl_errno($ch2);
            curl_close($ch2);
            if ($e2 || !$bin) throw new \RuntimeException('下载图片失败');
            return $bin;
        }
        return $body;
    }
}
