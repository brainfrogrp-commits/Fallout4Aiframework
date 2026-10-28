<?php
// Text to speech for speak.php: the provider request, the NPC's profile voice and the WAV check.
// This file only declares a class, so requesting it over HTTP prints nothing.
// tts.provider 'pockettts' uses DwemerDistro PocketTTS (audio.cpp, port 8086); 'openai' uses
// any OpenAI-compatible /v1/audio/speech endpoint that returns WAV; 'inworld' uses Inworld's cloud
// /tts/v1/voice endpoint (JSON with base64 LINEAR16 audio). The endpoint keeps request
// validation, the token check, the disabled answer and the connector audit row; this class
// never sees the request, never replies and never logs the text, the audio or a key.

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/profiles.php';

final class SpeechService
{
    public const MAX_WAV_BYTES = 20000000;
    public const INWORLD_URL = 'https://api.inworld.ai/tts/v1/voice';
    public const INWORLD_MODEL = 'inworld-tts-2-flash';
    // The sample rate asked of Inworld, used when its audio comes back as bare PCM.
    private const INWORLD_RATE = 24000;

    private function __construct(private array $tts, private string $voiceSource)
    {
    }

    // The configured provider. Configs from before tts.provider existed are PocketTTS.
    // Takes the raw config section, which may not be an array, so speak.php can name the
    // provider in its audit row before checking tts.enabled.
    public static function provider(mixed $tts): mixed
    {
        return $tts['provider'] ?? 'pockettts';
    }

    // The tts settings for one request. The NPC's profile may choose its voice; any problem
    // keeps the configured voice. $npcId only picks the voice, never who speaks.
    public static function forNpc(array $config, array $tts, ?string $npcId): self
    {
        if ($npcId !== null) {
            $db = db_connect_quiet($config);
            $profile = $db !== null && schema_problem($db) === null ? profile_for_npc($db, $npcId) : false;
            if ($profile === false) {
                app_log('profile voice lookup skipped; using the configured voice');
            } elseif (($profile['tts_voice'] ?? null) !== null) {
                return new self(profile_tts($tts, $profile), 'profile');
            }
        }
        return new self($tts, 'config');
    }

    // 'profile' or 'config': where the voice came from.
    public function voiceSource(): string
    {
        return $this->voiceSource;
    }

    // One provider call, no retry. Returns [WAV bytes, audit detail] or [null, audit detail].
    public function synthesize(string $text): array
    {
        $tts = $this->tts;
        $provider = self::provider($tts);
        if ($provider === 'inworld') {
            return $this->synthesizeInworld($text);
        }
        $headers = ['Content-Type: application/json', 'Accept: audio/wav'];
        $request = ['model' => $tts['model'] ?? 'pocket-tts', 'input' => $text, 'voice' => $tts['voice'] ?? 'alba'];
        if ($provider === 'openai') {
            if ((string)($tts['model'] ?? '') === '') {
                app_log('tts.model must be set for the openai provider');
                return [null, 'model missing'];
            }
            $request['response_format'] = 'wav';
            $apiKey = (string)($tts['api_key'] ?? '');
            if ($apiKey !== '') {
                $headers[] = "Authorization: Bearer $apiKey";
            }
        } elseif ($provider !== 'pockettts') {
            app_log('unknown tts.provider');
            return [null, 'unknown provider'];
        }

        try {
            [$status, $wav] = http_post((string)($tts['url'] ?? ''), $headers, json_encode($request),
                (int)($tts['timeout_seconds'] ?? 30), self::MAX_WAV_BYTES);
        } catch (Throwable $error) {
            app_log($error->getMessage());
            [$status, $wav] = [0, ''];
        }
        if ($status !== 200 || !is_wav($wav)) {
            app_log("TTS failed with HTTP $status");
            return [null, "provider HTTP $status, voice from {$this->voiceSource}"];
        }
        return [$wav, 'WAV ' . strlen($wav) . " bytes, voice from {$this->voiceSource}"];
    }

    // Inworld answers JSON {"audioContent": "<base64>"}; LINEAR16 is a WAV, or bare 16-bit mono
    // PCM, which is given a WAV header here. The key is the Portal's Basic (Base64) value.
    private function synthesizeInworld(string $text): array
    {
        $tts = $this->tts;
        $apiKey = (string)($tts['api_key'] ?? '');
        if ($apiKey === '') {
            app_log('tts.api_key must be set for the inworld provider');
            return [null, 'key missing'];
        }
        if (str_starts_with($apiKey, 'eyJ')) {
            app_log('tts.api_key is an Inworld session token (JWT); use the Basic (Base64) API key');
            return [null, 'session token instead of API key'];
        }
        $credential = str_contains($apiKey, ':') ? base64_encode($apiKey) : $apiKey;
        $model = (string)($tts['model'] ?? '');
        $request = ['text' => $text, 'voiceId' => (string)($tts['voice'] ?? ''),
            'modelId' => $model !== '' ? $model : self::INWORLD_MODEL,
            'audioConfig' => ['audioEncoding' => 'LINEAR16', 'sampleRateHertz' => self::INWORLD_RATE]];
        $url = (string)($tts['url'] ?? '');
        $url = $url !== '' ? $url : self::INWORLD_URL;
        try {
            [$status, $body] = http_post($url,
                ['Content-Type: application/json', "Authorization: Basic $credential"], json_encode($request),
                (int)($tts['timeout_seconds'] ?? 30), intdiv(self::MAX_WAV_BYTES * 4, 3) + 65536);
        } catch (Throwable $error) {
            app_log($error->getMessage());
            [$status, $body] = [0, ''];
        }
        $audio = null;
        if ($status === 200) {
            $reply = json_decode($body, true);
            $encoded = is_array($reply) ? ($reply['audioContent'] ?? null) : null;
            $audio = is_string($encoded) ? base64_decode($encoded, true) : false;
        }
        if (!is_string($audio) || $audio === '' || strlen($audio) > self::MAX_WAV_BYTES) {
            app_log("TTS failed with HTTP $status from " . (parse_url($url, PHP_URL_HOST) ?: 'an invalid URL'));
            return [null, "provider HTTP $status, voice from {$this->voiceSource}"];
        }
        $wav = is_wav($audio) ? $audio : self::pcmToWav($audio, self::INWORLD_RATE);
        return [$wav, 'WAV ' . strlen($wav) . " bytes, voice from {$this->voiceSource}"];
    }

    private static function pcmToWav(string $pcm, int $rate): string
    {
        $pcm = substr($pcm, 0, strlen($pcm) - strlen($pcm) % 2);
        return 'RIFF' . pack('V', 36 + strlen($pcm)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16)
            . 'data' . pack('V', strlen($pcm)) . $pcm;
    }
}
