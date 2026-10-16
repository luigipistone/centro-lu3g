<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ConvertVideoToWebm implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 1800;
    public bool $failOnTimeout = true;

    public function __construct(public string $id, public string $extension) {}

    public function handle(): void
    {
        $conversion = DB::table('video_conversions')->where('id', $this->id)->first();
        if (! $conversion || $conversion->expires_at <= now(config('app.timezone'))->toDateTimeString()) {
            Storage::disk('local')->deleteDirectory('video-conversions/'.$this->id);
            return;
        }

        DB::table('video_conversions')->where('id', $this->id)->update(['status' => 'processing', 'updated_at' => now()]);
        $disk = Storage::disk('local');
        $source = $disk->path('video-conversions/'.$this->id.'/source.'.$this->extension);
        $output = $disk->path('video-conversions/'.$this->id.'/output.webm');

        try {
            $probe = new Process([
                config('video.ffprobe'), '-v', 'error', '-show_entries', 'format=duration:stream=codec_type,width,height',
                '-of', 'json', $source,
            ]);
            $probe->setTimeout(20);
            $probe->mustRun();
            $metadata = json_decode($probe->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $video = collect($metadata['streams'] ?? [])->firstWhere('codec_type', 'video');
            $duration = (float) ($metadata['format']['duration'] ?? 0);
            if (! $video || $duration <= 0 || $duration > 1800 || ($video['width'] ?? 0) > 3840 || ($video['height'] ?? 0) > 2160) {
                throw new \RuntimeException('Video non valido o oltre i limiti (30 minuti, 4K).');
            }

            $crf = match ($conversion->quality) {
                'small' => 36, 'high' => 26, default => 31,
            };
            $command = [
                config('video.ffmpeg'), '-hide_banner', '-loglevel', 'error', '-nostdin', '-y',
                '-i', $source, '-map', '0:v:0', '-map', '0:a:0?',
                '-c:v', 'libvpx-vp9', '-crf', (string) $crf, '-b:v', '0',
                '-deadline', 'good', '-cpu-used', '4', '-row-mt', '1', '-threads', '2',
                '-c:a', 'libopus', '-b:a', '128k', '-fs', '314572800', $output,
            ];
            $process = new Process($command);
            $process->setTimeout(1700);
            $process->mustRun();
            if (! is_file($output) || filesize($output) === 0) {
                throw new \RuntimeException('Il file convertito risulta vuoto.');
            }
            $verify = new Process([
                config('video.ffprobe'), '-v', 'error', '-show_entries', 'stream=codec_type', '-of', 'json', $output,
            ]);
            $verify->setTimeout(20);
            $verify->mustRun();
            $converted = json_decode($verify->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            if (! collect($converted['streams'] ?? [])->contains('codec_type', 'video')) {
                throw new \RuntimeException('Il file WebM prodotto non contiene video valido.');
            }

            $disk->delete('video-conversions/'.$this->id.'/source.'.$this->extension);
            if ($conversion->expires_at <= now(config('app.timezone'))->toDateTimeString()) {
                $disk->deleteDirectory('video-conversions/'.$this->id);
                DB::table('video_conversions')->where('id', $this->id)->update(['status' => 'expired', 'updated_at' => now()]);
            } else {
                DB::table('video_conversions')->where('id', $this->id)->update(['status' => 'done', 'updated_at' => now()]);
            }
        } catch (\Throwable $e) {
            $disk->deleteDirectory('video-conversions/'.$this->id);
            Log::warning('Conversione video non riuscita', ['id' => $this->id, 'error' => $e->getMessage()]);
            DB::table('video_conversions')->where('id', $this->id)->update([
                'status' => 'failed', 'error' => 'Conversione non riuscita. Verifica il file e riprova.', 'updated_at' => now(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Storage::disk('local')->deleteDirectory('video-conversions/'.$this->id);
        DB::table('video_conversions')->where('id', $this->id)->update([
            'status' => 'failed', 'error' => 'Conversione interrotta. Riprova.', 'updated_at' => now(),
        ]);
        Log::warning('Job video interrotto', ['id' => $this->id, 'error' => $e->getMessage()]);
    }
}
