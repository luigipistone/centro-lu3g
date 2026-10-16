<?php

namespace App\Http\Controllers;

use App\Jobs\ConvertVideoToWebm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VideoConversionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Centro/VideoToWebm', [
            'available' => is_executable(config('video.ffmpeg')) && is_executable(config('video.ffprobe')),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'video' => ['required', 'file', 'max:102400'],
            'quality' => ['required', 'in:small,balanced,high'],
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        $file = $data['video'];
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['mov', 'mp4'], true)) {
            return response()->json(['errors' => ['video' => ['Seleziona un file MOV o MP4.']]], 422);
        }
        if (! is_executable(config('video.ffmpeg')) || ! is_executable(config('video.ffprobe'))) {
            return response()->json(['message' => 'Conversione video non disponibile sul server.'], 503);
        }

        $id = (string) Str::uuid();
        $directory = 'video-conversions/'.$id;
        $file->storeAs($directory, 'source.'.$extension, 'local');
        try {
            DB::table('video_conversions')->insert([
                'id' => $id,
                'user_id' => $request->user()->id,
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'status' => 'queued',
                'quality' => $data['quality'],
                'expires_at' => now()->addHours(2),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            ConvertVideoToWebm::dispatch($id, $extension)->onConnection('video');
        } catch (\Throwable $e) {
            Storage::disk('local')->deleteDirectory($directory);
            DB::table('video_conversions')->where('id', $id)->delete();
            throw $e;
        }

        return response()->json(['id' => $id], 202);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $conversion = $this->ownedConversion($request, $id);

        return response()->json([
            'status' => $conversion->expires_at <= now() ? 'expired' : $conversion->status,
            'error' => $conversion->error,
            'expires_at' => $conversion->expires_at->toIso8601String(),
        ]);
    }

    public function download(Request $request, string $id): BinaryFileResponse
    {
        $conversion = $this->ownedConversion($request, $id);
        abort_if($conversion->expires_at <= now() || $conversion->status !== 'done', 404);
        $path = 'video-conversions/'.$id.'/output.webm';
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->download(Storage::disk('local')->path($path), pathinfo($conversion->original_name, PATHINFO_FILENAME).'.webm', [
            'Content-Type' => 'video/webm',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function ownedConversion(Request $request, string $id): object
    {
        abort_unless(Str::isUuid($id), 404);
        $conversion = DB::table('video_conversions')->where('id', $id)->where('user_id', $request->user()->id)->first();
        abort_unless($conversion, 404);
        $conversion->expires_at = \Illuminate\Support\Carbon::parse($conversion->expires_at);

        return $conversion;
    }
}
